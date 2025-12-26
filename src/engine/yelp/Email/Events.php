<?php

namespace AwardWallet\Engine\yelp\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Events extends \TAccountChecker
{
    public $mailFiles = "yelp/it-654352487.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@yelp.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".yelp.com/") or contains(@href,".yelpreservations.com/") or contains(@href,"www.yelp.com") or contains(@href,"www.yelpreservations.com")]')->length === 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Yelp Inc')]")->length == 0) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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
        $event->setEventType(Event::TYPE_EVENT)
        ->setNoConfirmationNumber(true);

        $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '© ')]", null, true, "/\b(\d{4})\s+\|*\s*Yelp/");
        $dateTimeRange = $this->http->FindSingleNode("//img[contains(@src, 'marker')]/preceding::text()[normalize-space()][1]");

        if (preg_match("/^(?<date>\w+\,\s*\w+\s*\d{1,2})\,\s*(?<startTime>[\d\:]+\s*a?p?m)[\s\–]+(?<endTime>[\d\:]+\s*a?p?m)$/", $dateTimeRange, $m) && !empty($year)) {
            $event->setStartDate(strtotime($m['date'] . ' ' . $year . ', ' . $m['startTime']))
                  ->setEndDate(strtotime($m['date'] . ' ' . $year . ', ' . $m['endTime']));
        } elseif (preg_match("/^(?<dateStart>\w+\,\s*\w+\s*\d{1,2})\,\s*(?<startTime>[\d\:]+\s*a?p?m)[\s\–]+(?<dateEnd>\w+\,\s*\w+\s*\d{1,2})\,\s*(?<endTime>[\d\:]+\s*a?p?m)$/", $dateTimeRange, $m)) {
            $event->setStartDate(strtotime($m['dateStart'] . ' ' . $year . ', ' . $m['startTime']))
                ->setEndDate(strtotime($m['dateEnd'] . ' ' . $year . ', ' . $m['endTime']));
        }

        $event->setName($this->http->FindSingleNode("//img[contains(@src, 'marker')]/preceding::text()[normalize-space()][2]"))
            ->setAddress($this->http->FindSingleNode("//img[contains(@src, 'marker')]/following::text()[normalize-space()][2]/ancestor::*[normalize-space()][1]"));
    }

    private function detectBody(): bool
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('When:'))}]")
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Where:'))}]")
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Event Time:'))}]");
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
