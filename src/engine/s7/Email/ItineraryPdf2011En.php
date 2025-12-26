<?php

namespace AwardWallet\Engine\s7\Email;

/**
 * PDF, it-4153201.eml.
 *
 * @deprecated It is advisable to rewrite the parser! Was young and inexperienced:)
 *
 * @author Mark Iordan
 */
class ItineraryPdf2011En extends \TAccountChecker
{
    public $mailFiles = "s7/it-4153201.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->getPdfName());

        if (empty($pdf)) {
            return false;
        }

        $pdfBody = $parser->getAttachmentBody(array_shift($pdf));
        $pdf = str_replace(' ', ' ', \PDF::convertToText($pdfBody));

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($pdf)],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'etix@s7.ru') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Подтверждение покупки на сайте www.s7.ru') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Маршрутная квитанция подтверждает оплату стоимости билета в полном объеме,') !== false
                && stripos($parser->getHTMLBody(), 'S7 Airlines') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@s7.ru') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    protected function parseEmail($pdfText)
    {
        $this->result['Kind'] = 'T';
        $this->parseTrip($this->findСutSection($pdfText, 'E-Ticket itinerary receipt', 'Passenger'));
        $this->parsePassengers($this->findСutSection($pdfText, 'Passenger', 'Flight Information'));
        $this->result += total($this->findСutSection($pdfText, 'Total amount paid:', PHP_EOL));
        $this->iterationSegments($this->findСutSection($pdfText, 'Flight Information', 'Information about the fare'));

        return [$this->result];
    }

    protected function parseTrip($pdfText)
    {
        foreach (explode("\n", $pdfText) as $value) {
            if (preg_match('/\b[A-Z\d]{5,6}\b/', $value, $match)) {
                $this->result['RecordLocator'] = $match[0];
            }

            if (preg_match('/\b[\d]{1,2} [a-z]{3} [\d]{4}\b/i', $value, $match)) {
                $this->result['ReservationDate'] = strtotime($match[0]);
            }
        }
    }

    protected function parsePassengers($pdfText)
    {
        $array = array_map(function ($value) {
            if (preg_match('/^(Mr|Ms)\.*/i', trim($value))) {
                return preg_split('/\s{2,}/', $value, -1, PREG_SPLIT_NO_EMPTY);
            }
        }, preg_split('/\n{2,}/', $pdfText, -1, PREG_SPLIT_NO_EMPTY));

        foreach (array_values(array_filter($array)) as $value) {
            if (preg_match('/(Mr|Ms)\.*\s+([a-z\s]+)/i', $value[0], $matches)) {
                $this->result['Passengers'][] = $matches[2];
            }

            if (preg_match('/[\d]{10,}/', $value[1])) {
                $this->result['TicketNumbers'][] = $value[1];
            }
        }
    }

    protected function iterationSegments($pdfText)
    {
        $segments = [];

        foreach ($this->parseSegments($pdfText, 3, 1) as $key => $value) {
            // Saint Petersburg, Russia (LED) - Moscow (Domodedovo), Russia (DME)
            $name = $value[0][0] . ' ' . $value[1][1];

            if (preg_match('/([\w\s,()]+)\s+\(([A-Z]{3})\)\s*-\s*([\w\s,()]+)\s+\(([A-Z]{3})\)/ui', $name, $match)) {
                $segments[$key]['DepName'] = $match[1];
                $segments[$key]['DepCode'] = $match[2];
                $segments[$key]['ArrName'] = $match[3];
                $segments[$key]['ArrCode'] = $match[4];
            }

            if (preg_match('/([\w]{5,})\s*([\w]{1})/', $value[0][4], $match)) {
                $segments[$key]['Cabin'] = $match[1];
                $segments[$key]['BookingClass'] = $match[2];
            }

            if (preg_match('/([\w]{2})\s*([\d]{3,4})/', $value[1][0], $matches)) {
                $segments[$key]['AirlineName'] = $matches[1];
                $segments[$key]['FlightNumber'] = $matches[2];
            }

            $date = $this->joinDate($value);

            if ($date['DepDate'] !== false && $date['ArrDate'] !== false) {
                $segments[$key] += $date;
            }

            $segments[$key] += $this->searchAdditionalData($value);
        }

        $this->result['TripSegments'] = $segments;
    }

    protected function searchAdditionalData($array)
    {
        $segments = [];

        foreach ($array as $value) {
            foreach ($value as $v) {
                if (preg_match('/^Miles\/Kilometeres:\s*(.*)/', $v, $match)) {
                    $segments['TraveledMiles'] = $match[1];
                }

                if (preg_match('/^In transit:\s*(.*)/', $v, $match)) {
                    $segments['Duration'] = $match[1];
                }

                if (preg_match('/^Meal:\s*(.*)/', $v, $match)) {
                    $segments['Meal'] = $match[1];
                }
            }
        }

        return $segments;
    }

    protected function joinDate($value)
    {
        $date = array_values(array_filter($value[1], function ($value) {
            return preg_match('/\w{3} \d+, \d{4}/', $value);
        }));

        $time = array_values(array_filter($value[0], function ($value) {
            return preg_match('/\d+:\d+ \w{3}/', $value);
        }));

        $gmt = array_values(array_filter($value[2], function ($value) {
            return preg_match('/GMT\+\d+:\d+/', $value);
        }));

        foreach ($time as &$value) {
            if (preg_match('/\d+:\d+/', $value, $match)) {
                $value = $match[0];
            }
        }

        $depDate = strtotime($date[0] . ' ' . $time[0] . ' ' . $gmt[0]);
        $arrDate = strtotime($date[1] . ' ' . $time[1] . ' ' . $gmt[1]);

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    /**
     * TODO: do not repeat!
     * Pars PDF-text in the "TripSegments" array
     * <pre>
     * Example:
     * Рейс                       Отправление                 Прибытие
     * S7 052                     21 ноя                      21 ноя
     * S7 Airlines                06:20 Екатеринбург,         06:55 Москва,
     * Airbus A319                Кольцово                    Домодедово.
     *
     * Result:
     * (
     *  [0] => S7 052
     *  [1] => 21 ноя
     *  [2] => 21 ноя
     * )...
     * </pre>
     *
     * @param type $pdfText
     * @param type $rowsMin filter rows
     * @param type $cellsMin filter cells
     *
     * @return type
     */
    protected function parseSegments($pdfText, $rowsMin = 3, $cellsMin = 3)
    {
        // Iteration on flights
        $array = array_map(function ($value) use ($rowsMin, $cellsMin) {
            // Separation of the rows of flight
            $row = preg_split('/\n/', $value, -1, PREG_SPLIT_NO_EMPTY);
            // Cuts debris from the flight lines
            if (!empty($row) && count($row) >= $rowsMin) {
                // Iterate through rows flight
                foreach ($row as $key => &$val) {
                    $prefix = '';
                    // Separation of each flight line into columns
                    if (count(preg_split('/\s{3,}/', $val, -1, PREG_SPLIT_NO_EMPTY)) <= 2) {
                        $prefix = '*';
                    }
                    $col = preg_split('/\s{3,}/', $prefix . $val, -1, PREG_SPLIT_NO_EMPTY);

                    if (!empty($col) && count($col) >= $cellsMin) {
                        $val = $col;
                    } else {
                        unset($row[$key]);
                    }
                }

                return array_values($row);
            }
        }, preg_split('/\n{2,}/', $pdfText, -1, PREG_SPLIT_NO_EMPTY));

        return array_values(array_filter($array));
    }

    protected function findСutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_stristr(mb_stristr($input, $searchStart), $searchFinish, true);

        return mb_substr($input, mb_strlen($searchStart));
    }

    protected function getPdfName()
    {
        return '(eticket|E-Ticket)_[\w-\s]+_en\.pdf';
    }
}
