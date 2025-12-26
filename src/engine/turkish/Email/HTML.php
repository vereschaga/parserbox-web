<?php

namespace AwardWallet\Engine\turkish\Email;

class HTML extends \TAccountCheckerExtended
{
    public $mailFiles = "turkish/it-1.eml, turkish/it-10879659.eml, turkish/it-11089515.eml, turkish/it-11984655.eml, turkish/it-1590086.eml, turkish/it-1801662.eml, turkish/it-2.eml, turkish/it-2416991.eml, turkish/it-2878472.eml, turkish/it-3.eml, turkish/it-3091011.eml, turkish/it-3962949.eml, turkish/it-3980824.eml, turkish/it-4.eml, turkish/it-9928799.eml, turkish/it.eml";

    private $detectBody = [
        'en' => "It's a pleasure to see you among us at",
        'tr' => 'Türk Hava Yolları olarak sizleri aramızda görmekten mutluluk duyarız',
        'de' => 'freut sich Sie begrüßen zu dürfen',
    ];

    private $dict;
    private $regexp;
    private $lang;
    private $flightsInfo;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // dictionary for parsing
                    $this->lang = 'en';
                    $dictionary = [
                        'en' => [
                            'RecordLocator'   => 'Reservation Code',
                            'noRecordLocator' => 'The reservation will be automatically cancelled if you do not purchase your ticket within the time limit',
                            'ReservationDate' => 'Process date',
                            'TotalCharge'     => 'Total Amount',
                            'TripSegments'    => 'Airline/Flight Number',
                            'Passengers'      => ['Passenger', 'Ticket Number'],
                        ],
                        'tr' => [
                            'RecordLocator'   => 'Rezervasyon Kodu',
                            'ReservationDate' => 'İşlem tarihiniz',
                            'TotalCharge'     => 'Toplam Ücret',
                            'TripSegments'    => 'Havayolu/Uçuş No',
                            'Passengers'      => ['Yolcu', 'Bilet No'],
                        ],
                        'de' => [
                            'RecordLocator'   => 'Reservierungscode',
                            'ReservationDate' => 'Prozessdatum',
                            'TotalCharge'     => 'Gesamter Flugpreis',
                            'TripSegments'    => 'Airline/Flight Number',
                            'Passengers'      => ['Fluggast', 'Ticketnummer'],
                        ],
                    ];
                    $regularExpressions = [
                        'en' => [
                            'preferences' => '/^(?:meal\s*:\s+(.+)\s+)?(?:seat\s*:\s+(\d{1,3}[A-Z])\s*)?(Free\s+Baggage\s+Allowance\s*:\s+\d+\s+\w+)?$/U',
                        ],
                        'tr' => [
                            'preferences' => '/(?:meal\s*:\s+(.*)\s+)?(?:seat\s*:\s+(\w+)\s+)?Free\s+Baggage\s+Allowance\s*:\s+\d+\s+\w+/U',
                        ],
                        'de' => [
                            'preferences' => '/(?:Mahl\s*:\s+(.*)\s+)?(?:Sitz\s*:\s+(\w+)\s+)?Freigepäck\s*:\s+\d+\s+\w+/U',
                        ],
                    ];

                    foreach ($dictionary as $lang => $dict) {
                        if ($this->http->XPath->query('//node()[contains(.,"' . $dict['RecordLocator'] . '")]')->length > 0) {
                            $this->lang = $lang;

                            break;
                        }
                    }

                    if (!isset($dictionary[$this->lang])) {
                        return null;
                    }
                    $this->dict = $dictionary[$this->lang];

                    if (!isset($regularExpressions[$this->lang])) {
                        return null;
                    }
                    $this->regexp = $regularExpressions[$this->lang];

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $pnr = $this->http->FindSingleNode('(//td[starts-with(normalize-space(.),"' . $this->dict['RecordLocator'] . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1])[1]',
                            null, true, '/^\s*([A-Z\d]{5,7})\s*$/');

                        if (empty($pnr) && isset($this->dict['noRecordLocator']) && $this->http->XPath->query("//text()[contains(normalize-space(.),'{$this->dict['noRecordLocator']}')]")->length > 0) {
                            return CONFNO_UNKNOWN;
                        } else {
                            return $pnr;
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $result = [];
                        $this->flightsInfo = [];
                        $xpathRoot = '//tr[starts-with(normalize-space(.),"' . $this->dict['Passengers'][0] . '") and contains(.,"' . $this->dict['Passengers'][1] . '") and not(.//tr)]/following-sibling::tr[count(./td)>4 and string-length(normalize-space(.))>2]';
                        $passengersNodes = nodes($xpathRoot . '/td[2]');
                        $ticketNumberNodes = nodes($xpathRoot . '/td[3]', null, '/^\s*(\d[-\d\s]{2,})/');
                        $flightNumberNodes = nodes($xpathRoot . '/td[last()-1]');
                        $preferencesNodes = nodes($xpathRoot . '/td[last()]');

                        foreach ($passengersNodes as $i => $value) {
                            if (preg_match($this->regexp['preferences'], $preferencesNodes[$i], $matches)) {
                                $key = re('/^\s*([A-Z\d]{2}\s*\d+)/', $flightNumberNodes[$i]);

                                if (isset($matches[1]) && $matches[1] !== '') {
                                    $this->flightsInfo[$key]['Meal'][] = $matches[1];
                                }

                                if (isset($matches[2]) && $matches[2] !== '') {
                                    $this->flightsInfo[$key]['Seats'][] = $matches[2];
                                    $this->flightsInfo[$key]['Seats'] = array_unique($this->flightsInfo[$key]['Seats']);
                                }
                            }
                        }

                        if (!empty($passengersNodes[0])) {
                            $result['Passengers'] = array_values(array_unique($passengersNodes));
                        }

                        if (!empty($ticketNumberNodes[0])) {
                            $result['TicketNumbers'] = array_values(array_unique($ticketNumberNodes));
                        }

                        return $result;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $payment = $this->http->FindSingleNode('(//td[starts-with(normalize-space(.),"' . $this->dict['TotalCharge'] . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1])[1]');
                        $res['TotalCharge'] = cost($payment);
                        $res['Currency'] = currency($payment);

                        if ($res['Currency'] === null && stripos($payment, 'TL') !== false) {
                            $res['Currency'] = 'YTL';
                        }

                        if ($res['Currency'] === 'NOK') {
                            $res['TotalCharge'] = cost(str_replace(',', '', $payment));
                        }

                        return $res;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $dateReservation = $this->http->FindSingleNode('(//td[starts-with(normalize-space(.),"' . $this->dict['ReservationDate'] . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1])[1]');

                        return strtotime(en($dateReservation, $this->lang));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[starts-with(normalize-space(.),"' . $this->dict['TripSegments'] . '") and not(.//tr)]/following::tr[count(descendant::tr)=0 and count(./td)>4 and contains(translate(.,"0123456789","dddddddddd"),"d:dd")]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $flight = $this->http->FindSingleNode('./td[1]', $node, true, '/^\s*([A-Z\d]{2}\s*\d+)/');
                            $result = uberAir($flight);

                            if (isset($this->flightsInfo[$flight]['Seats'])) {
                                $result['Seats'] = join(', ', $this->flightsInfo[$flight]['Seats']);
                            }

                            if (isset($this->flightsInfo[$flight]['Meal'])) {
                                $result['Meal'] = join(', ', $this->flightsInfo[$flight]['Meal']);
                            }

                            return $result;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[2]');
                            $res = [];
                            $regex = '#(?P<Date>\d{1,2}\.(?:\d{2}|[^\.\s\d]{3,5})\.\d{4})\s+/\s+(?P<Time>\d{2}:\d{2})\s*(?P<Name>.*)#';

                            if (preg_match($regex, $subj, $m)) {
                                $dep = $m['Date'] . ' ' . $m['Time'];
                                $dep1 = preg_replace('#[.]#', ' ', $dep); // word month
                                $dep2 = preg_replace('#[.]#', '/', $dep); // number month
                                $res['DepDate'] = orval(
                                    strtotime(uberDateTime(en($dep1))),
                                    strtotime(uberDateTime(en($dep2)))
                                );
                                $res['DepName'] = $m['Name'];
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[3]');
                            $res = [];
                            $regex = '#(?<Date>\d{1,2}\.(?:\d{2}|[^\.\s\d]{3,5})\.\d{4})\s+\/\s+(?<Time>\d{2}:\d{2})\s*(?<Name>.*)#';

                            if (preg_match($regex, $subj, $m)) {
                                $arr = en($m['Date'] . ' ' . $m['Time']);
                                $arr1 = preg_replace('#[.]#', ' ', $arr); // word month
                                $arr2 = preg_replace('#[.]#', '/', $arr); // number month
                                $res['ArrDate'] = orval(
                                    strtotime(uberDateTime(en($arr1))),
                                    strtotime(uberDateTime(en($arr2)))
                                );
                                $res['ArrName'] = $m['Name'];
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[last()-1]');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return $this->http->FindSingleNode('./td[last()]', $node, true, '/^\s*([A-Z]{1,2})\s*$/');
                        },
                    ],
                ],
                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $it[0]['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $it[0]['TripSegments'])));

                    return $it;
                },
            ],
        ];
    }

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'please_do_not_reply@thy.com') !== false
            || preg_match('/Turkish\s+Airlines/i', $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"Turkish Airlines") or contains(.," turkishairlines.com") or contains(.," thy.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".thy.com/") or contains(@href,"//thy.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach ($this->detectBody as $phrase) {
            if ($this->http->XPath->query('//node()[contains(.,"' . $phrase . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'tr', 'de'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
