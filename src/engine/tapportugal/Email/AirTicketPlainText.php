<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\tapportugal\Email;

class AirTicketPlainText extends \TAccountChecker
{
    public $mailFiles = "tapportugal/it-5511015.eml";

    private $detectBody = [
        'Por favor, contate o Call Center da LATAM',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail($parser->getPlainBody());

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketPlainText',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lufthansa') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lufthansa') !== false;
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $year = 2000;
        $nameAndYear = $this->cutText('NOME', 'RESERVA', $text);

        if (!empty($nameAndYear) && preg_match('/(?<Name>[\w\/]+)\s+.+:\s+(?<Day>\d+)\s?(?<Month>\w+)\s?(?<Year>\d{2,4})/', $nameAndYear, $m)) {
            $it['Passengers'][] = $m['Name'];
            $it['ReservationDate'] = strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year']);
            $year += (int) $m['Year'];
        }

        $recordLoc = $this->cutText('CÓDIGO DA RESERVA', 'NÚMERO DO E-TICKET', $text);

        if (!empty($recordLoc) && preg_match('/:\s*([A-Z0-9]{5,7})/', $recordLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $textWithSegments = $this->cutText('DO E-TICKET', 'Tarifa Aerea', $text);
        $segments = explode('Data:', $textWithSegments);
        array_shift($segments);

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $date = substr($segment, 0, 30);

            if (preg_match('/(\d+)\s?([a-z]+)/i', $date, $m)) {
                $date = $m[1] . ' ' . $m[2];
            }
            $flightInfo = $this->cutText('Vôo:', 'Saída:', $segment);

            if (!empty($flightInfo) && preg_match('/([A-Z]{2})\s?(\d+)/', $flightInfo, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $dep = $this->cutText('Saída:', 'Chegada:', $segment);

            if (!empty($dep) && preg_match('/(?<Time>\d{1,2}:\d{2})\s+(?<Name>.+)\s?(?<Code>\b[A-Z]{3}\b)/', $dep, $m)) {
                $depTime = $m['Time'];
                $seg['DepDate'] = strtotime($date . ' ' . $year . ', ' . $depTime);
                $seg['DepCode'] = $m['Code'];
                $seg['DepName'] = $m['Name'];
            }
            $arr = $this->cutText('Chegada:', 'Classe:', $segment);

            if (!empty($arr) && preg_match('/(?<Time>\d{1,2}:\d{2})\s+(?<Name>.+)\s?(?<Code>\b[A-Z]{3}\b)/', $arr, $m)) {
                $arrTime = $m['Time'];
                $seg['ArrDate'] = strtotime($date . ' ' . $year . ', ' . $arrTime);
                $seg['ArrCode'] = $m['Code'];
                $seg['ArrName'] = $m['Name'];
            }
            $cabin = $this->cutText('Classe', 'Aeronave', $segment);

            if (!empty($cabin) && preg_match('/(\w+)\s+\(\s*([A-Z]{1})\s*\)/u', $cabin, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['BookingClass'] = $m[2];
            }
            $aircraft = $this->cutText('Aeronave', 'Bagagem', $segment);

            if (!empty($aircraft) && preg_match('/:\s+(.+)/', $aircraft, $m)) {
                $seg['Aircraft'] = $m[1];
            }
            $seat = substr($segment, stripos($segment, 'Bagagem'));

            if (!empty($seat) && preg_match('/\b([A-Z0-9]{1,3})\b/i', $seat, $m)) {
                $seg['Seats'] = $m[1];
            }
            $it['TripSegments'][] = $seg;
        }
        //		$it['segm'] = $segments;
        return [$it];
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = stristr(stristr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        foreach ($this->detectBody as $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }
}
