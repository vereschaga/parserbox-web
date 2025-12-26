<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\lastminute\Email;

class PDF extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "lastminute/it-5011868.eml";

    private $lang = '';

    private $pdf;

    private static $detectBody = [
        'it' => 'Ricevuta del biglietto elettronico',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBodyAndAcceptLang($parser);
        $its = isset($this->pdf) ? $this->parseEmail() : [];

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBodyAndAcceptLang($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'lastminute.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lastminute.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[contains(., 'CODICE PRENOTAZIONE')]/following-sibling::p[1]");

//        $it['body'] = $this->pdf->Response['body'];
        $xpath = "//p[contains(text(), 'NOTE')]/following-sibling::p[string-length()<10 and descendant::b and not(contains(b, 'CONTANTI')) and not(contains(b, 'Tariffa'))]"; // 18dic13
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found');

            return false;
        }

        $times = $this->pdf->FindNodes("//p[contains(text(), 'NOTE')]/following-sibling::p[string-length()<10 and descendant::b and not(contains(b, 'CONTANTI')) and not(contains(b, 'Tariffa'))]/preceding-sibling::p[contains(., 'Ora')]/following-sibling::p[1]");
        $times = array_reverse($times);

        foreach ($roots as $i => $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if (preg_match('/([A-Z]{2}) (\d+)/', $this->pdf->FindSingleNode('following-sibling::p[1]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['DepName'] = $this->pdf->FindSingleNode('following-sibling::p[2]', $root);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrName'] = $this->pdf->FindSingleNode('following-sibling::p[3]', $root);
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (preg_match('/Classe ((?:economica|))/i', $this->pdf->FindSingleNode('following-sibling::p[4]', $root), $m)) {
                $seg['Cabin'] = $m[1];
            }
            $it['Status'] = $this->pdf->FindSingleNode('following-sibling::p[5]', $root);

            if (preg_match('/(?<Day>\d+)(?<Month>\D+)(?<Year>\d+)/', $this->pdf->FindSingleNode('.', $root), $m)) {
                $date = $m['Day'] . ' ' . $this->monthNameToEnglish($m['Month'], 'it') . ' ' . $m['Year'];
            }
            $seg['DepDate'] = strtotime($date . ' ' . array_shift($times));
            $seg['ArrDate'] = strtotime($date . ' ' . array_shift($times));
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function detectBodyAndAcceptLang(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!empty($pdfs)) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetEmailBody($html);

                    break;
                }
            }

            if (empty($this->pdf)) {
                return false;
            }

            $body = $this->pdf->Response['body'];

            foreach (self::$detectBody as $lang => $item) {
                if (is_array($item)) {
                    foreach ($item as $detect) {
                        if (stripos($body, $detect) !== false) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                }

                if (is_string($item) && stripos($body, $item) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
