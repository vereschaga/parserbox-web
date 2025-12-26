<?php

namespace AwardWallet\Engine\lastminute\Email;

class ItPDFGerman extends \TAccountCheckerExtended
{
    public $mailFiles = "lastminute/it-1560582.eml, lastminute/it-1565489.eml";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    $simpleText = $this->getDocument('#Reisereservierung#', 'text');

                    if (!re("#lastminute#i", $simpleText)) {
                        return [];
                    }

                    $text = $this->setDocument('#Reisereservierung#', 'complex');

                    $this->recordLocators[] = re('#BUCHUNGSCODE DER FLUGGESELLSCHAFT\s+(\w+)#');

                    $subj = re('#ERSTELLT FÜR\s+(.*)\s+RESERVIERUNGSCODE#s');
                    $this->passengers = array_filter(array_values(explode("\n", $subj)));

                    $reservations = splitter('#(ABREISE:)#');
                    $this->simpleTextReservations = splitter('#(ABREISE:)#', $simpleText);

                    return $reservations;
                },

                "#ABREISE:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (isset($this->reservationIndex)) {
                            $this->reservationIndex++;
                        } else {
                            $this->reservationIndex = 0;
                        }

                        return $this->recordLocators[0];
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $regex = '#';
                            $regex .= 'Flugzeiten vor dem Abflug überprüfen\s+';
                            $regex .= '(?P<AirlineName1>.*)';
                            $regex .= '\s+\w+\s+(?P<FlightNumber>\d+)\s+';
                            $regex .= '(?:Betreiber-Fluggesellschaft:\s+(?P<AirlineName2>[^\n]+\n?[^\n]*)\s*\n\n\s*)?';
                            $regex .= 'Dauer:\s+(?P<Duration>.*)\s*\n\n\s*';
                            $regex .= '(?P<DepCode>\w+)\s*\n\n\s*(?P<DepName>[^\n]+\n?[^\n]*)\s*\n\n\s*';
                            $regex .= '(?P<ArrCode>\w+)\s*\n\n\s*(?P<ArrName>[^\n]+\n?[^\n]*)\s*\n\n\s*';
                            $regex .= '#sU';

                            if (preg_match($regex, $text, $m)) {
                                $keys = ['FlightNumber', 'Duration', 'DepCode', 'DepName', 'ArrCode', 'ArrName'];
                                $res = array_merge($res, array_intersect_key($m, array_flip($keys)));
                                $res['DepName'] = trim(str_replace("\n", ' ', $res['DepName']));
                                $res['ArrName'] = trim(str_replace("\n", ' ', $res['ArrName']));

                                if (isset($m['AirlineName1'])) {
                                    $airlineName = $m['AirlineName1'];
                                } elseif (isset($m['AirlineName2'])) {
                                    $airlineName = $m['AirlineName2'];
                                } else {
                                    $airlineName = '';
                                }

                                if (!empty($airlineName)) {
                                    $res['AirlineName'] = trim(preg_replace('#\s+#', ' ', $airlineName));
                                }
                            }

                            $regex = '#ABREISE:\s+\w+\s+(\d+)\s+(\w+)\s+(?:ANKUNFT:\s+\w+\s+(\d+)\s+(\w+)\s+)?#';

                            if (preg_match($regex, $text, $m)) {
                                $depDatetimeStr = $m[1] . ' ' . en($m[2]);

                                if (isset($m[3])) {
                                    $arrDatetimeStr = $m[3] . ' ' . en($m[4]);
                                } else {
                                    $arrDatetimeStr = $depDatetimeStr;
                                }
                            }
                            $depDatetimeStr .= ', ' . re('#Abflug um:\s+(\d{1,2}:\d{2}(?:am|pm)?)#');
                            $res['DepDate'] = strtotime($depDatetimeStr, $this->date);
                            $arrDatetimeStr .= ', ' . re('#Ankunft um:\s+(\d{1,2}:\d{2}(?:am|pm)?)#');
                            $res['ArrDate'] = strtotime($arrDatetimeStr, $this->date);

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Flugzeug:\s+([^\n]+)#");
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return (float) re("#Meilenzahl:\s+(\d+)#", $this->simpleTextReservations[$this->reservationIndex]);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = [];
                            $meal = [];
                            $class = [];
                            $subj = re('#Menüs:.*#s');

                            foreach ($this->passengers as $p) {
                                $regex = '#';
                                $regex .= preg_quote($p) . '\n{3}';
                                $regex .= '(?P<Seats>[^\n]+)\n{3}';
                                $regex .= '(?P<Cabin>[^\n]+)\n{3}';
                                $regex .= '[^\n]+\n{3}';
                                $regex .= '[^\n]+\n{3}';
                                $regex .= '(?P<Meal>Erfrischung - kostenlos|Mahlzeit)?';
                                $regex .= '#';

                                if (preg_match($regex, $subj, $m)) {
                                    $seats[] = $m['Seats'];
                                    $class[] = $m['Cabin'];

                                    if (isset($m['Meal'])) {
                                        $meal[] = $m['Meal'];
                                    }
                                }
                            }
                            $res = [];
                            $res['Cabin'] = join(', ', $class);
                            $res['Seats'] = join(', ', $seats);

                            if (!empty($meal)) {
                                $res['Meal'] = join(', ', $meal);
                            }

                            return $res;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return (int) re("#Aufenthalte:\s+(\d+)#", $this->simpleTextReservations[$this->reservationIndex]);
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    // This postprocessing joins air trip reservations with same record locator,
                    // keeping all other itineraries unchanged
                    $itMod = [];

                    foreach ($it as $i) {
                        if (isset($i['Kind']) && $i['Kind'] == 'T') {
                            // If current reservation is Air Trip
                            if (empty($itMod)) {
                                // If this is first reservation - we copy 'as is', because there is
                                // nothing to compare it with
                                $itMod[] = $i;
                            } else {
                                // Else if we've already processed some records, test whether record
                                // with same record locator already exist
                                $targetIndex = null;
                                $index = 0;

                                foreach ($itMod as $im) {
                                    if ($im['Kind'] == 'T' && $i['RecordLocator'] == $im['RecordLocator']) {
                                        $targetIndex = $index;

                                        break;
                                    }
                                    $index++;
                                }

                                if ($targetIndex !== null) {
                                    // If $targetIndex (record with same record locator as $i) was found
                                    // copy all segments from $i to it
                                    foreach ($i['TripSegments'] as $ts) {
                                        $itMod[$targetIndex]['TripSegments'][] = $ts;
                                    }
                                } else {
                                    // Else there is no previous reservations with same record locator
                                    // and we copy it 'as is'
                                    $itMod[] = $i;
                                }
                            }
                        } else {
                            // Else current reservation is not Air Trip - copy it without changes
                            $itMod[] = $i;
                        }
                    }

                    return $itMod;
                },
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@lastminute.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Travel Reservation to') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["de"];
    }
}
