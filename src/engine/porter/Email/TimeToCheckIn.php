<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TimeToCheckIn extends \TAccountChecker
{
    public $mailFiles = "porter/it-11314570.eml, porter/it-11670708.eml, porter/it-11772023.eml, porter/it-13183629.eml, porter/it-31069030.eml, porter/it-345674082.eml, porter/it-646063260.eml, porter/it-771393036.eml";
    public $reFrom = ["flyporter@flyporter.com", 'flyporter@notifications.flyporter.com'];
    public $reSubject = [
        "en" => "Welcome! It’s time to check in",
        'Time to Check In',
        'Time to check in!',
        "fr" => "Il est temps de vous enregistrer",
    ];
    public $reBody = 'flyporter.com';
    public $reBody2 = [
        "en"  => "Save time and check-in online",
        "en2" => "Reserve your seat now",
        "en3" => "Purchase a checked bag now",
        "en4" => "Save time and check in now",
        "en5" => "Your flight is now open for check-in",
        "fr"  => "Gagnez du temps ! Enregistrez-vous en ligne",
    ];

    public static $dictionary = [
        "en" => [
            'Confirmation number:' => ['Confirmation Number:', 'Confirmation number:', 'Porter Confirmation Number'],
            'Departure city:'      => 'Departure city:',
        ],
        "fr" => [
            'Confirmation number:' => ['Numéro de confirmation:', 'Numéro de confirmation :'],
            'Passenger name:'      => ['Nom:', 'Nom :'],
            'Departure city:'      => ['Ville de départ:', 'Ville de départ :'],
            'Departure date:'      => ['Date de départ:', 'Date de départ :'],
            'Flight number:'       => ['Numéro de vol:', 'Numéro de vol :'],
            'Arrival city:'        => ['Ville d’arrivée:', 'Ville d’arrivée :'],
        ],
    ];

    public $lang = "";

    public function parsePlain(Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number:'))}]/following::text()[normalize-space()!=''][1]",
                null, false, '#^\s*[A-Z\d]{5,7}\s*#'),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number:'))}]"), ':'));

        $confAT = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Air Transat Confirmation Number'))}]/following::text()[normalize-space()!=''][1]",
                null, false, '#^\s*[A-Z\d]{5,7}\s*#');

        if (!empty($confAT)) {
            $r->general()
                ->confirmation($confAT, trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Air Transat Confirmation Number'))}][1]"), ':'));
        }

        $passengers = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Confirmation number:'))}]/ancestor::td[{$this->contains($this->t('Passenger name:'))}][1]//text()"));
        $passengers = array_values(array_filter(explode("\n",
            $this->re("#{$this->opt($this->t('Passenger name:'))}(.+)#s", $passengers))));
        $r->general()->travellers($passengers);

        $column1 = implode("\n",
            $this->http->FindNodes("//text()[{$this->contains($this->t('Departure city:'))}]/ancestor::td[{$this->contains($this->t('Departure date:'))}][1]//text()"));
        $column2 = implode("\n",
            $this->http->FindNodes("//text()[{$this->contains($this->t('Flight number:'))}]/ancestor::td[{$this->contains($this->t('Passenger name:'))}][1]//text()"));
        $dates = $this->re("#{$this->opt($this->t('Departure date:'))}\s+(.*?)\s+{$this->opt($this->t('Departure city:'))}#s",
            $column1);
        $dep = $this->re("#{$this->opt($this->t('Departure city:'))}\s+(.*?)\s+{$this->opt($this->t('Arrival city:'))}#s",
            $column1);
        $arr = $this->re("#{$this->opt($this->t('Arrival city:'))}\s+(.+)#s", $column1);
        $flight = $this->re("#{$this->opt($this->t('Flight number:'))}\s+(.*?)\s+{$this->opt($this->t('Passenger name:'))}#s",
            $column2);

        if (!preg_match("/\d+:\d+/", $arr) && !preg_match("/\d+:\d+/", $dep)) {
            $this->parseSegment2($r, $dates, $dep, $arr, $flight);
        } else {
            $this->parseSegment($r, $dates, $dep, $arr, $flight);
        }

        return true;
    }

    public function parseSegment(Flight $r, $dates, $dep, $arr, $flight)
    {
        $this->logger->debug(__METHOD__);

        $con1 = preg_match_all("#([^\n]+)#", $dates, $dates, PREG_SET_ORDER);
        $con2 = preg_match_all("#([^\n]*?)\s*\(([A-Z]{3})\)\s+(\d+:\d+)#s", $dep, $dep, PREG_SET_ORDER);
        $con3 = preg_match_all("#([^\n]*?)\s*\(([A-Z]{3})\)\s+(\d+:\d+)#s", $arr, $arr, PREG_SET_ORDER);
        $con4 = preg_match_all("#(\d+)#", $flight, $flight, PREG_SET_ORDER);

        if ($con1 === 0 || $con2 === 0 || $con3 === 0 || $con4 === 0) {
            $this->logger->debug("maybe other format segments");

            return false;
        }

        if (count($dep) != count($arr) || count($dep) != count($flight) || count($dates) != count($dates)) {
            $this->logger->debug("segments count is different");

            return false;
        }

        foreach ($dates as $i => $date) {
            $s = $r->addSegment();

            $date = $dates[$i][1];

            $s->airline()
                ->number($flight[$i][1])
                ->name("PD");

            $s->departure()
                ->code($dep[$i][2])
                ->name(trim($dep[$i][1]));

            if (!empty($date)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $dep[$i][3]));
            }

            $s->arrival()
                ->code($arr[$i][2])
                ->name(trim($arr[$i][1]));

            if (!empty($date)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $arr[$i][3]));
            }
        }

        return true;
    }

    public function parseSegment2(Flight $r, $dates, $dep, $arr, $flight)
    {
        $this->logger->debug(__METHOD__);

        $s = $r->addSegment();

        $s->airline()
            ->number($this->re("/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(\d{1,4})\s*$/", $flight))
            ->name($this->re("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4}\s*$/", $flight) ?? "PD");

        if (preg_match("#^\s*([A-Z]{3})\s*$#s", $dep, $m)) {
            $s->departure()
                ->code($dep);
        } elseif (preg_match("#^\s*(.+?)\s*\(([A-Z]{3})\)\s*$#", $dep, $m)) {
            $s->departure()
                ->name($m[1])
                ->code($m[2]);
        }

        if (!empty($dates)) {
            $s->departure()
                ->date($this->normalizeDate($dates));
        }

        if (preg_match("#^\s*([A-Z]{3})\s*$#s", $arr, $m)) {
            $s->arrival()
                ->code($arr);
        } elseif (preg_match("#^\s*(.+?)\s*\(([A-Z]{3})\)\s*$#", $arr, $m)) {
            $s->arrival()
                ->name($m[1])
                ->code($m[2]);
        }
        $s->arrival()
            ->noDate();
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        return $this->assignLang($body);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parsePlain($email)) {
            return null;
        }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Confirmation number:']) && !empty($dict['Departure city:'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Confirmation number:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Departure city:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug($str);
        $in = [
            //22 déc. 2018, 11:20  |   10 Feb 2018
            "#^\s*(\d+)\s+(\w+)\.?\s+(\d{4}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $str)));

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
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
}
