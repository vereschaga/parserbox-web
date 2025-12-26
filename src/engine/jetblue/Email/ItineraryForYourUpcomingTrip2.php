<?php

namespace AwardWallet\Engine\jetblue\Email;

class ItineraryForYourUpcomingTrip2 extends \TAccountCheckerExtended
{
    public $rePlain = "#Thanks\s+for\s+choosing\s+JetBlue#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#mail@trans-em\.jetblue\.com#i";
    public $reProvider = "#jetblue\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "28.12.2014, 13:52";
    public $crDate = "28.12.2014, 13:43";
    public $xPath = "";
    public $mailFiles = "jetblue/it-2307017.eml";
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
                        return re('#Confirmation\s+Number\s*:\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//tr[contains(., "Name") and contains(., "Seats") and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 0]/td[2]//tr/td[1]');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s*:\s+(\S+)#'));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Date\s+Booked\s*:\s+(\d+\s+\w+\s+\d+)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Date") and contains(., "Flt") and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 0]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#^(\d+)$#i', node('./td[2]'));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return AIRLINE_UNKNOWN;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re('#^\d+\s+\w+\s+\d+$#i', node('./td[1]'));

                            if (!$dateStr) {
                                return null;
                            }
                            $res = null;

                            foreach (['Dep' => 3, 'Arr' => 4] as $key => $value) {
                                if (preg_match('#(.*)\s+\((\w{3})\)\s+(\d+:\d+[ap]m)#i', node('./td[' . $value . ']'), $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[3]);
                                }
                            }

                            return $res;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#^(\d+)$#', node('./td[last()]'));
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
