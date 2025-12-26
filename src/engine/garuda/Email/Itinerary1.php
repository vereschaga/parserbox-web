<?php

namespace AwardWallet\Engine\garuda\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "garuda/it-1.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $airsegments = $this->http->findNodes(".//*[contains(text(),'Fare Family')]/ancestor::table[1]/tbody/tr/td");
        $reservsegments = $this->http->FindNodes(".//*[contains(text(),'Reference:')]/ancestor::table[1]/tbody/tr");
        $resNum = '';
        $resDate = date('Y-m-d H:i:s');

        if (!empty($reservsegments)) {
            $resNum = trim(preg_replace('#Reference\s*\:#', '', $reservsegments[0]));
            $resDate = strtotime(trim(preg_replace('#Issue\s*Date\s*\:#', '', $reservsegments[1])));
        }
        $pass = $this->http->FindNodes(".//*[contains(text(),'Passengers')]/ancestor::*[2]/following-sibling::*[1]/tbody/tr/td[1]");
        $passangers = [];

        if (!empty($pass)) {
            foreach ($pass as $p) {
                if (!empty($p)) {
                    $passangers[] = $p;
                }
            }
        }
        $depCode = '';
        $depDate = null;
        $airNameDep = '';
        $arrCode = '';
        $arrDate = null;
        $airNameArr = '';
        $flNum = '';
        $seats = '';
        $class = '';
        $duration = '';
        $miles = '';

        if (!empty($airsegments)) {
            $depInfo = $airsegments[0];
            preg_match('#\((\w*)\)#', $depInfo, $depCodeTemp);

            if (!empty($depCodeTemp[1])) {
                $depCode = $depCodeTemp[1];
            }
            $depDateTemp = substr($depInfo, strpos($depInfo, 'ID') + 2, strlen($depInfo));
            $depDateTemp = date_parse_from_format('D, d M Y, H:i', $depDateTemp);
            $depDate = mktime($depDateTemp['hour'], $depDateTemp['minute'], 0, $depDateTemp['month'], $depDateTemp['day'], $depDateTemp['year']);

            $airNameDepTemp = explode(',', $depInfo);

            if (!empty($airNameDepTemp[1])) {
                $airNameDep = trim($airNameDepTemp[1]);
            }
            $arrInfo = $airsegments[1];
            preg_match('#\((\w*)\)#', $arrInfo, $arrCodeTemp);

            if (!empty($arrCodeTemp[1])) {
                $arrCode = $arrCodeTemp[1];
            }
            $arrDateTemp = substr($arrInfo, strpos($arrInfo, 'ID') + 2, strlen($arrInfo));
            $arrDateTemp = date_parse_from_format('D, d M Y, H:i', $arrDateTemp);
            $arrDate = mktime($arrDateTemp['hour'], $arrDateTemp['minute'], 0, $arrDateTemp['month'], $arrDateTemp['day'], $arrDateTemp['year']);

            $airNameArrTemp = explode(',', $arrInfo);

            if (!empty($airNameArrTemp[1])) {
                $airNameArr = trim($airNameArrTemp[1]);
            }
            preg_match('#\w{2,3}\s*\d*#', $airsegments[2], $flNumTemp);

            if (!empty($flNumTemp[0])) {
                $flNum = $flNumTemp[0];
            }

            foreach ($airsegments as $segment) {
                if (stripos($segment, 'Duration') !== false) {
                    $duration = trim(str_replace('Duration:', '', $segment));
                }

                if (stripos($segment, 'Miles') !== false) {
                    $miles = trim(str_replace('Miles:', '', $segment));
                }

                if (stripos($segment, 'Seats') !== false) {
                    preg_match('#Fare\s*Family:(.*)Stop:#', $segment, $classTemp);

                    if (!empty($classTemp[1])) {
                        $class = trim($classTemp[1]);
                    }
                    preg_match("#Seats:\s*(\d+\w*)#", $segment, $seatsTemp);

                    if (!empty($seatsTemp[1])) {
                        $seats = $seatsTemp[1];
                    }
                }
            }
        }
        $paySegments = $this->http->FindNodes(".//*[contains(text(),'Passenger Type')]/ancestor::table[1]/tbody/tr[2]/td");
        $base = null;
        $tax = null;
        $total = null;
        $currency = null;

        if (!empty($paySegments)) {
            $base = $paySegments[1];
            $curr = preg_replace("/(\d*,*)+/i", '', $base);

            if (!empty($curr)) {
                $currency = trim($curr);
            }
            $base = (float) preg_replace("/([^0-9\\.])/i", "", $base);
            $tax = (float) preg_replace("/([^0-9\\.])/i", "", $paySegments[3]);
            $total = (float) preg_replace("/([^0-9\\.])/i", "", $paySegments[4]);
        }
        $itineraries['RecordLocator'] = $resNum;
        $itineraries['Passengers'] = $passangers;
        $itineraries['TotalCharge'] = $total;
        $itineraries['BaseFare'] = $base;
        $itineraries['Currency'] = $currency;
        $itineraries['Tax'] = $tax;
        $itineraries['ReservationDate'] = $resDate;
        $itineraries['TripSegments'][] = [
            'FlightNumber'  => $flNum,
            'DepCode'       => $depCode,
            'DepDate'       => $depDate,
            'DepName'       => $airNameDep,
            'ArrCode'       => $arrCode,
            'ArrDate'       => $arrDate,
            'ArrName'       => $airNameArr,
            'Duration'      => $duration,
            'TraveledMiles' => $miles,
            'Seats'         => $seats,
            'Cabin'         => $class,
        ];

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'PT Garuda Indonesia') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("/[\.@]garuda-indonesia\.com/i", $headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]garuda-indonesia\.com$/ims', $from);
    }
}
