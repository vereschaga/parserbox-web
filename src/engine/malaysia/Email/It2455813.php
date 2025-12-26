<?php

namespace AwardWallet\Engine\malaysia\Email;

class It2455813 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?\bmalaysiaairlines\b#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#\bmalaysiaairlines\b#i', 'us', ''],
    ];
    public $reProvider = [
        ['#\bmalaysiaairlines\b#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "09.02.2015, 22:11";
    public $crDate = "09.02.2015, 21:50";
    public $xPath = "";
    public $mailFiles = "malaysia/it-12047064.eml, malaysia/it-2455813.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (strlen($text) < 300) {
                        $body = implode("\n", $this->parser->getRawBody());
                        $pos = stripos($body, '<html>');
                        $pos2 = stripos($body, '</html>');

                        if (!empty($pos) && !empty($pos2)) {
                            $text = strip_tags(substr($body, $pos, $pos2 - $pos));
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:\n\s*|\s{3,})LOCATOR/CONTROL\s+NO[.:\s]+([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ptext = re("#\n\s*FOR PASSENGERS\s*:\s*.*\n((?:.*\n){1,10})---#");
                        $rows = array_filter(explode("\n", $ptext));

                        foreach ($rows as $row) {
                            $values = preg_split("#\s{3,}#", trim($row));

                            if (count($values) == 1 && strpos($values[0], '/')) {
                                $passengers[] = $values[0];
                            }

                            if (count($values) == 2) {
                                if (strpos($values[0], '/')) {
                                    $result['Passengers'][] = $values[0];
                                }

                                if (preg_match("#TICKETED\s*([\d \-]{5,})\b#", $values[1], $m)) {
                                    $result['TicketNumbers'][] = trim($m[1]);
                                }
                            }
                        }

                        return $result;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*\d+ FREQUENT TRAVELER ([^\n]+)#ix")];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*[\s*]+(CONFIRMED)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#/#", re("#(?:\n\s*|\s{3,})DATE/TIME\s*:\s*([^\n]+)#"), ' '));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*([A-Z]{3}\s+\d+[A-Z]{3}\s+\d+\s*\n)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#^[^\n]+\s+(.*?)\s+(\d+)\s+([A-Z])\s+([^/]+)#"),
                                'FlightNumber' => re(2),
                                'Aircraft'     => trim(re(4)),
                                'BookingClass' => re(3),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\n\s*DEPART\s*:\s*([^\n]*?)\s{2,}#"));
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\n\s*DEPART\s*:\s*[^\n]*?\s{2,}TERMINAL:\s*(.+)#"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#^([^\n]+)#");

                            $dep = totime($date . ' ' . re("#\n\s*DEPART\s*:\s*.*?\s+(\d{4})#"));
                            $arr = totime($date . ' ' . re("#\n\s*ARRIVE\s*:\s*.*?\s+(\d{4})(\+\d+)?#"));

                            if (re(2)) {
                                $arr = strtotime(re(2) . ' days', $arr);
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\n\s*ARRIVE\s*:\s*([^\n]*?)\s{2,}#"));
                        },

                        "ArrivalTerminal" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#\n\s*ARRIVE\s*:\s*[^\n]*?\s{2,}TERMINAL:\s*(.+)#"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (preg_match_all("#\n\s*SEATS\s*:\s*(\d{1,3}[A-Z])\s#", $text, $m)) {
                                return $m[1];
                            }

                            return [];
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#NO SMOKING SEAT#ix") ? false : null;
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
