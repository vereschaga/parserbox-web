<?php

namespace AwardWallet\Engine\scoot\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "scoot/it-29780827.eml";

    public $reFrom = ["flight_notifications@advisory.flyscoot.com"];
    public $reBody = [
        'en' => ['we have engaged Singapore Airlines to charter', 'Route:'],
    ];
    public $reSubject = [
        '/\[Please Read\] Important information about your upcoming flight \([A-Z\d]{6}\)/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Scoot' or contains(@src,'.flyscoot.com')] | //a[contains(@href,'.flyscoot.com')]")->length > 0) {
            return $this->assignLang();
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
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)) {
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
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Ref'))}]", null,
                false, "/{$this->opt($this->t('Booking Ref'))}[ :]+([A-Z\d]{6})/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Ref'))}]/following::text()[normalize-space()!=''][1][{$this->starts($this->t('Dear '))}]",
                null, false, "/{$this->opt($this->t('Dear '))}[ ]*(.+?)(?:,|$)/"));

        $s = $r->addSegment();
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Route:'))}]/following::text()[normalize-space()!=''][1]");
        $nodes = array_map("trim", explode("–", $node));

        if (count($nodes) === 2) {
            $s->departure()
                ->name($nodes[0]);
            $s->arrival()
                ->name($nodes[1]);
        }
        $s->departure()
            ->noCode()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure*:'))}]/following::text()[normalize-space()!=''][1]")));

        $s->arrival()
            ->noCode()
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival*:'))}]/following::text()[normalize-space()!=''][1]")));

        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight No.:'))}]/following::text()[normalize-space()!=''][1]");

        if (preg_match("/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/", $node, $m) > 0) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //08-Dec-2018, 02:30 hrs
            '#^(\d+)\-(\w+)\-(\d{4}),\s+(\d+:\d+)\s*[hrs]+$#ui',
        ];
        $out = [
            '$1 $2 $3, $4',
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
