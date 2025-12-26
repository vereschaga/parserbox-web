<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Engine\MonthTranslate;

class It2734403 extends \TAccountChecker
{
    public $mailFiles = "thalys/it-2734403.eml, thalys/it-3129255.eml, thalys/it-3140953.eml, thalys/it-6825457.eml"; // +1 bcdtravel(html)[de]

    public $lang = '';

    public static $dictionary = [
        'nl' => [
            "Thalys No."   => "Thalys Nr.",
            "BOOKING REF." => "RESERVERINGSCODE",
            "PASSENGER"    => "REIZIGER",
            "PRICE"        => "PRIJS",
            "DEPARTURE"    => "VERTREK",
            "THALYS"       => "TREINNUMMER",
            "ARRIVAL"      => "AANKOMST",
            " at "         => " om ",
            "Coach"        => "Rijtuig",
            "CLASS"        => "KLASSE",
            "Seat"         => "Stoel",
        ],
        'fr' => [
            //			"Thalys No." => "",
            "BOOKING REF." => "REF RESERVATION",
            "PASSENGER"    => "PASSAGER",
            "PRICE"        => "PRIX",
            "DEPARTURE"    => "DEPART",
            "THALYS"       => "THALYS",
            "ARRIVAL"      => "ARRIVEE",
            " at "         => " à ",
            "Coach"        => "Voiture",
            "CLASS"        => "CLASS",
            "Seat"         => "Place",
        ],
        'de' => [
            "Thalys No."   => "Thalys Nummer.",
            "BOOKING REF." => "Buchungsreferenz.",
            "PASSENGER"    => "FAHRGAST",
            "PRICE"        => "PREIS",
            "DEPARTURE"    => "ABFAHRT",
            "THALYS"       => "THALYS NUMMER",
            "ARRIVAL"      => "ANKUNFT",
            " at "         => " um ",
            "Coach"        => "Wagen",
            "CLASS"        => "KLASSE",
            "Seat"         => "Sitz",
        ],
        'en' => [],
    ];

    protected $subjectDetectors = [
        'nl' => ['Thalys TheCard Boeking'],
        'de' => ['Thalys TheCard Buchung'],
        'en' => ['Thalys TheCard booking'],
    ];

    protected $langDetectors = [
        'nl' => ['AANKOMST'],
        'fr' => ['ARRIVEE'],
        'de' => ['ANKUNFT'],
        'en' => ['ARRIVAL'],
    ];

    public function parseEmail(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        $thalysNo = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t("Thalys No.") . "')]/following::text()[string-length(normalize-space(.))>1][1]", null, true, '/^([-\d\s\/]{8,})$/');

        if ($thalysNo) {
            $it['AccountNumbers'] = [$thalysNo];
        }

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t("BOOKING REF.") . "')]/following::text()[string-length(normalize-space(.))>1][1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = explode(', ', $this->http->FindSingleNode("//*[contains(text(),'" . $this->t("PASSENGER") . "')]/following::text()[string-length(normalize-space(.))>1][1]"));

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $payment = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t("PRICE") . "')]/following::text()[string-length(normalize-space(.))>1][1]");

        if (preg_match('/^([,.\d\s]+)$/', $payment, $matches)) {
            $it['TotalCharge'] = $this->normalizePrice($matches[1]);
        }

        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        // Segments roots
        $xpath = "(//*[contains(text(),'" . $this->t("DEPARTURE") . "')]/ancestor::table[1])[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments not found: $xpath", LOG_LEVEL_NORMAL);
        }

        // Parse segments
        foreach ($segments as $root) {
            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//*[contains(text(),'" . $this->t("THALYS") . "')]/following::text()[string-length(normalize-space(.))>1][1]", $root);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./ancestor::tr[2]/preceding-sibling::tr[1]//tr/td[2]", $root);

            // DepAddress
            // DepDate
            $dateDep = $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("DEPARTURE") . "')]/following::text()[string-length(normalize-space(.))>1])[1]", $root);
            $dateDepParts = explode($this->t(" at "), $dateDep);

            if (count($dateDepParts) === 2) {
                $itsegment['DepDate'] = strtotime($this->normalizeDate($dateDepParts[0]) . ', ' . $dateDepParts[1]);
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./ancestor::tr[2]/preceding-sibling::tr[1]//tr/td[6]", $root);

            // ArrAddress
            // ArrDate
            $dates = $this->http->FindNodes("//*[normalize-space()='" . $this->t("ARRIVAL") . "']/following::text()[string-length(normalize-space(.))>1][1]", $root);

            foreach ($dates as $date) {
                $dateArrParts = explode($this->t(" at "), $date);

                if ($t = $this->normalizeDate($dateArrParts[0])) {
                    $itsegment['ArrDate'] = strtotime($t . ', ' . $dateArrParts[1]);

                    break;
                }
            }

            // Type
            $itsegment['Type'] = $this->t("Coach") . ' ' . $this->http->FindSingleNode(".//*[contains(text(),'" . $this->t("Coach") . "')]/following::text()[string-length(normalize-space(.))>1][1]", $root);

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode(".//*[contains(text(),'" . $this->t("CLASS") . "')]/following::text()[string-length(normalize-space(.))>1][1]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $seatTexts = $this->http->FindNodes(".//*[contains(text(),'" . $this->t("Seat") . "')]/following::text()[string-length(normalize-space(.))>1][1]", null, '/^(\d+)$/');
            $seatValues = array_values(array_filter($seatTexts));

            if (!empty($seatValues[0])) {
                $itsegment['Seats'] = $seatValues;
            }

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Thalys Ticket') !== false
            || preg_match('/[.@]thalysticketless\.com/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'thalysticket@mail.thalysticketless.com') !== false) {
            return true;
        }

        foreach ($this->subjectDetectors as $phrases) {
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
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thalys TheCard") or contains(normalize-space(.),"Thalys platform") or contains(normalize-space(.),"the Thalys") or contains(.,"www.thalys.com") or contains(.,"www.thalysthecard.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.thalys.com") or contains(@href,"//www.thalysthecard.com") or contains(@href,"//thalys.com") or contains(@href,"//thalysthecard.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $this->assignLang();

        $itineraries = [];
        $this->parseEmail($itineraries);

        $result = [
            'emailType'  => 'TrainBooking_' . $this->lang,
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

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $string, $matches)) { // 11/08/2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
