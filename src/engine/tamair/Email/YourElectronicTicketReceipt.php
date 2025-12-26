<?php

namespace AwardWallet\Engine\tamair\Email;

class YourElectronicTicketReceipt extends \TAccountCheckerExtended
{
    public $rePlain = "#(consulte\s+o\s+site|visit\s+the\s+website|visit\s+our\s+website|consulte)\s*[:\s]*www\.[\w]*tam\.com[\.br]*#msi";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = [
        "Exclusive Invite",
        "Your Electronic Ticket Receipt",
    ];
    public $reBody = [
        "en"=> ["RECORD LOCATOR", "Departure"],
        "pt"=> ["CÓDIGO DA RESERVA", "Data"],
    ];
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#nao-responda@tam\.com\.br#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "tamair/it-2.eml, tamair/it-2035424.eml, tamair/it-2941536.eml, tamair/it-3.eml, tamair/it-3383027.eml, tamair/it-3956908.eml, tamair/it-3957143.eml, tamair/it-4.eml, tamair/it-5691354.eml";
    public $pdfRequired = "0";

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'tam.com')]")->length < 1) {
            return false;
        }

        $body = text($parser->getHTMLBody());

        foreach ($this->reBody as $re) {
            if (strpos($body, $re[0]) !== false && strpos($body, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:CÓDIGO\s+DA\s+RESERVA|RECORD\s+LOCATOR)\s*:\s*([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#(?:NOME|NAME):\s+(.*)#')];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return [node("(//span[contains(normalize-space(.), 'Nº Passageiro Frequente:')])[1]", null, true, '/Nº Passageiro Frequente:\s+(\w+)/')];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = orval(
                            re('#TOTAL\s+(\w{3}\s*[\d\.\s\,]+)#'),
                            re('#Total\s*:\s+(\w{3}\s*[\d\.\s\,]+)#')
                        );

                        return total($subj);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell([
                            'Tarifa Aerea:',
                            'Air Fare:',
                        ], +2));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return array_sum(
                            array_map(
                                function ($s) {
                                    return str_replace(',', '.', $s);
                                },
                                (preg_match_all("#[\d\.]+#", re("#(?:Tax|Taxas):(.*?)Total#ms"), $m, PREG_PATTERN_ORDER) ? $m[0] : [])
                            )
                        );
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = strtotime(re('#(?:Data\s+de\s+emissão|Issue\s+date):\s+(\d+\s*\w+\s*\d+)#i'));
                        $this->year = date('Y', $date);
                        $this->reservationDate = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[(contains(., "Data:") or contains(., "Date:")) and following-sibling::tr[1][contains(., "Vôo:") or contains(., "Flight:")]]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})\s*(\d+)#i', node('./following-sibling::tr[1]'), $m)) {
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
                            if (preg_match('#(\d+)(\w+)#i', node('.'), $m)) {
                                $dateStr = date('d M Y', strtotime($m[1] . ' ' . $m[2], $this->reservationDate));
                            } else {
                                return [];
                            }
                            $res = null;

                            foreach (['Departure' => 2, 'Arrival' => 3] as $key => $value) {
                                $subj = node('./following-sibling::tr[' . $value . ']');

                                if (preg_match('#:\s*(\d+:\d+\s*(?:am\b|pm\b)?)?\s*(.+?)(?:, Terminal\s*(\w*)|$)#i', $subj, $m)) {
                                    if (!empty($m[1])) {
                                        $res[substr($key, 0, 3) . 'Date'] = strtotime($dateStr . ', ' . $m[1], false);

                                        if ($res[substr($key, 0, 3) . 'Date'] != MISSING_DATE && $res[substr($key, 0, 3) . 'Date'] < $this->reservationDate) {
                                            $res[substr($key, 0, 3) . 'Date'] = strtotime("+1 year", $res[substr($key, 0, 3) . 'Date']);
                                        }
                                    } else {
                                        $res[substr($key, 0, 3) . 'Date'] = MISSING_DATE;
                                    }

                                    if (!empty($m[3])) {
                                        $res[$key . 'Terminal'] = $m[3];
                                    }
                                    $res[substr($key, 0, 3) . 'Name'] = $m[2];
                                }
                            }

                            if ($res['ArrDate'] != MISSING_DATE && $res['ArrDate'] < $res['DepDate']) {
                                $res['ArrDate'] = strtotime('+1 day', $res['ArrDate']);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#(?:Aeronave|Aircraft)\s*:\s*(.*)#', node('./following-sibling::tr[5]'));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./following-sibling::tr[4]');

                            if (preg_match('#(?:Classe|Class):\s*(.*)\s+\(\s*(\w)\s*\)(?:\s+Assento\*\s*:\s*(.*))?#i', $subj, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                    'Seats'        => $m[3] ?? null,
                                ];
                            }
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
        return ["pt", "en"];
    }
}
