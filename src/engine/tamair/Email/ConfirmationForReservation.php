<?php

namespace AwardWallet\Engine\tamair\Email;

class ConfirmationForReservation extends \TAccountCheckerExtended
{
    public $reFrom = "#siteb2c@tam\.com\.br#i";
    public $reProvider = "#tam\.com\.br#i";
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+TAM\s+B2C\s+to\s+make\s+your\s+travel\s+reservation#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "tamair/it-1747033.eml";
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
                        return re('#Order\s+Number:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Traveller\s+information\s+((?s).*?)\s+Contact\s+information#i');

                        if ($subj) {
                            $passengers = array_values(array_filter(nice(explode("\n", $subj))));

                            return $passengers;
                        }
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "travellers") and contains(., "taxes and fees") and not(.//tr)]/following-sibling::tr[1]';
                        $subj = node($xpath);

                        if (preg_match('#x\s+\((.*?)\s+\+\s+(.*?)\)\s+=\s+(.*)#', $subj, $m)) {
                            return [
                                'BaseFare'    => cost($m[1]),
                                'Tax'         => cost($m[2]),
                                'TotalCharge' => cost($m[3]),
                                'Currency'    => currency($m[3]),
                            ];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Confirmation\s+for\s+reservation\s+Thank\s+you\s+for\s+choosing#', $text)) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[contains(., "Departure:")]/ancestor::table[3]';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = cell('Airline:', +1);

                            if (preg_match('#\s(\w+?)(\d+)#', $subj, $m)) {
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
                            $res = null;
                            $dateStr = re('#\w+\s+\d+,\s+\d+#');

                            foreach (['Dep' => 'Departure', 'Arr' => 'Arrival'] as $key => $value) {
                                $timeStr = cell($value . ':', +1);
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                                $res[$key . 'Name'] = cell($value . ':', +2);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return cell('Aircraft:', +1);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $xpath = './/tr[contains(., "Meal:") and not(.//tr)]/preceding-sibling::tr[1]/td[last()]';

                            return node($xpath);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return cell('Duration:', +1);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return cell('Meal:', +1);
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
