<?php

namespace AwardWallet\Engine\thd\Email;

use AwardWallet\Engine\MonthTranslate;

class NewBookingRequest extends \TAccountChecker
{
    public $mailFiles = "thd/it-10156110.eml, thd/it-10159930.eml, thd/it-10232665.eml, thd/it-30995598.eml, thd/it-31009071.eml";

    public $subjects = [
        'es' => ['Su nueva la reservacion de reserva es:'],
        'en' => ['Your New Booking Request:', 'Your Exchange Request:'],
    ];
    public $reBody = 'travelerhelpdesk.com';
    public $langDetectors = [
        'es' => ['Fecha de llegada - Tiempo'],
        'en' => ['Arr. Date-Time', 'Arrival City', 'Arrival city'],
    ];

    public static $dictionary = [
        'es' => [
            'preTripNumber'        => 'Localizador de la reserva:',
            'First Name'           => 'Nombre',
            'Total Fare including' => 'Tarifas incluyen',
            //            'Departure Date' => '',
            //            'Arrival Time' => '',
            'Departure City'  => 'Ciudad de salida',
            'Dept. Date-Time' => 'Fecha de la salida - Tiempo',
        ],
        'en' => [
            'preTripNumber' => ['reservation under Confirmation', 'changes to your Booking'],
        ],
    ];

    public $lang = '';
    public $total = [];

    private $segmentTypes = [];

    public function parseHtml(&$its)
    {
        // RecordLocator
        // TripNumber
        $TripNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('preTripNumber'))}]/following::text()[normalize-space(.)][1]", null, true, '/^([-A-Z\d]{5,})\b/');

        // Passengers
        // TicketNumbers
        $Passengers = [];
        $ticketNumbers = [];
        $xpath = "//text()[{$this->eq($this->t('First Name'))}]/ancestor::tr[1]/following-sibling::tr";
        $passengerRows = $this->http->XPath->query($xpath);

        foreach ($passengerRows as $root) {
            $name1 = $this->http->FindSingleNode('./*[3]', $root);
            $name2 = $this->http->FindSingleNode('./*[4]', $root);
            $name3 = $this->http->FindSingleNode('./*[5]', $root);

            if (strcasecmp($name2, 'NA') === 0) {
                $Passengers[] = implode(' ', [$name1, $name3]);
            } else {
                $Passengers[] = implode(' ', [$name1, $name2, $name3]);
            }
            $ticketNumber = $this->http->FindSingleNode('./*[6]', $root, true, '/^\d[-\d ]{7,}\d$/');

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        // Currency
        // TotalCharge
        $totalFare = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Fare including'))}]/following::text()[normalize-space(.)][1]");
        $Currency = $totalFare ? $this->currency($totalFare) : null;
        $TotalCharge = $totalFare ? $this->amount($totalFare) : null;

        // table with 9 columns
        $xpath = "//text()[{$this->eq($this->t('Departure Date'))}]/ancestor::tr[{$this->contains($this->t('Arrival Time'))}][1]/following-sibling::tr";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->segmentTypes[] = '1';
        }

        foreach ($segments as $root) {
            $seg = [];

            $RecordLocator = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#([A-Z\d]{5,})\b#");

            // FlightNumber
            $seg['FlightNumber'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(\d{1,5})#");

            // DepCode
            // DepName
            $route = implode(' ', $this->http->FindNodes("./td[5]//text()", $root));

            if (preg_match("#^\s*([A-Z]{3})\s+(.+)#s", $route, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['DepName'] = $m[2];
            }

            // DepDate
            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root) . ' ' . $this->http->FindSingleNode("./td[4]", $root)));

            // ArrCode
            // ArrName
            $route = implode(' ', $this->http->FindNodes("./td[6]//text()", $root));

            if (preg_match("#^\s*([A-Z]{3})\s+(.+)#s", $route, $m)) {
                $seg['ArrCode'] = $m[1];
                $seg['ArrName'] = $m[2];
            }

            // ArrDate
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[8]", $root) . ' ' . $this->http->FindSingleNode("./td[7]", $root)));

            // AirlineName
            $seg['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#([A-Z][A-Z\d]|[A-Z\d][A-Z])#");

            // Operator
            $seg['Operator'] = $this->http->FindSingleNode("./td[9]/descendant::text()[normalize-space(.)][1]", $root, true, "#([A-Z][A-Z\d]|[A-Z\d][A-Z])#");

            // Cabin
            $seg['Cabin'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root);

            if (empty($RecordLocator) && $TripNumber) {
                $RecordLocator = CONFNO_UNKNOWN;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
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

                if (!empty($Passengers)) {
                    $it['Passengers'] = $Passengers;
                }

                if (!empty($ticketNumbers)) {
                    $it['TicketNumbers'] = $ticketNumbers;
                }

                if (isset($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        // table with 7 columns
        $xpath = "//text()[{$this->eq($this->t('Departure City'))}]/ancestor::tr[{$this->contains($this->t('Dept. Date-Time'))}][1]/following-sibling::tr";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->segmentTypes[] = '2';
        }

        foreach ($segments as $root) {
            $seg = [];

            $RecordLocator = '';

            // FlightNumber
            $seg['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])-(\d+)#");

            // DepCode
            // DepName
            $route = implode(' ', $this->http->FindNodes("./td[3]//text()", $root));

            if (preg_match("#^\s*([A-Z]{3})\s+(.+)#s", $route, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['DepName'] = $m[2];
            }

            // DepDate
            $seg['DepDate'] = strtotime($this->normalizeDate(implode(' ', $this->http->FindNodes("./td[4]//text()", $root))));

            // ArrCode
            // ArrName
            $route = implode(' ', $this->http->FindNodes("./td[6]//text()", $root));

            if (preg_match("#^\s*([A-Z]{3})\s+(.+)#s", $route, $m)) {
                $seg['ArrCode'] = $m[1];
                $seg['ArrName'] = $m[2];
            }

            // ArrDate
            $seg['ArrDate'] = strtotime($this->normalizeDate(implode(' ', $this->http->FindNodes("./td[5]//text()", $root))));

            // AirlineName
            $seg['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#([A-Z][A-Z\d]|[A-Z\d][A-Z])-\d+#");

            // Operator
            $seg['Operator'] = $this->http->FindSingleNode("./td[7]/descendant::text()[normalize-space(.)][1]", $root, true, "#([A-Z][A-Z\d]|[A-Z\d][A-Z])#");

            $cabinLocatorTexts = $this->http->FindNodes('./td[2]/descendant::text()[normalize-space(.)]', $root);
            $cabinLocatorText = implode("\n", $cabinLocatorTexts);

            // Cabin
            // RecordLocator
            if (preg_match('/^(.+)\s*\n+\s*([A-Z\d]{5,})[ ]*$/s', $cabinLocatorText, $m)) {
                $seg['Cabin'] = $m[1];
                $RecordLocator = $m[2];
            } else {
                $seg['Cabin'] = $cabinLocatorText;
            }

            if (empty($RecordLocator) && $TripNumber) {
                $RecordLocator = CONFNO_UNKNOWN;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
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

                if (!empty($Passengers)) {
                    $it['Passengers'] = $Passengers;
                }

                if (!empty($ticketNumbers)) {
                    $it['TicketNumbers'] = $ticketNumbers;
                }

                if (isset($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        if (count($its) == 1) {
            $its[0]['TotalCharge'] = $TotalCharge;
            $its[0]['Currency'] = $Currency;
        } else {
            $this->total['TotalCharge'] = $TotalCharge;
            $this->total['Currency'] = $Currency;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelerhelpdesk.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@travelerhelpdesk.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//travelerhelpdesk.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = true;

        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        $its = [];
        $this->parseHtml($its);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . implode($this->segmentTypes) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        if (isset($this->total['TotalCharge']) && isset($this->total['Currency'])) {
            $result['TotalCharge'] = [
                'Amount'   => $this->total['TotalCharge'],
                'Currency' => $this->total['Currency'],
            ];
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)(\w+)(\d{2}) (\d{1,2})(\d{2}[AP])$#", //03Aug17 640P
        ];
        $out = [
            "$1 $2 20$3 $4:$5M",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }
}
