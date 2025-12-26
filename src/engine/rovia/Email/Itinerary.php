<?php

namespace AwardWallet\Engine\rovia\Email;

class Itinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?rovia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#rovia#i";
    public $reProvider = "#@rovia\.#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "rovia/it-2037493.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->passengers = explode(', ', re('#Traveler\s+(.*)#i'));
                    $this->fullText = $text;

                    return xpath('//tr[contains(., "Depart") and ./following-sibling::tr[3][contains(., "Arrive")]]/ancestor::table[1]');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+Number\s*:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = node(".//*[contains(text(), 'Confirmation Number')]/ancestor-or-self::tr[1]/td[1]");

                            if (preg_match('#\s*(.*)\s+-\s+Flight\s+(\d+)#i', $subj, $m)) {
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
                            $dateStr = re('#\w+\s+\d+,\s+\d{4}#i');

                            if (!$dateStr) {
                                return;
                            }
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $res[$key . 'Name'] = cell($value, +1);

                                if ($res[$key . 'Name'] and !cell($value, 0, +1)) {
                                    $res[$key . 'Name'] .= ', ' . node('.//tr[contains(., "' . $value . '")]/following-sibling::tr[1]');
                                }

                                if ($terminal = cell($value, +4)) {
                                    $res[$key . 'Name'] .= ' (Terminal ' . $terminal . ')';
                                }
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . cell($value, +2));
                            }

                            if ($res['DepDate'] and $res['ArrDate']) {
                                correctDates($res['DepDate'], $res['ArrDate']);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class\s*:\s+(.*)#');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        $totalStr = re('#Total\s+Agency\s+Charges\s+to\s+Date\s+(.*)#i', $this->fullText);
                        $itNew[0]['TotalCharge'] = cost($totalStr);
                        $itNew[0]['Currency'] = currency($totalStr);
                        $itNew[0]['BaseFare'] = cost(re('#AirFare\s+(\d.*)#i', $this->fullText));
                        $itNew[0]['Tax'] = cost(re('#Tax\s+(\d.*)#i', $this->fullText));
                    }

                    return $itNew;
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
}
