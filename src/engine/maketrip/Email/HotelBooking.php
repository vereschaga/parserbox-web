<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: goibibo/HotelBookingVoucher, maketrip/BookingVoucher

class HotelBooking extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-11739945.eml, maketrip/it-11746950.eml, maketrip/it-11750054.eml, maketrip/it-34023677.eml"; // +1 bcdtravel(html)[en]

    public static $detectProvider = [
        'goibibo' => [
            'from'          => '@goibibo.com',
            'detectBodyUrl' => ['.goibibo.com'],
        ],
        'maketrip' => [
            'from'          => '@makemytrip.com',
            'detectBodyUrl' => ['.makemytrip.com'],
        ],
    ];

    public $lang = "en";
    public static $dictionary = [
        "en" => [],
    ];
    private $detectFrom = '@makemytrip.com';
    private $detectSubject = [
        'Hotel Booking Voucher',
    ];

    private $detectBody = [
        "Hotel Confirmation",
        "BOOKING DETAILS",
    ];

    private $xpath = [
        'bold' => '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])',
    ];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = $this->http->Response['body'];
//        foreach ($this->detectBody as $lang => $detectBody){
//            if (strpos($body, $detectBody) !== false) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $type = '';

        if ($this->http->XPath->query('//text()[normalize-space() = "HOTEL DETAILS"]')->length > 0) {
            $type = '1';
            $this->parseHotel($email);
        } else {
            $type = '2';
            $this->parseHotel2($email);
        }

        foreach (self::$detectProvider as $code => $params) {
            if (isset($params['from']) && !stripos($parser->getCleanFrom(), $params['from']) === false) {
                $email->setProviderCode($code);

                break;
            }

            if (isset($params['detectBodyUrl']) && $this->http->XPath->query("//*[" . $this->contains($params['detectBodyUrl']) . " or " . $this->contains($params['detectBodyUrl'], '@href') . " or " . $this->contains($params['detectBodyUrl'], '@src') . "]")->length > 0) {
                $email->setProviderCode($code);

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $detectedFrom = false;

        foreach (self::$detectProvider as $code => $params) {
            if (isset($params['from']) && !stripos($headers["from"], $params['from']) === false) {
                $detectedFrom = true;

                break;
            }
        }

        if ($detectedFrom == false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedProvider = false;

        foreach (self::$detectProvider as $code => $params) {
            if (isset($params['detectBodyUrl']) && $this->http->XPath->query("//*[" . $this->contains($params['detectBodyUrl']) . " or " . $this->contains($params['detectBodyUrl'], '@href') . " or " . $this->contains($params['detectBodyUrl'], '@src') . "]")->length > 0) {
                $detectedProvider = true;

                break;
            }
        }

        if ($detectedProvider == false) {
            return false;
        }

        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                return true;
            }
        }

        return false;
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
        return array_keys(self::$detectProvider);
    }

    private function parseHotel(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode('//*[contains(normalize-space(text()),"Booking ID:")]/strong', null, true, '/^([A-Z\d]{5,})$/'))
        ;

        // HOTEL
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes('//*[contains(normalize-space(text()),"GUEST DETAILS")]/following::td[count(table)=3]/table[1]'))
        ;

        if (!empty($this->http->FindSingleNode('(//text()[contains(normalize-space(), "Your booking is confirmed")])[1]'))) {
            $h->general()->status('Confirmed');
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode('//*[contains(normalize-space(text()),"HOTEL DETAILS")]/following::tbody[count(tr)=4][1]/tr[1]'))
            ->address(implode(', ', array_values(array_filter($this->http->FindNodes('//*[contains(normalize-space(text()),"HOTEL DETAILS")]/following::tbody[count(tr)=4][1]/tr[(position()=2 or position()=3) and not(.//tr)]')))))
            ->phone($this->http->FindSingleNode('//text()[normalize-space()="Contact:"]/following::text()[normalize-space()][1]'))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->getNode('Check in')))
            ->checkOut($this->normalizeDate($this->getNode('Check out')))
            ->rooms($this->getNode('Reservation', '/(\d+)\s+Room/i'), true, true)
        ;
        $guests = $this->getNode('No of Guests');

        if (preg_match('/(\d+)\s+Adult/i', $guests, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match('/(\d+)\s+Children/i', $guests, $m)) {
            $h->booked()->kids($m[1]);
        }

        // Rooms
        $h->addRoom()->setType($this->getNode('Room Type'));

        $payment = $this->getNode('GRAND TOTAL');

        if (preg_match('/([^\d]+)\s+([.\d]+)$/', $payment, $matches)) {
            $h->price()
                ->currency($this->currency($matches[1]))
                ->total($this->amount($matches[2]))
            ;

            if (!empty($h->getPrice()) && !empty($h->getPrice()->getCurrencyCode())) {
                $baseAmount = $this->getNode('Base Amount');

                if (preg_match('/' . $h->getPrice()->getCurrencyCode() . '\s+([.\d]+)$/', $baseAmount, $m)) {
                    $h->price()->cost($this->amount($m[1]));
                }

                $taxes = $this->getNode('Taxes and Service Fee');

                if (preg_match('/' . $h->getPrice()->getCurrencyCode() . '\s+([.\d]+)$/', $taxes, $m)) {
                    $h->price()->fee('Base Amount', $m[1]);
                }
            }
        }

        return $email;
    }

    private function parseHotel2(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation(
                $this->http->FindSingleNode('//text()[' . $this->starts(['Booking Id:', 'Booking ID']) . ']/following::text()[normalize-space()][1]', null, true, '/^([A-Z\d]{5,})$/'),
                trim($this->http->FindSingleNode('//text()[' . $this->starts(['Booking Id:', 'Booking ID']) . ']'), ': '))
        ;
        $pnr = $this->http->FindSingleNode('//text()[' . $this->starts(['PNR']) . ']/following::text()[normalize-space()][1]', null, true, '/^([A-Z\d]{5,})$/');

        if (!empty($pnr)) {
            $email->ota()
                ->confirmation($pnr, 'PNR');
        }

        // HOTEL
        $h = $email->add()->hotel();

        // General
        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Change Guest Name']/ancestor::*[preceding-sibling::*[normalize-space()]][1]/preceding-sibling::*[normalize-space()]/descendant::text()[contains(.,'@')][last()]/preceding::text()[normalize-space()][1]", null, true, "/^({$this->patterns['travellerName']})(?:[+ ]+\d{1,3})?$/u");

        if (empty($traveller)) {
            $traveller = trim($this->http->FindSingleNode('//text()[translate(normalize-space(), "0123456789", "**********") = "* GUESTS" or normalize-space() = "1 GUEST" or translate(normalize-space(), "0123456789", "**********") = "** GUESTS"]'
                . '/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[normalize-space() and not(.//td)][1]', null, true, "#^([^\+\(]+)#"));
        }

        $h->general()
            ->noConfirmation()
            ->traveller($traveller)
            ->date($this->normalizeDate($this->http->FindSingleNode('//text()[contains(normalize-space(), "Booking Date:")]', null, true, "#Booking Date:\s*(.+?)(?:\)|$)#")))
        ;

        if (!empty($this->http->FindSingleNode('//text()[starts-with(normalize-space(), "Your Booking at ")]', null, true, "#Your Booking at .+is Confirmed#"))) {
            $h->general()->status('Confirmed');
        }

        // Hotel
        $name = $this->http->FindSingleNode('//img[contains(@src, "HotelVoucher/star.png")]/ancestor::td[1]');
        $address = implode(', ', array_map('trim', array_filter($this->http->FindNodes('//img[contains(@src, "HotelVoucher/star.png")]/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space()]'))));

        if (empty($name) && empty($address)) {
            $name = $this->http->FindSingleNode('//text()[normalize-space()="BOOKING DETAILS"]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[not(.//td)][1]');
            $address = implode(', ', array_map('trim', array_filter($this->http->FindNodes('//text()[normalize-space()="BOOKING DETAILS"]/ancestor::tr[1]/following-sibling::tr[1]/descendant::td[not(.//td)][2]'))));
        }
        $h->hotel()
            ->name($name)
            ->address(preg_replace("#[, ]+#", ', ', $address))
            ->phone($this->http->FindSingleNode('//text()[normalize-space()="BOOKING DETAILS"]/ancestor::tr[1]/following-sibling::tr//text()[' . $this->eq(['Phone Number:', 'PHONE']) . ']/following::text()[normalize-space()][1]', null, true, "#^\s*([\d\-\+ \(\)]{6,})#"), true, true)
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate(implode(" ", $this->http->FindNodes('//text()[' . $this->eq(['Check In', 'Check-In']) . ']/ancestor::tr[1]/following-sibling::tr[position()<3]'))))
            ->checkOut($this->normalizeDate(implode(" ", $this->http->FindNodes('//text()[' . $this->eq(['Check Out', 'Check-Out']) . ']/ancestor::tr[1]/following-sibling::tr[position()<3]'))))
        ;
        $guests = array_filter($this->http->FindNodes('//img[' . $this->contains(['HotelVoucher/guest-img.png', 'hotel-voucher/users-icon.png'], '@src') . ']/ancestor::tr[1]'));

        foreach ($guests as $guest) {
            if (preg_match('/(\d+)\s+Adult/i', $guest, $m)) {
                $guestCount = (isset($guestCount)) ? $guestCount + $m[1] : $m[1];
            }

            if (preg_match('/(\d+)\s+Kid/i', $guest, $m)) {
                $kidsCount = (isset($kidsCount)) ? $kidsCount + $m[1] : $m[1];
            }
        }

        if (isset($guestCount)) {
            $h->booked()->guests($guestCount);
        }

        if (isset($kidsCount)) {
            $h->booked()->guests($kidsCount);
        }

        $roomsCount = $this->http->FindSingleNode('//text()[normalize-space()="1 ROOM" or translate(normalize-space(), "123456789", "ddddddddd") = "d ROOMS" or translate(normalize-space(), "1234567890", "dddddddddd") = "dd ROOMS"]', null, true, "#(\d+)\s*ROOM#");
        $beds = $this->http->FindSingleNode('//text()[normalize-space()="1 BED" or translate(normalize-space(), "123456789", "ddddddddd") = "d BEDS" or translate(normalize-space(), "1234567890", "dddddddddd") = "dd BEDS"]', null, true, "#(\d+)\s*BED#");

        if (empty($beds)) {
            $h->booked()->rooms($roomsCount);
        }

        // Rooms
        $roomTypes = $this->http->FindNodes('//tr[normalize-space()="1 ROOM" or translate(normalize-space(),"123456789","ddddddddd")="d ROOMS" or translate(normalize-space(),"1234567890","dddddddddd")="dd ROOMS"]/following-sibling::tr[contains(normalize-space(),"Meal Plan:")]/descendant::tr[not(.//tr)][1]/*[normalize-space()][1][descendant-or-self::*[' . $this->xpath['bold'] . ']]');

        foreach ($roomTypes as $type) {
            $h->addRoom()->setType($type);
        }

        // Price
        $h->price()
            ->total($this->amount($this->http->FindSingleNode('//text()[normalize-space()="TOTAL"]/ancestor::td[1]/following-sibling::td[normalize-space()][1]')))
            ->currency($this->http->FindSingleNode('//text()[starts-with(normalize-space(), "PRICE BREAKUP")]', null, true, "#\(\s*in\s+([A-Z]{3})\b#"))
        ;

        $cancellation1 = $this->http->FindSingleNode("//text()[normalize-space()='CANCELLATION POLICY']/ancestor::tr[1]/following-sibling::tr[1]");
        $cancellation2 = $this->http->FindSingleNode("//text()[normalize-space()='CANCELLATION POLICY']/ancestor::tr[1]/following-sibling::tr[2]");
        $h->general()
            ->cancellation(implode(". ", array_filter([trim($cancellation1, '.'), $cancellation2])));
        $detectDeadline = false;

        if (!empty($cancellation1)) {
            $detectDeadline = $this->detectDeadLine($h, $cancellation1);
        }

        if ($detectDeadline == false && !empty($cancellation2)) {
            $detectDeadline = $this->detectDeadLine($h, $cancellation2);
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("#Free Cancellation \(100% refund\) if you cancel this booking before ([\d\-]+ \d+:\d+):\d+ \(destination time\)\.#i", $cancellationText, $m)
            || preg_match("#Free Cancellation is valid on this booking till (.+?) \(#i", $cancellationText, $m)
            || preg_match("#Free Cancellation is valid on this booking till (.+)#i", $cancellationText, $m)
            || preg_match("#From [\d\-]+ \d+:\d+:\d+ to ([\d\-]+ \d+:\d+):\d+,100% penalty will be charged.#i", $cancellationText, $m)
            || preg_match("#From booking date to ([\d\-]+ \d+:\d+):\d+,0% penalty will be charged.#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));

            return true;
        }

        if (preg_match("#Non-Refundable Booking#i", $cancellationText)
            || preg_match("#This is a Non-refundable and non-amendable tariff#i", $cancellationText, $m)
            || preg_match("#Cancellation charges will apply \(refer policy below\)#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();

            return true;
        }

        return false;
    }

    private function normalizeDate($str)
    {
        if (empty($str)) {
            return false;
        }
        $in = [
            '#([^\d\s\.]{3,})[.]?\s+(\d{1,2})[,\s]+(\d{4})\s*\((\d{1,2}:\d{2}\s*[AP]M)\)$#i',
            '#(\d{1,2})\s+(\w+)\s+(\d{4})\s*\((.+)\)$#u',
            //			'#([^\d\s\.]{3,})[.]?\s+(\d{1,2})[,\s]+(\d{4})\s*\((\d{1,2}:\d{2}\s*[AP]M)\)$#i', // 2019-04-30 23:59:59
        ];
        $out = [
            "$2 $1 $3 $4",
            "$1 $2 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function getNode($str, $regexp = null)
    {
        return $this->http->FindSingleNode("//text()[{$this->contains($str)}]/following::td[1]", null, true, $regexp);
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            'Rs'=> 'INR',
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }
}
