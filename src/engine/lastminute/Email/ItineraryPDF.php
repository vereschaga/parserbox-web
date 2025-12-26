<?php
/**
 * Created by PhpStorm.
 * User: roman
 * Date: 05.12.16
 * Time: 18:08.
 */

namespace AwardWallet\Engine\lastminute\Email;

class ItineraryPDF extends PDF3
{
    public $mailFiles = "lastminute/it-5063310.eml";
    protected static $detectBody = [
        'nl' => ['Algemene reisgegevens', 'lastminute'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lastminute.nl') !== false
            || isset($headers['subject']) && stripos($headers['subject'], 'bij Lastminute.nl') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lastminute.nl') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    protected function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $pdf = $this->pdfText;
        $passengersAndLocator = $this->cutText($pdf, 'Reiziger(s)', 'Reispakket:');
        $passengers = [];

        if ($pasngr = preg_split('/\d{1,2}\s+\D{1,2}/', $passengersAndLocator, -1, PREG_SPLIT_NO_EMPTY)) {
            foreach ($pasngr as $ps) {
                if (preg_match('/(?<LName>\w+)\s+(?:de\s+\D\.|\D\.)\s+(?<FName>\w+)/', $ps, $m)) {
                    $passengers[] = $m['FName'] . ' ' . $m['LName'];
                }
            }
        }
        $it['Passengers'] = $passengers;
        $recordLocator = $this->cutText($pdf, 'Merk:', 'Bestemming:');

        if (preg_match('/Reserveringsnr:\s+(\w+)/', $recordLocator, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $segmentsText = $this->cutText($pdf, 'Vervoer', 'Let op!');
        $segments = explode('Van:', $segmentsText);
        array_shift($segments); //because it's not valid segment
        $countSegments = count($segments);

        for ($i = 0; $i < $countSegments; $i++) {
            $this->getTripSegments($segments[$i]);
            $it['TripSegments'][] = $this->it;
        }

        return [$it];
    }

    /**
     * example:
     *       Amsterdam                    Vertrekdatum/-tijd:                   27/04/2014           6:30
     * Naar:     Rhodos                       Aankomstdatum/-tijd:                  27/04/2014           11:10
     * Airline:                               Transavia Airlines                    Vliegtuigtype:                      Boeing 737
     * Vluchtnummer:                          HV 679                                Zitcomfort en service:              Economy
     * Reiziger(s):                           1, 2.
     *
     * @param $textSegment
     *
     * @return array
     */
    protected function getTripSegments($textSegment)
    {
        $re = '/';
        $re .= '(?<DepName>\w+)[\D\w\s]+(?<DepDate>\d{2}\/\d{2}\/\d{4}\s+\d{1,2}:\d{1,2})[\D\s\w]+Naar:\s+(?<ArrName>\w+)';
        $re .= '[\w\s\D]+(?<ArrDate>\d{2}\/\d{2}\/\d{4}\s+\d{1,2}:\d{1,2})[\s\D\w]+Vliegtuigtype:(?<Aircraft>.+)[\w\s\D]+';
        $re .= 'Vluchtnummer:\s+(?<AirlineName>\w{2})\s+(?<FlightNumber>\d+)[\s\D\w]+service:\s+(?<Cabin>\w+)';
        $re .= '/';

        if (preg_match($re, $textSegment, $m)) {
            $depDate = \DateTime::createFromFormat('d/m/Y H:i', trim($m['DepDate']));
            $depDate->format('m/d/Y H:i');
            $arrDate = \DateTime::createFromFormat('d/m/Y H:i', trim($m['ArrDate']));
            $arrDate->format('m/d/Y H:i');

            return $this->it = [
                'DepName'      => $m['DepName'],
                'DepDate'      => $depDate->getTimestamp(),
                'ArrDate'      => $arrDate->getTimestamp(),
                'ArrName'      => $m['ArrName'],
                'Aircraft'     => trim($m['Aircraft']),
                'AirlineName'  => $m['AirlineName'],
                'FlightNumber' => $m['FlightNumber'],
                'Cabin'        => $m['Cabin'],
                'DepCode'      => TRIP_CODE_UNKNOWN,
                'ArrCode'      => TRIP_CODE_UNKNOWN,
            ];
        } else {
            return $this->it = [null];
        }
    }

    protected function getPDFName()
    {
        return 'Reisspecificatie.*\.pdf';
    }
}
