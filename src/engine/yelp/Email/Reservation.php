<?php

namespace AwardWallet\Engine\yelp\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "yelp/it-279654497.eml, yelp/it-61246672.eml, yelp/it-61437596.eml, yelp/it-649534327.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'startDate'      => ['Party of'],
            'actionLinks'    => ['Edit this Reservation', 'Cancel this Reservation', 'Get Directions', 'Get directions', 'Edit this reservation'],
            'statusVariants' => ['updated', 'cancelled'],
            'Copyright'      => ['Copyright', '©'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation confirmation at', 'Your reservation at', 'Reminder: reservation for'],
    ];

    private $detectors = [
        'en' => [
            'thank you for making a reservation',
            'your reservation has been updated',
            'your reservation is right around the corner',
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

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".yelp.com/") or contains(@href,".yelpreservations.com/") or contains(@href,"www.yelp.com") or contains(@href,"www.yelpreservations.com")]')->length === 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Yelp Inc')]")->length == 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEvent($email);
        $email->setType('Reservation' . ucfirst($this->lang));

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

        $welcome = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]");

        if (preg_match("/^{$this->opt($this->t('Hi'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*,.+/u", $welcome, $m)) {
            $event->general()->traveller($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('reservation has been'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;!?]|$)/", $welcome, $m)) {
            $event->general()->status($m[1]);
        }

        $xpathLeftTable = "//text()[{$this->eq($this->t('actionLinks'))}]/ancestor::table[ preceding-sibling::table[normalize-space()] ][1]/preceding::table[normalize-space()]";

        $dateHtml = $this->http->FindHTMLByXpath($xpathLeftTable . "/descendant::tr[ *[1]/descendant::img[contains(@src,'reservation-black_regular.')] ]/*[2]");

        if (empty($dateHtml)) {
            $dateHtml = $this->http->FindHTMLByXpath("//text()[starts-with(normalize-space(), 'Party of')]/ancestor::div[1]");
        }

        $dateValue = $this->htmlToText($dateHtml);

        if (preg_match("/{$this->opt($this->t('startDate'))}[ ]+\d{1,3}[ ]*\n*[ ]*(?<date>.{6,})\s*{$this->opt($this->t('at'))}\s+(?<time>\d+[:]+\d+(?:[ ]*[AaPp]\.?[Mm]\.?)?)\s*$/", $dateValue, $matches)
            && preg_match("/^(?<wday>[[:alpha:]]+)[,\s]+(?<date>[[:alpha:]]{3,}\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]{3,})$/u", $matches['date'], $m) // Sunday, June 28
            && ($year = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Copyright'))}]", null, true, '/\b(\d{4})[\|\s]+Yelp/i'))
        ) {
            $weekDayNumber = WeekTranslate::number1($m['wday']);

            if ($weekDayNumber) {
                $dateDep = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDayNumber);
                $event->booked()
                    ->start(strtotime($matches['time'], $dateDep))
                    ->noEnd();
            }
        }

        if (preg_match("/{$this->opt($this->t('startDate'))}[ ]+(\d{1,3})[ ]*\n+/", $dateValue, $m)
        || preg_match("/{$this->opt($this->t('Party of'))}\s*(\d+)/", $dateValue, $m)) {
            $event->booked()->guests($m[1]);
        }

        $addressHtml = $this->http->FindHTMLByXpath($xpathLeftTable . "/descendant::tr[ *[1]/descendant::img[contains(@src,'marker-black_regular.')] ]/*[2]");
        $addressValue = $this->htmlToText($addressHtml);

        if (empty($addressValue)) {
            $addressValue = implode("\n", $this->http->FindNodes("//img[contains(@src,'marker_')]/ancestor::div[normalize-space()][1]/descendant::text()[normalize-space()]"));
        }

        if (preg_match("/^\s*(?<name>.{3,}?)[ ]*\n+[ ]*(?<address>[\s\S]+?)[ ]*\n+[ ]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:\s*$|\s+\D{2})/", $addressValue, $m)) {
            $event->place()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', $m['address']))
                ->phone($m['phone']);
        } elseif (preg_match("/^\s*(?<name>.{3,}?)[ ]*\n+[ ]*(?<address>[\s\S]+)/", $addressValue, $m)) {
            $event->place()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', $m['address']));
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['startDate']) || empty($phrases['actionLinks'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['startDate'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['actionLinks'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
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
