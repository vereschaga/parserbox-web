<?php

namespace AwardWallet\Engine\wynnlv\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2344508 extends \TAccountChecker
{
    public $mailFiles = "wynnlv/it-2049022.eml, wynnlv/it-2344508.eml, wynnlv/it-2466307.eml";

    private $detects = [
        'Thank you for choosing Encore',
        'Wynn Las Vegas',
        'Our reservation specialists are available to assist you with reservations for our restaurants',
    ];

    private $lang = 'en';

    private $from = '/[@\.](?:encorelasvegas|wynnlasvegas)\.com/i';

    private $subj = '';

    private static $dict = [
        'en' => [
            'Confirmation Number' => ['Confirmation Number', 'Confirmation', 'Reservation number:'],
            'Check-In time'       => ['Check In time', 'Check-In time', 'Check-in time', 'Check-In Time', 'Check In Time', 'Check-in Time'],
            'Check-Out time'      => ['Check-Out time', 'Check-Out Time', 'Check-out time', 'Check Out time', 'Check out Time', 'Check-Out Time', 'Check Out Time'],
            'Check-Out Date'      => ['Check-Out Date', 'Check-out Date', 'Departure Date'],
            'Check-In Date'       => ['Check-In Date', 'Check-in Date', 'Arrival Date'],
            'Daily Average Rate'  => ['Daily Average Rate', 'Nightly Rate'],
            'Total'               => ['Total'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $class = explode("\\", __CLASS__);
        $email->setType(end($class) . $this->lang);

        if (!$this->detect(text($parser->getHTMLBody()))) {
            return $email;
        }
        $this->subj = $parser->getSubject();
        $this->parseEmail($email, $parser->getPlainBody());

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() . $parser->getPlainBody();

        return $this->detect($body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function detect($body): bool
    {
        if (0 === $this->http->XPath->query("//img[contains(@src, 'encore')] | //node()[contains(normalize-space(.), 'Encore')]")->length) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect}')]")->length || false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email, string $text = ''): void
    {
        $h = $email->add()->hotel();

        if ($conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation #')]", null, true, "/{$this->opt($this->t('Confirmation #'))}\s*([A-Z\d]+)/")) {
            $h->addConfirmationNumber($conf);
        } elseif ($conf = $this->getNode('Confirmation Number')) {
            $h->addConfirmationNumber($conf);
        } elseif ($conf = $this->http->FindSingleNode("//text()[normalize-space()='Confirmation']/following::text()[normalize-space()][1]")) {
            $h->addConfirmationNumber($conf);
        }

        if ($cancelNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your cancellation number is'))}]", null, false, "/{$this->opt($this->t('Your cancellation number is'))}\s+(\d+)/")) {
            $h->general()
                ->status('cancelled')
                ->cancelled()
                ->cancellationNumber($cancelNo);
        }

        if ($this->http->XPath->query("//*[{$this->starts($this->t('This notice of cancellation confirms'))}]")->length > 0) {
            $h->general()
                ->status('cancelled')
                ->cancelled();
        }

        $hotelName = $this->orval(
            $this->re("#(?:Re|Fwd|Subject|FW)\:\s*(.*?) Online Reservation Confirmation#i", $this->subj),
            $this->re("#{$h->getConfirmationNumbers()[0][0]}\s*(.+)#i", $this->subj),
            $this->http->FindSingleNode("//text()[{$this->starts('Thank you for choosing')}][1]", null, true, "#Thank you for choosing (.*?)\. Our (?:reservation|specialists)#i"),
            $this->re("#\n\s*[^\n]*?,\s*([^\n]*?)Online\s*Reservation[\s<]+roomreservations@encorelasvegas.com\s*>\s*wrote#is", $text),
            $this->http->FindSingleNode("//node()[{$this->starts('Thank you for choosing')}][1]/following-sibling::a[1]"),
            $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Thank you for visiting')][1]", null, true, '/Thank you for visiting[ ]+(.+)/'),
            $this->http->FindSingleNode("//p[starts-with(normalize-space(.), 'email:')]/preceding-sibling::*[normalize-space()!=''][last()][contains(.,'For questions and information')]/following-sibling::*[normalize-space()!=''][1]", null, true, '/(.+)[ ]+RESERVATIONS$/i')
        );

        if (!empty($hotelName)) {
            $h->hotel()
                ->name($hotelName);
        }

        $t = $this->getNode('Check-In time');

        if (empty($t)) {
            $t = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check In Time'))}]/following::text()[normalize-space()][1]");
        }

        $checkInTime = null;
        $checkOutTime = null;

        if (empty($checkInTime) && preg_match('/(\d{1,2}(?::\d+)?) ([pa])\.(m)\./', $t, $m)) {
            $checkInTime = $m[1] . ':00 ' . strtoupper($m[2]) . strtoupper($m[3]);
        }

        $checkInDate = $this->getNode('Check-In Date');

        if (empty($checkInDate)) {
            $checkInDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-In Date'))}]/following::text()[normalize-space()][1]");
        }

        if (!strtotime($checkInDate) && preg_match("#^(\d{4}) (\w+) (\d+)$#i", $checkInDate, $m)) {
            $checkInDate = $m[3] . ' ' . $m[2] . ' ' . $m[1];
        }

        $checkInDate = strtotime($checkInDate . ', ' . $checkInTime);

        if (!empty($checkInDate)) {
            $h->booked()
                ->checkIn($checkInDate);
        }

        $t = $this->getNode('Check-Out time');

        if (empty($t)) {
            $t = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-Out time'))}]/following::text()[normalize-space()][1]");
        }

        if (preg_match('/(\d{1,2}(?::\d+)?) ([pa])\.(m)\./', $t, $m)) {
            $checkOutTime = $m[1] . ':00 ' . strtoupper($m[2]) . strtoupper($m[3]);
        }

        $checkOutDate = $this->getNode('Check-Out Date');

        if (empty($checkOutDate)) {
            $checkOutDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-Out Date'))}]/following::text()[normalize-space()][1]");
        }

        if (!strtotime($checkOutDate) && preg_match("#^(\d{4}) (\w+) (\d+)#i", $checkOutDate, $m)) {
            $checkOutDate = $m[3] . ' ' . $m[2] . ' ' . $m[1];
        }
        $checkOutDate = strtotime($checkOutDate . ',' . $checkOutTime);

        if (!empty($checkOutDate)) {
            $h->booked()
                ->checkOut($checkOutDate);
        }

        $addr = preg_replace(["#\|#", '/\[http.+\.gif\]/'], ['', ''], $this->re("#\n\s*[^\n]*?\s+RESERVATIONS\n.*?\n\s*Email\s*:\s*[^\n]+\s+([^\n]+)#is", $text));

        if (empty($addr)) {
            $addr = implode(', ', $this->http->FindNodes("//img[contains(@src, 'heading_hotel-details')]/following::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]"));
        }

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//p[starts-with(normalize-space(.), 'email:')]/following-sibling::p[normalize-space(.)][1]");
        }

        if (empty($addr)) {
            $addr = implode(', ', array_reverse($this->http->FindNodes("//img[contains(@src, 'phone')]/ancestor::td[1]/following-sibling::td[1]/ancestor::tr[1]/preceding-sibling::tr[position() < 3]")));
        }

        if (empty($addr)) {
            $h->setNoAddress(true);
        } else {
            $h->hotel()
                ->address($addr);
        }

        if (($phone = trim($this->getNode('Tel', '/[\(\) \d\-]+/')))
            || ($phone = $this->http->FindSingleNode("//img[contains(@src, 'phone')]/ancestor::td[1]/following-sibling::td[1]"))
            || ($phone = $this->http->FindSingleNode("//p[starts-with(normalize-space(.), 'email:')]/preceding-sibling::p[starts-with(normalize-space(),'tel:')]", null, false, "/tel: (.+)/"))
        ) {
            $h->hotel()
                ->phone($phone);
        }

        if (($fax = trim($this->getNode('Fax', '/[\(\) \d\-]+/')))
            || ($fax = $this->http->FindSingleNode("//p[starts-with(normalize-space(.), 'email:')]/preceding-sibling::p[starts-with(normalize-space(),'fax:')]", null, false, "/fax: (.+)/"))
        ) {
            $h->hotel()
                ->fax($fax);
        }

        if ($pax = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/following::text()[normalize-space()][1]")) {
            $h->addTraveller($pax);
        } elseif ($pax = $this->http->FindSingleNode("//text()[{$this->starts('Dear')}][1]", null, true, "#Dear\s+([^,\n]+)#")) {
            $h->addTraveller($pax);
        }

        if (null !== ($guests = $this->getNode('Number of Adults', '/\d+/'))) {
            $h->booked()
                ->guests($guests);
        }

        if (null !== ($kids = $this->getNode('Number of Children', '/\d+/'))) {
            $h->booked()
                ->kids($kids);
        }

        if (empty($h->getGuestCount())) {
            $guestInfo = $this->http->FindSingleNode("//text()[normalize-space()='Number of Guests']/ancestor::tr[1]/descendant::td[2]");

            if (preg_match("/^(?<adults>\d+)\s*Adults?\s*(?:\/\s*(?<kids>\d+)\s*Children)?$/", $guestInfo, $m)) {
                $h->booked()
                    ->guests($m['adults']);

                if (isset($m['kids'])) {
                    $h->booked()
                        ->kids($m['kids']);
                }
            }
        }

        if (null !== ($rooms = $this->getNode('Number of Rooms', '/\d+/')) || $rooms = $this->http->FindSingleNode("//text()[normalize-space()='Number of Rooms']/following::text()[normalize-space()][1]")) {
            $h->booked()
                ->rooms($rooms);
        }

        $r = $h->addRoom();
        $rate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Daily Average Rate'))}]", null, false, "/{$this->opt($this->t('Daily Average Rate'))}[:\s]*(.+)/");

        if (empty($rate)) {
            $rate = $this->getNode('Daily Average Rate');
        }

        if ($rate) {
            $r->setRate($rate);
        }

        if ($roomType = $this->getNode('Room Type')) {
            $r->setType($roomType);
        }

        if ($tax = $this->getNode('Tax:')) {
            $h->price()
                ->tax($this->cost($tax));
        }

        if ($fee = $this->getNode('Resort Fee:')) {
            $h->price()
                ->fee('Resort Fee', $this->cost($fee));
        }

        if ($fee = $this->getNode('Resort Fee Tax:')) {
            $h->price()
                ->fee('Resort Fee Tax', $this->cost($fee));
        }

        $total = $this->getNode('Total:');

        if (preg_match('/(\D+)[ ]*([\d\.]+)/', $total, $m)) {
            $h->price()
                ->total($m[2])
                ->currency($this->currency($m[1]));
        }

        if ($cancellation = $this->getNode('Cancellation Policy')) {
            $h->general()
                ->cancellation($cancellation);
        }

        if (!empty($h->getCancellation()) && preg_match('/The deposit is fully refundable upon notice of cancellation at least (\d{1,2} hours) prior to the arrival date/iu', $h->getCancellation(), $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), '{$s}')"; }, $field));
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"" . $s . "\")";
        }, $field));
    }

    private function getNode(string $s, ?string $re = null, string $node = 'td'): ?string
    {
        $res = $this->http->FindSingleNode("//{$node}[({$this->starts($this->t($s))}) and not(.//{$node})][1]/following-sibling::{$node}[normalize-space()!=''][1]", null, true, $re);

        if (!isset($res)) {
            foreach (['table', 'node()', 'text()'] as $node) {
                if ($res = $this->http->FindSingleNode("(//{$node}[({$this->starts($this->t($s))}) and not(.//{$node})][1]/following-sibling::{$node}[normalize-space()!=''][1])[1]", null, true, $re)) {
                    return $res;
                }
            }
        }

        return $res;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function currency($s): ?string
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
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

    private function re($re, ?string $str = null, $c = 1): ?string
    {
        if (empty($str)) {
            $str = $this->http->Response['body'];
        }
        preg_match($re, $str, $m);

        return $m[$c] ?? null;
    }

    private function orval(...$arr): ?string
    {
        foreach ($arr as $item) {
            if (!empty($item)) {
                return $item;
            }
        }

        return null;
    }

    private function cost(string $s): ?string
    {
        if (preg_match('/\D+[ ]*([\d\.]+)/', $s, $m)) {
            return $m[1];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s);
        }, $field)) . ')';
    }
}
