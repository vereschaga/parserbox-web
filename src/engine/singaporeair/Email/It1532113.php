<?php

namespace AwardWallet\Engine\singaporeair\Email;

class It1532113 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?singaporeair#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#singaporeair#i";
    public $reProvider = "#singaporeair#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "singaporeair/it-1532113.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->recordLocators = null;
                    $lines = explode("\n", re('#RECORD\s+LOCATORS\s+(.*?)\s+TRAVELERS#is'));

                    foreach ($lines as $l) {
                        if (preg_match('#^(.*)\s+(.*)$#i', $l, $m)) {
                            $this->recordLocators[$m[1]] = $m[2];
                        }
                    }

                    $this->passengers = null;

                    if (preg_match_all('#Passenger\s+\d+:\s+(.*)#i', $text, $m)) {
                        $this->passengers = $m[1];
                    }

                    if (preg_match_all('#.*Operated\s+by(?:(?s).*?)\d+:\d+.*\(\w{3}\)#i', $text, $m)) {
                        return $m[0];
                    }
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        //re("#\n\s*[A-Za-z\d]{2}\-\d+\s+([^\n]+)\s+\d+\s+Operated by#");
                        //return re("#\n\s*".re(1)."\s+([\w\d\-]+)#", $this->text());
                        $airlineName = preg_replace('#\s\s+.*#i', '', re('#Operated\s+by\s+(.*)#i'));

                        if (isset($this->recordLocators[$airlineName])) {
                            return $this->recordLocators[$airlineName];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $regex = '#';
                            $regex .= '(?P<FlightNumber>\d+)\s+Operated\s+by\s+(?P<AirlineName>.*)\s+';
                            $regex .= '(?:(?s).*)';
                            $regex .= '\w+,\s+(?P<Date>\w+\s+\d+,\s+\d+)\s+';
                            $regex .= '(?:(?P<Stops>\d+)\s+Stops?\s+\|\s+)?';
                            $regex .= '(?P<Cabin>.*?)\s+';
                            $regex .= '(?:\|\s+(?P<DayShift>\+\d+)\s+Days?\s+)?';
                            $regex .= '(?P<DepTime>\d+:\d+(?:am|pm))\s+(?P<DepName>.*)\s+\((?P<DepCode>\w{3})\)\s+';
                            $regex .= '(?P<ArrTime>\d+:\d+(?:am|pm))\s+(?P<ArrName>.*)\s+\((?P<ArrCode>\w{3})\)';
                            $regex .= '#i';
                            $res = null;

                            if (preg_match($regex, $text, $m)) {
                                foreach (['Dep', 'Arr'] as $key) {
                                    $res[$key . 'Date'] = null;
                                    $res[$key . 'Date'] = strtotime(nice($m['Date'] . ', ' . $m[$key . 'Time']));
                                    $m[$key . 'Name'] = trim($m[$key . 'Name'], ',');
                                }

                                if ($m['DayShift'] and $res['ArrDate']) {
                                    $res['ArrDate'] = strtotime($m['DayShift'] . ' day', $res['ArrDate']);
                                }
                                $res['Stops'] = $m['Stops'] ? $m['Stops'] : null;
                                copyArrayValues($res, $m, ['FlightNumber', 'AirlineName', 'DepCode', 'DepName', 'ArrCode', 'ArrName', 'Cabin']);
                            }

                            return nice($res);
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
