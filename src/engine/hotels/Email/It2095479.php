<?php

namespace AwardWallet\Engine\hotels\Email;

class It2095479 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]hotels[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]hotels[.]com#i";
    public $reProvider = "#[@.]hotels[.]com#i";
    public $caseReference = "";
    public $isAggregator = "1";
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
                        $node = node("((//*[contains(text(), 'Price Summary')]/ancestor-or-self::table[3]//tr)[1]//table)[3]");

                        return re("#CONFIRMED\s*[A-Za-z]+\s*([A-Z\d-]+)#", $node);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Traveler Information')]/ancestor-or-self::tr[1]/following-sibling::tr[1]//table//tr//td[1]//strong");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Total:\s*([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#CONFIRMED#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//*[contains(text(), "Total travel time")]/ancestor-or-self::tr[1]/following-sibling::tr[1]';

                        return xpath($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//tr[3]");

                            return re("#\s*[0-9]+#", $node);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return uberAircode(node(".//tr[2]//td[1]"));
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node(".//tr[1]//td[2]");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $time = uberTime(node(".//tr[2]//td[1]"));
                            $date = uberDate(node("./preceding-sibling::tr[1]//td[1]"));
                            $date = $date . " " . $time;

                            return totime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return uberAircode(node(".//tr[2]//td[2]"));
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node(".//tr[1]//td[3]");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $time = uberTime(node(".//tr[2]//td[2]"));
                            $date = uberDate(node("./preceding-sibling::tr[1]//td[1]"));
                            $date = $date . " " . $time;

                            return totime($date);
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//tr[3]");

                            return trim(re("#([A-Za-z]+)\s*[0-9]+#", $node));
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//tr[1]/td[4]");

                            return [
                                'Duration'      => re("#[0-9]+\sh\s*[0-9]+\s*m#", $node),
                                'TraveledMiles' => re("#([0-9]+)\s*mi#", $node),
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $node = node(".//tr[4]");
                            $node = re("#([^\n]+)\|#", $node);

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
        return true;
    }
}
