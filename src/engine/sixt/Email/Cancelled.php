<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "sixt/it-79097597.eml";

    public $detectBody = [
        'en' => [
            'we have canceled your reservation for',
            'we have cancelled your reservation for',
        ],
    ];
    public $detectSubject = [
        // en
        '/Cancellation Confirmation: (?<number>\d{9,}) for .+/u',
        '/Cancellation confirmation for your SIXT rental car/',
    ];
    public $emailSubject;

    public $lang = '';
    public static $dict = [
        'en' => [
            'Dear ' => ['Dear ', 'Hello '],
        ],
    ];

    private $detectFrom = ["@sixt.", 'noreply@partner.sixt.com'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();

        if (!$this->detectBody()) {
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
        if ($this->http->XPath->query("//img[contains(@alt,'Sixt Logo') or contains(@src,'.sixt.com')] | //a[contains(@href,'.sixt.com')]")->length > 0) {
            return $this->detectBody();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $dSubject) {
                if (preg_match($dSubject, $headers["subject"]) > 0
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
        $r = $email->add()->rental();

        // General
        $conf = null;

        foreach ($this->detectSubject as $re) {
            if (preg_match($re, $this->emailSubject, $m) && !empty($m['number'])) {
                $conf = $m['number'];
            }
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation number:'))}]", null, false,
                "#{$this->opt($this->t('Reservation number:'))}\s*(.+)#");
        }
        $r->general()->confirmation($conf);

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, false,
            "#{$this->opt($this->t('Dear '))}\s*(.+)#");

        if (empty($traveller) || $traveller == 'Mr.' || $traveller == 'Ms.') {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]/ancestor::td[1]", null, false,
                "#{$this->opt($this->t('Dear '))}\s*(.+)\,#");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION NUMBER:'))}]/ancestor::table[1]/descendant::tr[{$this->starts($this->t('Dear '))}][1]/descendant::span[1]");
        }

        $r->general()
            ->traveller(trim($traveller, ','))
            ->cancelled()
            ->status('Cancelled')
        ;

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('IN-'.$date);
        $in = [
            //Jul 2 22:14 2019 | Tue PM
            '#^(\w+)\s+(\d+)\s+([\d\:]+)\s+(\d{4})\s*\|\s*\w+\s*(A?P?M)$#',
            // Jun 29 18:30 2019 | Sat
            // juil. 4 10:30 2019 | jeu.
            '#^(\w+)\.?\s+(\d+)\s+(\d+:\d+)\s+(\d{4})\s*\|\s*(\w+)\.?$#u',
            '#^(\w+)\s*(\d+)\s*([\d\:]+)\s*(\d{4})\s*\|\s*\w+\s*(\w+)$#u',
        ];
        $out = [
            '$2 $1 $4, $3',
            '$2 $1 $4, $3',
            '$2 $1 $4, $3 $5',
        ];

        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        //$this->logger->debug('OUT-'.$str);

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }
}
