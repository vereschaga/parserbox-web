<?php

namespace AwardWallet\Engine\trepublic\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Transfer;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "trepublic/it-11678252.eml, trepublic/it-11684616.eml, trepublic/it-11766578.eml, trepublic/it-11772075.eml, trepublic/it-11801398.eml, trepublic/it-11928308.eml, trepublic/it-734798945.eml";

    public static $dictionary = [
        "en" => [
            'otaConfNumber' => ['Travel Republic Booking Ref', 'Travel Republic Booking Reference', 'Flight Booking Receipt'],
            'confNumber'    => ['Your Hotel Reference', 'Provider Booking Reference', "Flight Provider's Reference", 'Airline reference', 'booking reference:', 'Reference:'],
            // 'Seat(s)' => '',
        ],
    ];

    public $lang = "en";

    private $reFrom = "noreply@travelrepublic"; //noreply@travelrepublic.co.uk, noreply@travelrepublic.ie

    private $reSubject = [
        "en"  => "Travel Republic - Booking Confirmation",
        "en2" => "Travel Republic - PLEASE NOTE MINOR FLIGHT TIME CHANGE TO BOOKING",
        "en3" => "Travel Republic - Accommodation Voucher",
    ];

    private $reBody = "Travel Republic";

    private $reBody2 = [
        "en"  => "is confirmed as follows",
        'en2' => 'your accommodation is now confirmed',
    ];

    private $patterns = [
        'otaConfNumber' => '(?:[A-Z]{3}\/)?(?<number>\d{3,23}|[A-Z\d]{5,7})', // SCH/16365522  |  16365522  |  JXEZ8F
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        /*
            Travel Republic:
            Our reference numbers are 8 numbers long and will start with a three letter code such as ACM, EUF or TRN (ACM/10101010)
        */

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['otaConfNumber']}$/");

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
        } elseif (preg_match("/({$this->opt($this->t('otaConfNumber'))})[:\s]+{$this->patterns['otaConfNumber']}$/", $this->http->FindSingleNode("//text()[{$this->contains($this->t('otaConfNumber'))}]"), $m)) {
            $otaConfirmation = $m[2];
            $otaConfirmationTitle = trim($m[1], ': ');
        }

        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        if ($this->http->XPath->query('//text()[normalize-space()="Your Flight"]')->length > 0) {
            $this->parseHtml_1_flight($email);
        }

        if ($this->http->XPath->query('//text()[normalize-space()="Outbound" or normalize-space()="Return"]')->length > 0) {
            $this->parseHtml_2_flight($email);
        }

        if ($this->http->XPath->query('//text()[normalize-space()="ITINERARY:"]')->length > 0) {
            $this->parseHtml_3_flight($email);
        }

        if ($this->http->XPath->query("//tr[starts-with(normalize-space(.), 'Room') and not(.//tr)]")->length > 0) {
            $h = $email->add()->hotel();

            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^[\s:：]*{$this->patterns['otaConfNumber']}$/");

            if ($confirmation) {
                $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
                $h->general()->confirmation($confirmation, $confirmationTitle);
            } elseif (!empty($otaConfirmation) && $this->http->XPath->query("//text()[{$this->starts($this->t('confNumber'))}]")->length === 0) {
                $h->general()->noConfirmation();
            }

            $this->parseHtml_4_hotel($h);
        }

        $transferSegments = $this->http->XPath->query("//tr[{$this->starts($this->t('Itinerary for ∆'), "translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆')")}]/following-sibling::tr[{$this->starts($this->t('Pick-Up:'))}]");

        if ($transferSegments->length > 0) {
            $transfer = $email->add()->transfer();
            $this->parseHtml_5_transfer($transfer, $transferSegments);
        }

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
        return 3 * count(self::$dictionary);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseHtml_1_flight(Email $email): void
    {
        // examples: it-11678252.eml, it-11684616.eml, it-11772075.eml
        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^[\s:：]*{$this->patterns['otaConfNumber']}$/");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]*{$this->patterns['otaConfNumber']}$/", $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]"), $m)) {
            $f->general()->confirmation($m[2], trim($m[1], ': '));
        }

        $travellers = array_map('trim', $this->http->FindNodes("//text()[normalize-space(.) = 'Passengers']/ancestor::tr[1]/following::tr[1]//strong/following::text()[string-length(normalize-space())>1][1]", null, "#^([^\(]+)#"));

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        }

        if (count($travellers) > 0) {
            $f->setTicketNumbers(array_filter($this->http->FindNodes("//text()[" . $this->starts($travellers) . "]", null, "#\s+(\d[\d\- ]{5,})#")), false);
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Booking Cost'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'is confirmed')])[1]"))) {
            $f->general()
                ->status('confirmed');
        }

        $xpath = "//img[contains(@src, '/flyoutbound.png') or contains(@src, '/flyinbound.png')]/ancestor::table[2]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("segments root not found: $xpath");
        }

        $seatsByTraveller = [];
        $seatsTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seat(s)'))}]", null, "/{$this->opt($this->t('Seat(s)'))}\s*[:]+\s*(?:{$this->opt($this->t('Adult'))}\s*\d{1,3}\s+)?(.+)/i"));

        foreach ($seatsTexts as $seatsVal) {
            $seatsByTraveller[] = preg_split('/\s*,\s*/', $seatsVal);
        }

        foreach ($segments as $segNumber => $root) {
            $s = $f->addSegment();

            foreach ($seatsByTraveller as $seats) {
                if (count($seats) === $segments->length && array_key_exists($segNumber, $seats)) {
                    $s->extra()->seat($seats[$segNumber]);
                }
            }

            $flight = $this->http->FindSingleNode("(.//descendant::tr[not(.//tr)])[1]", $root);

            if (preg_match("/(.+?\s\d{4})\s*([A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})(\d+)(?:\s*-\s*(.+))?$/", $flight, $m)) {
                $date = $m[1];
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);

                if (isset($m[4])) {
                    $s->extra()
                        ->cabin($m[4]);
                }
            }

            $time = $this->http->FindSingleNode("(.//descendant::tr[not(.//tr)])[2]/td[normalize-space()][1]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($date . ' ' . $time));
            }

            $time = $this->http->FindSingleNode("(.//descendant::tr[not(.//tr)])[2]/td[normalize-space()][2]", $root);

            if (!empty($date) && !empty($time)) {
                if (preg_match("#(.+?)\(\s*\+\s*(\d+)\s*\)#", $time, $m)) {
                    $date = strtotime($date . ' ' . $m[1]);
                    $date = strtotime("+" . $m[2] . "day", $date);
                    $s->arrival()
                        ->date($date);
                } else {
                    $s->arrival()
                        ->date(strtotime($date . ' ' . $time));
                }
            }

            $duration = $this->http->FindSingleNode("(.//descendant::tr[not(.//tr)])[2]/td[normalize-space()][3]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $node = $this->http->FindSingleNode("(.//descendant::tr[not(.//tr)])[3]/td[normalize-space()][1]", $root);

            if (preg_match("#^\s*([A-Z]{3})\s(.+)#", $node, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->name($m[2]);
            }

            $node = $this->http->FindSingleNode("(.//descendant::tr[not(.//tr)])[3]/td[normalize-space()][2]", $root);

            if (preg_match("#^\s*([A-Z]{3})\s(.+)#", $node, $m)) {
                $s->arrival()
                    ->code($m[1])
                    ->name($m[2]);
            }
        }
    }

    private function parseHtml_2_flight(Email $email): void
    {
        // examples: it-11766578.eml
        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]*{$this->patterns['otaConfNumber']}$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], trim($m[1], ': '));
        }

        $travellers = array_map('trim', $this->http->FindNodes("//text()[normalize-space(.) = 'PASSENGERS:']/ancestor::tr[1]/following::tr[1]//strong/following::text()[string-length(normalize-space())>1][1]", null, "#^([^\(]+)#"));

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Booking Cost'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'is confirmed')])[1]"))) {
            $f->general()
                ->status('confirmed');
        }

        $xpath = "//img[contains(@src, 'flightarrowleft.png')]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->debug("segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("./preceding::tr[normalize-space()][1]", $root);

            if (preg_match("/(.+?\s\d{4})\s*([A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(\d+)$/", $flight, $m)) {
                $date = $m[1];
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
            }

            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#^\s*([A-Z]{3})\s*(\d.+)#", $node, $m)) {
                $s->departure()
                    ->code($m[1]);

                if (!empty($date) && !empty($m[2])) {
                    $s->departure()
                        ->date(strtotime($date . ' ' . $m[2]));
                }
            }

            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#^\s*([A-Z]{3})\s*(\d.+)#", $node, $m)) {
                $s->arrival()
                    ->code($m[1]);

                if (!empty($date) && !empty($m[2])) {
                    $s->arrival()
                        ->date(strtotime($date . ' ' . $m[2]));
                }
            }

            $node = $this->http->FindSingleNode("(./td[2]//text()[normalize-space()])[2]", $root);

            if (preg_match("#(.+?)\(\s*\+\s*(\d+)\s*\)#", $node, $m)) {
                $s->extra()
                    ->duration(trim($m[1]));

                if (!empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime("+" . $m[2] . 'day', $s->getArrDate()));
                }
            } else {
                $s->extra()
                    ->duration($node);
            }
        }
    }

    private function parseHtml_3_flight(Email $email): void
    {
        // examples: it-11801398.eml, it-11928308.eml
        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^[\s:：]*{$this->patterns['otaConfNumber']}$/");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $f->general()->travellers(array_map('trim', $this->http->FindNodes("//text()[normalize-space(.) = 'PASSENGERS:']/ancestor::tr[1]/following::tr[1]//strong/following::text()[string-length(normalize-space())>1][1]", null, "#^([^\(]+)#")));

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Booking Cost'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'is confirmed')])[1]"))) {
            $f->general()
                ->status('confirmed');
        }

        $xpath = "//text()[normalize-space() = 'Depart:']/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("segments root not found: $xpath");
        }

        $seatsByTraveller = [];
        $seatsTexts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Seat(s)'))}]", null, "/{$this->opt($this->t('Seat(s)'))}\s*[:]+\s*(?:{$this->opt($this->t('Adult'))}\s*\d{1,3}\s+)?(.+)/i"));

        foreach ($seatsTexts as $seatsVal) {
            $seatsByTraveller[] = preg_split('/\s*,\s*/', $seatsVal);
        }

        foreach ($segments as $segNumber => $root) {
            $s = $f->addSegment();

            foreach ($seatsByTraveller as $seats) {
                if (count($seats) === $segments->length && array_key_exists($segNumber, $seats)) {
                    $s->extra()->seat($seats[$segNumber]);
                }
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]", $root);

            if (preg_match("/\(\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(?<fn>\d+)\s*\)\s*(?<dep>.+?)\s+-\s+(?<arr>.+?)\s+\d+:\d+\s*\//", $node, $m)
                || preg_match("/:\s*(?<dep>.+?)\s+-\s+(?<arr>.+?)\s+\d+:\d+\s*\/\s*\d+:\d+\s*\(\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(?<fn>\d+)\s*\)/", $node, $m)
            ) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->departure()
                    ->name($m['dep'])
                    ->noCode();

                $s->arrival()
                    ->name($m['arr'])
                    ->noCode();
            }

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode(".//text()[normalize-space() = 'Depart:']/ancestor::td[1]", $root, true, "#:(.+)#")));

            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode(".//text()[normalize-space() = 'Arrive:']/ancestor::td[1]", $root, true, "#:(.+)#")));
        }
    }

    private function parseHtml_4_hotel(Hotel $h): void
    {
        // examples: ???
        $this->logger->debug(__FUNCTION__);

        $room = $this->http->FindSingleNode("//tr[starts-with(normalize-space(.), 'Room') and not(.//tr)]");

        if (preg_match('/\:[ ]+(.+)[ ]+\((\d{1,2}) Adults\)/', $room, $m)) {
            $room = $h->addRoom();
            $room->setDescription($m[1]);

            $h->booked()
                ->guests($m[2]);
        }

        if (empty($room)) {
            $room = $this->http->FindSingleNode("//text()[normalize-space()='Guests']/ancestor::tr[1]/following::tr[1]/descendant::text()[contains(normalize-space(), 'Adults')][last()]");
        }

        if (preg_match("/(\d+)\s*Adults?\,\s*(\d+)\s*Children/", $room, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        if ($names = $this->http->FindNodes("//node()[starts-with(normalize-space(.), 'Lead Guest:') or starts-with(normalize-space(.), 'Adult:')]/following-sibling::text()[normalize-space(.)][1]")) {
            $h->general()
                ->travellers($names);
        }

        $xp = "//tr[starts-with(normalize-space(.), 'Check-in') and contains(normalize-space(.), 'Check-out')]/following-sibling::tr[1]/";

        if ($checkin = $this->http->FindSingleNode($xp . 'td[1][not(contains(normalize-space(), "Notice"))]', null, true, '/(\d{1,2} \w+ \d{2,4})/')) {
            $h->booked()
                ->checkIn(strtotime($checkin));
        }

        if ($checkout = $this->http->FindSingleNode($xp . 'td[2]', null, true, '/(\d{1,2} \w+ \d{2,4})/')) {
            $h->booked()
                ->checkOut(strtotime($checkout));
        }

        if ($rooms = $this->http->FindSingleNode($xp . 'td[4]', null, true, '/(\d{1,2}) Room/')) {
            $h->booked()
                ->rooms($rooms);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Paid'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // £2,919.95
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $xpath = "//tr[starts-with(normalize-space(.), 'Your Accommodation') and not(.//tr)]/following-sibling::tr[.//img][1]/descendant::tr[contains(., 'Address') and not(.//tr)]";

        if ($addr = $this->http->FindSingleNode($xpath, null, true, '/Address[ ]*\:[ ]+(.+)/')) {
            $h->hotel()
                ->address($addr);
        }

        if ($phone = $this->http->FindSingleNode($xpath . '/following-sibling::tr[1]', null, true, '/Phone[ ]*\:[ ]+([\d \(\)\+]+)/')) {
            $h->hotel()
                ->phone($phone);
        }

        if ($name = $this->http->FindSingleNode($xpath . '/preceding-sibling::tr[1]')) {
            $h->hotel()
                ->name($name);
        }
    }

    private function parseHtml_5_transfer(Transfer $t, \DOMNodeList $segments): void
    {
        // examples: it-734798945.eml
        $this->logger->debug(__FUNCTION__);

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Lead Contact'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['travellerName']}$/u");
        $t->general()->traveller($traveller, true);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^[\s:：]*{$this->patterns['otaConfNumber']}$/");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $t->general()->confirmation($confirmation, $confirmationTitle);
        }

        if (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'is confirmed')])[1]"))) {
            $t->general()
                ->status('confirmed');
        }

        $adultsVal = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Itinerary for ∆'), "translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆')")} and not(.//tr[normalize-space()])]", null, true, "/^{$this->opt($this->t('Itinerary for'))}\s+(\d{1,3})\b/i");

        foreach ($segments as $root) {
            $s = $t->addSegment();

            $s->extra()->adults($adultsVal, false, true);

            $departure = preg_replace("/^{$this->opt($this->t('Pick-Up:'))}[:\s]*(.{2,})$/i", '$1', implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $root)));
            $arrival = preg_replace("/^{$this->opt($this->t('Drop-Off:'))}[:\s]*(.{2,})$/i", '$1', implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Drop-Off:'))}]/descendant::text()[normalize-space()]", $root)));

            $nameDep = $timeDep = $dateDep = null;

            if (preg_match($pattern1 = "/^(.{3,}?)\s+{$this->opt($this->t('to meet your'))}\s/", $departure, $m)) {
                $nameDep = $m[1];
            } elseif ($departure) {
                $nameDep = $departure;
            }

            if (preg_match($pattern2 = "/\s{$this->opt($this->t('to meet your'))}\s+({$this->patterns['time']})/", $departure, $m)) {
                $timeDep = $m[1];
            }

            if (preg_match($pattern3 = "/\s{$this->opt($this->t('on'))}\s+(.{4,24}\b\d{4})$/", $departure, $m)) {
                $dateDep = strtotime($m[1]);
            }

            $s->departure()->name($nameDep);

            if ($timeDep && $dateDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            $nameArr = $timeArr = $dateArr = null;

            if (preg_match($pattern1, $arrival, $m)) {
                $nameArr = $m[1];
            } elseif ($arrival) {
                $nameArr = $arrival;
            }

            if (preg_match($pattern2, $arrival, $m)) {
                $timeArr = $m[1];
            }

            if (preg_match($pattern3, $arrival, $m)) {
                $dateArr = strtotime($m[1]);
            }

            $s->arrival()->name($nameArr);

            if ($timeArr && $dateArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            } elseif (!$timeArr && !$dateArr) {
                $s->arrival()->noDate();
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment Details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total Booking Cost'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $t->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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
