<?php

namespace AwardWallet\Engine\germanwings\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class YourFlightTo extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "eurowings.com";
    public $reBody = [
        'de'  => ['IHRE FLUGBUCHUNG', 'Buchungscode'],
        'de2' => ['IHR FLUG', 'Buchungscode'],
    ];
    public $reSubject = [
        'Ihr Flug nach',
    ];
    public $lang = '';
    public static $dict = [
        'de' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $html = str_ireplace(['&zwj;', '&8205;', '‍'], '', $this->http->Response["body"]); // Zero-width joiner
        $html = str_ireplace(['&zwnj;', '&8204;', '‌'], '', $html); // Zero-width non-joiner
        $html = str_ireplace(['&zwnj;', '&8203;', '​'], '', $html); // Zero-width

        $this->http->SetEmailBody($html);

        $this->assignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'eurowings.com')]")->length > 0) {
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

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Buchungscode:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,})#");

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[normalize-space(.)='Buchungscode:']/following::table[normalize-space()][1]/descendant::text()[{$ruleTime}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root));
            $node = $this->http->FindNodes("./following::td[contains(.,'(')][1]//text()[string-length(normalize-space(.))>2]", $root);

            if (count($node) === 4) {
                $seg['DepName'] = $node[0];
                $seg['ArrName'] = $node[1];
                $seg['DepCode'] = $this->re("#\(\s*([A-Z]{3})#", $node[2]);
                $seg['ArrCode'] = $this->re("#([A-Z]{3})\s*\)#", $node[3]);
            }
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $seg['AirlineName'] = AIRLINE_UNKNOWN;
            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#(\d+:\d+(?:(?i)\s*[ap]m)?)\s*-\s*(\d+:\d+(?:(?i)\s*[ap]m)?)#", $node, $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['ArrDate'] = strtotime($m[2], $date);
            }
            $seg['Cabin'] = $this->http->FindSingleNode("./following::td[string-length(normalize-space(.))>2][not(contains(.,'('))][1]", $root);

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //Mo, 12‍.03‍.18
            '#^\s*\w+,\s+(\d+)\.(\d+)\.(\d{2})\s*$#u',
        ];
        $out = [
            '20$3-$2-$1',
        ];
        $outWeek = [
            '',
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

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
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
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }
}
