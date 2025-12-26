<?php

namespace AwardWallet\Engine\jurys\Email;

class It5839547 extends \TAccountChecker
{
    public $mailFiles = ""; // +2 bcdtravel(html)[en]

    public $reSubject = [
        "en"=> ["Booking Confirmation from", "Confirmation - Reference"],
    ];

    public $langDetectors = [
        "en"=> ["Hotel Location", "Your Reservation Details - Room"],
    ];

    public static $dictionary = [
        'en' => [
            'Your confirmation number is' => ['Your confirmation number is', 'Your booking, with reference number'],
            'Guest name:'                 => ['Guest name:', 'Guest Name:'],
            'Arrival Date'                => ['Arrival Date', 'Arrival:'],
            'Departure Date'              => ['Departure Date', 'Departure:'],
            'Adults'                      => ['Adults', 'Adults:'],
            'Children'                    => ['Children', 'Children:'],
            'Room Cost'                   => ['Room Cost', 'Room Cost:'],
            'Cancellation policy'         => ['Cancellation policy', 'Cancellation Policy:'],
            'Total Price'                 => ['Total Price', 'Total Price:'],
        ],
    ];

    public $lang = '';

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->nextText($this->t("Your confirmation number is"));

        $xpathFragment1 = '//text()[normalize-space(.)="VIEW HOTEL INFO"]/ancestor::td[ ./following-sibling::td[ ./descendant::img ] ][1]';

        // Hotel Name
        $hotelName = $this->http->FindSingleNode("//text()[normalize-space(.)='Hotel Location']/ancestor::tr[1]/../descendant::text()[normalize-space(.)][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode($xpathFragment1 . '/descendant::h4[normalize-space(.)][1]');
        }

        if (!empty($hotelName)) {
            $it['HotelName'] = $hotelName;
        }

        // Address
        if ($hotelName) {
            $address = implode(", ", $this->http->FindNodes("//text()[normalize-space(.)='{$hotelName}']/following::text()[string-length(normalize-space(.))>1 and following::text()[normalize-space(.)='Tel:']]"));
        }

        if (isset($address) && empty($address)) {
            $address = $this->http->FindSingleNode($xpathFragment1 . '/descendant::p[normalize-space(.)][ ./preceding-sibling::h4[normalize-space(.)] and ./following-sibling::*[ ./descendant::img[contains(@src,"/phone.")] ] ]');
        }

        if (isset($address) && !empty($address)) {
            $it['Address'] = $address;
        }

        // Phone
        $phone = $this->nextText("Tel:");

        if (!$phone) {
            $phone = $this->http->FindSingleNode($xpathFragment1 . '/descendant::img[contains(@src,"/phone.")]/following::text()[normalize-space(.)][1]', null, true, '/^([+)(\d][-\d\s)(]{5,}[)(\d])$/'); // +353 1 454 0000
        }

        if ($phone) {
            $it['Phone'] = $phone;
        }

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Arrival Date"))));
        $timeCheckIn = $this->nextText("Check-in Time");

        if ($timeCheckIn) {
            $it['CheckInDate'] = strtotime($timeCheckIn, $it['CheckInDate']);
        }

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Departure Date"))));
        $timeCheckOut = $this->nextText("Check-out Time");

        if ($timeCheckOut) {
            $it['CheckOutDate'] = strtotime($timeCheckOut, $it['CheckOutDate']);
        }

        // GuestNames
        $it['GuestNames'] = array_filter([$this->nextText($this->t("Guest name:"))]);

        // Guests
        $it['Guests'] = $this->nextText($this->t("Adults"));

        // Kids
        $it['Kids'] = $this->nextText($this->t("Children"));

        // Rate
        $it['Rate'] = $this->nextText($this->t("Room Cost"));

        // CancellationPolicy
        $it['CancellationPolicy'] = $this->nextCol($this->t("Cancellation policy"));

        // RoomType
        // RoomTypeDescription
        $roomType = $this->nextCol("Room Name");

        if (!$roomType) {
            $room = $this->nextCol("Room:");

            if (preg_match('/^(.+?) - (.+)/s', $room, $matches)) {
                $roomType = $matches[1];
                $roomTypeDescription = $matches[2];
            } elseif ($room) {
                $roomType = $room;
            }
        }

        if ($roomType) {
            $it['RoomType'] = $roomType;
        }

        if (isset($roomTypeDescription)) {
            $it['RoomTypeDescription'] = $roomTypeDescription;
        }

        // Currency
        // Total
        $payment = $this->nextText($this->t("Total Price"));

        if (preg_match('/^(?<currency>\D+)(?<amount>\d[,.\d\s]*)/', $payment, $matches)) {
            $it['Currency'] = $this->normalizeCurrency($matches[1]);
            $it['Total'] = $this->normalizeAmount($matches[2]);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        strpos($from, 'Jurys Inn') !== false
            || stripos($from, '@jurysinns.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Jurys Inn') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
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

        if (strpos($body, 'Jurys') === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        if ($this->assignLang() === false) {
            return false;
        }

        $this->date = strtotime($parser->getHeader('date'));

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations' . ucfirst($this->lang),
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//td[not(.//td) and {$rule}])[{$n}]/following-sibling::td[1]", $root);
    }

    private function assignLang(): bool
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)$#",
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
