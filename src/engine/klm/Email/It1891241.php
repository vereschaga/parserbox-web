<?php

namespace AwardWallet\Engine\klm\Email;

class It1891241 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[^\w\d]klm[^\w\d]#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#Itinerary\s+Information.*?\s+KLM\s+#";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[^\w\d]klm[^\w\d]#i";
    public $reProvider = "#[^\w\d]klm[^\w\d]#i";
    public $caseReference = "6998";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "klm/it-1891241.eml, klm/it-2148575.eml, klm/it-2166971.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));
                    $text = text($this->setDocument("application/pdf", "simpletable"));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s+number\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return beautifulName(re("#Passenger\s+Name\s+E\-ticket\s+number[:\s]+Number\s+Loyalty\s+Program\s+([^\n]+)\s+\d+\s+\w+/#"));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Fare\s+amount\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Fare amount\s*:\s*([^\n]+)#"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax/fee/surcharges\s*:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//tr[contains(., 'Depart') and contains(., 'Flight')]/following-sibling::tr[contains(., ':') and contains(., 'OK')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $r = [];

                            if (re("#(?:^|\n)\s*(\d+[A-Z]{3})\s+([A-Z\d]{2})(\d+)\s+(.*?)\s+([A-Z]{3})\s+(\d+:\d+)\s+(.*?)\s+([A-Z]{3})\s+([A-Z]\s+)?\w+\s+#")) {
                                $r = [
                                    'DepDate'      => strtotime(re(1) . ',' . re(6), $this->date),
                                    'AirlineName'  => preg_match("#^\d+$#", re(2)) ? AIRLINE_UNKNOWN : re(2),
                                    'FlightNumber' => preg_match("#^\d+$#", re(2)) ? re(2) . re(3) : re(3),
                                    'DepName'      => re(4),
                                    'DepCode'      => re(5),
                                    'ArrName'      => re(7),
                                    'ArrCode'      => re(8),
                                    'ArrDate'      => MISSING_DATE,
                                    'BookingClass' => nice(re(9)),
                                ];
                            } else {
                                $prev = filter(nodes("preceding-sibling::tr[1]/td"));
                                re("#(?:^|\n)\s*(\d+[A-Z]{3})\s+([A-Z\d]{2})(\d+)\s+(\d+:\d+)\s+([A-Z]\s+)?\w+\s+#");
                                $r = [
                                    'DepDate'      => strtotime(re(1) . ',' . re(4), $this->date),
                                    'AirlineName'  => preg_match("#^\d+$#", re(2)) ? AIRLINE_UNKNOWN : re(2),
                                    'FlightNumber' => preg_match("#^\d+$#", re(2)) ? re(2) . re(3) : re(3),
                                    'DepName'      => reset($prev),
                                    'DepCode'      => preg_match("#\b([A-Z]{3})\b$#", reset($prev), $m) ? $m[1] : TRIP_CODE_UNKNOWN,
                                    'ArrName'      => end($prev),
                                    'ArrCode'      => preg_match("#\b([A-Z]{3})\b$#", end($prev), $m) ? $m[1] : TRIP_CODE_UNKNOWN,
                                    'ArrDate'      => MISSING_DATE,
                                    'BookingClass' => nice(re(9)),
                                ];
                            }

                            return $r;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true, true);
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
