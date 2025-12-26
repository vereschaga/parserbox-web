<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar formats: gcampaigns/HResConfirmation (object), marriott/ReservationConfirmation (object), marriott/It2506177, mirage/It1591085, triprewards/It3520762, woodfield/It2220680

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-293325867.eml"; // +1 bcdtravel(html)[en]

    public $lang = '';

    public static $dictionary = [
        'en' => [],
    ];

    private $providerCode = '';

    private $reSubject = [
        'en' => ['Welcome to Hyatt'],
    ];

    private $reBody = [
        'en' => ['Room Type:'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@pkghlrss.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (isset($headers['subject']) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignProvider() === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        $this->assignProvider();
        $this->assignLang();

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'providerCode' => $this->providerCode,
            'emailType'    => 'WelcomeTo' . ucfirst($this->lang),
            'parsedData'   => [
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

    public static function getEmailProviders()
    {
        return ['goldpassport', 'harrah'];
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'R';

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->nextText($this->t("Hotel Confirmation:"));

        if (empty($it['ConfirmationNumber'])) {
            $it['ConfirmationNumber'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Acknowledgement Number:")]/following::text()[normalize-space(.)][1]', null, true, '/^([-A-Z\d]{5,})$/');
        }

        // HotelName
        $xpathFragment1 = '//text()[normalize-space(.)="Hotel"]/ancestor::tr/following-sibling::tr[contains(normalize-space(.),"Harrah\'s Resort Atlantic City")]';

        if ($this->http->XPath->query($xpathFragment1)->length > 0) {
            $it['HotelName'] = "Harrah's Resort Atlantic City";
        }

        if (empty($it['HotelName'])) {
            $it['HotelName'] = $this->http->FindSingleNode("//text()[contains(.,'•')]", null, true, "#•\s+(.+)$#");
        }

        // Address
        $it['Address'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][position()>1][contains(.,",")][1]');

        if (empty($it['Address']) && $this->providerCode === 'goldpassport') {
            $it['Address'] = $it['HotelName'];
        }

        // Phone
        $phones = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[normalize-space(.)][position()>1]', null, '/^([-+\d )(]+\d{3})$/');
        $phoneValues = array_values(array_filter($phones));

        if (!empty($phoneValues[0])) {
            $it['Phone'] = $phoneValues[0];
        }

        // CheckInDate
        $dateCheckIn = $this->nextText($this->t('Arrival Date:'));
        $timeCheckIn = $this->http->FindSingleNode('//text()[' . $this->contains(['Check-in time is', 'Check-In time is']) . ']', null, true, '/Check-in time is\s+([\d:]+\s*[AP]\.?M\.?)/i');

        if ($dateCheckIn && $timeCheckIn) {
            if ($dateCheckIn = $this->normalizeDate($dateCheckIn)) {
                $it['CheckInDate'] = strtotime($dateCheckIn . ', ' . $timeCheckIn);
            }
        }

        // CheckOutDate
        $dateCheckOut = $this->nextText($this->t('Departure Date:'));
        $timeCheckOut = $this->http->FindSingleNode('//text()[' . $this->contains(['Check-out time is', 'Check-Out time is']) . ']', null, true, '/Check-out time is\s+([\d:]+\s*[AP]\.?M\.?)/i');

        if ($dateCheckOut && $timeCheckOut) {
            if ($dateCheckOut = $this->normalizeDate($dateCheckOut)) {
                $it['CheckOutDate'] = strtotime($dateCheckOut . ', ' . $timeCheckOut);
            }
        }

        // GuestNames
        $reservationName = $this->nextText($this->t('Reservation Name:'));

        if ($reservationName) {
            $it['GuestNames'] = [$reservationName];
        }

        // RoomType
        $roomType = $this->nextText($this->t('Room Type:'));

        if ($roomType) {
            $it['RoomType'] = $roomType;
        }

        // Rooms
        $rooms = $this->nextText($this->t('Number of Rooms:'));

        if (preg_match('/^(\d{1,3})/', $rooms, $matches)) {
            $it['Rooms'] = $matches[1];
        }

        // Guests
        $guests = $this->nextText($this->t('Number of Guests:'));

        if (preg_match('/^(\d{1,3})/', $guests, $matches)) {
            $it['Guests'] = $matches[1];
        }

        // Total
        $totalCharge = $this->nextText($this->t('Total Charge:'));

        if (preg_match('/^([,.\d ]*\d)/', $totalCharge, $matches)) {
            $it['Total'] = $this->normalizePrice($matches[1]);
        }

        // CancellationPolicy
        $cancellPolicy = $this->nextText($this->t('Cancel Policy:'));

        if ($cancellPolicy) {
            $it['CancellationPolicy'] = $cancellPolicy;
        }

        $itineraries[] = $it;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignProvider()
    {
        if ($this->http->XPath->query('//text()[contains(normalize-space(.),"Hyatt")]')->length > 0) {
            $this->providerCode = 'goldpassport';

            return true;
        } elseif ($this->http->XPath->query('//text()[contains(normalize-space(.),"Harrah’s Resort Atlantic City")]')->length > 0) {
            $this->providerCode = 'harrah';

            return true;
        }

        return false;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//text()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($string)
    {
        if (preg_match('/^([^,.\d\s]{3,})\s+(\d{1,2})\s*,\s*(\d{4})/', $string, $matches)) { // Oct 15, 2017
            $month = $matches[1];
            $day = $matches[2];
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

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
