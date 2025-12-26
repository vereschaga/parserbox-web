<?php

namespace AwardWallet\Engine\icelandair\Email;

class It2 extends \TAccountCheckerExtended
{
    public $reFrom = "#icelandair#i";
    public $reProvider = "#icelandair#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?icelandair#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("#e-ticke.*?\.pdf#i", "text");

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Booking reference[:\s]+([A-Z\d\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Name\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Total airfare\s*:\s*([^\n]+)#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        //return cost(re("#\n\s*Air fare\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $tax = 0;

                        foreach (preg_split("#\s+#", clear("#[^\d. ]#", re("#\n\s*Taxes\s*:\s*([^\n]+)#"))) as $item) {
                            $tax += $item;
                        }

                        return $tax;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\n\s*Date of issue\s*:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#\n\s*([A-Z\d]{2})(\d+)\s+([A-Z]{3})\s+([A-Z]{3})\s+(\d+[A-Z]{3})\s+([A-Z])\s+(\d+:\d+)\s+(\d+:\d+)\s+(\d+[A-Z])#");

                            $dep = re(5) . ',' . re(7);
                            $arr = re(5) . ',' . re(7);

                            correctDates($dep, $arr);

                            return [
                                'AirlineName'  => re(1),
                                'FlightNumber' => re(2),
                                'DepCode'      => re(3),
                                'ArrCode'      => re(4),
                                'BookingClass' => re(6),
                                'DepDate'      => $dep,
                                'ArrDate'      => $arr,
                                'Seats'        => re(9),
                            ];
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
