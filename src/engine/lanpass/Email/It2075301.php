<?php

namespace AwardWallet\Engine\lanpass\Email;

class It2075301 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?lanpass#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#boardingpass@lan.com#i";
    public $reProvider = "#lanpass#i";
    public $caseReference = "7022";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re("#Reservation Code:\s*([^\n]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#Passenger:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $its = preg_split("#Flight Details#ims", $text);
                        unset($its[0]);

                        return $its;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $node = re("#Flight:\s*([^\n]+) operated#");
                            $node = uberAir($node);

                            return $node;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $node = re("#From:\s*([^\n]+)#");
                            $node = re("#\(([^\n]+)\)#", $node);

                            return $node;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#From:\s*([^\n]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#Departure Date:\s*([^\n]+)#");
                            $time = re("#Departure Time:\s*([^\n]+)#");
                            $date = $date . " " . $time;
                            $date = \DateTime::createFromFormat("d/M H:i", $date);

                            if ($date) {
                                return $date->getTimestamp();
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $node = re("#To:\s*([^\n]+)#");
                            $node = re("#\(([^\n]+)\)#", $node);

                            return $node;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#To:\s*([^\n]+)#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $node = re("#Class:\s*([^\n]+)#");

                            return $node;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $node = re("#Seat/Row:\s*([^\n]+)#");

                            return $node;
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
