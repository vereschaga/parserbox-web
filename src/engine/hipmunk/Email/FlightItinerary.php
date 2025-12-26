<?php

namespace AwardWallet\Engine\hipmunk\Email;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "hipmunk/it-6123037.eml";

    public $reFrom = "jetblue.com";
    public $reBody = [
        'en' => ['Hipmunk Flight Itinerary', 'Finish Booking Online'],
    ];
    public $reSubject = [
        'Hipmunk Flight Itinerary',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "FlightItinerary" . $this->lang,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'hipmunk.com')]")->length > 0) {
            $body = $this->http->Response['body'];

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $xpath = "//text()[contains(.,'→')]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#(.+)\s*\(([A-Z]{3})\s*→\s*([A-Z]{3})\)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['ArrCode'] = $m[3];
            }
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $node = $this->http->FindSingleNode("./following::text()[position()<4][contains(.,'Departs on')]", $root);
            $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#Departs on\s+(.+)#", $node)));

            $node = $this->http->FindSingleNode("./following::text()[position()<4][contains(.,'Arrives on')]", $root);
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->re("#Arrives on\s+(.+)#", $node)));

            $node = $this->http->FindSingleNode("./following::text()[position()<4][contains(.,'stop')]", $root);

            if (preg_match("#(.+)\s+-\s+(.*stop.*)#", $node, $m)) {
                $seg['Duration'] = $m[1];

                if (preg_match("#^non[-\s]*stop#", $m[2])) {
                    $seg['Stops'] = 0;
                } elseif (preg_match("#(\d+)\s*stop#", $m[2], $v)) {
                    $seg['Stops'] = $v[1];
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //6:00am · Sun, Nov 9, 2014
            '#^(\d+:\d+[ap]m).+?,\s+(\w+)\s+(\d+),\s+(\d+)$#i',
        ];
        $out = [
            '$3 $2 $4 $1',
        ];
        $str = preg_replace($in, $out, $date);

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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

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
}
