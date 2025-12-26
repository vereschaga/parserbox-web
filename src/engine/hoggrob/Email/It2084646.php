<?php

namespace AwardWallet\Engine\hoggrob\Email;

class It2084646 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@hrgworldwide[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@hrgworldwide[.]com#i";
    public $reProvider = "#[@.]hrgworldwide[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "hoggrob/it-2084646.eml, hoggrob/it-2084647.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $info = re_white('
						Airline Locator\(s\)
						(.+?)
						(?:Ticket Details|Frequent Flyer Information)
					');
                    $air2conf = [];
                    $q = white('(\w{2,4}) \s+ (\w+)');

                    if (preg_match_all("/$q/isu", $info, $ms)) {
                        $airs = $ms[1];
                        $confs = $ms[2];

                        foreach ($airs as $i => $a) {
                            $air2conf[$a] = $confs[$i];
                        }
                    }
                    $this->air2conf = $air2conf;

                    $this->ppl = [between('Traveller', 'HRG')];

                    return splitter('#(Air:)#isu');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $air = re_white('Air: .+? - (\w+)');

                        if (isset($this->air2conf[$air])) {
                            return $this->air2conf[$air];
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->ppl;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('Air: .+? - (\w+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('Depart From: .+? \( (\w+) \)');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('
								Departure Date: (.+?) (?:PM|AM)
								Destination:'
                            );

                            return strtotime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('Destination: .+? \( (\w+) \)');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('
								Arrival Date: (.+?) (?:PM|AM)
								(?:Class:|Operated By:)'
                            );

                            return strtotime($date);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return between('Class:', 'Status:');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    // return $it;
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
