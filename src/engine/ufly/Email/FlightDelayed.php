<?php

namespace AwardWallet\Engine\ufly\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightDelayed extends \TAccountChecker
{
    public $mailFiles = "ufly/it-101889463.eml";

    private $detectSubjects = [
        // en
        'Flight Delayed',
    ];

    private $detectBody = [
        'en' => ['Your Sun Country Airlines® flight time has changed'],
    ];

    private $lang = 'en';

    private static $dictionary = [
        'en' => [
            'Attention:' => 'Attention:',
            'scheduled to depart from' => 'scheduled to depart from',
            'flightRe' => 'flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5})\s*scheduled to depart from\s+(?<dname>.+?) to (?<aname>.+?) has been delayed',
            'Reservation Code:' => 'Reservation Code:',
            'Estimated departure:' => 'Estimated departure:',
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
            && $this->http->XPath->query('//node()['.$this->contains(['Sun Country, Inc', 'Sun Country Airlines® flight']).']')->length === 0
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
            if (empty($dict['Reservation code:']) || empty($dict['Attention:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($dict['Reservation code:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Attention:'])}]")->length > 0
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Code:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Attention:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([[:alpha:]][[:alpha:] \-]+[[:alpha:]])\s*$/"), true)
        ;


        $s = $f->addSegment();

        $text = $this->http->FindSingleNode("//text()[".$this->eq($this->t('scheduled to depart from'))."]/ancestor::*[self::p or self::div][1]");
//        $this->logger->debug('$text = ' . print_r($text, true));
        if (is_string($this->t("flightRe")) && preg_match("/".$this->t("flightRe")."/", $text, $m)) {
            // Airline
            $s->airline()
                ->name($m['al'] ?? null)
                ->number($m['fn'] ?? null);

            $s->departure()
                ->noCode()
                ->name($m['dname'] ?? null)
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated departure:'))}]/following::text()[normalize-space()][1]"))))
            ;

            $s->arrival()
                ->noCode()
                ->name($m['aname'] ?? null)
                ->noDate()
            ;

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

    private function normalizeDate($date)
    {
        $in = [
            //6:55 PM, 1/2/2020
            '/^\s*(\d{1,2}:\d{2}[ apm]*),\s*([\d\/]{6,})\s*$/i',
        ];
        $out = [
            '$2, $1',
        ];

        return preg_replace($in, $out, $date);
    }
}
