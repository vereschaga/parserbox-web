<?php

namespace AwardWallet\Engine\wagonlit\Email;

use AwardWallet\Engine\MonthTranslate;

class AlternativeOffers extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-11700717.eml, wagonlit/it-12279569.eml";

    protected $langDetectors = [
        'en' => ['Air Offer:', 'Air Offer :'],
        'da' => ['Rejseforslag'],
    ];
    protected $lang = '';
    protected static $dict = [
        "en" => [
            "Flight duration" => ["Flight duration", "Flight Duration"],
        ],
        'da' => [
            "Trip locator:"   => "Reservationsnr.:",
            "Date:"           => "Dato:",
            "Traveler"        => "Rejsende",
            "Air Offer"       => "Rejseforslag",
            "Flight duration" => "Rejsetid",
            "Price"           => "Pris",
        ],
    ];

    protected $tripLocator = '';
    protected $tripDate = 0;
    protected $traveler = '';
    protected $patterns = [
        'date' => '\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b', // 27/03/2018(en) or 27-03-2018(da)
        'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 15:30
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'CWT Service Center') !== false
            || stripos($from, '@contactcwt.com') !== false
            || stripos($from, '@carlsonwagonlit.com') !== false
            || stripos($from, '@reservation.carlsonwagonlit.dk') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'TRAVEL PROPOSAL') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"CWT Service Center") or contains(normalize-space(.),"CWT Travel Team") or contains(normalize-space(.),"contact CWT") or contains(.,"www.carlsonwagonlit.dk") or contains(.,"@contactcwt.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.mycwt.com") or contains(@href,"@contactcwt.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'AlternativeOffers' . ucfirst($this->lang),
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

    protected function parseEmail()
    {
        // go to parse by TripItineraryFor.php
        if ($this->http->XPath->query("//img[contains(@src, '/default/picto_')]/ancestor::tr[2][not({$this->contains($this->t('Traveler'))})]")->length > 0) {
            return null;
        }
        $its = [];

        // RecordLocator
        $this->tripLocator = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Trip locator:"))}]/following::text()[normalize-space(.)!=''][1]", null, true, '/^([A-Z\d]{5,})$/');

        // ReservationDate
        $tripDate = $this->http->FindSingleNode("//tr[({$this->contains($this->t("Trip locator:"))}) and not(.//tr)]/descendant::text()[{$this->eq($this->t("Date:"))}]/following::text()[normalize-space(.)!=''][1]");

        if ($tripDate) {
            $this->tripDate = strtotime($tripDate);
        }

        // Passengers
        $this->traveler = $this->http->FindSingleNode("//td[({$this->starts($this->t("Traveler"))}) and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]", null, true, '/^(\D{2,})$/');

        $offers = $this->http->XPath->query('//tr[ not(.//tr) and ./descendant::text()[string-length(normalize-space(.))>1][2][starts-with(normalize-space(.),":")] ]');

        foreach ($offers as $offer) {
            $offerTitle = $this->http->FindSingleNode('.', $offer);

            if (stripos($offerTitle, $this->t('Air Offer')) === 0) {
                $itFlight = $this->parseFlight($offer);

                if ($itFlight === false) {
                    continue;
                }
                $its[] = $itFlight;
            }
        }

        return $its;
    }

    protected function parseFlight($root)
    {
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        if ($this->tripLocator) {
            $it['TripNumber'] = $this->tripLocator;
        }
        $it['Status'] = 'not booked';
        // ReservationDate
        if ($this->tripDate) {
            $it['ReservationDate'] = $this->tripDate;
        }

        // Passengers
        if ($this->traveler) {
            $it['Passengers'] = [$this->traveler];
        }

        // TotalCharge
        // Currency
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(./following::table[normalize-space(.)!=''][1]/descendant::text()[{$this->starts($this->t('Price'))}])[1]/ancestor::td[1]",
            $root));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $tableTexts = $this->http->FindNodes("./following::table[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!='']", $root);
        $tableText = implode("\n", $tableTexts);

        // TripSegments
        $it['TripSegments'] = [];

        $pattern = '/'
            . '^(?<date>' . $this->patterns['date'] . ')[^\n]*$'
            . '.*?'
            . '\s+^(?<airline>[A-Z\d]{2})\s*(?<flightNumber>\d+)$' // SK984
            . '.*?'
            . '(?:\s+^(?<cabin>[^\n]{3,}?)\s*\((?<bookingClass>[A-Z]{1,2})\)$)?' // Economy/Coach (E)
            . '\s+^(?<codeDep>[A-Z]{3})(?:\s*-\s*(?<nameDep>[^\n]{2,}))?$' // NRT - Tokyo Narita
            . '\s+^(?<codeArr>[A-Z]{3})(?:\s*-\s*(?<nameArr>[^\n]{2,}))?$'
            . '\s+^(?<timeDep>' . $this->patterns['time'] . ')$'
            . '\s+^(?<timeArr>' . $this->patterns['time'] . ')(?:\s*\([+]\s*(?<plusDays>\d{1,3})\))?$' // 03:50 (+1)
            . '(\s+^' . $this->opt($this->t('Flight duration')) . '\s*:\s*(?<duration>\d[^\n]*)$)?' // Flight duration: 3:15
            . '/ms';
        preg_match_all($pattern, $tableText, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $matches) {
            $seg = [];

            // AirlineName
            // FlightNumber
            $seg['AirlineName'] = $matches['airline'];
            $seg['FlightNumber'] = $matches['flightNumber'];

            // Cabin
            if (!empty($matches['cabin'])) {
                $seg['Cabin'] = $matches['cabin'];
            }

            // BookingClass
            if (!empty($matches['bookingClass'])) {
                $seg['BookingClass'] = $matches['bookingClass'];
            }

            // DepCode
            $seg['DepCode'] = $matches['codeDep'];

            // DepName
            if (!empty($matches['nameDep'])) {
                $seg['DepName'] = $matches['nameDep'];
            }

            // ArrCode
            $seg['ArrCode'] = $matches['codeArr'];

            // ArrName
            if (!empty($matches['nameArr'])) {
                $seg['ArrName'] = $matches['nameArr'];
            }

            // DepDate
            // ArrDate
            if ($date = $this->normalizeDate($matches['date'])) {
                $seg['DepDate'] = strtotime($date . ', ' . $matches['timeDep']);
                $seg['ArrDate'] = strtotime($date . ', ' . $matches['timeArr']);

                if (!empty($matches['plusDays'])) {
                    $seg['ArrDate'] = strtotime("+{$matches['plusDays']} days", $seg['ArrDate']);
                }
            }

            // Duration
            if (!empty($matches['duration'])) {
                $seg['Duration'] = $matches['duration'];
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    protected function normalizeDate($string = '')
    {
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $string, $matches)) { // 27/03/2018 or 27-03-2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
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
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);	// 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
