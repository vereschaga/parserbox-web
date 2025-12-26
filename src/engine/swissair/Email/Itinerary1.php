<?php

namespace AwardWallet\Engine\swissair\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "swissair/it-5904133.eml";

    private $dateRegex = '/(?<Day>\d{1,2})\.(?<Month>\d{2})\.(?<Year>\d{4})\s*(?<DepTime>\d{2}[:\.]?\d{2})\s+\w+\s*-\s*(?<ArrTime>\d{1,2}[:\.]?\d{2})/';

    private $lang = '';

    private static $detectBody = [
        'en' => 'Your booking reference is',
        'it' => 'La Sua referenza di prenotazione',
    ];

    private $provider = 'swiss';

    private $dict = [
        'it' => [
            'booking reference is' => 'referenza di prenotazione è',
            'Flight information'   => 'Informazioni sul volo',
            'Operated by'          => 'Operato da',
            'Selected services'    => 'Servizi scelti',
            'Grand total'          => 'Totale',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);

        $its = $this->parseEmail();

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && in_array($headers["from"], ["noreply@swiss.com", "noreply-www@services.swiss.com"]))
        || stripos($headers['subject'], 'Your SWISS flight') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'swiss.com') !== false;
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
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindPreg('/' . $this->t('booking reference is') . '\:\s*(\w+)/');
        $itineraries['Passengers'] = $this->http->FindSingleNode("//text()[contains(., '" . $this->t('Selected services') . "')]/ancestor::tr[1]/following-sibling::tr[1]");
        $itineraries['TotalCharge'] = $this->normalizeFloat($this->http->FindSingleNode("(//text()[contains(normalize-space(.), '" . $this->t('Grand total') . "')]/ancestor::tr[1]/descendant::td[last()])[last()]"));
        $itineraries['Currency'] = $this->http->FindPreg('/(?:Price in|Prezzo in)\s+(\w+)/');
        $itineraries['Tax'] = $this->normalizeFloat($this->http->FindSingleNode('//div/table//tr[4]/td[1]/table//tr[4]/td/table//tr[4]/td/table//tr[3]/td[2]/p/span'));
        $itineraries['BaseFare'] = $this->normalizeFloat($this->http->FindSingleNode('//div/table//tr[4]/td[1]/table//tr[4]/td/table//tr[4]/td/table//tr[1]/td[3]/p/span'));

        $xpath = "//text()[contains(., '" . $this->t('Flight information') . "')]/ancestor::tr[1]/following-sibling::tr[contains(., '" . $this->t('Operated by') . "')]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info('Segments NOT found by: ' . $xpath);

            return false;
        }
        $this->logger->info('Segments found by:' . $xpath);

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $infoFlight = $this->http->FindSingleNode('descendant::tr[1]', $segment);

            if (preg_match('/(.+)\s+\(([A-Z]{3})\)\s*-\s*(.+)\s+\(([A-Z]{3})\)/', $infoFlight, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['ArrName'] = $m[3];
                $seg['ArrCode'] = $m[4];
            }
            $dates = $this->http->FindSingleNode('descendant::tr[2]', $segment);

            if (preg_match($this->dateRegex, $dates, $m)) {
                $seg['DepDate'] = strtotime($m['Month'] . '/' . $m['Day'] . '/' . $m['Year'] . ', ' . $this->replace($m['DepTime']));
                $seg['ArrDate'] = strtotime($m['Month'] . '/' . $m['Day'] . '/' . $m['Year'] . ', ' . $this->replace($m['ArrTime']));
            }
            $flight = $this->http->FindSingleNode('descendant::tr[3]', $segment);

            if (preg_match('/([A-Z]{2})\s+(\d+)\s*((?:Economy))\s*\D\s*(\D)/u', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Cabin'] = $m[3];
                $seg['BookingClass'] = $m[4];
            }

            $itineraries['TripSegments'][] = $seg;
        }

        return [$itineraries];
    }

    private function replace($str)
    {
        return is_string($str) ? str_replace(['.'], [':'], $str) : null;
    }

    private function t($str)
    {
        if (!isset($this->dict[$this->lang]) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }

    private function normalizeFloat($float)
    {
        return floatval(preg_replace("/'/", '', $float));
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detectBody as $lang => $detect) {
            if (stripos($body, $detect) !== false && stripos($body, $this->provider) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
