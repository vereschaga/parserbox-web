<?php

namespace AwardWallet\Engine\hostelworld\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Countdown extends \TAccountChecker
{
    public $mailFiles = "hostelworld/it-41935702.eml, hostelworld/it-41938350.eml";

    public $reFrom = ["@emails.hostelworld.com"];
    public $reBody = [
        'en' => ['re you getting excited about your stay at'],
    ];
    public $reSubject = [
        'The countdown is on! Here are your booking details for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Your hostel'        => ['Your hostel', 'Your hostel:'],
            'Check-in'           => ['Check-in', 'Check-in:', 'Arriving:'],
            'Reservation number' => ['Reservation number', 'Reservation number:'],
            'Check-out'          => ['Check-out', 'Check-out:', 'You’re staying until:'],
            'Hi '                => 'Hi ',
        ],
    ];
    private $keywordProv = 'hostelworld';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query("//img[@alt='Hostelworld.com' or contains(@src,'.hostelworld.com')] | //a[contains(@href,'.hostelworld.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($this->nextText($this->t('Reservation number')))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]", null, false,
                "#{$this->opt($this->t('Hi '))}\s*([\w\-]+),#"), false);

        $r->hotel()
            ->name($this->nextText($this->t('Your hostel')))
            ->noAddress();

        $r->booked()
            ->checkIn($this->normalizeDate($this->nextText($this->t('Check-in'))))
            ->checkOut($this->normalizeDate($this->nextText($this->t('Check-out'))));

        return true;
    }

    private function nextText($field)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/following::text()[normalize-space()!=''][1]");
    }

    private function normalizeDate($date)
    {
        $in = [
            //25/07/2019
            '#^(\d+)\/(\d+)\/(\d{4})$#u',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Your hostel'], $words['Check-in'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Your hostel'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Check-in'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
