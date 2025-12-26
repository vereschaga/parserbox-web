<?php

namespace AwardWallet\Engine\lufthansa\Email;

class TravelInformation4 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?lufthansa#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#lufthansa#i";
    public $reProvider = "#lufthansa#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-2148585.eml";
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
                        return re('#Booking\s+Code\s*:\s+([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Dear\s+(.*?)\s*,#i')];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[./td[normalize-space(.) = "Departure"] and ./td[normalize-space(.) = "Arrival"]]/ancestor::table[1]/following-sibling::table[1]/descendant::td[1]/table[string-length(normalize-space(.)) > 1]/tbody/tr[2]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})(\d+)#i', node('./td[8]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $dateStr = re('#\d+/\d+#i', node('./td[2]'));

                            if (!$dateStr) {
                                return null;
                            }
                            $dateStr .= '/' . $year;
                            $res = null;

                            foreach (['Dep' => 4, 'Arr' => 6] as $key => $value) {
                                if (preg_match('#(\d+:\d+)\s+(.*)#i', node('./td[' . $value . ']'), $m)) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);
                                    $res[$key . 'Name'] = $m[2];
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[10]');

                            if (strlen($s) != 1) {
                                return ['Cabin' => $s];
                            } else {
                                return ['BookingClass' => $s];
                            }
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
