<?php

namespace AwardWallet\Engine\virginamerica\Email;

class It2224078 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?virginamerica#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#virginamerica#i";
    public $reProvider = "#virginamerica#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "virginamerica/it-2224078.eml";
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
                        return re('#Confirmation\s+Code\s*:\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//tr[contains(., "Traveler") and contains(., "Points") and contains(., "Frequent") and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 1]/td[1]');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (re('#Total\s+\(in\s+(.*?)\):\s+(.*)#i')) {
                            return [
                                'Currency'    => currency(nice(re(1))),
                                'TotalCharge' => cost(re(2)),
                            ];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//text()[normalize-space(.) = "Departing"]/ancestor::table[2]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match_all('#Depart:#i', $node->nodeValue, $m) and count($m[0]) > 1) {
                                return null;
                            }

                            return re('#Flight\s+(\d+)#');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $dateStr = re('#(\d+/\d+/\d+)\s+-\s+Flight\s+\d+#i');

                            if (!strtotime($dateStr)) {
                                return null;
                            }
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                if (preg_match('#:\s+(.*)\s+\((\w{3})\)#i', cell($value), $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                }
                                $t = re('#^\d+:\d+\s*(?:[ap]m)?$#i', cell($value, +2));

                                if ($t) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $t);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Seat\s+Type:\s+(.*)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (preg_match_all('#Seat\s+(\d+[A-Z])\s+#i', $node->nodeValue, $m)) {
                                return $m[1];
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re('#Stops:\s+(\d+)#');
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
