<?php
/**
 * Created by PhpStorm.
 * User: roman
 * Date: 16.03.17
 * Time: 20:35.
 */

namespace AwardWallet\Engine\golair\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightTravel extends \TAccountChecker
{
    public $mailFiles = "golair/it-4356127.eml, golair/it-6001465.eml, golair/it-7320087.eml, golair/it-7325023.eml, golair/it-7465348.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@voegol.com.br') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@voegol.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], "Partida") !== false && stripos($this->http->Response['body'], "G3") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return [$text];
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function parseEmail()
    {
        $text = strip_tags(str_replace("</td>", "\n</td>", $this->http->Response['body']));
        $its = [];
        $flights = $this->splitText("#(Nome)#", $text);

        foreach ($flights as $flight) {
            $seg = [];

            if (preg_match("#Localizador:\s*([\w]{5,6})\s+#", $flight, $m)) {
                $RecordLocator = $m[1];
            }

            if (preg_match("#Nome\s+([A-Z\s]+)\s+#", $flight, $m)) {
                $passangers = trim($m[1]);
            }

            if (preg_match("#Tarifa:\s+([^(\n]+)(?:\(([A-Z]{1,2})\))\s+#", $flight, $m)) {
                $seg['Cabin'] = trim($m[1]);

                if (!empty($m[2])) {
                    $seg['BookingClass'] = $m[2];
                }
            }

            if (preg_match("#Data:\s+([\d\/]+)#", $flight, $m)) {
                $seg['flightDate'] = $m[1];
            }

            if (preg_match("#Voo:\s+([A-Z]{3})\s*-\s*([A-Z]{3})#", $flight, $m)) {
                $seg['flightName'] = $m[1] . '-' . $m[2];
            }

            if (preg_match("#\n\s*(\w[\w\s-]+)\s*\(([A-Z]{3})\)\s+(\d{2})\/(\w{3})\/(\d{2})\s*-\s*([\d:]{5})\s*Partida#u", $flight, $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['DepCode'] = $m[2];
                $seg['DepDate'] = strtotime($m[3] . ' ' . MonthTranslate::translate($m[4], 'pt') . ' 20' . $m[5] . ' ' . $m[6]);
            }

            if (preg_match("#\n\s*(\w[\w\s-]+)\s*\(([A-Z]{3})\)\s+(\d{2})\/(\w{3})\/(\d{2})\s*-\s*([\d:]{5})\s*Chegada#u", $flight, $m)) {
                $seg['ArrName'] = trim($m[1]);
                $seg['ArrCode'] = $m[2];
                $seg['ArrDate'] = strtotime($m[3] . ' ' . MonthTranslate::translate($m[4], 'pt') . ' 20' . $m[5] . ' ' . $m[6]);
            }

            if (preg_match("#\s+Voo\s+([A-Z\d]{2})\s*(\d{1,5})#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#\s+Operador por:\s*(.*)\n#", $flight, $m)) {
                $seg['Operator'] = trim($m[1]);
            }

            if (preg_match("#\s+Assento\s+(\d+[A-Z])\s+#", $flight, $m)) {
                $seg['Seats'][] = trim($m[1]);
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($passangers)) {
                        $its[$key]['Passengers'][] = $passangers;
                    }

                    if (isset($ReservationDate) && !isset($it['ReservationDate'])) {
                        $its[$key]['ReservationDate'] = $ReservationDate;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $i => $value) {
                        if (isset($seg['flightName']) && $seg['flightName'] == $value['flightName'] && isset($seg['flightDate']) && $seg['flightDate'] == $value['flightDate']) {
                            $its[$key]['TripSegments'][$i]['Seats'] = array_merge($value['Seats'], $seg['Seats']);
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }
            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($passangers)) {
                    $it['Passengers'][] = $passangers;
                }

                if (isset($ReservationDate)) {
                    $it['ReservationDate'] = $ReservationDate;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                unset($its[$key]['TripSegments'][$i]['flightName']);
                unset($its[$key]['TripSegments'][$i]['flightDate']);
            }

            if (isset($its[$key]['Passengers'])) {
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
            }
        }

        return $its;
    }
}
