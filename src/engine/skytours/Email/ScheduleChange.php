<?php

namespace AwardWallet\Engine\skytours\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "skytours/it-12238331.eml";

    public $reFrom = [
        'skytours'      => '@sky-tours.com',
        'militaryfares' => '@militaryfares.com',
    ];
    public $reBody = [
        'en' => "Departure airport",
    ];
    public $reSubject = [
        "en" => [
            "Schedule change for booking number",
        ],
    ];
    public $lang = '';
    public $subject;
    public $provider;
    public $date;
    public static $dict = [
        'en' => [],
    ];

    public static function getEmailProviders()
    {
        return ['skytours', 'militaryfares'];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHtmlBody();
        $this->AssignLang($body);
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getSubject();
        $its = $this->parseEmail();

        $result = [
            'emailType'  => 'ScheduleChange' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];

        if (!empty($this->provider)) {
            $result['providerCode'] = $this->provider;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHtmlBody();
        $body = iconv('UTF-8', 'windows-1251//IGNORE', $body);
        $body = iconv('UTF-8', 'UTF-8//IGNORE', $body);

        foreach ($this->reFrom as $provider => $reFrom) {
            if ($this->http->XPath->query("//a[" . $this->contains(trim($reFrom, '@'), '@href') . "]")->length > 0) {
                $this->provider = $provider;

                return $this->AssignLang($body);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        foreach ($this->reFrom as $provider => $reFrom) {
            if (strpos($headers["from"], $reFrom) !== false) {
                $find = true;
                $this->provider = $provider;

                break;
            }
        }

        if ($find == false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $subject) {
                if (stripos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $provider => $reFrom) {
            if (strpos($from, $reFrom) !== false) {
                $this->provider = $provider;

                return true;
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

    private function parseEmail()
    {
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Passengers:'])[last()]/ancestor::div[1]/preceding-sibling::div[normalize-space()][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");
        $it['Passengers'] = array_filter($this->http->FindNodes("(//text()[normalize-space(.)='Passengers:'])[last()]/ancestor::div[1]/following-sibling::div[normalize-space()][1]//text()"));

        $xpath = "(//text()[normalize-space(.)='Departure airport'])[1]/ancestor::table[1]//tr[not(.//tr) and contains(translate(./td[1], '0123456789', 'dddddddddd'), 'd')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#(.+?)\s+(\d{1,5})#u", $node, $m)) {
                $seg['AirlineName'] = trim($m[1]);
                $seg['FlightNumber'] = $m[2];
            }

            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#(.+?)\(([A-Z]{3})\)\s*(.+)#u", $node, $m)) {
                $seg['DepName'] = trim($m[1]) . ', ' . trim($m[3]);
                $seg['DepCode'] = $m[2];
            }

            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#(.+?)\(([A-Z]{3})\)\s*(.+)#u", $node, $m)) {
                $seg['ArrName'] = trim($m[1]) . ', ' . trim($m[3]);
                $seg['ArrCode'] = $m[2];
            }

            $node = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#(.+)\s+(\d{1,2}:\d{2})\b#u", $node, $m)) {
                $seg['DepDate'] = $this->normalizeDate($node);
                $date = trim($m[1]);
            }

            $node = $this->http->FindSingleNode("./td[5]", $root);

            if (!empty($date) && preg_match("#(.+\s+)?(\d{1,2}:\d{2})\b#", $node, $m)) {
                if (!empty($m[1])) {
                    $seg['ArrDate'] = $this->normalizeDate($m[1] . ' ' . $m[2]);
                } else {
                    $seg['ArrDate'] = $this->normalizeDate($date . ' ' . $m[2]);
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }
        $year = date('Y', $this->date);
        $in = [
            '#^\s*([^\d\s]+)\s+([^\d\s]+)\s*\/\s*(\d+)[\s]+(\d+:\d+)\s*$#u', //Fri Mar/30 20:20
        ];
        $out = [
            '$1, $3 $2 ' . $year . ' $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#(?<week>[^\d\s\,\.]+),\s+(?<date>\d+\s+[^\d\s]+\s+\d{4}\s*\d+:\d+)#", $date, $m)) {
            $dateL = $m['date'];
            $week = WeekTranslate::number1($m[1], $this->lang);

            if (empty($week)) {
                return false;
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($dateL, $week);

            return $date;
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "{$text} = \"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }
}
