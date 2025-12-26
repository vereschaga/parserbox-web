<?php

namespace AwardWallet\Engine\taj\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "taj/it-44124590.eml, taj/it-98140011.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Confirmation No'          => ['Confirmation No', 'Confirmation #'],
            'Check In Date'            => ['Check In Date', 'Arrival Date'],
            'Check Out Date'           => ['Check Out Date', 'Departure Date'],
            'Guest Name:'              => ['Guest Name:', 'Guest Name :', 'Guest:', 'Guest :', 'Name'],
            'Kid'                      => ['Kid', 'Child', 'Number of Children'],
            'Rate Applicable per day'  => ['Rate Applicable per day', 'Rate Applicable'],
            'Total Price'              => ['Total Price', 'Grand Total'],
            'Cancellation Policy'      => ['Cancellation and No Show Policies', 'Cancellation Policy'],
            'No. of Guests'            => ['No. of Guests', 'Number of Adults'],
        ],
    ];

    private $datesInverted = false;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tajhotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], ' at Taj ') === false
        ) {
            return false;
        }

        return preg_match('/Your Reservation .+ is confirmed/', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".tajhotels.com/") or contains(@href,"www.tajhotels.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing Taj")'
                . ' or contains(normalize-space(),"You can call our Taj Reservation")'
                . ' or contains(.,"www.tajhotels.com") or contains(.,"@tajhotels.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('YourReservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
            'phone' => '[+(\d][-. \d)(]{5,}[\d)]',
        ];

        if (preg_match_all('/\b\d{1,2}\s*-\s*(\d{1,2})\s*-\s*\d{2,4}\b/', $this->http->Response['body'], $dateMatches)) {
            // 11-19-21
            foreach ($dateMatches[1] as $simpleDate) {
                if ($simpleDate > 12) {
                    $this->datesInverted = true;

                    break;
                }
            }
        }

        $h = $email->add()->hotel();

        $hotelName = null;
        $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation No'))}]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][last()]");

        if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
            $hotelName = $hotelName_temp;
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation is confirmed at')]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Your reservation is confirmed at'))}\s*(.+)$/");
        }

        $h->hotel()->name($hotelName);

        $confirmationNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation No'))}]");

        if (preg_match("/({$this->opt($this->t('Confirmation No'))})[\s:#]+([A-Z\d]{5,})$/", $confirmationNo, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': #'));
        } else {
            $confirmationNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation No'))}]/following::text()[normalize-space()][1]", null, true, '/^[:\s]*([A-Z\d]{5,})$/');

            if ($confirmationNo) {
                $confirmationNoTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation No'))}]", null, true, '/^(.+?)[\s:#]*$/');
                $h->general()->confirmation($confirmationNo, $confirmationNoTitle);
            }
        }

        $guestName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name:'))}]", null, true, "/^{$this->opt($this->t('Guest Name:'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/");

        if (!$guestName) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]/following::text()[normalize-space()][1]", null, true, '/^\:?\s*?([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/');
        }
        $h->general()->traveller($guestName);

        $itineraryNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary #'))}]/following::text()[normalize-space()][1]", null, true, '/^\:*\s*([A-Z\d]{5,})$/');

        if ($itineraryNo) {
            $itineraryNoTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary #'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($itineraryNo, $itineraryNoTitle);
        }

        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check In Date'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check In Date'))}[:\s]*(.+)/");
        $h->booked()->checkIn2($this->normalizeDate($dateCheckIn));
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Time'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Arrival Time'))}[:\s]*(.+)/");

        if (!empty($h->getCheckInDate()) && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check Out Date'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check Out Date'))}[:\s]*(.+)/");
        $h->booked()->checkOut2($this->normalizeDate($dateCheckOut));
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check Out Time'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check Out Time'))}[:\s]*(.+)/");

        if (!empty($h->getCheckOutDate()) && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        $roomsCount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('No. of Rooms'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('No. of Rooms'))}[:\s]*(\d{1,3})$/");
        $h->booked()->rooms($roomsCount, false, true);

        $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('No. of Guests'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('No. of Guests'))}[:\s]*(.+)$/");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $guests, $m)
            || preg_match("/^(\d+)$/i", $guests, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Kid'))}/i", $guests, $m)) {
            $h->booked()->kids($m[1]);
        } else {
            $kids = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Kid'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Kid'))}[:\s]*(\d+)$/");

            if ($kids !== null) {
                $h->setKidsCount($kids);
            }
        }

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Room Type'))}[:\s]*(.+)$/");
        $room->setType($roomType);

        $rateText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('Rate Applicable per day'))}]/ancestor::tr[1]"));
        $rate = preg_match("/{$this->opt($this->t('Rate Applicable per day'))}[:\s]*(.+?)\s*$/s", $rateText, $m) > 0
            ? preg_replace('/[ ]*\n+[ ]*/', '; ', $m[1]) : null
        ;

        if (!empty($rate)) {
            $room->setRate($rate);
        }

        $rateName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Rate Name'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Rate Name'))}[:\s]*(.+)$/");
        $room->setRateType($rateName, false, true);

        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Rate Applicable per day'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Rate Applicable per day'))}[\s\:]+([A-Z]{3})\s+\d+/su");
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Price'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Price'))}[:\s]*(.*?\d.*?)(?:\s*\(|$)/");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
        ) {
            if (empty($matches['currency']) && !empty($currency)) {
                $matches['currency'] = $currency;
            }

            $h->price()->currency($matches['currency']);

            if (preg_match("/^\d+\,\d+\,\d+\.\d+$/", $matches['amount'])) {
                $matches['amount'] = str_replace(',', '', $matches['amount']);
            }

            // INR 8,260.00    |    $ 1,891.41
            $h->price()->total(PriceHelper::parse($matches['amount'], $matches['currency']));

            $taxes = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Taxes & Fees'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Taxes & Fees'))}[:\s]*(.*?\d.*?)(?:\s*\(|$)/");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $taxes, $m)) {
                $h->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Cancellation Policy'))}[:\s]*(.+)$/");
        $h->general()->cancellation($cancellation);

        if (!empty($h->getCheckInDate()) && (
                preg_match("/Free cancellation by (?<hour>{$patterns['time']}) on Day of arrival to avoid a penalty/i", $cancellation, $m)
                // Free cancellation by 2PM - 1 day prior to arrival to avoid a penalty
                //|| preg_match("/Free cancellation by (?<hour>{$patterns['time']}) - \d+ day prior to arrival to avoid a penalty/i", $cancellation, $m)
            )
        ) {
            $h->booked()->deadline(strtotime(date('Y-m-d', $h->getCheckInDate()) . $m['hour']));
        } elseif (preg_match("/Reservations must be cancelled by (?<hour>{$patterns['time']}) - (?<prior>\d{1,3} days?) prior to arrival to avoid a penalty/", $cancellation, $m)
            || preg_match("/Cancellations or modifications must be received by (?<hour>{$patterns['time']})(?:\s*[[:upper:]]{3,})?\s*\([^)(]+\) the (?<prior>day|\d{1,3} days?) prior to arrival to avoid a penalty charge of the first night's room rate and tax/", $cancellation, $m)
            || preg_match("/Free cancellation by (?<hour>{$patterns['time']}) - (?<prior>\d+ days?) prior to arrival to avoid a penalty/i", $cancellation, $m)
            || preg_match("/Free cancellation by (?<hour>\d+\s*A?P?M)\-(?<prior>\d+\s*days?) prior to arrival to avoid a penalty of/i", $cancellation, $m)
        ) {
            $m['prior'] = preg_replace("/^day$/i", '1 day', $m['prior']);
            $this->parseDeadlineRelative($h, $m['prior'], $m['hour']);
        }

        $addressRow = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation No'))}]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]");

        if (preg_match("/^(?<address>.{3,}?)\s*\|\s*T:\s*(?<phone>{$patterns['phone']})\s*\|\s*F:\s*(?<fax>{$patterns['phone']})(?:\s*\||$)/i", $addressRow, $m)) {
            // it-98140011.eml
            // 2 East 61st Street, New York, NY 10065-8402 | T: 1.212.838.8000 | F: 1.212.940.8109
            $h->hotel()->address($m['address'])->phone($m['phone'])->fax($m['fax']);

            return;
        }

        if ($hotelName) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hotel Information'))}]/following::text()[{$this->starts($hotelName . ':')}]", null, true, "/{$this->opt($hotelName . ':')}\s*(.{3,}?)\.?$/");

            if (!empty($address)) {
                $h->hotel()->address($address);
            }
        }

        if ($hotelName) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Address'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Address'))}[\s\:]*(.+)$/");

            if (!empty($address)) {
                $h->hotel()->address($address);
            }
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hotel Information'))}]/ancestor::tr[1]/following::tr[normalize-space()][position()<4][{$this->contains($this->t('please contact at'))}]", null, true, "/{$this->opt($this->t('please contact at'))}\s+([+(\d][-. \d)(]{5,}[\d)])(?:\s|[.,!?]|$)/");

        if (!empty($phone)) {
            $h->hotel()->phone($phone);
        }
    }

    private function parseDeadlineRelative(Hotel $h, $prior, $hour = null): bool
    {
        $checkInDate = $h->getCheckInDate();

        if (empty($checkInDate)) {
            return false;
        }

        if (empty($hour)) {
            $deadline = strtotime('-' . $prior, $checkInDate);
            $h->booked()->deadline($deadline);

            return true;
        }

        $base = strtotime('-' . $prior, $checkInDate);

        if (empty($base)) {
            return false;
        }
        $deadline = strtotime($hour, strtotime(date('Y-m-d', $base)));

        if (empty($deadline)) {
            return false;
        }
        $priorUnix = strtotime($prior);

        if (empty($priorUnix)) {
            return false;
        }
        $priorSeconds = $priorUnix - strtotime('now');

        while ($checkInDate - $deadline < $priorSeconds) {
            $deadline = strtotime('-1 day', $deadline);
        }
        $h->booked()->deadline($deadline);

        return true;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Confirmation No']) || empty($phrases['Check In Date'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation No'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Check In Date'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($text)
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 26-02-20    |    11-19-21
            '/^(\d{1,2})-(\d{1,2})-(\d{2,4})$/u',
            // 26-02-20    |    11-19-21
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/u',
        ];
        $out[0] = $this->datesInverted ? '$1/$2/$3' : '$2/$1/$3';
        $out[1] = $this->datesInverted ? '$1/$2/$3' : '$2/$1/$3';

        return preg_replace($in, $out, $text);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
