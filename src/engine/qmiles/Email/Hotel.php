<?php

namespace AwardWallet\Engine\qmiles\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-112003311.eml, qmiles/it-112018606.eml, qmiles/it-656078775.eml, qmiles/it-659188689.eml, qmiles/it-6624266.eml";

    public $detectFrom = "qatarairways.com";
    public $detectSubjects = [
        // en
        'Your Discover Qatar Booking Confirmation',
        'Your Qatar Airways Holidays Acknowledgement',
        'Your Qatar Airways Holidays Booking Confirmation',
        'Your Discover Qatar - B2C Booking Confirmation',
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'detectHotel'    => ['Check-in:', 'Check-in Date:'],
            'detectTour'     => ['Tour Timing:', 'Tour Details', 'Departing From:'],
            'detectTransfer' => ['Standard Arrival Transfer'],
            'Tour Timing:'   => ['Tour Timing:', 'Departing From:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking Reference:')]", null, true, "/:\s*([A-Z\d]{5,})\s*$/"));

        if ($this->http->XPath->query("//*[{$this->starts($this->t('detectHotel'))}]")->length > 0) {
            $this->parseHotel($email);
        }

        if ($this->http->XPath->query("//*[{$this->starts($this->t('detectTour'))}]")->length > 0) {
            $this->parseTour($email);
        }

        if ($this->http->XPath->query("//*[{$this->starts($this->t('detectTransfer'))}]")->length > 0) {
            $this->parseTransfer($email);
        }

        // Price
        $totals = $this->http->FindNodes("//*[self::td or self::th][not(.//td) and not(.//th)][{$this->eq(['Payment Received', 'Balance Remaining'])}]/following-sibling::*[self::td or self::th][normalize-space()][1]");

        if (empty($totals)) {
            $totals = $this->http->FindNodes("//*[self::td or self::th][not(.//td) and not(.//th)][normalize-space()='Total Cost is']/following-sibling::*[self::td or self::th][normalize-space()][1]");
        }
        $totalAll = null;

        foreach ($totals as $total) {
            if (preg_match("/^\s*([A-Z]{3})\s(\d[\d, ]*)\s*$/", $total, $m)) {
                $currency = $m[1];
                $totalAll = ($totalAll ?? 0.0) + str_replace([' ', ','], '', $m[2]);
            }
        }

        if ($totalAll !== null) {
            $email->price()
                ->total($totalAll)
                ->currency($currency);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Your Discover Qatar') === false
        ) {
            return false;
        }

        foreach ($this->detectSubjects as $subject) {
            if (strpos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'qatarairways.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".qatarairways.com/") or contains(@href,"discoverqatar.qatarairways.com") or contains(@href,".qatarairwaysholidays.com/") or contains(@href,"www.qatarairwaysholidays.com")]')->length === 0
            && $this->http->XPath->query('//node()[' . $this->contains(['Yours faithfully, Discover Qatar Customer Services', '@qatarairways.com', 'Qatar Airways Holidays Customer Services']) . ']')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//node()[contains(normalize-space(),"Please print and carry your email confirmation with you")]')->length > 0;
    }

    private function parseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//*[{$this->eq($this->t('detectHotel'))}]/ancestor::*[.//img][1]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation()
                ->status($this->http->FindSingleNode("//text()[contains(normalize-space(),'Your reservation is') or contains(normalize-space(),'your request has been received but')]",
                    null, true,
                    "/(?:Your reservation is|your request has been received but)\s+(pending confirmation|confirmed)(?:\s*[,.!]|$)/i"));
            $travellers = array_filter($this->http->FindNodes(".//h3[normalize-space()='Room Details']/following-sibling::*[normalize-space()]/descendant-or-self::tr[not(.//tr)]",
                $root, '/^(?:Adult|Infant)\s+\d{1,3}\s*[:]+\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/i'));

            if (empty($travellers)) {
                $travellers = array_filter($this->http->FindNodes(".//h3[normalize-space()='Room Details']/following-sibling::*[normalize-space()]",
                    $root, '/^(?:Adult|Infant)\s+\d{1,3}\s*[:]+\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/i'));
            }
            $travellers = preg_replace('/^\s*(Mr|Mrs|Miss|Ms) /', '', $travellers);
            $h->general()
                ->travellers(array_values($travellers));

            // Hotel
            $name = $this->http->FindSingleNode(".//tr[contains(., 'Address') and not(descendant::tr)]/ancestor::table[1]/preceding-sibling::*[1]", $root);

            if (empty($name)) {
                $name = $this->http->FindSingleNode(".//tr[starts-with(., 'Address') and not(descendant::tr)]/preceding::td[not(.//td)][normalize-space()][1][preceding::text()[normalize-space()='Hotel Details']]", $root);
            }
            $h->hotel()
                ->name($name)
                ->address($this->http->FindSingleNode(".//tr[contains(.,'Address:') and not(descendant::tr)]", $root,
                    true, '/:\s+(.+)/'));

            // Booked
            $checkinDate = $this->http->FindSingleNode(".//tr[{$this->starts(['Check-in'])} and not(descendant::tr)]", $root);

            if (preg_match('/(\w+\s+\d{1,2},\s+\d{4})\s+\((\d{1,3})\s*nights?\)/i', $checkinDate, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1]))
                    ->checkOut(strtotime("+" . $m[2] . "days", strtotime($m[1])));
            }

            $room = $this->http->FindSingleNode(".//h3[normalize-space()='Room Details']/following-sibling::p[1]", $root);

            if (preg_match('/(.+),\s+(\d+)\s+\w+(?:\s*\+ (\d+) \w+)?,\s+(.+)/', $room, $m)) {
                $h->booked()
                    ->guests($m[2])
                    ->kids($m[3] ?? null, true, true);

                $room = $h->addRoom();

                $room->setType($m[1]);
            }
            $rate = $this->http->FindSingleNode(".//td[not(.//td)][normalize-space()='Average price per room per night:']/following-sibling::td[normalize-space()][1]", $root);

            if (!empty($rate)) {
                if (isset($room)) {
                    $room->setRate($rate);
                } else {
                    $room = $h->addRoom();

                    $room->setRate($rate);
                }
            }
        }

        return true;
    }

    private function parseTour(Email $email)
    {
        $nodes = $this->http->XPath->query("//*[{$this->eq($this->t('detectTour'))}]/ancestor::*[.//img][1]");

        foreach ($nodes as $root) {
            $event = $email->add()->event();

            $event->type()
                ->event();

            // General
            $event->general()
                ->noConfirmation()
                ->status($this->http->FindSingleNode("//text()[contains(normalize-space(),'Your reservation is') or contains(normalize-space(),'your request has been received but')]",
                    null, true,
                    "/(?:Your reservation is|your request has been received but)\s+(pending confirmation|confirmed)(?:\s*[,.!]|$)/i"))
                ->notes($this->http->FindSingleNode("//text()[{$this->eq('Pick-up location:')}]/ancestor::tr[1]"));
            $xpath = 'translate(normalize-space(),"0123456789","dddddddddd")';
            $travellers = array_filter($this->http->FindNodes(".//h3[normalize-space()='Passenger Details']/following::table[1]//tr[*[1][{$this->eq(['Adult d:', 'Adult dd:', 'Infant d:', 'Child d:'], $xpath)}]]/*[2]",
                $root, '/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$/'));
            $travellers = preg_replace('/^\s*(Mr|Mrs|Miss|Ms) /', '', $travellers);
            $event->general()
                ->travellers(array_values($travellers));

            // Hotel
            $name = $this->http->FindSingleNode("preceding::text()[{$this->eq('Your Tour Details')}][1]//following::text()[normalize-space()][1]", $root);
            $event->place()
                ->name($name);

            if (stripos($name, 'Transit Exclusive - Discover Doha') !== false) {
                $event->place()
                    ->address('Doha Airport, Qatar');
            }

            // Booked
            $date = $this->http->FindSingleNode(".//text()[{$this->eq('Booking Date:')}]/following::text()[normalize-space()][1]", $root);
            $times = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Tour Timing:'))}]/following::text()[normalize-space()][1]", $root);

            if (empty($times)) {
                $times = $this->http->FindSingleNode(".//text()[{$this->eq('Tour Details')}]/following::tr[*[2][normalize-space() = 'Type:']]/following::tr[1]/*[2]",
                    $root, true, "/^.+?:\s*(\d{1,2}:\d{2}.*)$/");
            }

            if (!empty($date) && preg_match('/^\s*(\d{1,2}:\d{2}.*?) - (\d{1,2}:\d{2}.*?)\s*$/', $times, $m)) {
                $start = strtotime($date . ', ' . $m[1]);
                $end = strtotime($date . ', ' . $m[2]);

                if (!empty($start) && !empty($end) && $end < $start && strtotime('+1 day', $end) > $start) {
                    $end = strtotime('+1 day', $end);
                }
                $event->booked()
                    ->start($start)
                    ->end($end);
            }
        }

        return true;
    }

    private function parseTransfer(Email $email)
    {
        $nodes = $this->http->XPath->query("//*[{$this->eq($this->t('detectTransfer'))}]/ancestor::*[.//img][1]");

        foreach ($nodes as $root) {
            $t = $email->add()->transfer();

            // General
            $t->general()
                ->noConfirmation()
                ->status($this->http->FindSingleNode("//text()[contains(normalize-space(),'Your reservation is') or contains(normalize-space(),'your request has been received but')]",
                    null, true,
                    "/(?:Your reservation is|your request has been received but)\s+(pending confirmation|confirmed)(?:\s*[,.!]|$)/i"))
                ->notes($this->http->FindSingleNode(".//text()[{$this->eq('Note:')}]/ancestor::*[not({$this->eq('Note:')})][1]",
                    $root));
            $xpath = 'translate(normalize-space(),"0123456789","dddddddddd")';
            $travellers = array_filter($this->http->FindNodes(".//h3[normalize-space()='Passenger Details']/following::table[1]//tr[*[1][{$this->eq(['Adult d:', 'Adult dd:', 'Infant d:', 'Child d:'], $xpath)}]]/*[2]",
                $root, '/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$/'));
            $travellers = preg_replace('/^\s*(Mr|Mrs|Miss|Ms) /', '', $travellers);
            $t->general()
                ->travellers(array_values($travellers));

            // Segment
            $s = $t->addSegment();

            // Booked
            $date = $this->http->FindSingleNode(".//text()[{$this->eq('Booking Date:')}]/following::text()[normalize-space()][1]", $root);
            $time = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Flight Arrival Time:'))}]/following::text()[normalize-space()][1]", $root, null, "/^\s*(\d{1,2}:\d{2})\s*-/");

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $time));
                $s->arrival()
                    ->noDate();
            }

            if (!empty($this->http->XPath->query(".//*[{$this->contains('Meeting point: Discover Qatar Information Desk at the HIA ')}]", $root))) {
                $s->departure()
                    ->name('Hamad International Airport')
                    ->code('DOH');
            }
            $dropoff = $this->http->FindSingleNode(".//text()[{$this->eq('Drop-off location:')}]/following::text()[normalize-space()][1]", $root);

            foreach ($email->getItineraries() as $it) {
                if ($it->getType() == 'hotel' && stripos($it->getHotelName(), $dropoff) === 0) {
                    $dropoff .= ', ' . $it->getAddress();

                    break;
                }
            }
            $s->arrival()
                ->address($dropoff);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }
}
