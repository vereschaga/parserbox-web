<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingInfo extends \TAccountChecker
{
    public $mailFiles = "japanair/it-296015261.eml, japanair/it-622106081.eml";

    public $reFrom = "jal.com";
    public $reSubject = [
        'JAL Domestic: Boarding Information',
        'JAL Domestic: Notice of Ticket Purchase Deadline for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flight information as' => 'Flight information as',
            'Scheduled Dep'         => 'Scheduled Dep',
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $this->parseEmail($email);
        $a = explode('\\', __CLASS__);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'Japan Airlines')]")->length > 0) {
            return $this->AssignLang();
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

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $confs = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Booking Number:'))}]/ancestor::tr[1]", null,
            "/{$this->opt($this->t('Booking Number:'))}\s*([A-Z\d]{5,7})\s*$/")));
        $confs = array_merge($confs, array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Booking Number:'))}]/following::text()[normalize-space()][1]", null,
            "/^\s*([A-Z\d]{5,7})\s*$/"))));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        if (empty($confs) && $this->http->XPath->query("//node()[{$this->contains($this->t('Booking Number'))}]")->length === 0) {
            $f->general()
                ->noConfirmation();
        }

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true,
                "/{$this->opt($this->t('Dear'))}\s+(.+)/"));

        $text = implode("\n",
            $this->http->FindNodes("//text()[{$this->starts($this->t('Flight Number'))}]/ancestor::*[{$this->contains($this->t('Flight information as'))}][1]//tr[not(.//tr)]"));

        $segments = $this->split("/(\n\S.+\s*{$this->opt($this->t('Flight Number'))})/", $text);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            // Wed, 27DecFlight Number: RAC897
            // Departure: MIYAKOArrival: TARAMA
            // Scheduled Dep.: 15:45 Scheduled Arr.: 16:10
            // Booking Number: 6OTPW5

            // Airline
            if (preg_match("/{$this->opt($this->t('Flight Number:'))} *(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3}) *(?<fn>\d{1,5})\s+/", $stext, $m)) {
                $s->airline()
                    ->name(($m['al'] == "JAL") ? "JL" : $m['al'])
                    ->number($m['fn']);
            }

            // Depature, Arrival
            if (preg_match("/\n\s*{$this->opt($this->t('Departure:'))}\s*(?<dName>.+?)\s*{$this->opt($this->t('Arrival:'))} *(?<aName>.+?)\n/", $stext, $m)) {
                $s->departure()
                    ->name($m['dName'])
                    ->noCode();
                $s->arrival()
                    ->name($m['aName'])
                    ->noCode();
            }
            $date = null;

            if (preg_match("/^\s*(\S.+)\s*{$this->opt($this->t('Flight Number:'))}/", $stext, $m)) {
                $date = $this->normalizeDate($m[1]);
            }

            if ($date && preg_match("/\n\s*{$this->opt($this->t('Scheduled Dep.:'))}\s*(?<dTime>.+?)\s*{$this->opt($this->t('Scheduled Arr.:'))} *(?<aTime>.+?)\n/", $stext, $m)) {
                $s->departure()
                    ->date(strtotime($m['dTime'], $date));
                $s->arrival()
                    ->date(strtotime($m['aTime'], $date));
            }

            // Extra
            if (preg_match("/\n\s*{$this->opt($this->t('Seat Number:'))} *(\d{1,2}[A-Z])\s*(?:\n|$)/", $stext, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Thu, 12Apr
            '#^\s*([[:alpha:]]+),\s+(\d+)\s*([[:alpha:]]+)\s*$#u',
        ];
        $out = [
            '$2 $3 ' . $year,
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        foreach (self::$dict as $lang => $dict) {
            if (
                !empty($dict['Flight information as']) && $this->http->XPath->query("//*[" . $this->contains($dict['Flight information as']) . "]")->length > 0
                || !empty($dict['Scheduled Dep']) && $this->http->XPath->query("//*[" . $this->contains($dict['Scheduled Dep']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s);
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
