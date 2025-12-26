<?php

namespace AwardWallet\Engine\thd\Email;

// TODO: merge with parser thd/Itinerary (in favor of thd/ItineraryFor)

class ItineraryFor extends \TAccountChecker
{
    public $mailFiles = "thd/it-10204078.eml";

    public $subjects = [
        'smartfares'     => ['Smartfares', ' – Itinerary for'],
        'cheapflightnow' => ['Cheapflightnow', ' – Itinerary for'],
        'cheapfaresnow'  => ['CheapFaresNow', ' – Itinerary for'],
        'lcairlines'     => ['Lowcostairlines', ' – Itinerary for'],
    ];
    public $subjectsDefault = [
        'en' => [' – Itinerary for', ' - Itinerary for'],
    ];

    public $langDetectors = [
        'en' => ['Flight Reservation Code:', 'Flight Confirmation Number:'],
    ];

    public static $dictionary = [
        // if you add languages, add this lang to weekTranslate
        'en' => [
            'Flight Reservation Code:' => ['Flight Reservation Code:', 'Flight Confirmation Number:'],
            'Flight:'                  => ['Flight:', 'Flight'],
        ],
    ];

    public $lang = "en";
    public $codeProvider = '';
    private $bodies = [
        'smartfares' => [
            '//a[contains(@href, "smartfares.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing Smartfares")]',
            'Thank you for choosing Smartfares',
        ],
        'cheapflightnow' => [
            '//a[contains(@href, "cheapflightnow.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing Cheapflightnow")]',
            'Thank you for choosing Cheapflightnow',
        ],
        'cheapfaresnow' => [
            '//a[contains(@href, "cheapfaresnow.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing CheapFaresNow")]',
            'Thank you for choosing CheapFaresNow',
        ],
        'lcairlines' => [
            '//a[contains(@href, "lowcostairlines.com")]',
            '//text()[contains(normalize-space(), "Thank you for choosing Lowcostairlines")]',
            'Thank you for choosing Lowcostairlines',
        ],
    ];

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $reservationCode = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Flight Reservation Code:'))}]", null, true, "/{$this->opt($this->t('Flight Reservation Code:'))}\s*([A-Z\d]{5,})$/");

        if (!$reservationCode) {
            $reservationCode = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Flight Reservation Code:'))}]/following::text()[normalize-space(.)][1]", null, true, "/^([A-Z\d]{5,})$/");
        }

        if ($reservationCode) {
            $it['RecordLocator'] = $reservationCode;
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Flight Reservation Code:'))}]")->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // Passengers
        $passengers = $this->http->FindNodes("//div[@id='pax']/descendant::text()[normalize-space(.)]", null, "/^(?:{$this->opt($this->t('Passangers:'))}\s*)?({$patterns['travellerName']})$/u");
        $passengers = array_filter($passengers);

        if (count($passengers) === 0) {
            $passengers = $this->http->FindNodes("//img[contains(@src,'/images/Airlines35/')]/ancestor::table[1]/preceding::div[normalize-space(.)][1]/descendant::text()[normalize-space(.)]", null, "/^(?:{$this->opt($this->t('Passangers:'))}\s*)?({$patterns['travellerName']})$/u");
            $passengers = array_filter($passengers);
        }

        if (count($passengers)) {
            $it['Passengers'] = $passengers;
        }

        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Total Trip Cost:"));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Ticket Price") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total Trip Cost:"));

        $xpath = "//img[contains(@src,'/images/Airlines35/')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[not(" . $this->contains("DEPART:") . ")][1]/descendant::text()[normalize-space(.)][1]", $root));

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Flight:'))}]", $root, true, "/{$this->opt($this->t('Flight:'))}\s*(\d+)/");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\d+:\d+\s+[AP]M\s+(.+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(\d+:\d+\s+[AP]M)#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#\d+:\d+\s+[AP]M\s+(.+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#(\d+:\d+\s+[AP]M)#"), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space() and not(contains(normalize-space(), 'Flight'))][1]", $root);

            // Operator
            $itsegment['Operator'] = $this->nextText("Operated By", $root);

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space()][last()]", $root);

            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Stops:") . "]", $root, true, "#Stops:\s+(.+)#");

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function getProvider(\PlancakeEmailParser $parser)
    {
        if (!empty($this->codeProvider)) {
            return $this->codeProvider;
        }

        foreach ($this->bodies as $code => $criteria) {
            foreach ($criteria as $search) {
                if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                    continue 2;
                }
            }

            return $code;
        }

        return null;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelerhelpdesk.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers['subject'])) {
            foreach ($this->subjects as $code => $arr) {
                if (stripos($headers['subject'], $arr[0]) !== false && stripos($headers['subject'], $arr[1]) !== false) {
                    $this->codeProvider = $code;

                    return true;
                }
            }
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjectsDefault as $phrases) {
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
        $body = $parser->getHTMLBody();

        $findProvider = false;

        foreach ($this->bodies as $code => $criteria) {
            foreach ($criteria as $search) {
                if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                    $findProvider = true;

                    break 2;
                }
            }
        }

        if ($findProvider === false && strpos($body, 'travelerhelpdesk.com') === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = true;
        $itineraries = [];

//        if ( !$this->assignLang() ) {
//            $this->logger->notice("Can't determine a language!");
//            return $email;
//        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['thd', 'smartfares', 'cheapflightnow', 'cheapfaresnow', 'lcairlines'];
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
            "#^([^\s\d]+) (\d+)-([^\s\d]+)$#", //Sunday 01-November
        ];
        $out = [
            "$1, $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);

                return strtotime($str);
            }
        } else {
            if (preg_match("#\s*([^,]+),\s+(\d+)\s+([^\d\s]+)\s*$#", $str, $m)) {
                if (!($en = \AwardWallet\Engine\MonthTranslate::translate($m[3], $this->lang))) {
                    $en = $m[3];
                }
                $dayOfWeekInt = \AwardWallet\Engine\WeekTranslate::number1(trim($m[1]), $this->lang);
                $str = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($m[2] . ' ' . $en, $dayOfWeekInt);

                return $str;
            }
        }

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
            '₹'=> 'INR',
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
