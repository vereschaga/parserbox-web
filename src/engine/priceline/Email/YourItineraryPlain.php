<?php

namespace AwardWallet\Engine\priceline\Email;

class YourItineraryPlain extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank\s+you\s+for\s+booking\s+your\s+trip\s+on\s+Priceline#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#ItineraryAir@trans\.priceline\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]priceline\.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "08.05.2015, 12:03";
    public $crDate = "07.05.2015, 09:27";
    public $xPath = "";
    public $mailFiles = "priceline/it-12.eml, priceline/it-9.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#AIRLINE\s+CONFIRMATION\s+\#\s+.*?:\s+([\w\-]+)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+Trip\s+Cost:\s+(.*)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $s = re('#Flight\s+Details\s+((?s).*?)\s+Passenger\s+and\s+Ticket\s+Information#i');
                        $flightRe = 'Flight\s+(\d+)';
                        $dateRe = 'Leaving\s+(?:(?s).*?)\s+on\s+\w+\s*,\s+(\w+\s+\d+\s*,\s+\d+)';
                        $this->flightDates = [];

                        if (preg_match_all('#' . $flightRe . '|' . $dateRe . '#', $s, $matches)) {
                            $currentDate = null;

                            foreach ($matches[0] as $match) {
                                if ($d = nice(re('#' . $dateRe . '#', $match))) {
                                    $currentDate = $d;
                                } elseif ($f = nice(re('#' . $flightRe . '#', $match))) {
                                    $this->flightDates[$f] = $currentDate;
                                }
                            }
                        }

                        return splitter('#(.+\s+Flight\s+\d+.*)#i', $s);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $r = '#';
                            $r .= '(?<AirlineName>[^\n]+)\s+';
                            $r .= 'Flight\s+(?P<FlightNumber>\d+)\s+';
                            $r .= '(operated\s+by\s*PAL\s+Express)?';
                            $r .= '(?P<DepName1>.*?)\s+\(\s*(?P<DepCode>\w{3})\s*\)\s+';
                            $r .= '(?P<DepName2>.*?)\s+';
                            $r .= '(?P<DepTime>\d+:\d+\s+[AP]M)\s+';
                            $r .= '(?P<ArrName1>.*?)\s+\(\s*(?P<ArrCode>\w{3})\s*\)\s+';
                            $r .= '(?P<ArrName2>.*?)\s+';
                            $r .= '(?P<ArrTime>\d+:\d+\s+[AP]M)\s+';
                            $r .= '#is';
                            $res = [];

                            if (preg_match($r, $text, $m)) {
                                $res['AirlineName'] = $m['AirlineName'];
                                $res['FlightNumber'] = $m['FlightNumber'];

                                foreach (['Dep', 'Arr'] as $key) {
                                    $res[$key . 'Code'] = $m[$key . 'Code'];
                                    $res[$key . 'Name'] = nice($m[$key . 'Name1'] . ' (' . $m[$key . 'Name2'] . ')');

                                    if ($d = $this->flightDates[$res['FlightNumber']]) {
                                        $res[$key . 'Date'] = strtotime($d . ', ' . $m[$key . 'Time']);
                                    }
                                }
                            }

                            return $res;
                        },
                    ],
                ],
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
