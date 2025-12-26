<?php

namespace AwardWallet\Engine\singaporeair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation3 extends \TAccountChecker
{
    public $mailFiles = "singaporeair/it-151813955.eml, singaporeair/it-297335723.eml, singaporeair/it-4575450.eml, singaporeair/it-49202591.eml, singaporeair/it-4946937.eml, singaporeair/it-5047298.eml, singaporeair/it-5087707.eml, singaporeair/it-5096109.eml, singaporeair/it-5119689.eml, singaporeair/it-6712624.eml, singaporeair/it-6764591.eml, singaporeair/it-717003877.eml, singaporeair/it-717172520.eml, singaporeair/it-8383439.eml";

    public $reBody = [
        'en' => ['Booking', 'Flight'],
    ];
    public $reSubject = [
        'Auto Check-in Successful',
        'Your booking confirmation',
        'Your check-in confirmation',
        'has shared a flight booking',
        'has shared a check-in itinerary with you',
        'has shared a redemption booking itinerary',
        'Your booking has been cancelled',
        'Your booking has been canceled',
        'Your redemption booking has been cancelled',
        'Your redemption booking has been canceled',
        'Ticketing time limit for your upcoming flight(s)',
        'Your booking has been cancelled',
    ];
    /** @var \HttpBrowser */
    public $pdf;
    public $isset_pdf = false;
    public $dateEmail;
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Record locator'                 => ['Booking reference', 'Booking Reference'],
            'Passengers'                     => ['Passengers', 'Pasengers', 'Passenger', 'Pasenger'],
            'Flights'                        => ['Flights', 'Flight'],
            'Seats'                          => 'Seats',
            'ReservationDate'                => ['Date of Issue:', 'Date of issue:'],
            'Ticket'                         => 'Electronic ticket:',
            'Status'                         => 'Status:',
            'Departs'                        => ['Departs', 'Depart', 'Departure'],
            'Arrives'                        => ['Arrives', 'Arrive', 'Arrival'],
            'Checked bags'                   => 'Checked bags:',
            'meal'                           => ['Meal', 'meal', 'Dinner', 'dinner'],
            'You were originally booked on:' => ['You were originally booked on:', 'Travel Restrictions:', 'Original Seat(s):'],
            // 'depart on' => '',
            // 'has been cancelled' => '',
            // 'Cost breakdown' => '',
            'statusPhrases' => [
                'Your booking is',
                'Your booking has been',
                'Your check-in is',
                'Your flight has been',
                'Your redemption booking is',
                'Your redemption booking has been',
                'Your additional baggage purchase is',
            ],
            'statusVariants'                   => ['confirmed', 'rescheduled', 'cancelled', 'canceled'],
            'Your flight has been revised to:' => ['Your flight has been revised to:', 'Reassigned Seat(s):'],
        ],
    ];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->dateEmail = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName("(?!refund).*pdf");

        if (count($pdfs) > 0) {
            $htmlPdfFull = '';

            foreach ($pdfs as $pdf) {
                $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);

                if (preg_match("/\b{$this->opt($this->t('ReservationDate'))}/", $htmlPdf)) {
                    $htmlPdfFull .= $htmlPdf;
                }
            }

            if ($htmlPdfFull) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($htmlPdfFull);
                $this->isset_pdf = true;
            }
        }

        $this->parseEmail($email);

        if (!empty($email->getItineraries()) && $this->http->FindSingleNode("(//node()[" . $this->contains($this->t("re on the waitlist for your redemption booking.")) . "])[1]")) {
            $email->removeItinerary($email->getItineraries()[0]);
            $email->setIsJunk(true);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Singapore Airlines')] | //a[contains(@href,'singaporeair.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'singaporeair.com.sg') !== false
            || stripos($from, 'flightinfo.singaporeair.com') !== false
            || preg_match('/@singaporeair\.com$/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        // General

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confTitle = array_merge((array) $this->t('Record locator'), array_map('strtoupper', (array) $this->t('Record locator')), array_map('strtolower', (array) $this->t('Record locator')), array_map('ucwords', array_map('strtolower', (array) $this->t('Record locator'))));
        $conf = array_filter($this->http->FindNodes("//text()[" . $this->contains($confTitle) . "]", null, "#{$this->opt($this->t('Record locator'))}\s+([A-Z\d]{5,})$#i"));

        if (empty($conf) && (
            ($this->http->XPath->query("//text()[contains(.,'shared a flight booking') or contains(.,'shared a check-in itinerary') or contains(.,'has shared a redemption booking')]")->length > 0)
//            // ??
//            || !empty($this->http->FindSingleNode("//text()[contains(translate(normalize-space(.), '" . strtoupper($this->t('Record locator')) . "', '" . strtolower($this->t('Record locator')) . "'),'" . strtolower($this->t('Record locator')) . "')]", null, true, "#\b{$this->t('Record locator')}\s*$#i"))
            )
        ) {
            $f->general()
                ->noConfirmation();
        } else {
            foreach ($conf as $confirmation) {
                $f->general()
                    ->confirmation($confirmation);
            }
        }

        $passengers = array_filter($this->http->FindNodes("//tr[ *[{$this->eq($this->t('Flights'))}]/following-sibling::*[{$this->eq($this->t('Seats'))}] ]/ancestor::table[1]/descendant::tr[normalize-space() and count(*)=2]/*[1]", null, "/^\d+(?:[ ]*\.[ ]*)+({$this->patterns['travellerName']})$/u"));

        if (count($passengers) === 0) {
            $passengers = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Passengers'))}]/following-sibling::*[normalize-space()][1]/descendant::tr[not(.//tr) and count(*[normalize-space()])=1]", null, "/^\d+(?:[ ]*\.[ ]*)+({$this->patterns['travellerName']})$/u"));
        }

        if (count($passengers) > 0) {
            $passengers = preg_replace("/^\s*(MISS|MSTR|MR|MS|MRS)\s+/i", '', $passengers);
            $f->general()
                ->travellers($passengers, true);
        }

        // Program
        $accounts = array_values(array_filter(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Membership no:')]", null, "#:\s+(.+)#"))));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        $priceNodes = $this->http->XPath->query("//text()[" . $this->eq($this->t('Cost breakdown')) . "]/ancestor::tr[1]/ancestor::*[1]/tr[normalize-space()]");

        foreach ($priceNodes as $proot) {
            $values = $this->http->FindNodes("./td[normalize-space()]", $proot);

            if (empty($values) || count($values) > 2) {
                unset($spentAwards, $cost, $total);

                break;
            }

            if ($values[0] == 'Cost breakdown' || (count($values) == 1 && preg_match("#^\s*([A-Z]{3}|Miles)\s*$#", $values[0], $m))) {
                $currency = trim($values[count($values) - 1]);

                continue;
            }

            if (count($values) == 1) {
                break;
            }

            if (count($values) > 1) {
                if ($values[0] === 'Total miles' && isset($currency) && $currency === 'Miles') {
                    $spentAwards = $values[1] . ' ' . $currency;
                } elseif ($values[0] === 'Total fare' && isset($currency) && $currency !== 'Miles') {
                    $cost = PriceHelper::parse($values[1]);
                } elseif (($values[0] == 'Total cost' || $values[0] == 'TOTAL') && isset($currency) && $currency !== 'Miles') {
                    $total = PriceHelper::parse($values[1]);

                    break;
                } else {
                    $fees[] = ['name' => $values[0], 'amount' => PriceHelper::parse($values[1])];
                }
            }
        }

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards($spentAwards);
        }

        if (isset($total, $currency)) {
            $f->price()
                ->total($total)
                ->currency($currency);

            if (isset($cost)) {
                $f->price()
                    ->cost($cost);
            }

            foreach ($fees as $fee) {
                $f->price()
                    ->fee($fee['name'], $fee['amount']);
            }
        }

        if ($this->isset_pdf) {
            $this->logger->debug('PDF parsing...');

            $status = $this->pdf->FindSingleNode("(//text()[{$this->contains($this->t('Status'))}]/following-sibling::text()[1])[1]");

            if (empty($status)) {
                $status = $this->pdf->FindSingleNode("(//text()[{$this->contains($this->t('STATUS'))}])[1]", null, true, "/{$this->opt($this->t('STATUS'))}\s*\:?\s*(\w+)/");
            }

            if (empty($status)) {
                $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]/preceding::text()[{$this->starts($this->t('Your seat selections are'))}][1]", null, true, "/{$this->opt($this->t('Your seat selections are'))}\s*\:?\s*(\w+)/");
            }

            $f->general()
                ->date2($this->pdf->FindSingleNode("(//text()[{$this->contains($this->t('ReservationDate'))}]/following-sibling::text()[normalize-space()][1])[1]"));

            if (!empty($status)) {
                $f->general()
                    ->status($status);
            }

            $tickets = array_values(array_unique(array_filter($this->pdf->FindNodes("//text()[{$this->contains($this->t('Ticket'))}]/following-sibling::text()[1]", null, "#^\s*(\d+)\s*$#"))));

            if (!empty($tickets)) {
                $f->issued()
                    ->tickets($tickets, false);
            }

            if (empty($f->getAccountNumbers())) {
                $accounts = array_values(array_unique(array_filter($this->pdf->FindNodes("//text()[contains(.,'" . $this->t('KrisFlyer') . "')]/following::text()[normalize-space(.)][1]",
                    null, "#^\s*(\d+)\s*$#"))));

                if (empty($accounts)) {
                    $accounts = array_values(array_unique(array_filter($this->pdf->FindNodes("//text()[contains(.,'" . $this->t('KrisFlyer') . "')]",
                        null, "#KrisFlyer\s*(\d+)\s*$#"))));
                }

                if (!empty($accounts)) {
                    $f->program()
                        ->accounts($accounts, false);
                }
            }
        }

        $cancelText = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('depart on'))} and {$this->contains($this->t('has been cancelled'))}])[1]");

        if (!empty($cancelText)
            && preg_match("/(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5})[ ]*, [\w ]+{$this->opt($this->t('depart on'))} (?<date>.+) from (?<from>.+),\s*{$this->opt($this->t('has been cancelled'))}/", $cancelText, $m)
        ) {
            $f->general()->cancelled();

            $s = $f->addSegment();

            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);

            $s->departure()
                ->name($m['from'])
                ->noCode()
                ->date(strtotime(str_replace('at ', '', $m['date'])));

            $s->arrival()
                ->noCode()
                ->noDate()
            ;

            return;
        }

        if (!empty($this->http->FindSingleNode("//*[{$this->starts($this->t('Record locator'))}]/preceding::text()[normalize-space()][1][" . $this->contains($this->t("has been cancelled")) . "]"))) {
            // it-6712624.eml
            $f->general()->cancelled();
        }

        $SUBNODE_FLIGHT = "//table[thead[.//*[{$this->eq($this->t('Departs'))}] and .//*[{$this->eq($this->t('Arrives'))}]]]/tbody/tr"
        ;
        $nodes = $this->http->XPath->query($SUBNODE_FLIGHT);

        if ($nodes->length === 0) {
            $SUBNODE_FLIGHT = "//table[thead[.//*[{$this->eq($this->t('Departs'))}] and .//*[{$this->eq($this->t('Arrives'))}]]]/*[not(self::thead)]";
            $nodes = $this->http->XPath->query($SUBNODE_FLIGHT);
        }
        $this->logger->debug('$SUBNODE_FLIGHT = ' . print_r($SUBNODE_FLIGHT, true));

        $this->ParseSegment($nodes, $f);
    }

    private function ParseSegment(?\DOMNodeList $nodes, Flight $f): void
    {
        for ($i = 0; $i < $nodes->length; $i++) {
            $root = $nodes->item($i);

            if ($this->http->XPath->query("following-sibling::*[{$this->starts($this->t('Your flight has been revised to:'))}]"
                . " | preceding-sibling::*[{$this->eq($this->t('Travel Restrictions:'))}]", $root)->length > 0
                || $this->http->XPath->query("./td[@colspan=4]", $root)->length > 0
                || $this->http->XPath->query("./ancestor-or-self::*[1][count(.//td[not(.//td)][normalize-space()]) = 1]", $root)->length > 0
                || empty($root->nodeValue)
            ) {
                continue;
            }

            if (count($this->http->FindNodes(".//td[not(.//td)][normalize-space()]", $root)) == 1
                && $this->http->FindSingleNode("(.//text()[{$this->contains($this->t("This waitlist will be cancelled"))}])[1]", $root)
            ) {
                continue;
            }
            $s = $f->addSegment();

            // Airline
            if ($this->http->XPath->query("preceding-sibling::*[normalize-space()][1][{$this->starts($this->t('Layover time:'))}]", $root)->length > 0) {
                $flightAftesLayout = true;
            } else {
                $flightAftesLayout = false;
            }
            $flight = $this->htmlToText($this->http->FindHTMLByXpath("*[1]", null, $root));

            if (empty($flight) && $flightAftesLayout) {
                $flight = $this->htmlToText($this->http->FindHTMLByXpath("preceding-sibling::*[not({$this->starts($this->t('Layover time:'))})][*[1][normalize-space()]][1]/*[1][normalize-space()]", null, $root));
            }

            $airlineName = null;
            $flightNumber = null;

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)(?:[ ]*\n|\s*$)/", $flight, $m)) {
                $airlineName = $m['name'];
                $flightNumber = $m['number'];
            }

            $s->airline()
                ->name($airlineName)
                ->number($flightNumber);

            if (!empty($airlineName) && !empty($flightNumber)) {
                $xpathExtra = "//tr[ *[{$this->eq($this->t('Flights'))}]/following-sibling::*[{$this->eq($this->t('Seats'))}] ]/ancestor::table[1]/descendant::tr[ *[normalize-space()][1][{$this->starts($airlineName . ' ' . $flightNumber)}] ]";

                $seats = array_filter($this->http->FindNodes($xpathExtra . "/*[normalize-space()][2]", null, "/^[ ]*(\d+[A-Z])\b/"));

                if (count($seats) > 0) {
                    $s->extra()
                        ->seats(array_filter($seats));
                }

                $meals = array_filter($this->http->FindNodes($xpathExtra . "/*[{$this->starts($this->t('meal'))} and not(contains(normalize-space(),'No meal selected'))]", null, "/^{$this->opt($this->t('meal'))}\s*[:]+\s*(.+)/i"));

                if (count($meals) === 0) {
                    $meals = $this->http->FindNodes($xpathExtra . "/*[{$this->contains($this->t('meal'))} and not(contains(normalize-space(),'No meal selected')) and not(contains(.,':'))]");
                }

                if (count($meals) > 0) {
                    $s->extra()->meals(array_unique($meals));
                }
            }

            if (preg_match("/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+[ ]*((?:\n+.{2,}){1,2})\n+[ ]*{$this->opt($this->t('Operated by'))}/", $flight, $m)
                || preg_match("/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+\s*\n(.+)\n.+\s*$/", $flight, $m)
            ) {
                $s->extra()->aircraft(preg_replace('/\s+/', ' ', trim($m[1])), true, true);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Operated by'))}[ ]+(.{2,}?)[ ]*$/m", $flight, $m)) {
                $s->airline()->operator($m[1]);
            }

            // SIN 20:55 28 Apr 2020 Singapore, Changi
            // MXP 12:00 20 Oct (Thu) Milan, Malpensa, Terminal 1
            $pattern1 = "/(?<time>{$this->patterns['time']})\s+(\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4}\b)\s*(?<names>[\s\S]*)/u";
            $pattern2 = "/(?<time>{$this->patterns['time']})\s+(\d{1,2}[ ]+[[:alpha:]]+[ ]+\([ ]*[-[:alpha:]]+[ ]*\))\s*(?<names>[\s\S]*)/u";

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("td[2]/descendant::text()[normalize-space(.)!=''][not(contains(normalize-space(.), 'Scheduled Time'))][1]", $root, false, '/^\s*([A-Z]{3})/'));

            $dTerminal = $this->http->FindSingleNode("td[2]", $root, true, "#Terminal\s+(.+)#");

            $node = $this->htmlToText($this->http->FindHTMLByXpath("*[2]", null, $root));

            if (preg_match($pattern1, $node, $m)
                || preg_match($pattern2, $node, $m)
            ) {
                $m['names'] = trim(preg_replace('/\s+/', ' ', $m['names']));

                if (preg_match("#(.+,\s+.+),\s+(.+)#", $m['names'], $mat)) {
                    $s->departure()
                        ->name(trim($mat[1]));
                    $dTerminal = trim(str_ireplace('Terminal', '', $mat[2]));
                } elseif (!empty($m['names'])) {
                    $s->departure()
                        ->name(trim($m['names']));
                }

                $s->departure()
                    ->date(strtotime($m[1], $this->normalizeDate($m[2])));
            }
            $s->departure()
                ->terminal($dTerminal, true, true);

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("td[3]/descendant::text()[normalize-space(.)!=''][not(contains(normalize-space(.), 'Scheduled Time'))][1]", $root, false, '/^\s*([A-Z]{3})/'));

            $aTerminal = $this->http->FindSingleNode("td[3]", $root, true, "#Terminal\s+(.+)#");

            $node = $this->htmlToText($this->http->FindHTMLByXpath("*[3]", null, $root));

            if (preg_match($pattern1, $node, $m)
                || preg_match($pattern2, $node, $m)
            ) {
                $m['names'] = trim(preg_replace('/\s+/', ' ', $m['names']));

                if (preg_match("#(.+,\s+.+),\s+(.+)#", $m['names'], $mat)) {
                    $s->arrival()
                        ->name(trim($mat[1]));
                    $aTerminal = trim(str_ireplace('Terminal', '', $mat[2]));
                } elseif (!empty($m['names'])) {
                    $s->arrival()
                        ->name(trim($m['names']));
                }

                $s->arrival()
                    ->date(strtotime($m[1], $this->normalizeDate($m[2])));
            }
            $s->arrival()
                ->terminal($aTerminal, true, true);

            $rowSpan = $this->http->FindSingleNode("td[1]/@rowspan", $root) ?? 0;
            $i += $rowSpan;

            $node = $this->http->FindSingleNode("following-sibling::tr[position() <= {$rowSpan}][{$this->contains($this->t('Cabin class:'))}]/td[normalize-space()]",
                $root, true, "/{$this->opt($this->t('Cabin class:'))}[: ]*(.+)$/");

            if ($node !== null) {
                if (preg_match("/^([\w ]+?)\s*\(\s*([A-Z]{1,2})\s*\)/", $node, $m)) {
                    // Economy (N)
                    $s->extra()->cabin($m[1])->bookingCode($m[2]);
                } elseif (preg_match("/^\s*\(\s*([A-Z]{1,2})\s*\)\s*$/", $node, $m)) {
                    // (N)
                    $s->extra()->bookingCode($m[1]);
                } else {
                    // Economy
                    $s->extra()->cabin($node);
                }

                if ($flightAftesLayout) {
                    foreach ($f->getSegments() as $seg) {
                        if ($seg->getAirlineName() === $s->getAirlineName()
                            && $seg->getFlightNumber() === $s->getFlightNumber()
                            && empty($seg->getCabin()) && empty($seg->getBookingCode())
                        ) {
                            if (!empty($s->getCabin())) {
                                $seg->extra()->cabin($s->getCabin());
                            }

                            if (!empty($s->getBookingCode())) {
                                $seg->extra()->bookingCode($s->getBookingCode());
                            }
                        }
                    }
                }
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[position() <= {$rowSpan}][{$this->contains($this->t('Flying time:'))}]/td[1]", $root);

            if (!$flightAftesLayout && $node != null) {
                $s->extra()
                    ->duration(trim(substr($node, strpos($node, ':') + 1)), true, true);
            }
        }
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (!is_string($lang) || !is_array($reBody) || empty($reBody[0]) || empty($reBody[1])) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->starts($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->starts($reBody[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            // 02 Jul (Mon)
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\s*\(\s*([-[:alpha:]]+)\s*\)\s*$/u',
        ];
        $out = [
            '$3, $1 $2 year',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#^(?<week>[^\d\s]+),\s+(?<date>\d+\s+\w+.+)#", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $m['date'] = str_replace('year', date('Y', $this->dateEmail), $m['date']);
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
