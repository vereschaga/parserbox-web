<?php

namespace AwardWallet\Engine\tport\Email;

class It2122363 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@travelport[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#OTR@travelport[.]com#i";
    public $reProvider = "";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "tport/it-2122363.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $userEmail = strtolower(re("#\n\s*To\s*:[^\n]*?([^\s@\n]+@[\w\-.]+)#"));

                    if (!$userEmail) {
                        $userEmail = niceName(re("#\n\s*To\s*:\s*([^\n]+)#"));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower(re("#([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-.]+)#", $this->parser->getHeader("To")));
                    }

                    if (!$userEmail) {
                        $userEmail = strtolower($this->parser->getHeader("To"));
                    }

                    if ($userEmail) {
                        $this->parsedValue('userEmail', $userEmail);
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('Reservierungs-ID:  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//img[contains(@src, 'People')]/ancestor-or-self::tr[2]/following-sibling::tr");

                        return nice($ppl);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = between('Heutiges Datum:', 'Reservierungs-ID:');
                        $date = uberDateTime(en($date));

                        return strtotime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Bestätigungsnummer:')]/ancestor::table[2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re_white('Flug .+? - (\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('Abreise: .+? \( (\w+) \)');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $date = clear('/[.]/', $date);
                            $date = en($date);

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $date = strtotime($date);
                            $dt1 = strtotime($time1, $date);
                            $dt2 = strtotime($time2, $date);

                            if ($dt2 < $dt1) {
                                $dt2 = strtotime('+1 day', $dt2);
                            }

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('Ankunft: .+? \( (\w+) \)');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re_white('Flug .+? \( (\w+) \)');
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return between('Flugzeug:', 'Flugzeit:');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return between('Serviceklasse:', '(');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re_white('Serviceklasse: .+? \( (\w+) \)');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $a = nodes(".//*[contains(text(), 'Sitzplatz')]/ancestor::tr[1]/following-sibling::tr//tr/td[1]");
                            $a = array_map(function ($x) { return re_white('(\w+)', $x); }, $a);

                            return implode(',', $a);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return between('Flugzeit:', 'Mahlzeiten:');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return between('Mahlzeiten:', 'Bordservice:');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (re_white('Nonstop')) {
                                return 0;
                            }
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return re_white('Bestätigungsnummer:  (\w+)');
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
