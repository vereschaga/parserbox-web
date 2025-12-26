<?php

namespace AwardWallet\Engine\xanterra\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ActivityReservation extends \TAccountChecker
{
    public $mailFiles = "xanterra/it-146936383.eml, xanterra/it-78216783.eml, xanterra/it-78650090.eml, xanterra/it-78650182.eml, xanterra/it-78650183.eml, xanterra/it-79100896.eml, xanterra/it-82938836.eml";
    public $subjects = [
        'Your Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Activity Begins'                               => ['Activity Begins', 'Your Confirmation For'],
            'Rate/Package'                                  => ['Rate/Package', 'Thank you for contacting us concerning your reservations'],
            'Deposit Required'                              => ['Deposit Required', 'Dep Required'],
            'Starting At'                                   => ['Starting At', 'Time'],
            'Xanterra Parks & Resorts Central Reservations' => ['Xanterra Parks & Resorts Central Reservations', 'xanterra.com'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@xanterra.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Xanterra Parks & Resorts Central Reservations'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Activity Begins'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Rate/Package'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]xanterra\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();
        $e->setEventType(Event::TYPE_EVENT);
        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Welcome,')]", null, true, "/{$this->opt($this->t('Welcome,'))}\s*(\D+)/"), true)
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #')]", null, true, "/{$this->opt($this->t('Itinerary #'))}\s*([A-Z\d]+)/"), 'Itinerary #');

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Status')]/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $e->general()
                ->status($status);
        }

        if ($status == 'Cancelled') {
            $e->general()
                ->cancelled();
        }

        if ($status !== 'Cancelled') {
            $e->general()
                ->cancellation($this->http->FindSingleNode("//tr/*[normalize-space()='Cancellation']/following-sibling::*[normalize-space()][1]"), false, true);
        }

        $xpathHeader = "//*[ count(tr[normalize-space()])=3 and tr[3][contains(normalize-space(),'[Map & Directions]')] ]";

        $xpathTHead = "//tr[ *[3][{$this->eq($this->t('Starting At'))}] ]";

        $dateStart = $this->http->FindSingleNode($xpathTHead . "/*[2]");
        $timeStart = $this->http->FindSingleNode($xpathTHead . "/following::tr[not(.//tr) and normalize-space()][1]/*[3]");
        $e->booked()
            ->start(strtotime($dateStart . ', ' . $timeStart))
            ->noEnd();

        $name = $this->http->FindSingleNode($xpathTHead . "/following::tr[not(.//tr) and normalize-space()][1]/*[2]", null, true, "/(.+?)(?: Adult| Child)?\s*$/");

        if (!empty($name)) {
            $e->place()
                ->name($name);

            $e->booked()
                ->guests(array_sum($this->http->FindNodes($xpathTHead . "/following::tr[not(.//tr) and normalize-space()][*[2][starts-with(normalize-space(), '{$name}')]]/*[4]")));
        }

        $park = $this->http->FindSingleNode("//text()[preceding::text()[normalize-space()][1][normalize-space()='Your Confirmation For'] and following::text()[normalize-space()][1][starts-with(normalize-space(), 'Itinerary #')]][1]", null, true, "/^\s*(\D+?)(\s+Lodges)?\s*$/");

        $address = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'You must be at the')]", null, true, "/You must be at the (\D+) at/");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup:')]", null, true, "/Pickup:\s*[\d:]+[apm]* at (\D+?)\./");
        }

        if (!empty($park) && !empty($address)
        ) {
            // it-78216783.eml
            $e->setAddress($park . ', ' . $address);
        } else {
            // it-82938836.eml
            $headerLine3 = implode(' ', $this->http->FindNodes($xpathHeader . "/tr[normalize-space()][3]/descendant::text()[normalize-space()]"));

            if (preg_match("/^(?<address>.{3,}?)[ ]+·[ ]+(?<phone>[+(\d][-. \d)(]{5,}[\d)])/", $headerLine3, $m)) {
                $e->place()
                    ->address($m['address'])
                    ->phone($m['phone']);
            }
        }

        $totalPrice = $this->http->FindSingleNode($xpathTHead . "[ *[6][{$this->eq($this->t('Total'))}] ]/following::tr[not(.//tr) and normalize-space()][1]/*[6]");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Deposit Required'))}]/following::text()[normalize-space()][1]", null, true, "/^(\S)/");

            if (empty($currency)) {
                $currency = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'late fee will apply')]", null, true, "/\s*(\S)\s*[\d\.]+\s*{$this->opt($this->t('late fee will apply'))}/");
            }
            $e->price()
                ->total($m['amount'])
                ->currency($currency);
        }

        return true;
    }

    public function ParseHotel(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(), 'Arriving')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $h->general()
                ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Welcome,')]", null, true, "/{$this->opt($this->t('Welcome,'))}\s*(\D+)/"), true)
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #')]", null, true, "/{$this->opt($this->t('Itinerary #'))}\s*([A-Z\d]+)/"), 'Itinerary #');

            $status = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Reservation Status')][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($status)) {
                $h->general()
                    ->status($status);
            }

            if ($status !== 'Cancelled') {
                $cancellation = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Cancellation')][1]/following::text()[string-length()>2][1]", $root);

                if (empty($cancellation)) {
                    $cancellation = $this->http->FindSingleNode("//a[normalize-space()='Reservations Policies']/following::text()[normalize-space()][1]");
                }

                if (empty($cancellation)) {
                    $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary')]/following::text()[starts-with(normalize-space(), 'Deposits and Cancellations:')][1]/following::text()[normalize-space()][1]");
                }

                $h->general()
                    ->cancellation(trim($cancellation, '.'));
            } else {
                $h->general()
                    ->cancelled();
            }

            $h->booked()
                ->guests($this->http->FindSingleNode("./following::text()[normalize-space()='Adults/Children'][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+)\s*\//"))
                ->kids($this->http->FindSingleNode("./following::text()[normalize-space()='Adults/Children'][1]/following::text()[normalize-space()][1]", $root, true, "/\/\s*(\d+)/"))
                ->rooms($this->http->FindSingleNode("./following::text()[normalize-space()='Rooms Reserved'][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+)/"));

            $h->hotel()
                ->name($this->http->FindSingleNode("./preceding::text()[string-length()>2][1]", $root))
                ->address($this->http->FindSingleNode("./following::text()[string-length()>2][1]", $root, true, "/^(.+)\s[·]\s*[+]/u"))
                ->phone($this->http->FindSingleNode("./following::text()[string-length()>2][1]", $root, true, "/^.+\s[·]\s*([+].+)/u"));

            $checkIn = strtotime(str_replace([' after', ' by'], ', ', $this->http->FindSingleNode("./following::text()[normalize-space()='Check-In'][1]/following::text()[normalize-space()][1]", $root, null, "/^(.+)\s\(/")));

            if (empty($checkIn)) {
                $checkIn = strtotime(str_replace([' after', ' by'], ', ', $this->http->FindSingleNode("./following::text()[normalize-space()='Check-In'][1]/following::text()[normalize-space()][1]", $root)));
            }
            $h->booked()
                ->checkIn($checkIn)
                ->checkOut(strtotime(str_replace([' after', ' by'], ', ', $this->http->FindSingleNode("./following::text()[normalize-space()='Check-Out'][1]/following::text()[normalize-space()][1]", $root))));

            if (preg_match("/A penalty equivalent to the first night's room and tax will be assessed if this reservation is cancelled after\s(.+)\./u", $h->getCancellation(), $m)) {
                $h->booked()->deadline(strtotime($m[1]));
            }

            $roomType = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Room Type')][1]/following::text()[string-length()>2][1]", $root);

            if (!empty($roomType)) {
                $room = $h->addRoom();
                $room->setType($roomType);

                $rateType = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Rate/Package')][1]/following::text()[string-length()>2][1]", $root);

                if (!empty($rateType)) {
                    $room->setRateType($rateType);
                }

                $rate = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Nightly Rate')][1]/following::text()[string-length()>2][1]", $root);

                if (!empty($rate)) {
                    $room->setRate($rate);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Room Type'))}]")->length > 0) {
            $this->ParseHotel($email);
        } else {
            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Activity Begins'))}]")->length > 0) {
                $this->ParseEvent($email);
            }
        }
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
