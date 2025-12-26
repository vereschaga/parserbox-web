<?php

namespace AwardWallet\Engine\swissair\Email;

use AwardWallet\Engine\MonthTranslate;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "swissair/it-10304022.eml";

    private $detects = [
        'en' => 'Please kindly note that your itinerary has been modified due to a schedule change',
        'fr' => 'Veuillez noter que votre itinéraire a été modifié en raison d\'un changement d\'horaire',
    ];

    private $provider = 'swiss';

    private $from = '/[@\.]swiss\.com/';

    private $lang = 'en';

    private static $dict = [
        'en' => [],
        'fr' => [
            'New flight schedule'      => 'Nouvel horaire du vol',
            'Original flight schedule' => 'Horaire original du vol',
            'Flight number'            => 'Numéro de vol',
            'Booking reference'        => 'Référence de réservation',
            'Departure date'           => 'Date de départ',
            'Departure time'           => 'Heure de départ',
            'Arrival date'             => "Date d'arrivée",
            'Arrival time'             => "Heure d'arrivée",
            //            'Your SWISS flight' => '',
            'Passenger' => 'Passenger',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "AirTicket" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->provider)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail()
    {
        $xpath = "//*[contains(text(), '{$this->t('New flight schedule')}') or contains(text(), '{$this->t('Original flight schedule')}')]/ancestor::table[contains(., '{$this->t('Flight number')}')][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info('Segments not found by: ' . $xpath);

            return false;
        }

        if ($this->http->XPath->query($xpath . "/descendant::table")->length == 3) {
            $this->logger->info('parsing by parseEmail2');

            return $this->parseEmail2();
        }
        $this->logger->info('parsing by main');

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), '{$this->t('Booking number')}')]", null, true, '/:\s+(\w+)/');
        $psng = $this->http->FindSingleNode("//*[contains(text(), '{$this->t('Passenger')}')]/following::*[normalize-space(.)!=''][1]");

        if (preg_match('/(\w+)\/(\w+)(?:mr|miss|ms)/i', $psng, $m)) {
            $it['Passengers'][] = $m[1] . ' ' . $m[2];
        }

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $flight = $this->http->FindSingleNode("descendant::tr[contains(., '{$this->t('Flight number')}')]/td[last()]", $segment);

            if (preg_match('/([A-Z]{1,2})\s?(\d+)/', $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $dateDep = $this->http->FindSingleNode("descendant::tr[contains(., '{$this->t('Departure date')}')]/td[last()]", $segment);
            $depTime = $this->http->FindSingleNode("descendant::tr[contains(., '{$this->t('Departure time')}')]/td[last()]", $segment);

            $dateArr = $this->http->FindSingleNode("descendant::tr[contains(., '{$this->t('Arrival date')}')]/td[last()]", $segment);

            if (empty($dateArr)) {
                $dateArr = $dateDep;
            }
            $arrTime = $this->http->FindSingleNode("descendant::tr[contains(., '{$this->t('Arrival time')}')]/td[last()]", $segment);

            if (!empty($dateDep) && !empty($depTime)) {
                $seg['DepDate'] = $this->normalizeDate($dateDep . ', ' . $depTime);
            }

            if (!empty($dateArr) && !empty($arrTime)) {
                $seg['ArrDate'] = $this->normalizeDate($dateArr . ', ' . $arrTime);
            }

            $airCodes = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '{$this->t('Your SWISS flight')}')]/ancestor::td[1]");

            if (preg_match('/from\s+(?<DepName>.+)\s+\((?<DepCode>[A-Z]{3})\)\s+to\s+(?<ArrName>.+)\s+\((?<ArrCode>[A-Z]{3})\)/iu', $airCodes, $m)) {
                $seg['DepName'] = $m['DepName'];
                $seg['DepCode'] = $m['DepCode'];
                $seg['ArrName'] = $m['ArrName'];
                $seg['ArrCode'] = $m['ArrCode'];
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseEmail2()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Booking number') or contains(text(), '{$this->t('Booking reference')}')]", null, true, '/:\s+(\w+)/');

        $psng = $this->http->FindSingleNode("//*[contains(text(), '{$this->t('Passenger')}')]/following::*[normalize-space(.)!=''][1]");
        $arr = preg_split('/(?:mr|miss|ms)\.?\s+/i', $psng);
        $it['Passengers'] = array_values(array_filter(array_map("trim", $arr)));

        $xpath = "//*[contains(text(), '{$this->t('New flight schedule')}') or contains(text(), '{$this->t('Original flight schedule')}')]/ancestor::table[contains(.,'{$this->t('Flight number')}')][1]/descendant::table[last()]/descendant::tr[contains(.,'{$this->t('New flight schedule')}')]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info('Segments not found by: ' . $xpath);

            return false;
        }

        $xpathForOrderNum = '//*[contains(text(), "' . $this->t('New flight schedule') . '") or contains(text(), "' . $this->t('Original flight schedule') . '")]/ancestor::table[contains(., "' . $this->t('Flight number') . '")][1]/descendant::table[1]';

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $num = $this->http->XPath->query($xpathForOrderNum . '/descendant::tr[contains(., "' . $this->t('Flight number') . '")]/preceding-sibling::tr')->length;

            if ($num > 0) {
                $flight = $this->http->FindSingleNode("following-sibling::tr[{$num}]", $segment);

                if (preg_match('/([A-Z]{1,2})\s?(\d+)/', $flight, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
            }

            $num = $this->http->XPath->query($xpathForOrderNum . '/descendant::tr[contains(., "' . $this->t('Departure date') . '")]/preceding-sibling::tr')->length;

            if ($num > 0) {
                $dateDep = $this->http->FindSingleNode("following-sibling::tr[{$num}]", $segment);
            }
            $num = $this->http->XPath->query($xpathForOrderNum . '/descendant::tr[contains(., "' . $this->t('Departure time') . '")]/preceding-sibling::tr')->length;

            if ($num > 0) {
                $depTime = $this->http->FindSingleNode("following-sibling::tr[{$num}]", $segment);
            }

            $num = $this->http->XPath->query($xpathForOrderNum . '/descendant::tr[contains(., "' . $this->t('Arrival date') . '")]/preceding-sibling::tr')->length;

            if ($num > 0) {
                $dateArr = $this->http->FindSingleNode("following-sibling::tr[{$num}]", $segment);

                if (empty($dateArr) && isset($dateDep)) {
                    $dateArr = $dateDep;
                }
            }

            $num = $this->http->XPath->query($xpathForOrderNum . '/descendant::tr[contains(., "' . $this->t('Arrival time') . '")]/preceding-sibling::tr')->length;

            if ($num > 0) {
                $arrTime = $this->http->FindSingleNode("following-sibling::tr[{$num}]", $segment);
            }

            if (!empty($dateDep) && !empty($depTime)) {
                $seg['DepDate'] = $this->normalizeDate($dateDep . ', ' . $depTime);
            }

            if (!empty($dateArr) && !empty($arrTime)) {
                $seg['ArrDate'] = $this->normalizeDate($dateArr . ', ' . $arrTime);
            }

            $airCodes = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "' . $this->t('Your SWISS flight') . '")]/ancestor::td[1]');

            if (preg_match('/from\s+(?<DepName>.+)\s+\((?<DepCode>[A-Z]{3})\)\s+to\s+(?<ArrName>.+)\s+\((?<ArrCode>[A-Z]{3})\)/iu', $airCodes, $m)) {
                $seg['DepName'] = $m['DepName'];
                $seg['DepCode'] = $m['DepCode'];
                $seg['ArrName'] = $m['ArrName'];
                $seg['ArrCode'] = $m['ArrCode'];
            } else {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate(string $s)
    {
        if ($this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $s, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang))) {
                $s = str_replace($m[1], $en, $s);
            }
        }

        return strtotime($s);
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function assignLang()
    {
        foreach ($this->detects as $lang => $re) {
            if (is_string($re) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                $this->lang = trim($lang, '1234567890');

                return true;
            } elseif (is_array($re) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re[0] . '")]')->length > 0 && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re[1] . '")]')->length > 0) {
                $this->lang = trim($lang, '1234567890');

                return true;
            }
        }

        return false;
    }
}
