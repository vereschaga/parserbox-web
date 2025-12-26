<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class HotelRating extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-64859156.eml";

    public $detectFrom = "@goibibo.com";
    public $detectSubject = [
        ', review your stay at',
        ', did you enjoy your stay at',
    ];
    public $detectBody = [
        'en' => [
            'You can start by rating it',
            'Your review will help millions of travellers across ',
        ],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $detectBody) {
//            if ($this->http->XPath->query("//text()[{$this->contains($detectBody)}]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"goibibo.com")]')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): bool
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^\s*[\w\-]+\s*$#"), trim($this->t('Booking ID:'), " :"));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Hi '))}])[1]", null, true, "#{$this->opt($this->t('Hi '))} ?(\w+)\s*$#"), false);

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID:'))}]/preceding::text()[normalize-space(.)!=''][1]"))
            ->noAddress()
        ;

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Checkin Date:'))}]/following::text()[normalize-space(.)!=''][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Checkout Date:'))}]/following::text()[normalize-space(.)!=''][1]")));

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        $in = [
            // 17 Aug 2020
            '/^\s*(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})\s*$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
