<?php

namespace AwardWallet\Engine\yelp\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "yelp/it-277656768.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'cancelledText' => ['has been cancelled'],
            'Copyright'     => ['Copyright', '© '],
        ],
    ];

    private $detectSubjects = [
        // en
        'has been cancelled',
    ];

    private $detectors = [
        'en' => [
            'your reservation has been cancelled',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@seatme.com') !== false || stripos($from, 'no-reply@yelp.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".seatme.com/")]')->length === 0
        && $this->http->XPath->query('//a[contains(@href,".yelp.com/")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEvent(Email $email): void
    {
        $event = $email->add()->event();

        $event->setEventType(Event::TYPE_RESTAURANT);
        $event->general()->noConfirmation();

        $welcome = $this->http->FindSingleNode("//h2[{$this->starts($this->t('Hi'))}]");

        if (empty($welcome)) {
            $welcome = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]");
        }

        if (preg_match("/^{$this->opt($this->t('Hi'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*,.+/", $welcome, $m)) {
            $event->general()->traveller($m[1]);
        }

        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('cancelledText'))}])[1]"))) {
            $event->general()
                ->cancelled()
                ->status('Cancelled')
            ;
        }

        $text = implode(' ', $this->http->FindNodes("//h2[{$this->starts($this->t('Hi'))}]/following::text()[{$this->starts($this->t('Your reservation at'))}]/ancestor::*[{$this->contains($this->t('has been cancelled'))}][1]//text()[normalize-space()]"));

        if (empty($text)) {
            $text = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]/following::text()[{$this->starts($this->t('Your reservation at'))}]/ancestor::*[{$this->contains($this->t('has been cancelled'))}][1]//text()[normalize-space()]"));
        }

        if (preg_match("/Your reservation at (?<name>.+?) +on +(?<date>.{6,})\s+{$this->opt($this->t('at'))}\s+(?<time>\d+[:]+\d+(?:[ ]*[AaPp]\.?[Mm]\.?)?) has been cancelled/", $text, $matches)
            && preg_match("/^(?<wday>[[:alpha:]]+)[,\s]+(?<date>[[:alpha:]]{3,}\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]{3,})$/u", $matches['date'], $m) // Sunday, June 28
            && ($year = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Copyright'))}]", null, true, '/\b(\d{4})\s+\|*\s*Yelp/i'))
        ) {
            // Your reservation at Running Goose on Saturday, August 21 at 7:15 PM has been cancelled.

            $event->place()
                ->name($matches['name']);

            $weekDayNumber = WeekTranslate::number1($m['wday']);

            if ($weekDayNumber) {
                $dateDep = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDayNumber);
                $event->booked()
                    ->start(strtotime($matches['time'], $dateDep))
                    ->noEnd();
            }
        }
    }

    private function detectBody(): bool
    {
        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
