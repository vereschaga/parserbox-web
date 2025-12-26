<?php

namespace AwardWallet\Engine\hawaiian\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmETicket extends \TAccountChecker
{
    public $mailFiles = "hawaiian/it-93383536.eml, hawaiian/it-94661802.eml";
    public $reProvider = 'Hawaiian Airlines';
    public $reBody = [
        'en' => ['Thank you for booking with Hawaiian Airlines', 'Additional Hotel Services'],
    ];
    public $reSubject = [
        'Hawaiian Airlines travel confirmation/e-Ticket',
    ];
    public $lang = 'en';
    public $tot = [];
    public static $dict = [
        'en' => [
            'Hotel' => ['Hotel', 'Sheraton'],
        ],
    ];

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $otaConf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'itinerary #')]", null, true, "/{$this->opt($this->t('itinerary #'))}\s*(\d+)/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D{1}([\d\.\,]+)/u");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\D{1})[\d\.\,]+/");

        if (!empty($total) && !empty($currency)) {
            $email->price()
                ->total(str_replace(',', '', $total))
                ->currency($currency);
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'" . $this->reProvider . "')]")->length > 0) {
            $text = $parser->getHTMLBody();

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "hawaiianairlines.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        //Flight#
        $pax = $this->http->FindNodes("//*[normalize-space(text())='" . $this->t('Traveler Information') . "']/ancestor-or-self::tr[1]/following::table[1]//td[1]//text()[normalize-space(.) and not(normalize-space(.)='Adult') and not(normalize-space(.)='Child')]");
        $tickets = $this->http->FindNodes("//*[normalize-space(text())='Traveler Information']/ancestor-or-self::tr[1]/following::table[1]//td[3]//text()[normalize-space(.)]", null, "#\#\s+([A-Z\d]+)#");
        //$tripNum = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()),'Itinerary #')]/ancestor-or-self::td[1]/following-sibling::td[1]");

        $this->tot = $this->getTotalCurrency(str_replace("$", "USD", $this->http->FindSingleNode("//*[starts-with(normalize-space(text()),'Total Price')]/following::text()[normalize-space(.)][1]")));

        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space(.)='Hawaiian Airlines' or normalize-space(.)='Booking ID']/ancestor-or-self::td[1][contains(@style,'FFFFFF') or contains(@style,'ffffff')]/following-sibling::td[1]"))
            ->travellers($pax);

        if (!empty($tickets)) {
            $f->setTicketNumbers($tickets, false);
        }

        $status = $this->http->FindSingleNode("//text()[normalize-space(.)='Hawaiian Airlines']/ancestor::tr[1]/preceding-sibling::tr[1]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[normalize-space(.)='Booking ID']/preceding::text()[normalize-space()][1]", null, true, "/^[A-Z]+$/");
        }
        $f->general()
            ->status($status);

        $xpath = "//text()[contains(.,'Departure') or contains(.,'Return')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#(.+?)\s*-#", $node, $m)) {
                $date = strtotime($this->normalizeDate($m[1]));
            }

            if (stripos($node, 'nonstop')) {
                $s->extra()
                    ->stops(0);
            }

            $s->departure()
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::table[1]//tr[2]/td[2]", $root));

            $s->arrival()
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::table[1]//tr[2]/td[3]", $root));

            $duration = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::table[1]//tr[2]/td[4]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $nodes = $this->http->FindNodes("./following-sibling::tr[1]/descendant::table[1]//tr[3]/td[1]//text()[normalize-space(.)]", $root);

            if (isset($node[0]) && preg_match("#([A-Z]{3})\s+(\d+:\d+\s*[ap]m)#i", $nodes[0], $m)) {
                $s->departure()
                    ->code($m[1]);

                if (isset($date)) {
                    $s->departure()
                        ->date(strtotime($m[2], $date));
                }
            }

            if (isset($nodes[1])) {
                $s->departure()
                    ->terminal(str_replace('Terminal', '', $nodes[1]));
            }

            $nodes = $this->http->FindNodes("./following-sibling::tr[1]/descendant::table[1]//tr[3]/td[2]//text()[normalize-space(.)]", $root);

            if (isset($node[0]) && preg_match("#([A-Z]{3})\s+(\d+:\d+\s*[ap]m)#i", $nodes[0], $m)) {
                $s->arrival()
                    ->code($m[1]);

                if (isset($date)) {
                    $s->arrival()
                        ->date(strtotime($m[2], $date));
                }
            }

            if (isset($nodes[1])) {
                $s->arrival()
                    ->terminal(str_replace('Terminal', '', $nodes[1]));
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::table[1]//tr[4]/td[1]", $root);

            if (preg_match("#(.+?)\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::table[1]//tr[5]/td[1]", $root);

            if (preg_match("#^(.+?)\s*\/\s*Coach\s*\(\s*([A-Z]{1,2})\s*\)\s*\|(?:\s*Seat\s+(.+?)\s*\||\D+\|\s*(\d{2}[A-Z])\s*\|\s*Confirm)?#", $node, $m)) {
                $m = array_values(array_filter($m));
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);

                if (isset($m[3]) && !empty($m[3])) {
                    $s->extra()
                        ->seat($m[3]);
                }
            }
        }
        //Hotel#
        //general(the same) information of reservations in hotel
        $hotel = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel'))}]/ancestor::tr[./td[contains(@style,'FFFFFF') or contains(@style,'ffffff')]][1]");

        $dateStr = array_map('trim', explode('-', $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel'))}]/ancestor::tr[./td[contains(@style,'FFFFFF') or contains(@style,'ffffff')]][1]/following-sibling::tr[1]")));

        if (stripos($dateStr[1], 'room')) {
            $dateStr[1] = $this->re("/^(.+)\s*\,\s*\d+\s*room/", $dateStr[1]);
        }
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel'))}]/ancestor::tr[./td[contains(@style,'FFFFFF') or contains(@style,'ffffff')]][1]/following::text()[contains(.,'Check-in time starts at')]", null, true, "#\d+(?:\:\d+)?\s*[ap]m\s*$#i");
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel'))}]/ancestor::tr[./td[contains(@style,'FFFFFF') or contains(@style,'ffffff')]][1]/following::text()[contains(.,'Check-in time ends at')]", null, true, "#\d+(?:\:\d+)?\s*[ap]m\s*$#i");

        $node = implode(' ', $this->http->FindNodes("//text()[{$this->contains($this->t('Hotel'))}]/ancestor::tr[./td[contains(@style,'FFFFFF') or contains(@style,'ffffff')]][1]/following::text()[contains(normalize-space(.),'View hotel details')]/ancestor::td[1]//text()[normalize-space(.) and not(contains(normalize-space(.),'View hotel details'))]"));

        if (preg_match("#(.+?)\s*Tel:\s+([\d\s\(\)-]+),\s+Fax:\s+([\d\s\(\)-]+)#", $node, $m)) {
            $addr = $m[1];
            $tel = $m[2];
            $fax = $m[3];
        }
        //reservations in hotel
        $xpath = "//text()[normalize-space()='Room']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[normalize-space()='Room 1']/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $cancellation = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Cancellations')]", $root);

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'CANCELLATION POLICY:')]", $root, true, "/{$this->opt($this->t('CANCELLATION POLICY:'))}\s*(.+)/u");
            }

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            $confirmation = $this->http->FindSingleNode("./following-sibling::tr[1]", $root, true, "/{$this->opt($this->t('Confirmation #:'))}\D+(\d+)/");

            if (!empty($confirmation)) {
                $h->general()
                    ->confirmation($confirmation);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            $h->hotel()
                ->name($hotel)
                ->address($addr)
                ->phone($tel);

            if (!empty($fax)) {
                $h->hotel()
                    ->fax($fax);
            }

            if (isset($dateStr[0])) {
                $h->booked()
                    ->checkIn(strtotime($this->normalizeDate($dateStr[0])));
            }

            if (!empty($timeCheckIn) && !empty($h->getCheckInDate())) {
                $h->booked()
                    ->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
            }

            if (isset($dateStr[1])) {
                $h->booked()
                    ->checkOut(strtotime($this->normalizeDate($dateStr[1])));
            }

            if (!empty($timeCheckOut) && !empty($h->getCheckOutDate())) {
                $h->booked()
                    ->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
            }

            $roomTypeDescription = $this->http->FindSingleNode("./following-sibling::tr[3]/td[3]", $root);

            if (!empty($roomTypeDescription)) {
                $room = $h->addRoom();
                $room->setDescription($roomTypeDescription);
            }

            $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Reserved for')]/following::text()[normalize-space()][string-length()>5][1]");

            if (count($travellers) > 0) {
                $h->general()
                    ->travellers($travellers);
            }

            $adults = null;
            $kids = null;

            $nodes = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Reserved for')]/following::text()[contains(normalize-space(), 'adults')]");

            foreach ($nodes as $node) {
                if (preg_match("#(\d+)\s*adults\s*\,\s*(\d+)\s*child#su", $node, $m) || preg_match("#(\d+)\s*adults#su", $node, $m)) {
                    $adults = $adults + $m[1];

                    if (isset($m[2])) {
                        $kids = $kids + $m[2];
                    }
                }
            }

            if ($adults !== null) {
                $h->booked()
                    ->guests($adults);
            }

            if ($kids !== null) {
                $h->booked()
                    ->kids($kids);
            }

            $rooms = $this->http->FindNodes("//text()[normalize-space()='Reserved for']/preceding::text()[starts-with(normalize-space(), 'Room ')]");

            if (count($rooms) > 0) {
                $h->booked()
                    ->rooms(count($rooms));
            }

            $this->detectDeadLine($h);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\S+)\s*(\d+),\s+(\d+)#',
        ];
        $out = [
            '$2 $1 $3',
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $date));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(' ', '', $m['t']);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/after\s*([\d\:]+a?p?m)\s*\(\D+\)\s*on\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*or/u', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m[3] . ' ' . $m[2] . ' ' . $m[4] . ', ' . $m[1]));
        }

        if (preg_match('/This purchase is non\-refundable and non\-transferable/u', $cancellationText, $m)) {
            $h->booked()->nonRefundable();
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
}
