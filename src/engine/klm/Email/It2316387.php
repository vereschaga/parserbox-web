<?php

namespace AwardWallet\Engine\klm\Email;

class It2316387 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]klm[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Ihrer\s+KLM[-]Buchung#i";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#[@.]klm[.]com#i";
    public $reProvider = "#[@.]klm[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "15.01.2015, 09:14";
    public $crDate = "13.01.2015, 12:52";
    public $xPath = "";
    public $mailFiles = "klm/it-2316387.eml, klm/it-2318081.eml";
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
                        return reni('(\w+)  Ihr gewählter Flug');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = reni('
							Zahlungsinformationen .*?
							Nachname  (.+?)
							Kartennummer	
						');

                        return [$name];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Preis	 	 	([\d.,]+ \w+)');

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('
							Ihr gewählter Flug:
							(.+?)
							Preisübersicht
						');
                        $q = white('\b Von \b');

                        return splitter("/($q)/su", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('Flugnummer  (\w+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								Von (?P<DepName> .+?)
								nach (?P<ArrName> .+?)
								Flugnummer
							');
                            $res = re2dict($q, $text);

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate(1));
                            $time = uberTime(1);

                            $date = totime($date);
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate(2));
                            $time = uberTime(2);

                            $date = totime($date);
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('Klasse (.+?) \n');
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
