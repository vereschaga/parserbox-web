<?php

namespace AwardWallet\Engine\lufthansa\Email;

class It1918353 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@mobile[.]lufthansa[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@mobile[.]lufthansa[.]com#i";
    public $reProvider = "#[@.]mobile[.]lufthansa[.]com#i";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1608173.eml, lufthansa/it-1615971.eml, lufthansa/it-1918353.eml, lufthansa/it-1919292.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Booking\s*reference:\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re("#Name:\s*(.+?)\s*Flight-No:#is");

                        return [nice($name)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re("#Flight-No:\s*(.+?)\s*Flight:#is");

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/Flight:\s*(.+?)-(.+?)\s*Date:/is', node('.'), $ms)) {
                                return [
                                    'DepCode' => nice($ms[1]),
                                    'ArrCode' => nice($ms[2]),
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#Date:\s*(.+?)\s*Boarding:#is");
                            $date = \DateTime::createFromFormat('dMy', $date);

                            if (!$date) {
                                return;
                            }

                            $time = re('/Boarding:\s*(.+?)\s*Gate:/is');
                            $dt = $date;
                            $dt->modify($time);

                            return $dt ? $dt->getTimestamp() : null;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $cab = re("#Class:\s*(.+?)\s*BN:#is");
                            $cab = re('/\s*(.+?)\s*(?:$|,)/', $cab);

                            return nice($cab);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = re("#Seat:\s*(.+?)\s*Class:#is");

                            return nice($seat);
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
}
