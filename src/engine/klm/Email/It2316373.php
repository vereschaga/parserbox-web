<?php

namespace AwardWallet\Engine\klm\Email;

class It2316373 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]klm[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "nl";
    public $typesCount = "1";
    public $reFrom = "#[@.]klm[.]com#i";
    public $reProvider = "#[@.]klm[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "14.01.2015, 11:42";
    public $crDate = "14.01.2015, 11:20";
    public $xPath = "";
    public $mailFiles = "klm/it-2316360.eml, klm/it-2316373.eml";
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
                        return reni('Uw (?:reserveringscode|boekingscode) is: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('\bNaam  (.+?) \(');

                        if (preg_match_all("/$q/isu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Flying Blue .*? (\w+ \d+) \n
						');

                        if (preg_match_all("/$q/iu", $text, $m)) {
                            return implode(',', nice($m[1]));
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Prijs	(\w+ [\d.,]+)');

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('
							Uw gekozen vlucht:
							(.+?)
							Prijsoverzicht
						');
                        $q = white('\bVan\b');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('Vluchtnummer (\w+\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return reni('Van (.+?) naar');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate(1));
                            $date = totime($date);
                            $time = uberTime(1);
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return reni('naar (.+?) Vlucht');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate(2));
                            $date = totime($date);
                            $time = uberTime(2);
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('Klasse (\w+)');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return reni('Totale reistijd: (\d+ uren \d+ minuten)');
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
        return ["nl"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
