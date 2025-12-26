<?php

namespace AwardWallet\Engine\israel\Email;

class It2045724 extends \TAccountCheckerExtended
{
    public $mailFiles = "israel/it-2045724.eml, israel/it-2045725.eml, israel/it-2045726.eml, israel/it-2045727.eml, israel/it-2045728.eml, israel/it-2045729.eml, israel/it-12036814.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $ppl = node("(//*[contains(text(), 'Passenger Name(s) :')]/ancestor::table[1])[1]");
                    $ppl = preg_split('/\s*\d+[.]\s*/', $ppl);
                    array_shift($ppl);
                    $this->ppl = $ppl;

                    $sts = nodes("//*[contains(text(), 'Res status :')]/following::td[1]");
                    $sts = array_unique($sts);
                    $this->st = sizeof($sts) === 1 ? $sts[0] : null;

                    return $this->http->XPath->query('//*[contains(text(),"Pnr Number :")]/ancestor::blockquote[1] | //*[contains(text(),"Flight :")]/ancestor::table[1]');
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('Booking Reference : \w+[/]([A-Z\d]{5,})');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->ppl;
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $ticket = re('/TICKET\s*:\s+\w+\/[A-Z\s]*([\d\s]*?\d{5}[\d\s]*?)\s+FOR\s+/i'); // TICKET: RO/ETKT 281 4917158573 FOR BARZILAY/BILLY MR

                        if ($ticket) {
                            return [$ticket];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('^ (\d+ .)');

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return $this->st;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//*[contains(text(), 'Flight :')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('Flight : (?:.+?) : (\w+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(text(), 'Departure :')]/following::tr[1]/td[1]");

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = cell('Departure :', +1);
                            $dt = \DateTime::createFromFormat('H:i (d M Y)', $dt);
                            $dt = $dt ? $dt->getTimestamp() : null;

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(text(), 'Departure :')]/following::tr[1]/td[2]");

                            return nice($name);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = cell('Arrival :', +1);
                            $dt = \DateTime::createFromFormat('H:i (d M Y)', $dt);
                            $dt = $dt ? $dt->getTimestamp() : null;

                            return $dt;
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            $result = [];

                            $patternTerminal = '/^Terminal\s+([A-z\d\s])$/i';

                            if ($terminalDep = $this->http->FindSingleNode(".//*[contains(text(), 'Departure :')]/following::tr[3]/td[1]", $node, true, $patternTerminal)) {
                                $result['DepartureTerminal'] = $terminalDep;
                            }

                            if ($terminalArr = $this->http->FindSingleNode(".//*[contains(text(), 'Departure :')]/following::tr[3]/td[3]", $node, true, $patternTerminal)) {
                                $result['ArrivalTerminal'] = $terminalArr;
                            }

                            return $result;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Aircraft Type', +1);

                            return nice($x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = cell('Flight Class :', +1);
                            $q = white('(\w+) \( (\w+) \)');

                            if (preg_match("/$q/", $info, $ms)) {
                                return [
                                    'Cabin'        => $ms[1],
                                    'BookingClass' => $ms[2],
                                ];
                            }

                            return nice($info); // fallback
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $info = cell('Reserved Seats :', +1);
                            $q = white('Seat: (\d+[A-Z]+)');

                            if (preg_match_all("/$q/isu", $info, $ms)) {
                                $seats = implode(',', $ms[1]);

                                return $seats;
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Flight Duration :', +1);

                            return nice($x);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            $x = cell('Flight Service :', +1);

                            return nice($x);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            if (re_white('Non Stop')) {
                                return 0;
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@talma.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'E-TICKET FOR ') !== false
            || stripos($headers['subject'], 'Trip Details ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"EL AL Israel")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(.),"Res status :")]')->length > 0;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }
}
