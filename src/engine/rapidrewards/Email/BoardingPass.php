<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-75519555.eml";
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'CONFIRMATION #' => '',
            //            'FLIGHT #' => '',
            //            'PASSENGER' => '',
            //            'FLIGHT DATE' => '',
            //            'DEPARTS' => '',
            //            'ARRIVES' => '',
            //            'DEPARTURE TIME' => '',
            //            'RAPID REWARDS #' => '',
        ],
    ];

    private $detectFrom = ["southwestairlines@ifly.southwest.com"];
    private $detectSubject = [
        // if subject doesn't contain 'Southwest' then check detectEmailByHeaders
        'Southwest Airlines Boarding Pass',
    ];

    private $detectBody = [
        'en' => ['Here\'s your Southwest Airlines boarding pass'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['CONFIRMATION #']) && $this->http->XPath->query("//*[" . $this->contains($dict['CONFIRMATION #']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
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
        if ($this->http->XPath->query("//img[contains(@alt,'Southwest')] | //a[contains(@href,'.southwest.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
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

        if ($this->striposAll($headers['from'], $this->detectFrom) === false) {
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
        return $this->striposAll($from, $this->detectFrom);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->nextText($this->t('CONFIRMATION #'), "/^\s*([A-Z\d]{5,7})\s*$/"),
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION #'))}]"))
            ->traveller($this->nextText($this->t('PASSENGER')), true)
        ;

        // Program
        $account = $this->nextText($this->t('RAPID REWARDS #'));

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        // Segment
        $s = $f->addSegment();

        // Airline
        $s->airline()
            ->name('WN')
            ->number($this->nextText($this->t('FLIGHT #')))
        ;

        // Departure
        $s->departure()
            ->code($this->nextText($this->t('DEPARTS'), "/^\s*([A-Z]{3})\b/"))
            ->date($this->normalizeDate($this->nextText($this->t('FLIGHT DATE')) . ', ' . $this->nextText($this->t('DEPARTURE TIME'))))
        ;

        // Arrival
        $s->arrival()
            ->code($this->nextText($this->t('ARRIVES'), "/^\s*([A-Z]{3})\b/"))
            ->noDate()
        ;

        $bp = $email->add()->bpass();

        $bp
            ->setDepCode($s->getDepCode())
            ->setDepDate($s->getDepDate())
            ->setFlightNumber($s->getFlightNumber())
            ->setTraveller($this->nextText($this->t('PASSENGER')))
            ->setRecordLocator($this->nextText($this->t('CONFIRMATION #'), "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->setUrl($this->http->FindSingleNode("//img[contains(@src, 'boarding-pass') and contains(@src, 'barcode')]/@src"))
        ;

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function nextText($text, $regexp = null)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($text)}]/following::text()[normalize-space()][1]", null, true, $regexp);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('Date: '. $date);
        $in = [
            //            "#^\s*(\d{1,2})([^\d\s]+)\s+(\d+:\d+)\s*$#",//23JUL 14:40
        ];
        $out = [
            //            "$1 $2 {$year}, $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
