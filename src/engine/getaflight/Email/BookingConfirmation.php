<?php

namespace AwardWallet\Engine\getaflight\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "getaflight/it-10116201.eml, getaflight/it-10104446.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Your Flight Details'],
    ];
    protected static $dict = [
        'en' => [
            'Payment Authorised' => ['Payment Authorised', 'Payment Received'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'GetAFlight') !== false
            || stripos($from, '@getaflight.co.uk') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'GetAFlight') !== false
            && (
                stripos($headers['subject'], 'Confirmation') !== false
                    || stripos($headers['subject'], 'Acknowledgement') !== false
            );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $from = $parser->getHeader('from');
        $subject = $parser->getHeader('subject');

        $condition1 = $this->http->XPath->query('//a[contains(@href,"getaflight.co.uk/")]')->length === 0;
        $condition2 = self::detectEmailFromProvider($from) || self::detectEmailByHeaders(['from' => $from, 'subject' => $subject]);

        if ($condition1 && $condition2 === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        return $this->parseEmail();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail()
    {
        $patterns = [
            'pnr'      => '/^([A-Z\d]{5,})$/',
            'code'     => '/\(([A-Z]{3})\)$/',
            'terminal' => '/^[^:]+:\s*([^:]+)$/',
        ];

        $it = [];
        $it['Kind'] = 'T';

        $bookingReference = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Booking Reference:")]', null, true, '/:\s*([A-Z\d]{5,})$/');

        if ($bookingReference) {
            $it['TripNumber'] = $bookingReference;
        }

        $recordLocator = $this->http->FindSingleNode('//text()[normalize-space(.)="Airline Booking Reference:"]/following::text()[normalize-space(.)][1]', null, true, $patterns['pnr']);

        if (!$recordLocator) {
            $recordLocator = $this->http->FindSingleNode('//text()[normalize-space(.)="Main Booking Reference:"]/following::text()[normalize-space(.)][1]', null, true, $patterns['pnr']);
        }

        if (!$recordLocator) {
            $recordLocator = $bookingReference;
        }

        if ($recordLocator) {
            $it['RecordLocator'] = $recordLocator;
        }

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[ ./td[position()<last()][contains(.,"Departs:")] and ./td[last()][contains(.,"Arrives:")] ]');

        foreach ($segments as $segment) {
            $seg = [];

            $flightTexts = $this->http->FindNodes('./td[1]/descendant::text()[normalize-space(.)]', $segment, '/^([A-Z]{2}\s*\d+)$/');
            $flightValues = array_values(array_filter($flightTexts));

            if (!empty($flightValues[0])) {
                if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flightValues[0], $matches)) {
                    $seg['AirlineName'] = $matches[1];
                    $seg['FlightNumber'] = $matches[2];
                }
            }

            $xpathFragment1 = './td[contains(.,"Departs:")][1]';

            $seg['DepCode'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[contains(.,")") and contains(.,"(")]', $segment, true, $patterns['code']);

            $terminalDep = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][position()>1][contains(.,"Terminal")][1]', $segment, true, $patterns['terminal']);

            if ($terminalDep) {
                $seg['DepartureTerminal'] = $terminalDep;
            }

            $dateDep = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][position()>1][contains(.,"Departs:")][1]/following::text()[normalize-space(.)][1]', $segment);

            if ($dateDep) {
                $seg['DepDate'] = strtotime($dateDep);
            }

            $xpathFragment2 = './td[contains(.,"Arrives:")][1]';

            $seg['ArrCode'] = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[contains(.,")") and contains(.,"(")]', $segment, true, $patterns['code']);

            $terminalArr = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[normalize-space(.)][position()>1][contains(.,"Terminal")][1]', $segment, true, $patterns['terminal']);

            if ($terminalArr) {
                $seg['ArrivalTerminal'] = $terminalArr;
            }

            $dateArr = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[normalize-space(.)][position()>1][contains(.,"Arrives:")][1]/following::text()[normalize-space(.)][1]', $segment);

            if ($dateArr) {
                $seg['ArrDate'] = strtotime($dateArr);
            }

            $seg['Cabin'] = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)="Class:"]/following::text()[normalize-space(.)][1]', $segment, true, '/^([^:]+)$/');

            $it['TripSegments'][] = $seg;
        }

        $passengers = [];
        $passengerRows = $this->http->XPath->query('//tr[starts-with(normalize-space(.),"Adult") and ./*[2] and not(.//tr)]');

        foreach ($passengerRows as $passengerRow) {
            if ($this->http->FindSingleNode('./*[1]', $passengerRow, true, '/Adult\s+\d{1,3}\s*:/i')) {
                $passengers[] = $this->http->FindSingleNode('./*[2]', $passengerRow);
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        $payment = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Total Cost is")]/ancestor::tr[1]/*[normalize-space(.)][last()]');
        // £193.27
        if (preg_match('/^(\D+)\s*(\d[,.\d\s]*)/', $payment, $matches)) {
            $it['Currency'] = trim($matches[1]);
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
        }

        $result = [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'BookingConfirmation_' . $this->lang,
        ];

        $paymentAuthorised = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Payment Authorised')) . ']/ancestor::tr[1]/*[normalize-space(.)][last()]');
        // £416.13
        if (preg_match('/^(\D+)\s*(\d[,.\d\s]*)/', $paymentAuthorised, $matches)) {
            $result['parsedData']['TotalCharge']['Currency'] = trim($matches[1]);
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[2]);
        }

        return $result;
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
