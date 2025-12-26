<?php

namespace AwardWallet\Engine\transavia\Email;

class PDF extends \TAccountCheckerExtended
{
    public $mailFiles = "transavia/it-1.eml, transavia/it-1586470.eml, transavia/it-1602264.eml, transavia/it-1604479.eml, transavia/it-2.eml, transavia/it-3.eml, transavia/it-4.eml, transavia/it-5.eml, transavia/it-6.eml, transavia/it-7.eml, transavia/it-7124803.eml";

    public $reFrom = "#transavia#i";
    public $reProvider = "#transavia#i";
    public $rePlain = "#(?:boekingsbevestiging|booking\sconfirmation).*transavia#is";
    public $rePlainRange = "";
    public $typesCount = "3";
    public $langSupported = "nl, de, en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $rePDF = "#transavia#";
    public $rePDFRange = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Buchungsbestätigungsnummer|bevestigingsnummer\s+van\s+uw\s+boeking|uw\s+boekingsnummer|your\s+booking\s+number):\s+([-A-Z\d]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $this->passengers = [];
                        $regex = '#';
                        $regex .= '(?:';
                        $regex .= 'Erwachsene\s+&\s+Kinder\s+ab\s+12\s+J.';
                        $regex .= '|volwassenen\s+&\s+kinderen\s+vanaf\s+12\s+jaar';
                        $regex .= '|adults\s+&\s+children\s+from\s+the\s+age\s+of\s+12\s+yrs';
                        $regex .= ')\s+';
                        $regex .= '(.*?)\n\n#s';

                        if (preg_match($regex, $text, $m)) {
                            $this->passengers = array_merge($this->passengers, explode("\n", $m[1]));
                        }

                        if (preg_match('#kinderen\s+van\s+2-11\s+jaar\s+oud\s+(.*?)\n\n#s', $text, $m)) {
                            $this->passengers = array_merge($this->passengers, explode("\n", $m[1]));
                        }
                        array_walk($this->passengers, function (&$value, $key) { $value = preg_replace('#\s+\(.*\)#', '', $value); });

                        return $this->passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#(?:totaal|Gesamtpreis|total)\s+(.*)#');

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= '(?:incl|inkl)\.\s+';
                        $regex .= '(.*)\s+';
                        $regex .= '(?:belastingen\s+en\s+toeslagen|Steuern\s+und\s+Zuschlägen)';
                        $regex .= '#';

                        return cost(re($regex));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/(?:boekingsdatum|Buchungsdatum|date\s+of\s+booking):\s+\w+\s+(\d+)\s+(\w+),\s+(\d+)/u', $text, $m)) {
                            return strtotime($m[1] . ' ' . en($m[2]) . ' ' . $m[3]);
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#(?:vluchten|Flüge|flights).*?(?:passagiers|Passagiere|passengers)#s');
                        $segments = splitter('#((?:heenvlucht|terugvlucht|Hinflug|onward\s+flight|return\s+flight).*)#', $subj);

                        //Seats
                        $this->seats = [];
                        $countTripSegments = count($segments);
                        $seatsByPassengers = [];

                        foreach ($this->passengers as $passenger) {
                            preg_match_all('/^\s*' . $passenger . '\s*(\d{1,2}[A-Z])$/m', $text, $seatMatches);
                            $countSeats = count($seatMatches[1]);

                            if ($countSeats === $countTripSegments) {
                                $seatsByPassengers[] = $seatMatches[1];
                            }
                        }

                        for ($i = 0; $i < $countTripSegments; $i++) {
                            $seatsBySegment = [];

                            foreach ($seatsByPassengers as $seats) {
                                $seatsBySegment[] = $seats[$i];
                            }

                            if (count($seatsBySegment)) {
                                $this->seats[] = implode(', ', $seatsBySegment);
                            }
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(?:vluchtnummer|flight\s+number):\s+(\w+)\s+(\d+)#', $text, $m)) {
                                return ['AirlineName' => $m[1], 'FlightNumber' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $dateStr = '';
                            $regex = '#(?:heenvlucht|terugvlucht|Hinflug|onward\s+flight|return\s+flight)\s+\w+\s+(\d+)\s+(\w+),\s+(\d+)#';

                            if (preg_match($regex, $text, $m)) {
                                $dateStr = $m[1] . ' ' . en($m[2]) . ' ' . $m[3];
                            }

                            foreach (['Dep' => '(?:van|von|from)', 'Arr' => '(?:naar|nach|to)'] as $key => $value) {
                                $regex = '#';
                                $regex .= $value . ':\s+';
                                $regex .= '(?P<' . $key . 'Name>.*?)\s+';
                                $regex .= '\(\s*(?P<' . $key . 'Code>\w{3})\s*\),\s+';
                                $regex .= '.*?';
                                $regex .= '(?P<' . $key . 'Date>\d+:\d+)';
                                $regex .= '#';

                                if (preg_match($regex, $text, $m)) {
                                    $m[$key . 'Date'] = strtotime($dateStr . ', ' . $m[$key . 'Date']);
                                    copyArrayValues($res, $m, [$key . 'Code', $key . 'Name', $key . 'Date']);
                                }
                            }

                            return $res;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return array_shift($this->seats);
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['nl', 'de', 'en'];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }
}
