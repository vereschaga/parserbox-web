<?php

namespace AwardWallet\Engine\ufly\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReadyCheckIn extends \TAccountChecker
{
    public $mailFiles = "ufly/it-102379708.eml";

    private $detectSubjects = [
        // en
        'Ready for flight check-in',
    ];

    private $detectBody = [
        'en' => ['Your flight is available for online check-in'],
    ];

    private $lang = 'en';

    private static $dictionary = [
        'en' => [
            'Flight Number:' => 'Flight Number:',
            'Reservation code:' => 'Reservation code:',
            'Traveler(s)' => 'Traveler(s)',
            'Depart' => 'Depart',
            'Arrive' => 'Arrive',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@suncountry.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".suncountry.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Sun Country, Inc")]')->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (empty($dict['Reservation code:']) || empty($dict['Flight Number:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($dict['Reservation code:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Flight Number:'])}]")->length > 0
            ) {
                $this->lang = $lang;
                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");
            return $email;
        }

        $this->parseFlight($email);

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation code:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Traveler(s)'))}]/ancestor::*[self::td or self::th][1]//text()[not({$this->eq($this->t('Traveler(s)'))})]",
                null, "/^\s*([[:alpha:]][[:alpha:] \-]+[[:alpha:]])\s*$/"), true)
        ;

        $xpath = "//text()[{$this->eq($this->t('Depart'))}]/ancestor::*[self::td or self::th][1][following::text()[normalize-space()][1][{$this->eq($this->t('Arrive'))}]]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $sroot) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight Number:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d]) ?\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight Number:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(\d{1,5})\s*$/"));

            // Departure
            $departText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $sroot));
            if (preg_match("/{$this->opt($this->t("Depart"))}\s*\n\s*(?<date>[\s\S]+?\b\d{1,2}:\d{2}.+)\n\s*(?<name>[\s\S]+)$/",
                $departText, $m)) {
                $m = preg_replace("/\s+/", ' ', $m);
                $s->departure()
                    ->noCode()
                    ->name(trim($m['name']))
                    ->date(strtotime(trim($m['date'])));
            }

            // Arrival
            $arriveText = implode("\n",
                $this->http->FindNodes("./following::text()[normalize-space()][1][{$this->eq($this->t('Arrive'))}]/ancestor::*[self::td or self::th][1]//text()[normalize-space()]",
                    $sroot));
            if (preg_match("/{$this->opt($this->t("Arrive"))}\s*\n\s*(?<date>[\s\S]+?\b\d{1,2}:\d{2}.+)\n\s*(?<name>[\s\S]+)$/",
                $arriveText, $m)) {
                $m = preg_replace("/\s+/", ' ', $m);
                $s->arrival()
                    ->noCode()
                    ->name(trim($m['name']))
                    ->date(strtotime(trim($m['date'])));
            }
        }

        return $email;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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
}
