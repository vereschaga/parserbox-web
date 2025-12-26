<?php

namespace AwardWallet\Engine\korean\Email;

class ConfirmationOfReservationAndTicketPurchaseRequest extends \TAccountCheckerExtended
{
    public $reFrom = "";
    public $reProvider = "";
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Koreanair\.com#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "korean/it-1712566.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation Number:.*?\s+([\w\d]+)\n#is");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re('#traveller information\s*(.*?)\s*Frequent#is');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return str_replace(',', '', re("#([\d,.]+)\s+\w+\s+total#is"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re("#[\d,.]+\s+(\w+)\s+total#is");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $its = preg_split("#Flight \d+#ms", $text);
                        unset($its[0]);

                        return $its;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Airline:.*?([\d\w]+)\n#is");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#Departure:\s*[\d:]+\s*(.*?)\s+Arrival#ims");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $depDate = strtotime(re('#\s*\w+,(.*?)\n#ims') . ' ' . re("#Departure:\s*([\d:]+)\s*.*\n#ims"));
                            $arrDate = strtotime(re('#\s*\w+,(.*?)\n#ims') . ' ' . re("#Arrival:\s*([\d:]+)\s*(?:(\+\d+)\s*[^\s]+\s+)?.*\n#ims"));

                            if (!empty(re(2))) {
                                $arrDate = strtotime(re(2) . ' day', $arrDate);
                            }

                            return [
                                'DepDate' => $depDate,
                                'ArrDate' => $arrDate,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#Arrival:\s*[\d:]+\s*(?:\+\d+\s*[^\s]+\s+)?(.*?)\s+Airline#ims");
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#Airline:\s*(.*?)\s*[\d\w]+\n#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Aircraft:\s*(.*)\s*\n#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#Fare Type:\s*(.*?)\s*\n#ims");
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
