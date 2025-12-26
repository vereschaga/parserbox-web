<?php

namespace AwardWallet\Engine\qmiles\Email;

class FlStatusNotification extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-28748590.eml, qmiles/it-6407290.eml";

    public $lang = '';
    public $date;

    public $reBody = [
        'en' => ['This message was sent to you as you subscribed for flight status notification', 'Flight Status Notification'],
    ];

    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();
        $name = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//img[contains(@src,"qatarairways.com/")] | //a[contains(@href,"qatarairways.com/")]')->length > 0) {
            $body = $this->http->Response['body'];

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'qrflightstatus@qatarairways.com.qa') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'Qatar Airways') === false) {
            return false;
        }

        if (stripos($headers['subject'], 'Flight') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@qatarairways.com.qa') !== false;
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

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[contains(normalize-space(),'Generated on') and contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')])[1]", null, true, "#Generated\s+on\s+(.+)\s+GMT#")));

        if (!$date) {
            $this->date = $date;
        }

        $patterns = [
            'date' => '/^(?:Actual|Estimated)\s*(.+)$/i',
        ];

        $xpath = "//tr[count(descendant::tr)=0 and contains(.,'From') and contains(.,'To')]/following-sibling::tr[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') and count(td)>2]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0 && $date
            && (strpos($this->http->Response['body'], 'From ') === false)
            && (strpos($this->http->Response['body'], 'To ') === false)
        ) {
            $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Flight status updates for Flight')]");

            if (preg_match("/Flight status updates for Flight ([A-Z\d]{2})(\d+) on \w+, \d+ \w+ will not be available/",
                $node, $m)) {
                //set default result to junk
                $seg = [];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = MISSING_DATE;
                $seg['ArrDate'] = MISSING_DATE;
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $it['TripSegments'][] = $seg;
            }
        } else {
            foreach ($segments as $root) {
                $seg = [];
                $route = $this->http->FindSingleNode("./preceding-sibling::tr[contains(.,'From') and contains(.,'To')][1]", $root);

                if (preg_match('#From\s+(.+?)\s+\(([A-Z]{3})\)\s+To\s+(.+?)\s+\(([A-Z]{3})\)\s+On\s+(.+)#', $route, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                    $seg['ArrName'] = $m[3];
                    $seg['ArrCode'] = $m[4];
                }
                $flight = $this->http->FindSingleNode('(./td[1]//text()[string-length(normalize-space(.))>2])[1]', $root);

                if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $dateDep = $this->http->FindSingleNode('./td[position()=3 and (./text()[normalize-space(.)="Actual"] or ./text()[normalize-space(.)="Estimated"])]', $root);

                if (preg_match($patterns['date'], $dateDep, $m)) {
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
                }
                $dateArr = $this->http->FindSingleNode('./td[position()=5 and (./text()[normalize-space(.)="Actual"] or ./text()[normalize-space(.)="Estimated"])]', $root);

                if (preg_match($patterns['date'], $dateArr, $m)) {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^.*?(\d+\s+\w+\s+\d{4}\s+\d+:\d+(?:\s*[ap]m)?)$#',
            '#^.*?(\d+\s+\w+)\s+(\d+:\d+(?:\s*[ap]m)?)$#',
        ];
        $out = [
            '$1',
            '$1 ' . $year . ' $2',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
