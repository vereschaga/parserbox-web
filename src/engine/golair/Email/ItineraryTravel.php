<?php

namespace AwardWallet\Engine\golair\Email;

class ItineraryTravel extends \TAccountChecker
{
    public $mailFiles = "golair/it-4005171.eml, golair/it-4010832.eml";

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'voegol@voegol.com.br') !== false
            || isset($headers['subject']) && preg_match('/Alerta\s+GOL\s+-\s+Itinerário\s+de\s+Viagem/i', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return preg_match('/Visite\s+nosso\s+site:\s*(www\.|)voegol\.com\.br/i', $parser->getPlainBody());
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voegol.com.br') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $it = $this->ParseEmail($parser);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ItineraryTravel',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    // The function returns the index of the first element found if it was found in the array, otherwise FALSE
    protected function ArrayRegexp($regexp, $array = [])
    {
        $result = false;

        foreach ($array as $i => $value) {
            if (preg_match($regexp, $value)) {
                $result = $i;

                break;
            }
        }

        return $result;
    }

    protected function ParseEmail(\PlancakeEmailParser $parser)
    {
        $it = [];
        $it['Kind'] = 'T';
        $plain = $parser->getPlainBody();
        $lines = array_values(array_filter(array_map('trim', explode("\n", $plain)), 'strlen'));
        $segmentMarkers = [];
        $passengerMarkers = [];

        foreach ($lines as $i => $line) {
            if (!isset($it['RecordLocator'])) {
                if (preg_match('/^LOCALIZADOR\s+GOL:\s*([A-Z\d]{6})$/i', $line, $matches)) {
                    $it['RecordLocator'] = $matches[1];
                }
            }

            if (preg_match('/Chegada$/i', $line)) {
                $segmentsStart = $i + 1;
            }

            if (preg_match('/^\d{1,2}\s+[A-Z]{3}(\s+\d{2,4}|)$/', $line)) {
                $segmentMarkers[] = $i;
            }

            if (preg_match('/^PASSAGEIROS$/i', $line)) {
                $segmentsEnd = $i - 1;
            }

            if (preg_match('/Número\s+do\s+Recibo$/i', $line)) {
                $passengersStart = $i + 1;
            }

            if (preg_match('/^\d{1,2}\.\s+.+$/', $line)) {
                $passengerMarkers[] = $i;
            }

            if (preg_match('/^INFORMAÇÕES\s+SOBRE\s+A\s+COMPRA$/i', $line)) {
                $passengersEnd = $i - 1;
            }

            if (!isset($it['ReservationDate'])) {
                if (preg_match('/^Data\s+da\s+Compra:\s+(\d{2}\/\d{2}\/\d{4})$/i', $line, $matches)) {
                    $reservationDate = str_replace('/', '.', $matches[1]);
                    $it['ReservationDate'] = strtotime($reservationDate);
                }
            }

            if (!isset($it['Status'])) {
                if (preg_match('/^Situação\s+da\s+Passagem:\s+(.+)$/i', $line, $matches)) {
                    $it['Status'] = $matches[1];
                }
            }

            if (!isset($it['Currency']) || !isset($it['TotalCharge'])) {
                if (preg_match('/^TOTAL\s+DA\s+VIAGEM\s+(BRL)\s+([,\d]+)$/i', $line, $matches)) {
                    $it['Currency'] = $matches[1];
                    $it['TotalCharge'] = str_replace(',', '.', $matches[2]);
                }
            }
        }
        $it['TripSegments'] = [];

        if (isset($segmentsStart) && count($segmentMarkers) !== 0 && isset($segmentsEnd)) {
            $segments = array_slice($lines, $segmentsStart, $segmentsEnd - $segmentsStart + 1, true);

            foreach ($segmentMarkers as $i => $marker) {
                $seg = [];

                if (count($segmentMarkers) > $i + 1) {
                    $currentSegment = array_slice($segments, $marker - $segmentMarkers[0], $segmentMarkers[$i + 1] - $marker);
                } else {
                    $currentSegment = array_slice($segments, $marker - $segmentMarkers[0], null); // for last segment
                }
                $date = $currentSegment[0];
                $times = $this->ArrayRegexp('/^\d{2}:\d{2}\s*\d{2}:\d{2}$/', $currentSegment);

                if (preg_match('/^\d{1,2}\s+[A-Z]{3}(\s+\d{2,4}|)$/', $date) && $times !== false) {
                    if (!preg_match('/\s+\d{2,4}$/', $date)) {
                        $year = getdate(strtotime($parser->getHeader('date')))['year'];
                        $date .= ' ' . $year;
                    }
                    $date = strtotime(preg_replace('#\s+#', ' ', $date));
                    preg_match('/^(\d{2}:\d{2})\s*(\d{2}:\d{2})$/', $currentSegment[$times], $matches);
                    $seg['DepDate'] = strtotime($matches[1], $date);
                    $seg['ArrDate'] = strtotime($matches[2], $date);
                }

                if (($flight = $this->ArrayRegexp('/^[A-Z]{2,3}\s*\d+$/', $currentSegment)) !== false) {
                    if (preg_match('/^([A-Z\d]{2}[A-Z]?)\s*(\d+)$/', $currentSegment[$flight], $matches)) {
                        $seg['FlightNumber'] = $matches[2];
                        $seg['AirlineName'] = $matches[1];
                    }
                }
                $names = array_values(preg_grep('/^.+\s+\([A-Z]{3}\)$/', $currentSegment));

                if (count($names) === 2) {
                    preg_match('/^(.+)\s+\(([A-Z]{3})\)$/', $names[0], $matchesDep);
                    $seg['DepName'] = $matchesDep[1];
                    $seg['DepCode'] = $matchesDep[2];
                    preg_match('/^(.+)\s+\(([A-Z]{3})\)$/', $names[1], $matchesArr);
                    $seg['ArrName'] = $matchesArr[1];
                    $seg['ArrCode'] = $matchesArr[2];
                }
                $it['TripSegments'][] = $seg;
            }
        }
        $it['Passengers'] = [];

        if (isset($passengersStart) && count($passengerMarkers) !== 0 && isset($passengersEnd)) {
            $passengers = array_slice($lines, $passengersStart, $passengersEnd - $passengersStart + 1, true);

            foreach ($passengerMarkers as $i => $passenger) {
                preg_match('/^\d{1,2}\.\s+(.+)$/', $passengers[$passenger], $matches);
                $it['Passengers'][] = $matches[1];
            }
        }

        return $it;
    }
}
