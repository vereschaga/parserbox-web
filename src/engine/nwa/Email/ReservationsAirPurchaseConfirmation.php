<?php

namespace AwardWallet\Engine\nwa\Email;

class ReservationsAirPurchaseConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#Northwest\.Airlines@nwa\.com#i";
    public $reProvider = "#nwa\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+purchasing\s+your\s+travel\s+with\s+nwa\.com#i";
    public $rePlainRange = "";
    public $typesCount = "2";
    public $langSupported = "en";
    public $reSubject = "#nwa\.com\s+Reservations\s+Air\s+Purchase\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "nwa/it-1735032.eml, nwa/it-1735034.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#Date:.*(\d{4})#');
                    $regex = '#(.*)\s+Confirmation\s+number:\s+([\w\-]+)#';
                    $this->confNos = [];

                    if (preg_match_all($regex, $text, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->confNos[$m[1]] = $m[2];
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (count($this->confNos) == 1) {
                            return reset($this->confNos);
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Total\s+Cost:\s+(.*)#');

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $regex = '#\w+,\s+\w+\s+\d+\s+Flight:\s+(?:(?s).*?)Seat.*#';

                        if (preg_match_all($regex, $text, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight:\s+(.*)\s+\#?(\d+)#', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $regex1 = '#' . $value . ':\s+(.*?),\s+(\w+)\s+(\d+)\s+(\d+:\d+\s+(?:am|pm))#i';
                                $regex2 = '#' . $value . ':\s+(.*)\s+\((\w+)\)\s+\w+\.\s+(\w+)\.\s*(\d+)\s+(\d+:\d+\s+(?:am|pm))#i';

                                if (preg_match($regex1, $text, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                                    $res[$key . 'Date'] = strtotime($m[3] . ' ' . $m[2] . ' ' . $this->year . ', ' . $m[4]);
                                } elseif (preg_match($regex2, $text, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($m[4] . ' ' . $m[3] . ' ' . $this->year . ', ' . $m[5]);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class:\s+(.*)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Seat\(?s?\)?:\s+(.*)#');
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
