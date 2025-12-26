<?php

namespace AwardWallet\Engine\tport\Email;

class It2046565 extends \TAccountCheckerExtended
{
    public $reSubject = [
        "Electronic Ticket Receipt",
        "Recibo de bilhete eletrônico",
        "Ricevuta biglietto elettronico",
    ];

    public $mailFiles = "tport/it-2046511.eml, tport/it-2046512.eml, tport/it-2046514.eml, tport/it-2046515.eml, tport/it-2046565.eml, tport/it-2131187.eml, tport/it-5605542.eml, tport/it-5605546.eml, tport/it-6744939.eml, tport/it-8576925.eml";

    private static $reBody = [
        'en' => ["This Electronic Ticket Receipt has been brought to you", 'This Electronic Ticket Receipt has been brought to you by Travelport ViewTrip and your travel provider'],
        'pt' => ["Este Recibo de bilhete eletrônico foi fornecido a você pelo", 'Este Recibo de Bilhete Electrónico é posto à sua disposição pelo Travelport'],
        'it' => "Questa ricevuta del biglietto elettronico ti è stata fatta pervenire",
        'sk' => 'vaša cestovná kancelária vám prinášajú toto potvrdenie o elektronickej letenke',
        'de' => 'Diesen e-Ticket-Beleg stellen Ihnen Travelport ViewTrip und Ihr Reiseanbieter zur Verfügung',
        'es' => 'Travelport ViewTrip y su agencia de viajes le proporcionan el presente recibo de billete electrónico',
        'hu' => 'Ezt az elektronikus jegy-visszaigazolást a Travelport ViewTrip',
        'zh' => '本电子机票存根联由 Travelport',
    ];

    private $anchor = 'Travelport';

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$reBody as $lang => $re) {
            if (is_array($re)) {
                foreach ($re as $r) {
                    if ($this->http->XPath->query("//text()[contains(normalize-space(.),'{$r}')]")->length > 0 && stripos($body, $this->anchor) !== false) {
                        return true;
                    }
                }
            } elseif (is_string($re) && $this->http->XPath->query("//text()[contains(normalize-space(.),'{$re}')]")->length > 0 && stripos($body, $this->anchor) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'viewtrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // слишком слабый детект
//        if (isset($this->reSubject)) {
//            foreach ($this->reSubject as $reSubject) {
//                if (stripos($headers["subject"], $reSubject) !== false) {
//                    return true;
//                }
//            }
//        }

        return false;
    }

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
                        return re_white('Reservation Number: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[1]");

                        return $ppl;
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $tn = nodes("//*[contains(text(), 'e-Ticket Number')]/following::text()[normalize-space(.)][1]");

                        return $tn;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return $this->http->FindSingleNode("(//td[contains(., 'Status') and not(.//td)]/following-sibling::td[1])[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return (float) $this->http->FindSingleNode("//td[contains(., 'Total') and not(.//td)]/following-sibling::td[2]");
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return $this->http->FindSingleNode("//td[contains(., 'Total') and not(.//td)]/following-sibling::td[1]", null, true, '/\s*([A-Z]{3})\s*/');
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return (float) $this->http->FindSingleNode("//td[contains(., 'Fare:') and not(.//td)]/following-sibling::td[2]");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $res = xPath("//*[contains(normalize-space(text()), 'Confirmation Number:')]/ancestor::table[1]");

                        if ($res->length === 0) {
                            $res = xPath("//tr[contains(., 'Arrive:') and not(.//tr)]/ancestor::table[1]/preceding-sibling::table[1]");
                        }

                        return $res;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re_white('\( (?:\w+) \) (\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node("./following::table[1]//*[contains(text(), 'Depart:')]/following::td[1]");

                            return re_white('\( (\w+) \)', $info);
                        },

                        "DepartureTerminal" => function ($text = '', $node = '', $it = null) {
                            return node("./following::table[1]//*[contains(text(), 'Depart:')]/following::td[1]/descendant::text()[contains(., 'Terminal')]", $node, true, '/Terminal\s+\b([A-Z\d]{1,3})\b/');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('(\d+ \w+ \d{4})');
                            $date = nice($date);

                            $info = implode(' ', nodes('./following::table[1]//td[3]'));
                            $time1 = uberTime($info, 1);
                            $time2 = uberTime($info, 2);

                            if (stripos($time2, 'm') === false) {
                                $time2 = substr(trim($time2), 0, 5);
                            }

                            $date = strtotime($date);
                            $dt1 = strtotime($time1, $date);

                            if (!empty($time2)) {
                                $dt2 = strtotime($time2, $date);
                            } else {
                                $dt2 = MISSING_DATE;
                            }
                            //							if ( $dt2 !== MISSING_DATE && $dt2 < $dt1)
                            //								$dt2 = strtotime('+1 day', $dt2);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = node("./following::table[1]//*[contains(text(), 'Arrive:')]/following::td[1]");

                            return re_white('\( (\w+) \)', $info);
                        },

                        "ArrivalTerminal" => function ($text = '', $node = '', $it = null) {
                            return node("./following::table[1]//*[contains(text(), 'Arrive:')]/following::td[1]/descendant::text()[contains(., 'Terminal')]", $node, true, '/Terminal\s+([A-Z\d]{1,3})/');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re_white('\( (\w+) \) (?:\d+)');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('\) \d+ \s{2,} (.+?) \(');

                            return nice($x);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $x = re_white('\) \d+ \s{2,} .+? \( (\w+) \)');

                            return nice($x);
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return re_white('Confirmation Number: (\w+)');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return $it;
                },
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return count(self::$reBody);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$reBody);
    }
}
