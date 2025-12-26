<?php

namespace AwardWallet\Engine\westjet\Email;

class Itinerary extends \TAccountCheckerExtended
{
    public $reFrom = "#noreply@itinerary.westjet.com#i";
    public $reProvider = "#itinerary.westjet.com#i";
    public $rePlain = "#Thank you for choosing WestJet#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "westjet/it-1437937.eml";

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
                        $xpath = "//text()[contains(., 'Reservation code')]/ancestor::td[1]/following-sibling::td[last()]";

                        return node($xpath);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\s+(.*)'s\s+itinerary#")];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//td[contains(text(), 'Date') and following-sibling::td[1][contains(text(), 'From')]]/ancestor::tr[3]/following-sibling::tr[1]//tr/td[5]";
                        $subjs = nodes($xpath);

                        if (isset($subjs[0])) {
                            return nodes($xpath)[0];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//td[contains(text(), 'Date') and following-sibling::td[1][contains(text(), 'From')]]/ancestor::tr[3]/following-sibling::tr[1]//tr";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+#", node('./td[4]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[2]/text()[1]');
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]/text()[1]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[1]');
                            $depDatetimeStr = $arrDatetimeStr = '';
                            $regex = '#(\d+\s+\w+\s+\d+)(?:\s+-\s+(\d+\s+\w+\s+\d+))?#';

                            if (preg_match($regex, $subj, $m)) {
                                $depDatetimeStr = $m[1];
                                $arrDatetimeStr = (isset($m[2])) ? $m[2] : $m[1];
                            }
                            $depDatetimeStr .= ', ' . node('./td[2]/text()[2]');
                            $arrDatetimeStr .= ', ' . node('./td[3]/text()[2]');

                            return [
                                'DepDate' => strtotime($depDatetimeStr),
                                'ArrDate' => strtotime($arrDatetimeStr),
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re('#(\w+)\s+\d+#', node('./td[4]'));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node('./td[6]');
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
