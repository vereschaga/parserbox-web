<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class It16 extends \TAccountCheckerExtended
{
    public $mailFiles = "wagonlit/it-15.eml, wagonlit/it-1585366.eml, wagonlit/it-16.eml, wagonlit/it-17.eml, wagonlit/it-1850783.eml, wagonlit/it-2244121.eml, wagonlit/it-2397021.eml, wagonlit/it-2538508.eml, wagonlit/it-2631413.eml, wagonlit/it-3.eml, wagonlit/it-3010842.eml, wagonlit/it-3010843.eml, wagonlit/it-3010845.eml, wagonlit/it-4118286.eml, wagonlit/it-6341456.eml, wagonlit/it-8.eml, wagonlit/it-8741873.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->setDocument('application/pdf', 'text');

                    $text = re("#^(.*?)\s+(?:INFORMAÇÕES\s+GERAIS|GENERAL\s+INFORMATION)#ms");

                    $info = re('#(.+)\n[ ]*(?:Agent|Agente)#is');
                    $email = re('#\n\s*([A-Z._\-\d]+[@/:]+[A-Z._\-\d]+\.[A-Z_\-\d]+)#i', $info);
                    $email = strtolower(clear("#//|:#", $email, '@'));

                    if ($email) {
                        $this->parsedValue("userEmail", $email);
                    }

                    if (preg_match('/Total Amount\s+([\d.,]+)/', $text, $matches)) {
                        $this->parsedValue('TotalCharge', ['Amount' => (float) str_replace(',', '', $matches[1])]);
                    }

                    return splitter("#(\n\s*[^\s\d]+\s*,\s*[^\d\s]+\s*\d+\s*,\s*\d{4}\s+(?:Confirmação|Confirmation))#", $text);
                },

                "#Rail\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re([
                            "#\s+Confirmation\s+([\w\d]+)#",
                        ]);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re([
                            "#\n\s*Traveler\s*([\dA-Z ]+)#",
                            "#\n\s*Viajante\s*([\dA-Z ]+)#",
                        ], $this->text())];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $i = ['DepCode' => TRIP_CODE_UNKNOWN, 'ArrCode' => TRIP_CODE_UNKNOWN];
                            $pattern = '(\d+:\d+(?:\s*[AP]M)?, [\w\s]+, \d+)\s+';
                            $pattern .= '(\d+:\d+(?:\s*[AP]M)?, [\w\s]+, \d+)\s+';
                            $pattern .= '(.+?)\s*\n\s*(.+?)\nStatus\s+(\w+)\s*Class\s*([A-Z])';

                            if (preg_match("/{$pattern}/s", $text, $matches)) {
                                // 7:13 PM, Apr 27, 2017
                                $i['DepDate'] = strtotime(preg_replace('/([\d:]+\s*[AP]M), (\w+) (\d+), (\d+)/', '$3 $2 $4, $1', $matches[1]));
                                $i['ArrDate'] = strtotime(preg_replace('/([\d:]+\s*[AP]M), (\w+) (\d+), (\d+)/', '$3 $2 $4, $1', $matches[2]));
                                $i['DepName'] = $matches[3];
                                $i['ArrName'] = $matches[4];
                                $i['BookingClass'] = $matches[6];
                            }

                            // Coach 016, Seat 074
                            if (preg_match_all('/Coach (\w+), Seat (\w+)/', $text, $matches, PREG_SET_ORDER)) {
                                foreach ($matches as $match) {
                                    $i['Seats'][] = $match[1] . '-' . $match[2];
                                }
                            }

                            return $i;
                        },
                    ],
                ],

                "#Hotel\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'R';
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re([
                            "#\s+Confirmation\s+([\w\d]+)#",
                            "#\s+Confirmação\s+([\w\d]+)#",
                        ]);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel\s+([^\n]+)#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime($this->monthToEn(re("#\n\s*Check\-In\s*([^\n]+)#")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime($this->monthToEn(re("#\n\s*Check\-Out\s*([^\n]+)#")));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/(CONTACT|CONTATO)\s+(.*?)\s+(?:Fax|Reserved For)/s', $text, $match)) {
                            return nice(clear("#\s*Tel\s*[\d\-+]+#", $match[2]));
                        }
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Tel\s+([\d\-+]+)#");
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Fax\s+([\d\-+]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('/\n\s*(Reserved For|Reservado Para)\s+([^\n]+)/', $text, 2);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(Number of Rooms|Número de Quartos)\s+(\d+)#", $text, 2);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(Rate|Taxa)\s+([^\n]+)#", $text, 2);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s+([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cancellation Policy\s+([^\n]+)#");
                    },
                ],

                "#\s+Car\s+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'L';
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Confirmation\s+([A-Z\d\-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $r = [
                            'PickupLocation'  => trim(re("#(\n\s*[A-Z ,.\d]+)(\n\s*[A-Z ,.\d]+)*(\n\s*(?:\d+\-)+\d+)(\n\s*(?:\d+\-)+\d+)*#")),
                            'PickupPhone'     => trim(re(3)),
                            'DropoffLocation' => trim(orval(re(2), re(1))),
                            'DropoffPhone'    => trim(re(4)),
                        ];

                        if (empty($r['PickupLocation'])) {
                            $r = [
                                'PickupLocation'  => trim(re("#\w+\s+\d+,\s*\d{4}\s+\d+:\d+\s*PM,\s*\w+\s+\d+,\s*\d{4}\s+([^\n]+)#")),
                                'DropoffLocation' => trim(re(1)),
                            ];
                        }

                        if (empty($r['PickupLocation']) and preg_match('#\w+\s+\d+,\s+\d{4}\s+\d+:\d+.*\w+\s+\d+,\s+\d{4}\s+(.*)#i', $text, $m)) {
                            $r = [
                                'PickupLocation'  => trim($m[1]),
                                'DropoffLocation' => trim($m[1]),
                            ];
                        }
                        // Mar 01, 2016 Mar 03, 2016 Nashville, TN
                        if (empty($r['PickupLocation']) and preg_match('#\w+\s+\d+,\s+\d{4}\s+\w+\s+\d+,\s+\d{4}\s+(.+?)(?:[+\d\s()-]{7,}|Reserved)#is', $text, $m)) {
                            $addr = trim(str_replace("\n", ' ', $m[1]));
                            $r = [
                                'PickupLocation'  => $addr,
                                'DropoffLocation' => $addr,
                            ];
                        }

                        return $r;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDate(2) . ', ' . uberTime(1));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(uberDate(3) . ', ' . uberTime(2));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car\s+([^\n]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car Type\s+([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reserved For\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Approximate Total\s+([^\n/]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Approximate Total\s+([^\n/]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*([^\n]+)#");
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $its = uniteAirSegments($it);

                    if (preg_match_all("/^\s*(.+?)[ ]+TICKET PRICE (\d[\d\.]+)$/m", $text, $m, PREG_SET_ORDER)) {
                        foreach ($m as $v) {
                            if (isset($rl, $rl[strtoupper($v[1])])) {
                                $rl = null;

                                break;
                            }
                            $rl[strtoupper($v[1])] = $v[2];
                        }

                        if (isset($rl)) {
                            foreach ($its as &$it) {
                                if ($it['Kind'] === 'T' && (!isset($it['TripCategory']) || $it['TripCategory'] === TRIP_CATEGORY_AIR)
                                && isset($it['TripSegments']) && is_array($it['TripSegments'])) {
                                    if (null !== ($airline = $this->searchAirline($it['TripSegments']))) {
                                        $sum = null;
                                        $airline_ = trim(str_replace('AIRLINES', '', strtoupper($airline)));
                                        $airlineext = strtoupper($airline) . ' ' . 'AIRLINES';

                                        if (isset($rl[strtoupper($airline)])) {
                                            $sum = PriceHelper::cost($rl[strtoupper($airline)]);
                                        } elseif (isset($rl[$airlineext])) {
                                            $sum = PriceHelper::cost($rl[$airlineext]);
                                        } elseif (isset($rl[$airline_])) {
                                            $sum = PriceHelper::cost($rl[$airline_]);
                                        }

                                        if (isset($sum) && !isset($it['TotalCharge']) || empty($it['TotalCharge'])) {
                                            $it['TotalCharge'] = $sum;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    return $its;
                },

                "#(?:Voo|Flight)\s+[\w\s]+\s+\d+#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re([
                            "#\s+Confirmation\s+\*?([\w\d]+)#",
                            "#\s+Confirmação\s+([\w\d]+)#",
                        ]);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re([
                            "#\n\s*Traveler\s*([\dA-Z ]+)#",
                            "#\n\s*Viajante\s*([\dA-Z ]+)#",
                        ], $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re([
                            "#\n\s*Total Amount\s+([^\n]+)#",
                            "#\n\s*Valor Total\s+([^\n]+)#",
                        ], $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Tax\s+\d+\s+Total\s+\b([A-Z]{3})\b#", $this->text()),
                            re("#\d+\s+([A-Z]{3})\s+[\d.,]{3,}\s+[\d.,]{3,}[A-Z]+#", $this->text())
                        );
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status[:\s]+(Confirmado|Confirmed|Cancel+ed)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime($this->monthToEn(re('/\s+(Date|Data)\s*:\s*(\w{3}\s*\d+\s*,\s*\d{4})/', $this->text(), 2)));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $text = clear("#Notes\s+(.+)#ms", $text);

                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re([
                                "#\n\s*Flight\s+[^\d]+\s*(\d+)#",
                                "#\n\s*Voo\s+[^\d]+\s*(\d+)#",
                            ]);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re("#(DEPARTURE\s+ARRIVAL|CHEGADA)\s+\b(\w{3})\b\s*\-#", $text, 2),
                                TRIP_CODE_UNKNOWN
                            );
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return trim(re([
                                "#DEPARTURE\s+ARRIVAL\s+(?:\w{3}\s*\-\s*)*([^\n]+)#",
                                "#PARTIDA\s+CHEGADA\s+(?:\w{3}\s*\-\s*)*([^\n]+)#",
                            ]));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dep = totime(en(uberDateTime()));
                            $arr = totime(en(uberDateTime(2)));
                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re("#(ARRIVAL|CHEGADA)\s+[^\n]+\s+\b(\w{3})\b\s*\-#", $text, 2),
                                TRIP_CODE_UNKNOWN
                            );
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return trim(re([
                                "#ARRIVAL\s+[^\n]+\s+(?:\w{3}\s*\-\s*)*([^\n]+)#",
                                "#CHEGADA\s+[^\n]+\s+(?:\w{3}\s*\-\s*)*([^\n]+)#",
                            ]));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return trim(re([
                                "#\n\s*Flight\s+([^\d]+)\s*\d+#",
                                "#\n\s*Voo\s+([^\d]+)\s*\d+#",
                            ]));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re([
                                "#\n\s*Equipment\s+([^\n]+)#",
                                "#\n\s*Equipamento\s+([^\n]+)#",
                            ]);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re([
                                "#\n\s*Class\s+(.*?)\s*\-\s*(\w)#",
                                "#\n\s*Classe\s+(.*?)\s*\-\s*(\w)#",
                            ]);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re(2);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re([
                                "#Reserved Seats\s+(\d+\w)#",
                                "#Assentos Reservados\s+(\d+\w)#",
                            ]);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re([
                                "#\n\s*Duration\s+(\d+:\d+)#",
                                "#\n\s*Duração\s+(\d+:\d+)#",
                            ]);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re([
                                "#\n\s*Meal Service\s+([^\n]+)#",
                                "#\n\s*Refeição\s+([^\n]+)#",
                            ]);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Duration\s+\d+:\d+\s*\(\s*(Non\-stop)\s*\)#") ? 0 : null;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function monthToEn($date)
    {
        return trim(str_replace(
                ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'] // pt
                , ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'] //en
                , strtolower($date)));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from'])
                && (
                    stripos($headers['from'], '@carlsonwagonlit.com') !== false
                    || stripos($headers['from'], '@cwtsatotravel.com') !== false
                )
                && isset($headers['subject']) && (
                    stripos($headers['subject'], 'HP Ticket Receipt for TRAVEL CONFIRMATION:') !== false
                    || stripos($headers['subject'], 'Travel Document for:') !== false
                    || stripos($headers['subject'], 'Itinerary Only For') !== false
                    || stripos($headers['subject'], 'Ticketed Invoice For') !== false
                    || stripos($headers['subject'], 'Travel Reservations for') !== false
                    || stripos($headers['subject'], 'Documento de viagem para:') !== false
                    || stripos($headers['subject'], 'ITINERARY ONLY') !== false
                    || stripos($headers['subject'], 'TICKETED INVOICE:') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*\.pdf');
        //$pdf = $parser->searchAttachmentByName('[A-Z\d]{5,6}(\s*revised)?\.pdf');

        if (empty($pdf)) {
            return false;
        }

        $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        return (stripos($pdfText, 'CARLSONWAGONLIT.COM') !== false
                || stripos($pdfText, 'CWT.COM') !== false
                || stripos($pdfText, 'CARLSON WAGONLIT') !== false
                || stripos($pdfText, 'CWT TRAVEL') !== false
                || stripos($pdfText, 'CWTSATOTRAVEL') !== false
                || stripos($pdfText, 'CWTSATO.COM') !== false
            )
            && (stripos($pdfText, 'GENERAL INFORMATION') !== false
                || stripos($pdfText, 'INFORMAÇÕES GERAIS') !== false)
            || (
                stripos($pdfText, 'GENERAL INFORMATION') !== false && stripos($parser->getBody(), ' CWT ') !== false
            )
                ;
        //     || stripos($pdfText, 'CARLSONWAGONLIT.COM') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@carlsonwagonlit.com') !== false
            || stripos($from, '@cwtsatotravel.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'pt'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function searchAirline(array $segments)
    {
        $airline = null;

        foreach ($segments as $segment) {
            if (isset($segment['AirlineName'])) {
                if (isset($airline)) {
                    if ($airline !== $segment['AirlineName']) {
                        return null;
                    }
                } else {
                    $airline = $segment['AirlineName'];
                }
            }
        }

        return $airline;
    }
}
