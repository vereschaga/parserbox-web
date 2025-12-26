<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\lufthansa\Email;

class PDF extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "lufthansa/it-4442330.eml";
    public $pdf;
    public $reBody = [
        'Please print this receipt and retain throughout your journey',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('\w+ - \d+ \w+.+pdf'); //EMD - 2208207082781 PNR - 3Y28W5 - DUPLICATE.pdf

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetBody($html);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $reBody) {
                if (stripos($text, $reBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lufthansa.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lufthansa.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//*[contains(text(), 'Booking reference')]/following::text()[2]");
        $it['Passengers'][] = $this->pdf->FindSingleNode("//*[contains(text(), 'Travel data for')]/following::text()[2]");
        $date = $this->pdf->FindSingleNode("//*[contains(text(), 'Date of issue')]/following::text()[2]");

        if (preg_match('#(\d{2})(\D+)(\d{2})#', $date, $math)) {
            $date = strtotime($math[1] . ' ' . $this->monthNameToEnglish($math[2]) . ' ' . $math[3]);
        }

        if (preg_match('#(\w{3})\s+([\d\.]+)#', $this->pdf->FindSingleNode("//*[contains(text(), 'Total')]/following::text()[2]"), $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = $m[2];
        }
        $seg = [];
        $xpath = "//*[contains(text(), 'To')]/following::text()[following::b[contains(., 'Issued in connection with')]]";
        $arr = $this->pdf->FindNodes($xpath);
        $segments = array_map(function ($e) {
            if (preg_match('#.*([A-Z]{3}).*#', $e, $m)) {
                return $m[1];
            }
        }, $arr);
        $depArrCode = array_diff($segments, [null]);
        $seg['DepCode'] = array_shift($depArrCode);
        $seg['ArrCode'] = array_shift($depArrCode);
        $seg['DepDate'] = $date;
        $seg['ArrDate'] = $date;
        $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
        $seg['AirlineName'] = AIRLINE_UNKNOWN;
        $it['TripSegments'][] = $seg;

        return [$it];
    }
}
