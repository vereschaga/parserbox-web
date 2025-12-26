<?php

namespace AwardWallet\Engine\hotwire\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelPurchaseConfirmationRed extends \TAccountChecker
{
    public $mailFiles = "hotwire/it-10.eml, hotwire/it-11.eml, hotwire/it-12.eml, hotwire/it-1641584.eml, hotwire/it-1889614.eml, hotwire/it-3206010.eml, hotwire/it-3449617.eml, hotwire/it-3489175.eml, hotwire/it-45583940.eml, hotwire/it-5.eml, hotwire/it-9.eml";

    public static $dictionary = [
        "en" => [
            "Hotwire itinerary"              => ["Hotwire itinerary", "Hotwire Itinerary:", 'Hotwire itinerary #'],
            "Check-in"                       => ["Check-in", "Check in", "Check Inn", "Check In"],
            "Check-out"                      => ["Check-out", "Check out"],
            "Date booked"                    => ["Date booked", "Date"],
            "Your hotel confirmation number" => ["Your hotel confirmation number", "Your Hotel Confirmation Number", "Hotel itinerary number", "Hotel Itinerary Number"],
            "Trip Total:"                    => ["Trip Total:", "Hotwire Total", 'Trip total'],
            "Tax recovery charges & fees:"   => ["Tax recovery charges & fees:", "Tax recovery charges & fees", "Taxes and fees"],
            "Summary Of Charges"             => ["Summary Of Charges", "Cost summary"],
            "Important Travel Information:"  => ["Important Travel Information:", "Important travel information", "Some reminders"],
            "Cancellation:"                  => ["Cancellation", "Cancellation:", "Cancellation :"],
        ],
    ];

    public $lang = "en";

    private $reSubject = [
        'en' => 'Purchase Confirmation',
        'Booking Confirmation',
        'Your stay in',
    ];

    private $reBody = 'Hotwire';

    private $reBody2 = [
        "en"  => "Billed to",
        'en2' => 'Enjoy your stay in',
        'en3' => 'Your trip to',
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Hotwire Booking Confirmation') !== false
            || preg_match('/[-.@]hotwire\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Hotwire Hotel') === false) {
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
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHotel($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseHotel(Email $email)
    {
        $xpathBold = '(self::b or self::strong)';

        $h = $email->add()->hotel();

        $taConfirmation = $this->nextText($this->t("Hotwire itinerary"));

        if (empty($taConfirmation)) {
            $taConfirmation = $this->re("/Hotwire Itinerary[:#] (.+)/", $this->nextText("Reservation Confirmation"));
        }

        if (!empty($taConfirmation)) {
            $h->ota()->confirmation($taConfirmation);
        }

        $dateBooked = $this->http->FindSingleNode("//text()[normalize-space(.)='Billed to']/ancestor::tr[1]/following-sibling::tr[1]/td[3]");

        if (empty($dateBooked)) {
            $dateBooked = $this->nextText($this->t("Date booked"));
        }

        if (!empty($dateBooked)) {
            $h->general()->date2($this->normalizeDate($dateBooked));
        }

        $confirmation = join(', ', $this->http->FindNodes("//text()[{$this->starts($this->t('Your hotel confirmation number'))}]/following-sibling::node()[normalize-space()]"));

        if (!empty($confirmation)) {
            foreach (preg_split('/,\s*/', $confirmation) as $item) {
                if (preg_match('/^[-A-Z\d]{5,}$/', $item, $m)) {
                    $h->general()->confirmation($m[0]);
                }
            }
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Your hotel confirmation number'))}]")->length === 0) {
            $h->general()->noConfirmation();
        }

        $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("View map")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Map")) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][1]");
        }

        $address = implode(' ', $this->http->FindNodes("//text()[" . $this->eq($this->t("View map")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][position()>1]"));

        if (empty($address) && !empty($hotelName)) {
            $address = implode(' ', $this->http->FindNodes('//text()[normalize-space()="Map"]/ancestor::td[1]/descendant::text()[normalize-space() and not(contains(normalize-space(),"' . $hotelName . '"))][position()=1 or position()=2]'));
        }

        $phone = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("View map")) . "]/preceding::text()[string-length(normalize-space(.))>1][1]");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Map")) . "]/ancestor::tr[1]/following-sibling::tr[1]//td[1]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//a[normalize-space(.)='Map']/preceding-sibling::a[1]");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//a[normalize-space(.)='Map']/preceding-sibling::text()[normalize-space()][1]", null, true, "/^([+(\d][-. \d)(]{5,}[\d)])\s*\|$/");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $checkInDate = $this->normalizeDate($this->nextText($this->t("Check-in")));

        if (empty($checkInDate)) {
            $checkInDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in'))}]/following::text()[string-length()>1][1]"));
        }
        $h->booked()->checkIn2($checkInDate);
        $checkInTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'After')]", null, true, '/(\d{1,2}:\d{1,2} [AP]M)/');

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $h->booked()->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        $checkOutDate = $this->normalizeDate($this->nextText($this->t("Check-out")));

        if (empty($checkOutDate)) {
            $checkOutDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out'))}]/following::text()[string-length()>1][1]"));
        }
        $h->booked()->checkOut2($checkOutDate);
        $checkOutTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Before')]", null, true, '/(\d{1,2}:\d{1,2} [AP]M)/');

        if (!empty($h->getCheckOutDate()) && $checkOutTime) {
            $h->booked()->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }

        $roomType = $this->nextText("Bed type");

        // it-45583940.eml
        $hotelDetailsHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Add a room"))}]/ancestor::*[count(descendant::text()[normalize-space()])>2][1]");
        $hotelDetails = $this->htmlToText($hotelDetailsHtml);

        if (stripos($hotelDetails, "{$this->opt($this->t('room'))}") == false) {
            $hotelDetails = $this->http->FindSingleNode("//a[normalize-space(.)='Add a room']/ancestor::tr[1]");
        }

        if (preg_match("/^(?<count>\d{1,3}) {$this->opt($this->t("room"))}s?(?:, (?<type>[^,]{3,}?))? {$this->opt($this->t("Add a room"))}/is", $hotelDetails, $m)) {
            // 1 room, 2 queen beds Add a room    |    1 rooms Add a room
            $h->booked()->rooms($m['count']);

            if (!empty($m['type']) && empty($roomType)) {
                $roomType = $m['type'];
            }
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t("adult"))}]");

        if (preg_match('/\b(\d{1,3}) adult/i', $guests, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match('/\b(\d{1,3}) child/i', $guests, $m)) {
            $h->booked()->kids($m[1]);
        }

        $roomRate = $this->re("#@\s*(.+night)#i", $this->nextText($this->t("Summary Of Charges")));

        if ($roomRate === null) {
            $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Nightly room rate"))}]/following::text()[normalize-space()][1]", null, true, "/^(\d[,.\'\d]*)\s*x\s*\d+\s*{$this->opt($this->t("room"))}/i");
        }

        if ($roomRate === null) {
            $roomRate = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your room price per night"))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\.]+)$/i");
        }

        $roomDescription = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Hotel amenities:"))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]");

        if (!empty($roomType) || !empty($roomRate) || !empty($roomDescription)) {
            $room = $h->addRoom();
            $room
                ->setType($roomType, false, true)
                ->setRate($roomRate, false, true)
                ->setDescription($roomDescription, false, true);
        }

        $guestName = $this->http->FindSingleNode("//text()[normalize-space()='Billed to']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        if (empty($guestName)) {
            $guestName = $this->nextText($this->t("Billed to"));
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Primary guest:"))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, true, "/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t("must be"))}/u");
        }

        if (!empty($guestName)) {
            $h->general()->traveller($guestName);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Important Travel Information:"))}]/ancestor::tr[1]/following::tr[normalize-space() and not(.//tr) and not(contains(.,':'))][1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//node()[{$this->eq($this->t("Cancellation:"))}]/following-sibling::text()[normalize-space()][1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//node()[{$this->eq($this->t("Cancellation:"))}]/following-sibling::*[normalize-space()][1]");
        }
        $h->general()->cancellation($cancellation ? preg_replace("/\s*{$this->opt($this->t("For details,"))}$/", '', ltrim($cancellation, '• ')) : null);

        if (preg_match("/Your booking is final and can't be refunded or changed\./", $cancellation)
            || preg_match("/^All bookings are final\. No refunds or changes\./", $cancellation)
            || preg_match("/Your booking is final and can’t be refunded or changed/", $cancellation)
        ) {
            $h->booked()->nonRefundable();
        }

        $totalPrice = $this->nextText($this->t("Trip Total:"));

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Trip total')]/ancestor::tr[1]");
        }

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d]*) ?(?<currency>[^\d)(]+)$/', $totalPrice, $m)
            || preg_match('/\((?<currency>[^\d)(]+)\) ?(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)
        ) {
            // $324.24    |    202.99 USD
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['currency']));

            $m['currency'] = trim($m['currency']);

            $cost = $this->nextText($this->t("Summary Of Charges"), null, 2);

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')? ?(?<amount>\d[,.\'\d]*)$/', $cost, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d]*) ?(?:' . preg_quote($m['currency'], '/') . ')?$/', $cost, $matches)
            ) {
                $h->price()->cost($this->amount($matches['amount']));
            }

            $tax = $this->nextText($this->t("Tax recovery charges & fees:"));

            if (empty($tax)) {
                $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tax recovery charges & fees:'))}]/following::text()[string-length()>1][1]", null, true, "/^([\d\.]+)$/");
            }

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')? ?(?<amount>\d[,.\'\d]*)$/', $tax, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d]*) ?(?:' . preg_quote($m['currency'], '/') . ')?$/', $tax, $matches)
            ) {
                $h->price()->tax($this->amount($matches['amount']));
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $this->logger->error('IN-' . $str);
        $in = [
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+\s+[AP]M)$#", //Tue, May 15, 2012, 3:00 PM
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //Tue, May 15, 2012
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $this->logger->error('OUT-' . $str);

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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[string-length()>1][{$n}]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
