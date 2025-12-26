<?php

namespace AwardWallet\Engine\goair\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "goair/it-11476465.eml";

    protected $langDetectors = [
        'en' => ['CHECK-IN BOARDING PASS', 'CHECKIN BOARDING PASS'],
    ];

    protected $lang = '';

    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@goair.in') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], 'Boarding pass') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'www.GoAir.in') === false && stripos($textPdf, ' GOAIR WEBCHECK') === false && stripos($textPdf, 'requested by Go Air') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $bps = [];

        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $textPdfFull = $textPdf;

                break;
            } else {
                continue;
            }
        }

        if (!$textPdfFull) {
            return false;
        }

        if ($it = $this->parsePdf($textPdfFull)) {
            $its[] = $it;
            $bp = $this->parseBp($it);

            if (!empty($bp)) {
                $bp['AttachmentFileName'] = $this->getAttachmentName($parser, $pdf);
                $bps[] = $bp;
            }
        } else {
            return false;
        }

        return [
            'parsedData' => [
                'Itineraries'  => $its,
                'BoardingPass' => $bps,
            ],
            'emailType' => 'BoardingPassPdf_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parsePdf($textSource)
    {
        $it = [];
        $it['Kind'] = 'T';

        $start = stripos($textSource, "Airline Copy\n");
        $end = stripos($textSource, "Customer Copy\n");

        if ($start === false || $end === false) {
            return false;
        }

        $text = substr($textSource, $start, $end - $start);

        // Passengers
        if (preg_match('/^[ ]*Name:\s*(\w.+?\/.{2,}?)(?:[ ]{2}|$)/miu', $text, $matches)) { // Name: QURESHI / IMRAN MR
            $it['Passengers'] = [$matches[1]];
        }

        $it['TripSegments'] = [];
        $seg = [];

        // DepName
        // ArrName
        // DepCode
        // ArrCode
        // AirlineName
        // FlightNumber
        $patternRoute = '/'
            . '^[ ]*From[ ]+To[ ]+Flight.*$\s+^[ ]*'
            . '(.+?)'
            . '^[ ]*PNR No.+Date.+Time'
            . '/mis';

        if (preg_match($patternRoute, $text, $matches)) {
            $textRoute = $matches[1];
        } else {
            return false;
        }

        if (preg_match('/^[ ]*(?<airportDep>\w.+)\b[ ]{2,}(?<airportArr>\w.+)\b[ ]{2,}(?<airline>[A-Z\d]{2})\s*(?<flightNumber>\d+)(?:[ ]{2}|$)/mu', $textRoute, $matches)) { // GOA    MUMBAI    G8 381
            $seg['DepName'] = $matches['airportDep'];
            $seg['ArrName'] = $matches['airportArr'];
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['AirlineName'] = $matches['airline'];
            $seg['FlightNumber'] = $matches['flightNumber'];
        }

        // RecordLocator
        // BookingClass
        // DepDate
        // ArrDate
        $patternPNR = '/'
            . '^[ ]*PNR No.+Date.+Time.*$\s+^[ ]*'
            . '(.+?)'
            . '^[ ]*Gate No'
            . '/mis';

        if (preg_match($patternPNR, $text, $matches)) {
            $textPNR = $matches[1];
        } else {
            return false;
        }

        if (preg_match('/^[ ]*(?<pnr>[A-Z\d]{5,7})(?:[ ]+(?<bClass>[A-Z]{1,2}))?\b[ ]{2,}(?<dateDep>\d{1,2}-[^-,.\d\s]{3,}-\d{2,4})[ ]+(?<timeDep>\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?)/m', $textPNR, $matches)) { // 1MC8FU    W    23-Apr-17    11:30
            $it['RecordLocator'] = $matches['pnr'];

            if (!empty($matches['bClass'])) {
                $seg['BookingClass'] = $matches['bClass'];
            }
            $seg['DepDate'] = strtotime($matches['dateDep'] . ', ' . $matches['timeDep']);
            $seg['ArrDate'] = MISSING_DATE;
        }

        // Seats
        if (preg_match('/(?:[ ]{2}|^)Seat No[.: ]+(\d{1,2}[A-Z])(?:[ ]{2}|$)/mi', $text, $matches)) { // Seat No. : 9C
            $seg['Seats'] = [$matches[1]];
        }

        $it['TripSegments'][] = $seg;

        return $it;
    }

    protected function parseBp($it)
    {
        if ($it['TripSegments'][0]['DepCode'] !== TRIP_CODE_UNKNOWN) {
            $bp = [];
            $bp['FlightNumber'] = $it['TripSegments'][0]['FlightNumber'];
            $bp['DepCode'] = $it['TripSegments'][0]['DepCode'];
            $bp['DepDate'] = $it['TripSegments'][0]['DepDate'];
            $bp['RecordLocator'] = $it['RecordLocator'];
            $bp['Passengers'] = $it['Passengers'];

            return $bp;
        }
    }

    //	protected function t($phrase) {
    //		if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase]))
    //			return $phrase;
    //		return self::$dict[$this->lang][$phrase];
    //	}

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    protected function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }
}
