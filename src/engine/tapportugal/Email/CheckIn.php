<?php

namespace AwardWallet\Engine\tapportugal\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-6866341.eml, tapportugal/it-6998205.eml";

    public $reFrom = "tapcheckinopen@tap.pt";
    public $reBody = [
        'en' => ['Complete your Check-in here', ['Check-in is now open for', 'Check-in now on']],
    ];
    public $reSubject = [
        'Check-in here - Booking Ref',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Check-in is now open for' => ['Check-in is now open for', 'Check-in now on'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        if (!$this->parseEmail($email)) {
            return null;
        }

        $name = explode('\\', __CLASS__);
        $email->setType(end($name) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.flytap.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Your booking reference')]/following::text()[normalize-space(.)!=''][1]",
                null, true, '/^\s*([A-Z\d]+)\s*$/'))
            ->traveller($this->http->FindSingleNode("//*[contains(text(), 'Dear')]/ancestor-or-self::td[1]", null, true,
                '/Dear\s+Mr\.\/Mrs\.\s+(.+?)\s*(?:,|$)/i'));

        $xpath = "//text()[{$this->starts($this->t('Check-in is now open for'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#{$this->opt($this->t('Check-in is now open for'))}\s+([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#departing from\s+(.+?)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($this->re("#departing from\s+.+?\s+to\s+.+?,\s+on\s+(\d+.+)#", $node)))
                    ->name($m[1])
                    ->code($m[2]);
            }

            if (preg_match("#departing from\s+.+?\s+to\s+(.+?)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $s->arrival()
                    ->noDate()
                    ->name($m[1])
                    ->code($m[2]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //12FEB16 at 12:50
            '#^\s*(\d+)\s*([A-Z]{3})\s*(\d+)\s+at\s+(\d+:\d+)\.\s*$#',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0) {
                    foreach ((array) $reBody[1] as $r) {
                        if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$r}')]")->length > 0) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
