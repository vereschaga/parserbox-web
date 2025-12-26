<?php

namespace AwardWallet\Engine\saudisrabianairlin\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank you for booking your flight with Saudia Airlines#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ibesupport@saudiairlines\.com#i";
    public $reProvider = "#saudiairlines\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "saudisrabianairlin/it-2034935.eml";
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
                        return str_replace(' ', '', re('#Booking\s+reference\s*:\s+(\w+\s+-\s+\w+)#i'));
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//td[normalize-space(.) = "Passenger"]/ancestor::tr[1]/following-sibling::tr[1]/td[1]//b');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+amount:\s+(.*)#i'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Departure") and contains(., "Arrival") and not(.//tr)]/following-sibling::tr[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})(\d+)#i', node('./td[6]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Status'       => node('./td[last()]'),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./ancestor::*[1]/preceding-sibling::*[contains(., "to") and contains(., "-")][1]') . "\n";
                            $dateStr = re('#\d+\s+\w+\s+\d{4}#i', $subj);

                            if (!$dateStr) {
                                return;
                            }
                            $res = null;

                            foreach (['Dep' => 2, 'Arr' => 4] as $key => $value) {
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . node('./td[' . $value . ']'));
                                $res[$key . 'Name'] = nice(implode("\n", nodes('./td[' . ($value + 1) . ']//text()')), ',');
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[last() - 1]');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node('./td[last() - 3]');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#(\d+)\s+Stop#i', node('./td[last() - 4]'));
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
