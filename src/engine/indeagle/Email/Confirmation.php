<?php

namespace AwardWallet\Engine\indeagle\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "indeagle/it-10113470.eml, indeagle/it-10113473.eml, indeagle/it-297389490.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Arrive:'],
    ];

    protected static $dict = [
        'en' => [
            'Flight:'     => ['Flight:', 'Flight :'],
            'Total Fare:' => ['Total Fare:', 'Total:'],
        ],
    ];

    protected $patterns = [
        'pnr'  => '/^([A-Z\d]{5,})$/',
        'date' => '[-[:alpha:]]{3,}\s*\d{1,2}\s*,\s*\d{4}', // Friday, Oct 27, 2017
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    protected $passengers = [];
    protected $tripID = '';
    protected $PNRs = [];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Indian Eagle') !== false
            || stripos($from, '@indianeagle.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'Indian Eagle') !== false
            && (
                stripos($headers['subject'], 'Travel Confirmation') !== false
                    || stripos($headers['subject'], 'travel booking') !== false
            );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".indianeagle.com/") or contains(@href,"www.indianeagle.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@indianeagle.com") or contains(.,"@IndianEagle.com") or contains(normalize-space(),"your travel through Indian Eagle") or contains(normalize-space(),"Thank you for choosing Indian Eagle") or contains(normalize-space(),"Thank you for booking your trip with Indian Eagle") or contains(normalize-space(),"Sincerely, The Indian Eagle")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->assignLang()) {
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

    protected function parseEmail()
    {
        $passengerText = $this->http->FindSingleNode('//text()[normalize-space(.)="Passenger Name(s):"]/following::text()[normalize-space(.)][1]', null, true, '/^([^}{:]+)$/');
        $this->passengers = preg_split('/\s*,\s*/', $passengerText);

        $this->tripID = $this->http->FindSingleNode('//text()[normalize-space(.)="Trip ID:"]/following::text()[normalize-space(.)][1]', null, true, $this->patterns['pnr']);

        $pnrRows = $this->http->XPath->query('//text()[normalize-space(.)="Confirmation Code"]/ancestor::div[ ./descendant::text()[normalize-space(.)="Airline"] ][1]/following-sibling::div[ ./div[2] ]');

        foreach ($pnrRows as $pnrRow) {
            $airline = $this->http->FindSingleNode('./div[1]', $pnrRow, true, "/^(.{2,}?)\s*(?:{$this->opt($this->t('Operated by'))}|$)/");
            $pnr = $this->http->FindSingleNode('./div[2]', $pnrRow, true, $this->patterns['pnr']);

            if ($airline && $pnr) {
                $this->PNRs[$airline] = $pnr;
            }
        }

        $its = [];

        $travelSegments = $this->http->XPath->query("//*[ div[2][{$this->starts($this->t('Depart:'))}] and div[3][{$this->starts($this->t('Arrive:'))}] ]");
        $this->logger->debug('Found ' . $travelSegments->length . ' travel segments.');

        foreach ($travelSegments as $travelSegment) {
            $itFlight = $this->parseFlight($travelSegment);

            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== null) {
                if (!empty($itFlight['Passengers'][0])) {
                    if (!empty($its[$key]['Passengers'][0])) {
                        $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    } else {
                        $its[$key]['Passengers'] = $itFlight['Passengers'];
                    }
                }
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
            } else {
                $its[] = $itFlight;
            }
        }

        if (empty($its[0]['RecordLocator'])) {
            return false;
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        $result = [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'Confirmation' . ucfirst($this->lang),
        ];

        $payment = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Total Fare:')) . ']/ancestor::tr[1]/*[normalize-space(.)][last()]');

        if (preg_match('/^(?<currency>\D+?)\s*(?<amount>\d[,.\d\s]*)/', $payment, $matches)) {
            // $ 1398.72
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $result['parsedData']['TotalCharge']['Currency'] = $matches['currency'];
            $result['parsedData']['TotalCharge']['Amount'] = PriceHelper::parse($matches['amount'], $currencyCode);
        }

        return $result;
    }

    protected function parseFlight($root)
    {
        $it = [];
        $it['Kind'] = 'T';

        if (!empty($this->passengers[0])) {
            $it['Passengers'] = $this->passengers;
        }

        if ($this->tripID) {
            $it['TripNumber'] = $this->tripID;
        }

        $it['TripSegments'] = [];
        $seg = [];

        $xpathFragment1 = './div[1]/descendant::text()[' . $this->starts($this->t('Flight:')) . ']';

        $seg['AirlineName'] = $this->http->FindSingleNode($xpathFragment1 . '/preceding::text()[normalize-space(.)][1]', $root, true, '/^([^:]+)$/');

        if ($seg['AirlineName'] && !empty($this->PNRs[$seg['AirlineName']])) {
            $it['RecordLocator'] = $this->PNRs[$seg['AirlineName']];
        } elseif (count($this->PNRs) === 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('Please note that the fares are NOT guaranteed until ticketed'))}]")->length > 0
        ) {
            // it-297389490.eml
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $seg['FlightNumber'] = $this->http->FindSingleNode($xpathFragment1, $root, true, '/^[^:]+:\s*(\d+)$/');

        $operator = $this->http->FindSingleNode("div[5]/descendant::text()[{$this->starts($this->t('Operated by'))}]", $root, true, "/^{$this->opt($this->t('Operated by'))}\s+(.{2,})$/");

        if ($operator) {
            $seg['Operator'] = $operator;
        }

        /*
            Friday, Oct 27, 2017 at 12:40 PM
            John F. Kennedy International Airport (JFK) New York , NY, US
        */
        $pattern = "/"
            . "(?<date>{$this->patterns['date']})\s*{$this->opt($this->t('at'))}\s*(?<time>{$this->patterns['time']})\s*"
            . "(?<name>.{2,})?\s*\(\s*(?<code>[A-Z]{3})\s*\).*"
            . "\s*$/u"
        ;

        $departure = implode('', $this->http->FindNodes("div[2]/descendant::text()[normalize-space() and not({$this->contains($this->t('Depart:'))})]", $root));

        if (preg_match($pattern, $departure, $matches)) {
            $seg['DepDate'] = strtotime($matches['time'], strtotime($matches['date']));
            $seg['DepCode'] = $matches['code'];
        }

        $arrival = implode('', $this->http->FindNodes("div[3]/descendant::text()[normalize-space() and not({$this->contains($this->t('Arrive:'))})]", $root));

        if (preg_match($pattern, $arrival, $matches)) {
            $seg['ArrDate'] = strtotime($matches['time'], strtotime($matches['date']));
            $seg['ArrCode'] = $matches['code'];
        }

        $xpathCell4 = 'div[4]/descendant::text()[contains(normalize-space(),"Flight Duration:")]';
        $xpathCell4v2 = 'following::div[normalize-space()][1][not(contains(normalize-space(),"Depart:") or contains(normalize-space(),"Arrive:"))]/descendant::text()[contains(normalize-space(),"Flight Duration:")]';

        $seg['Duration'] = $this->http->FindSingleNode($xpathCell4 . '/following::text()[normalize-space()][1]', $root, true, '/^([\d hrmin]{3,})$/i')
            ?? $this->http->FindSingleNode($xpathCell4v2 . '/following::text()[normalize-space()][1]', $root, true, '/^([\d hrmin]{3,})$/i');
        $seg['Cabin'] = $this->http->FindSingleNode($xpathCell4 . '/following::text()[normalize-space()][2]', $root, true, '/^([^\d:]{2,})$/')
            ?? $this->http->FindSingleNode($xpathCell4v2 . '/following::text()[normalize-space()][2]', $root, true, '/^([^\d:]{2,})$/');

        $it['TripSegments'][] = $seg;

        return $it;
    }

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function recordLocatorInArray($recordLocator, $array): ?int
    {
        foreach ($array as $key => $value) {
            if (array_key_exists('Kind', $value) && $value['Kind'] === 'T') {
                if (array_key_exists('RecordLocator', $value) && $value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return null;
    }
}
