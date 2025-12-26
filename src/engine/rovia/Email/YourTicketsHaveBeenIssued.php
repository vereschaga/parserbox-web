<?php

namespace AwardWallet\Engine\rovia\Email;

class YourTicketsHaveBeenIssued extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Rovia.\s+Please\s+review\s+your\s+flights\s+itinerary\s+status\s+below#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#rovia[.]com#i";
    public $reProvider = "#@rovia\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "rovia/it-1983132.eml, rovia/it-2040336.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->passengers = [nice(re('#Dear\s+(.*?),#i'))];
                    $this->fullText = $text;

                    return xpath('//*[contains(text(), "Takes Off")]/ancestor::tr[1]/following-sibling::tr');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return ($confNo = node('./td[9]')) ? $confNo : CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return node('./td[7]');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#^([\w\s]+?)\s*-?\s*(\d+)$#i', node('td[5]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 1, 'Arr' => 2] as $key =>$value) {
                                $subj = node('./td[' . $value . ']');

                                if ($code = re('#[(](\w+)[)]#', $subj)) {
                                    $res[$key . 'Code'] = $code;
                                } else {
                                    $res[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                                    $res[$key . 'Name'] = $subj;
                                }
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $d = node('td[3]');

                            return totime(uberDateTime($d));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $d = node('td[4]');

                            return totime(uberDateTime($d));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = node('td[8]');

                            if ($seats != '-' and $seats) {
                                return $seats;
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = node('./td[last()]');

                            if (re('#^\s*(\d+)\s*$#i', $stops) !== null) {
                                return $stops;
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $regex = '#.*\((\w{3})\).*\((\w{3})\),\s+(\d+/\d+/\d+)\s+(\d+:\d+\s*(?:am|pm)?)\s+Lands\s+(\d+:\d+\s*(?:am|pm)?)\s+(\w{2})\s+(\d+)#';

                    if (preg_match($regex, $this->fullText, $m)) {
                        $segment['DepCode'] = $m[1];
                        $segment['ArrCode'] = $m[2];
                        $segment['DepDate'] = strtotime($m[3] . ', ' . $m[4]);
                        $segment['ArrDate'] = strtotime($m[3] . ', ' . $m[5]);
                        $segment['AirlineName'] = $m[6];
                        $segment['FlightNumber'] = $m[7];
                        $gotIt = false;

                        foreach ($it as &$i) {
                            if ($i['RecordLocator'] == CONFNO_UNKNOWN) {
                                $i['TripSegments'][] = $segment;
                                $gotIt = true;
                            }
                        }

                        if (!$gotIt) {
                            $it[] = [
                                'RecordLocator' => CONFNO_UNKNOWN,
                                'Passengers'    => $this->passengers,
                                'TripSegments'  => [$segment],
                            ];
                        }
                    }

                    return $it;
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
