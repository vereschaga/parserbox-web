<?php

namespace AwardWallet\Engine\lufthansa\Email;

class TicketDetailsAndTravelInformation extends \TAccountCheckerExtended
{
    public $mailFiles = "lufthansa/it-11.eml, lufthansa/it-1702443.eml, lufthansa/it-1741345.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@booking-lufthansa.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Your ticket details & travel information') !== false
                //stripos($headers['subject'], 'Ihre Flugdetails & Reiseinformationen') !== FALSE||
                || stripos($headers['subject'], 'lufthansa.com - Grazie per la sua prenotazione') !== false
                || stripos($headers['subject'], 'Cambio di prenotazione') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'lufthansa.com/online/portal/') !== false && (
                $this->http->XPath->query('//text()[contains(normalize-space(.), "Ticket details & travel information")]')->length > 0
                || stripos($parser->getHTMLBody(), 'You have access to the passenger receipt ') !== false
                || $this->http->XPath->query('//text()[contains(normalize-space(.), "Flugscheindetails & ")]')->length > 0
                || stripos($parser->getHTMLBody(), 'Travel Information') !== false
                || $this->http->XPath->query('//text()[contains(normalize-space(.), "Détails du billet & informations sur le voyage")]')->length > 0
                || $this->http->XPath->query('//text()[contains(normalize-space(.), "Cambio di prenotazione")]')->length > 0
                );
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bmp.viaamadeus.com') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = orval(
                        re('#\s+(\d{4})\s+\|#i', $this->parser->getHeader('subject')),
                        re('#\d{4}#i', $this->parser->getHeader('date'))
                    );
                    $subj = $this->parser->getHtmlBody();
                    // Cutting out encoding meta definition which fails email parsing
                    $subj = preg_replace('#<META[^>]+>#', '', $subj);
                    $this->http->setBody($subj);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $variants = [
                            'Reservation\s+code',
                            'Buchungscode',
                            'Codice\s+di\s+prenotazione',
                            'Code de réservation',
                        ];

                        return re('#(?:' . implode('|', $variants) . ')\s*:\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Travel dates for:') or contains(., 'Neue Reisedaten für:') or contains(., 'Date di viaggio per:') or contains(., 'Reisedaten für:') or contains(., 'Dates de voyage pour')]/ancestor::td[1]/following-sibling::td[1]";

                        return nodes($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Total Price for all') or contains(., 'Zusatzkosten für alle Passagiere') or contains(., 'Gesamtpreis für alle Reisenden') or contains(., 'prezzo totale per tutti I passeggeri') or contains(., 'Prix total pour tous les passagers')]/ancestor::td[1]/following-sibling::td[position() > 1]";
                        $subj = join(' ', nodes($xpath));

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $pref = "//tr[(contains(., 'Departure') or contains(., 'Von') or contains(., 'Partenza') or contains(., 'Départ')) and (contains(., 'Arrival') or contains(., 'Nach') or contains(., 'Arrivo') or contains(., 'Arrivée'))]";
                        $xpath = "$pref/ancestor::table/following-sibling::table[following-sibling::table//img[contains(@src, 'LhcomLine.jpg')]]//tr[count(./td) = 8]";
                        $xpath .= " | $pref/following-sibling::tr[count(./td) = 7]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[string-length(normalize-space(.)) > 1][1]');
                            $variants = [
                                'operated\s+by',
                                'durchgeführt\s+von',
                                'operato\s+da',
                                'opéré\s+par',
                            ];

                            if (preg_match('#\w+\s+(\d+)\s*(?:' . implode('|', $variants) . ')\s*:?\s*(.*)#', $subj, $m)) {
                                return ['FlightNumber' => $m[1], 'AirlineName' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[string-length(normalize-space(.)) > 1][3]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = [];
                            $subj = preg_replace('/\xe2\x80\x8b/', '', node('./td[string-length(normalize-space(.)) > 1][2]')); //[\x00-\x1F\x80-\xFF]

                            if (preg_match_all('#(\d+)\.?\s+(\w+)#', $subj, $m, PREG_SET_ORDER)) {
                                $dateStr['Dep'] = $m[0][1] . ' ' . en($m[0][2]) . ' ' . $this->year;

                                if (isset($m[1])) {
                                    $dateStr['Arr'] = $m[1][1] . ' ' . en($m[1][2]) . ' ' . $this->year;
                                } else {
                                    $dateStr['Arr'] = $dateStr['Dep'];
                                }
                            }
                            $res = [];

                            foreach (['Dep' => 5, 'Arr' => 6] as $key => $value) {
                                $timeStr = re('#\d+:\d+#', preg_replace('/[\x00-\x1F\x80-\xFF]/', '', node('./td[string-length(normalize-space(.)) > 1][' . $value . ']')));
                                $res[$key . 'Date'] = strtotime($dateStr[$key] . ', ' . $timeStr);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[string-length(normalize-space(.)) > 1][4]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $subj = node('./td[string-length(normalize-space(.)) > 1][7]');
                            $regex = '#';
                            $regex .= '(?P<Cabin>.*)\s+';
                            $regex .= '\((?P<BookingClass>\w)\)';
                            $regex .= '(.*:\s+(?P<Seats>(?:\d+\w/?)+))?';
                            $regex .= '#';

                            if (preg_match($regex, $subj, $m)) {
                                copyArrayValues($res, $m, ['Cabin', 'BookingClass', 'Seats']);

                                if (isset($res['Seats'])) {
                                    $res['Seats'] = str_replace('/', ', ', $res['Seats']);
                                }
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de", "it", "fr"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
