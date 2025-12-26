<?php

namespace AwardWallet\Engine\priceline\Email;

class It2214405 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?priceline#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#priceline#i";
    public $reProvider = "#priceline#i";
    public $caseReference = "6701";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "priceline/it-2214405.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $html = $this->getDocument("text/html");
                    $html = clear("#<html[^>]*?>#", $html, "<html>");
                    $text = $this->setDocument("source", $html);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#,\s*AIRLINE\s*:\s*[A-Z]+/\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return niceName(re("#\n\s*PASSENGER\s+(.*?)\s+BOOKING\s+REF#s"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*ISSUE\s+DATE\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'DEPARTURE')]/ancestor::tr[1]/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^[^\n]+\s+([A-Z\d]{2})\s*(\d+)\s*/\s*([A-Z])#", xpath("td[2]")),
                                'FlightNumber' => re(2),
                                'BookingClass' => re(3),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('td[3]', $node, true, "#([^/]+)\s*/#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepDate' => totime(re("#(\d+[A-Z]{3}\d+\s+\d+)\s+(\d+[A-Z]{3}\d+\s+\d+)#")),
                                'ArrDate' => totime(re(2)),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('td[3]', $node, true, "#/\s*([^/]+)#");
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
