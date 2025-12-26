<?php

namespace AwardWallet\Engine\jetblue\Email;

class Acknowledgement extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "jetblue/it-4371967.eml";

    public $reBody = [
        'en'  => ['Confirmation', 'Dear'],
        'en2' => ['Your Confirmation Code', 'Dear'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'New Fly'      => 'Your new flight',
            'Seats'        => 'Your seats:',
            'Confirmation' => ['Confirmation', 'Your Confirmation Code'],
        ],
    ];
    private $regExp = [
        '#[A-Z][a-z]+\s\d{1,2},\s\d{4}$#', //August 24, 2016
        '#\d+#', //306
        '#.+#',
        '#.+#',
        '#[0-1]?[0-9]+\:[0-5][0-9][PA]M#',
        '#.+#',
        '#[0-1]?[0-9]+\:[0-5][0-9][PA]M#',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Acknowledgement" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//img[@alt='Acknowledge Changes']")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Acknowledgement Requested') !== false
        || isset($headers['from']) && stripos($headers['from'], 'info@change.jetblue.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "jetblue.com") !== false;
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
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation")) . "]/following::span[normalize-space()][1]");

        $newFlightArr = [];
        $segs = [];

        $it['Passengers'] = $this->http->FindNodes("//table//text()[contains(normalize-space(),'" . $this->t("Seats") . "')]/ancestor::td[1]/../following-sibling::tr[not(contains(@style,'font-weight')) and not(contains(., 'Customer'))]//td[1][normalize-space()]");

        foreach ($this->regExp as $i => $v) {
            $newFlightArr[$i] = $this->http->FindNodes("//table//text()[contains(normalize-space(),'" . $this->t("New Fly") . "')]/ancestor::td[1]/../following-sibling::tr[normalize-space()][not(contains(@style,'font-weight')) and not(contains(., 'Departure'))]//td[" . intval($i + 1) . "]", null, $v);
        }

        for ($i = 0; $i < count($newFlightArr[0]); $i++) {
            $segs[$i]['FlightNumber'] = $newFlightArr[1][$i];
            $segs[$i]['AirlineName'] = $newFlightArr[2][$i];
            $re = '/(.+)[ ]+\(([A-Z]{3})\)/';

            if (preg_match($re, $newFlightArr[3][$i], $m)) {
                $segs[$i]['DepName'] = $m[1];
                $segs[$i]['DepCode'] = $m[2];
            } else {
                $segs[$i]['DepName'] = $newFlightArr[3][$i];
                $segs[$i]['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match($re, $newFlightArr[5][$i], $m)) {
                $segs[$i]['ArrName'] = $m[1];
                $segs[$i]['ArrCode'] = $m[2];
            } else {
                $segs[$i]['ArrName'] = $newFlightArr[5][$i];
                $segs[$i]['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $segs[$i]['DepDate'] = strtotime($this->normalizeDate($newFlightArr[0][$i] . " " . $newFlightArr[4][$i]));
            $segs[$i]['ArrDate'] = strtotime($this->normalizeDate($newFlightArr[0][$i] . " " . $newFlightArr[6][$i]));

            if (!empty($segs[$i]['FlightNumber'])) {
                $column = 1 + count($this->http->FindNodes("//text()[contains(normalize-space(),'" . $this->t("Seats") . "')]/ancestor::table[1]//td[normalize-space() = 'Flight " . $segs[$i]['FlightNumber'] . "']/preceding-sibling::td"));

                if ($column > 1) {
                    $segs[$i]['Seats'] = array_filter($this->http->FindNodes("//table//text()[contains(normalize-space(),'" . $this->t("Seats") . "')]/ancestor::td/../following-sibling::tr[not(contains(@style,'font-weight'))]//td[" . $column . "][normalize-space()]", null, "#^\s*(\d{1,3}[A-Z])\s*$#"));
                }
            }
        }

        for ($i = 0; $i < count($newFlightArr[1]); $i++) {
            $it['TripSegments'][$i] = $segs[$i];
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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
}
