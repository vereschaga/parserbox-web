<?php

namespace AwardWallet\Engine\lufthansa\Email;

class AirTravelPlane extends \TAccountCheckerExtended
{
    public $mailFiles = "lufthansa/it-1608187.eml, lufthansa/it-1615990.eml, lufthansa/it-1630701.eml, lufthansa/it-1654334.eml, lufthansa/it-1731017.eml, lufthansa/it-1738472.eml, lufthansa/it-1746275.eml, lufthansa/it-1747204.eml, lufthansa/it-1884455.eml, lufthansa/it-4038033.eml, lufthansa/it-4047933.eml, lufthansa/it-4445793.eml, lufthansa/it-5136051.eml";
    public $detectBody = [
        'tr' => 'Seyahatiniz için Lufthansa\'yı tercih ettiğiniz için memnuniyet duyuyoruz',
        'en' => ['you have chosen Lufthansa for your next trip', 'thank you for choosing to travel with Lufthansa'],
        'de' => ['wir freuen uns, dass Sie sich bei Ihrer Reise für Lufthansa', 'bei Ihrer nächsten Reise für Lufthansa'],
        'it' => 'Siamo lieti che abbia scelto Lufthansa per il suo prossimo viaggio',
        'pt' => 'Recebemos com enorme satisfação a sua decisão de viajar com a Lufthansa',
        'es' => 'haya elegido volar con Lufthansa en su',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    //goto parse by TravelInformation2.php
                    if (xpath("//span[contains(@class, 'flight_nr')]/ancestor::table[1]/ancestor::td[1]/following-sibling::td[1]/descendant::table[1]/descendant::tr[1]/following-sibling::tr[1]")->length > 0) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:BOOKING\s*CODE|Code de réservation|REZERVASYON KODUNUZ|Buchungscode|CODICE DI PRENOTAZIONE|Código da reserva|Código de reserva)\s*:\s*([\w-]+)#i");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re("#(?:Dear|Sehr geehrter|Sehr geehrte|Gentile Signora|Caro|Estimado Sr.)\s*(.+?)\s*,#i");

                        return [nice($name)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $nodes = xpath("//span[contains(@class, 'flight_nr')]/ancestor::table[2]");

                        if ($nodes->length == 0) {
                            $nodes = xpath("//img[contains(@src, 'XX320_BOOKING_FLIGHT_') and @height=16]/ancestor::table[2]");
                        }

                        return $nodes;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('(.//*[normalize-space(text())])[1]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = node('(.//*[string-length(normalize-space(text())) > 1])[3]');

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            // the date is taken from before the "group of segments"
                            // and is remembered for later items in "group of segments".
                            $date = node("./ancestor::table[2]/preceding-sibling::table[1]/descendant::span[contains(@class, 'black12')]");

                            if (empty($date)) {
                                $date = node("ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)!=''][1]/descendant::span[contains(@class, 'black12')]");
                            }

                            if (empty($date)) {
                                $date = node("./preceding::text()[normalize-space()][1]");
                            }
                            $date = nice($date);

                            if (preg_match('/\D+, (\d+\.\d+\.\d+)/', $date, $m)) {
                                $date = $m[1];
                            }
                            $date = \DateTime::createFromFormat('d.m.Y', $date);

                            // this weirdness is needed because of '+1' logic (see 187 eml, last two segments).
                            // basically if $date was parsed we use it, otherwise saved value.
                            if ($date) {
                                $this->date = $date;
                            } elseif (isset($this->date)) {
                                $date = $this->date;
                            } else { // both empty
                                return;
                            }

                            $dt1 = clone $date;
                            $dt2 = clone $date;

                            $time1 = node('(.//*[normalize-space(text())])[2]', null, true, '/(\d{2}:\d{2})/');
                            $time2 = node('(.//*[normalize-space(text())])[4]', null, true, '/(\d{2}:\d{2}.*)/');

                            if (empty($time2)) {
                                $time2 = node('(.//*[normalize-space(text())])[5]', null, true, '/(\d{2}:\d{2}.*)/');
                            }

                            $time1 = re('/(\d+:\d+)/', $time1);

                            if (preg_match('/[(]\s*[+]1\s*[)]/', $time2, $ms)) {
                                $dt2->modify('+1 day');
                                $this->date = clone $dt2;
                            }
                            $time2 = re('/(\d+:\d+)/', $time2);

                            if ($time1) {
                                $dt1->modify($time1);
                            }

                            if ($time2) {
                                $dt2->modify($time2);
                            }

                            return [
                                'DepDate' => $dt1 ? $dt1->getTimestamp() : null,
                                'ArrDate' => $dt2 ? $dt2->getTimestamp() : null,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = node('(.//text()[contains(translate(.,"0123456789", "dddddddddd"), "dd:dd")])[2]/ancestor::td[1]/following::td[normalize-space()][1]');

                            return nice($name);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $cab = node('(.//*[normalize-space(text()) != ""])[6]', null, true, '/(?:Economy|Business|\b[A-Z]{1}\b)/i');

                            if (empty($cab)) {
                                $cab = node('(.//*[normalize-space(text()) != ""])[7]', null, true, '/(?:Economy|Business|\b[A-Z]{1}\b)/i');
                            }

                            if (empty($cab)) {
                                $cab = node(".//img[contains(@src, 'economy') or contains(@src, 'business') or contains(@src, 'first')]/@src");

                                if (preg_match('/((?:business|economy|first))\.(?:gif|jpeg|png|jpg)/i', $cab, $m)) {
                                    $cab = strtoupper($m[1]);
                                }
                            }

                            if (strlen($cab) <= 3) {
                                return [
                                    'BookingClass' => $cab,
                                ];
                            }

                            return $cab;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = node("( .//img[contains(@src, 'SHOW_SEAT')] )[1]/following::span[1]");

                            return nice($seat);
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            $meal = node("( .//img[contains(@src, 'essen2')] )[1]/following::td[2]");

                            return nice($meal);
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detect) {
            if (is_array($detect)) {
                foreach ($detect as $det) {
                    if (stripos($body, $det) !== false) {
                        return true;
                    }
                }
            }

            if (is_string($detect) && stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lufthansa.com') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'Travel information for your flight') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lufthansa.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'tr', 'de', 'it'];
    }
}
