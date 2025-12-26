<?php

namespace AwardWallet\Engine\s7\Email;

/**
 * @deprecated It is advisable to rewrite the parser! Was young and inexperienced:)
 *
 * @author Mark Iordan
 */
class ItineraryPdf2012En extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "s7/it-4153170.eml";

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
        return isset($headers['from']) && stripos($headers['from'], 'aero@s7.ru') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'E-Ticket itinerary receipt') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->getPdfName());

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (strpos($text, 'ITINERARY RECEIPT') !== false
                && strpos($text, 'Thank you for choosing S7 Airlines') !== false
            ) {
                return true;
            }
        }

        return false;
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
        $this->parseTrip($this->findСutSection($pdfText, 'Purchase date', 'Passenger'));
        $this->parsePassengers($this->findСutSection($pdfText, 'Passenger', 'Flight information'));
        $this->parsePayment($this->findСutSection($pdfText, 'Payment method', 'What\'s next?'));
        $this->iterationSegments($this->findСutSection($pdfText, 'Flight information', 'Fare '));

        return [$this->result];
    }

    protected function parseTrip($pdfText)
    {
        foreach (array_values(array_filter(explode("\n", $pdfText))) as $value) {
            if (preg_match('/\b[A-Z\d]{5,6}\b/', $value, $matches)) {
                $this->result['RecordLocator'] = $matches[0];
            }

            if (preg_match('/\b[\d]{1,2} [a-z]{3} [\d]{4}\b/i', $value, $matches)) {
                $date = new \DateTime($matches[0]);
                $this->result['ReservationDate'] = $date->getTimestamp();
            }
        }
    }

    protected function parsePayment($pdfText, $label = [])
    {
        $array = preg_split('/\n{2,}/', $pdfText, -1, PREG_SPLIT_NO_EMPTY);
        $array = preg_split('/\s{2,}/', end($array), -1, PREG_SPLIT_NO_EMPTY);

        if (!empty($array)) {
            $this->result['BaseFare'] = cost($array[0]);
            $this->result['Tax'] = cost($array[1]);
        }

        $index = 0;
        $totalCharge = 0;

        foreach ($array as $key => $value) {
            if (preg_match("#^\s*([\d\.,]+)#", $value, $m)) {
                $sum = (int) $m[1];

                if ($sum > $totalCharge) {
                    $totalCharge = $sum;
                    $index = $key;
                }
            } else {
                break;
            }
        }

        if (isset($array[$index])) {
            $this->result += total($array[$index]);
        }
    }

    /**
     * Pars PDF-text in the "Passengers" array.
     *
     * @param type $pdfText
     *
     * @return type
     */
    protected function parsePassengers($pdfText)
    {
        $array = array_map(function ($value) {
            if (preg_match('/^(Mr|Ms|Mrs)\.*/', trim($value))) {
                return preg_split('/\s{2,}/', $value, -1, PREG_SPLIT_NO_EMPTY);
            }
        }, preg_split('/\n{2,}/', $pdfText, -1, PREG_SPLIT_NO_EMPTY));

        foreach (array_values(array_filter($array)) as $value) {
            if (preg_match('/(Mr|Ms|Mrs)\.*\s+([a-z\s]+)/i', $value[0], $matches)) {
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

        foreach ($this->parseSegments($pdfText) as $key => $value) {
            // S7 4625
            if (preg_match('/([\w]{2})?\s*([\d]{3,4})/', $value[0][0], $matches)) {
                $segments[$key]['AirlineName'] = $matches[1];
                $segments[$key]['FlightNumber'] = $matches[2];
            }

            // 15 Mar 2014 18:25
            if (preg_match('/[\d]{1,2} \w{3} [\d]{4} [\d]{2}:[\d]{2}/ui', $value[0][1], $matches)) {
                $segments[$key]['DepDate'] = strtotime($this->dateStringToEnglish($matches[0]));
            }

            if (preg_match('/[\d]{1,2} \w{3} [\d]{4} [\d]{2}:[\d]{2}/ui', $value[0][2], $matches)) {
                $segments[$key]['ArrDate'] = strtotime($this->dateStringToEnglish($matches[0]));
            }

            $segments[$key]['DepName'] = trim($value[1][1], ' \t\n\r\0\x0B,*');
            $segments[$key]['ArrName'] = trim($value[1][2], ' \t\n\r\0\x0B,*');

            if ($value[1][0] !== '*') {
                $segments[$key]['Aircraft'] = $value[1][0];
            }
            $segments[$key]['ArrCode'] = $segments[$key]['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        $this->result['TripSegments'] = $segments;
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
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function getPdfName()
    {
        return '(eticket|E-Ticket)_[\w-\s_]+?_en\.pdf';
    }
}
