<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Engine\MonthTranslate;

class SharingTravelPlans extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-36044752.eml, wagonlit/it-44626022.eml, wagonlit/it-8024934.eml"; // +1 bcdtravel(html)[es]

    public $reSubject = [
        "es"=> "comparte sus planes de viaje",
        "en"=> "is sharing travel plans for",
    ];

    public $langDetectors = [
        'es' => ['Llegada:', 'Salida:'],
        'en' => ['Arrival:', 'Drop-off:', 'Check-out:'],
    ];

    public static $dictionary = [
        'es' => [
            'subjectPostfix'                => 'comparte sus planes de viaje a Viaje',
            'This email was sent to you by' => 'Este correo electrónico es de',
            // FLIGHTS
            //            'Confirmation Code:' => '',
            'Flight'     => 'Vuelo',
            'to'         => 'a',
            'Departure:' => 'Salida:',
            'Arrival:'   => 'Llegada:',
            // CARS
            //            'Pick-up:' => '',
            //            'Drop-off:' => '',
            // HOTELS
            'Phone:'     => 'Teléfono:',
            'Check-in:'  => 'Entrada:',
            'Check-out:' => 'Salida:',
        ],
        'en' => [
            'subjectPostfix' => 'is sharing travel plans for Trip',
        ],
    ];

    public $lang = '';
    private $pax = '';

    public function parseHtml(&$itineraries)
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        if (empty($this->pax)) {
            $this->pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to you by'))}]",
                null, true, "#{$this->opt($this->t('This email was sent to you by'))}\s+(.+?)\s*(?:\(|$)#");
        }

        /////////////////
        //// FLIGHTS ////
        /////////////////

        $xpath = "//text()[{$this->eq($this->t('Departure:'))}]/ancestor::tr[1][following-sibling::tr[$xpathNoEmpty][1][{$this->starts($this->t('Arrival:'))}]]/..";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $key => $root) {
            if ($key === 0) {
                $it = [];
                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Code:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

                if (empty($it['RecordLocator'])) {
                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                }

                if (!empty($this->pax)) {
                    $it['Passengers'] = [$this->pax];
                }
            }

            $itsegment = [];

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./tr[1]', $root);

            if (preg_match("/^{$this->opt($this->t('Flight'))}\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)/", $flight, $m)) {
                $itsegment['AirlineName'] = $m['airline'];
                $itsegment['FlightNumber'] = $m['flightNumber'];
            }

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]", $root, true, "#,\s+(.*?)\s+{$this->opt($this->t('to'))}\s+#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[1]", $root, true, "#\s+{$this->opt($this->t('to'))}\s+(.+)#");

            // ATL, Thursday, May 2 2019, 5:49 PM
            $patterns['codeDate'] = '/^(?<code>[A-Z]{3})[ ]*,[ ]*(?<date>.{6,})$/';

            // DepCode
            // DepDate
            $departure = $this->nextText($this->t('Departure:'), $root);

            if (preg_match($patterns['codeDate'], $departure, $m)) {
                $itsegment['DepCode'] = $m['code'];
                $itsegment['DepDate'] = strtotime($this->normalizeDate($m['date']));
            } elseif ($departure !== null) {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                $itsegment['DepDate'] = strtotime($this->normalizeDate($departure));
            }

            // ArrCode
            // ArrDate
            $arrival = $this->nextText($this->t('Arrival:'), $root);

            if (preg_match($patterns['codeDate'], $arrival, $m)) {
                $itsegment['ArrCode'] = $m['code'];
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($m['date']));
            } elseif ($arrival !== null) {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($arrival));
            }

            $it['TripSegments'][] = $itsegment;

            if ($key + 1 === $segments->length) {
                $itineraries[] = $it;
            }
        }

        //////////////
        //// CARS ////
        //////////////

        $xpath = "//text()[{$this->eq($this->t('Pick-up:'))}]/ancestor::tr[1][following-sibling::tr[$xpathNoEmpty][1][{$this->starts($this->t('Drop-off:'))}]]/..";
        $cars = $this->http->XPath->query($xpath);

        foreach ($cars as $root) {
            $it = [];
            $it['Kind'] = 'L';

            if (!empty($this->pax)) {
                $it['RenterName'] = $this->pax;
            }

            $it['RentalCompany'] = $this->http->FindSingleNode("tr[{$this->contains($this->t('Pick-up:'))}]/preceding-sibling::tr[$xpathNoEmpty][1]", $root);

            $patterns['dateLoc'] = "#^(.{6,})\s+-\s+(.{3,})$#"; // Friday, Sep 13 2019 12:00 - YYZ Toronto

            $pickUp = $this->nextText($this->t('Pick-up:'), $root);

            if (preg_match($patterns['dateLoc'], $pickUp, $m)) {
                $it['PickupDatetime'] = strtotime($this->normalizeDate($m[1]));
                $it['PickupLocation'] = $m[2];
            }

            $dropOff = $this->nextText($this->t('Drop-off:'), $root);

            if (preg_match($patterns['dateLoc'], $dropOff, $m)) {
                $it['DropoffDatetime'] = strtotime($this->normalizeDate($m[1]));
                $it['DropoffLocation'] = $m[2];
            }

            if (!empty($it['PickupDatetime']) && !empty($it['DropoffDatetime'])) {
                $it['Number'] = CONFNO_UNKNOWN;
            }

            $itineraries[] = $it;
        }

        ////////////////
        //// HOTELS ////
        ////////////////

        $xpath = "//text()[{$this->eq($this->t('Check-in:'))}]/ancestor::tr[1][following-sibling::tr[$xpathNoEmpty][1][{$this->starts($this->t('Check-out:'))}]]/..";
        $hotels = $this->http->XPath->query($xpath);

        foreach ($hotels as $root) {
            $it = [];
            $it['Kind'] = 'R';

            if (!empty($this->pax)) {
                $it['GuestNames'] = [$this->pax];
            }

            $hotel = $this->http->FindSingleNode("tr[{$this->contains($this->t('Phone:'))}]/preceding-sibling::tr[$xpathNoEmpty][1]", $root);

            if (!empty($hotel)
                && count($hotelParts = preg_split('/\s*,+\s+/', $hotel)) === 2
            ) {
                // GUYANA MARRIOTT GEORGETOWN , BLOCK ALPHA BATTERY ROAD GEORGETOWN GY 00000
                $it['HotelName'] = $hotelParts[0];
                $it['Address'] = $hotelParts[1];
            }

            $it['Phone'] = $this->nextText($this->t('Phone:'), $root);

            $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t('Check-in:'), $root)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t('Check-out:'), $root)));

            if (!empty($it['CheckInDate']) && !empty($it['CheckOutDate'])) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }

            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'CWT To Go') !== false
            || stripos($from, '@cwttogo.com') !== false
            || stripos($from, '@carlsonwagonlit.com') !== false
            || stripos($from, '@mycwt.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) === false
            && $this->http->XPath->query("//node()[{$this->contains([
                "I'm using CWT To Go to organize", "I'm using myCWT to organize", 'CWT To Go Available for',
                'and want to share my itinerary with you.', 'myCWT Available for', 'myCWT is available for',
                'support@cwttogo.com', 'support@myCWT.com', ])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $this->http->setEmailBody($parser->getHTMLBody());

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }
        $this->pax = $this->http->FindPreg("#(?:.+:|)?\s*\b([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])\s+{$this->opt($this->t('subjectPostfix'))}#u", false, $parser->getSubject());

        $itineraries = [];
        $this->parseHtml($itineraries);
        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

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
        //		$year = date("Y", $this->date);
        $in = [
            // Wednesday, 18 Oct 2017, 23:20    |    Wednesday, 18 Oct 2017
            '/^[-[:alpha:]]{2,},\s+(\d{1,2}\s+[[:alpha:]]{3,}\s+\d{4}(?:,\s+\d{1,2}:\d{2})?)$/u',
        ];
        $out = [
            '$1',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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
}
