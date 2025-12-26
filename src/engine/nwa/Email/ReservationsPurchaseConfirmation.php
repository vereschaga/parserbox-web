<?php

namespace AwardWallet\Engine\nwa\Email;

class ReservationsPurchaseConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#nwa@nwemail.nwa.com#i";
    public $reProvider = "#nwa@nwemail\.nwa\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Northwest\s+Airlines#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#nwa.com\s+Reservations\s+Purchase\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "nwa/it-1735023.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re('#Air\s+Confirmation\s+\#:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nice(explode(',', re('#Traveler\(s\):\s+(.*)#')));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Total\s+Flight\s+Cost:\s+(.*)#');

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Flight Information") and not(.//tr)]/following-sibling::tr[contains(., ":") and not(contains(., "Total"))]';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[2]');
                            $regex = '#';
                            $regex .= '(.*)\s+\#(\d+)\s+';
                            $regex .= '(.*?)\s+\((\w+)\)\s+';
                            $regex .= '(.*?)\s+\((\w+)\)\s+';
                            $regex .= '#';

                            if (preg_match($regex, $subj, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'DepName'      => $m[3],
                                    'DepCode'      => $m[4],
                                    'ArrName'      => $m[5],
                                    'ArrCode'      => $m[6],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[1]');

                            if (preg_match('#(\w+\s+\d+,\s+\d+)\s+Depart\s+(.*)\s+Arrive\s+(.*)#', $subj, $m)) {
                                return [
                                    'DepDate' => strtotime($m[1] . ', ' . $m[2]),
                                    'ArrDate' => strtotime($m[1] . ', ' . $m[3]),
                                ];
                            }
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node('./td[4]');
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
