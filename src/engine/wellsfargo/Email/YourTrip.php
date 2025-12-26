<?php

namespace AwardWallet\Engine\wellsfargo\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "wellsfargo/it-212268730-flight.eml, wellsfargo/it-212269615-rental.eml, wellsfargo/it-437390529.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'bookingDate'    => ['Booking Date'],
            'orderTotal'     => ['Order Total', 'Order'],
            'statusPhrases'  => ['your trip was'],
            'statusVariants' => ['booked'],
        ],
    ];

    private $subjects = [
        'en' => ['Travel Confirmation'],
    ];

    private $detectors = [
        'en' => ['Booking Summary', 'Flight Details', 'Activity Details', 'Flight Itinerary Details'],
    ];

    private $http2; // for remote html-content

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]wellsfargorewards\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Wells Fargo') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".wellsfargorewards.com/") or contains(@href,"travel.wellsfargorewards.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
        }

        $bookingDate = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Booking Date'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^.*\d.*$/');

        if ($bookingDate !== null) {
            $bookingDate = strtotime($bookingDate);

            if (!$bookingDate) {
                $bookingDate = null;
            }
        }

        /*
            19,909
            points
            or $199.09
        */
        $orderTotal = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Order Total'))}] ]/*[normalize-space()][2]"));

        if (preg_match("/^[ ]*(?<pAmount>\d[,.‘\'\d ]*?)\s+(?<pCurrency>{$this->opt($this->t('points'))})[ ]*$/im", $orderTotal, $matches)) {
            $email->price()->spentAwards($matches['pAmount'] . ' ' . $matches['pCurrency']);
        } elseif (preg_match("/(?:\n[ ]*{$this->opt($this->t('or'))}[ ]+|^[ ]*)(?<currency>[^\-\d)(\n]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*$/u", $orderTotal, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $flightNodes = $this->http->XPath->query("//table[{$this->eq($this->t('Flight Details'))}]/following-sibling::table[normalize-space()][1]");

        if ($flightNodes->length == 0) {
            $flightNodes = $this->http->XPath->query("//table[{$this->eq($this->t('Flight Details'))}]/following::table[normalize-space()][1]");
        }

        if ($flightNodes->length > 0) {
            foreach ($flightNodes as $flightRoot) {
                $this->parseFlight($email, $flightRoot, $status, $bookingDate);
            }
        } else {
            $flightNodes = $this->http->XPath->query("//table[{$this->eq($this->t('Flight Itinerary Details'))}]/following::table[normalize-space()][not({$this->contains($this->t('Booking Date'))})][1]");

            foreach ($flightNodes as $flightRoot) {
                $this->parseFlight2($email, $flightRoot, $status, $bookingDate);
            }
        }

        $rentalNodes = $this->http->XPath->query("//table[{$this->eq($this->t('Car Rental Details'))}]/following-sibling::table[normalize-space()][1]");

        foreach ($rentalNodes as $rentalRoot) {
            $this->parseRental($email, $rentalRoot, $status, $bookingDate);
        }

        $eventNodes = $this->http->XPath->query("//table[{$this->eq($this->t('Activity Details'))}]/following-sibling::table[normalize-space()][1]");

        foreach ($eventNodes as $eventRoot) {
            $this->parseEvent($email, $eventRoot, $status, $bookingDate);
        }

        $email->setType('YourTrip' . ucfirst($this->lang));

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

    private function parseFlight(Email $email, \DOMNode $root, ?string $status, ?int $bookingDate): void
    {
        $f = $email->add()->flight();

        if ($status) {
            $f->general()->status($status);
        }

        if ($bookingDate) {
            $f->general()->date($bookingDate);
        }

        $confNoByFlightNumber = $seatsByFlightNumber = $tickets = [];

        $detailedInfoRows = $this->http->XPath->query("descendant::tr[{$this->eq($this->t('Detailed Traveler Information'))}]/following-sibling::tr[ descendant-or-self::tr/*[normalize-space()][1][{$this->eq($this->t('Flight Number'))}] ]", $root);

        foreach ($detailedInfoRows as $row) {
            $flightNumber = $this->http->FindSingleNode("descendant-or-self::tr[ *[normalize-space()][1][{$this->eq($this->t('Flight Number'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]", $row, true, '/(?:^|.{2}\s+)(\d+)$/');

            if (!$flightNumber) {
                continue;
            }

            $airlineConfirmation = $this->http->FindSingleNode("descendant-or-self::tr[ *[normalize-space()][2][{$this->eq($this->t('Airline Confirmation #'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", $row, true, '/^[A-Z\d]{5,}$/');

            if (!$airlineConfirmation) {
                continue;
            }

            if (array_key_exists($flightNumber, $confNoByFlightNumber)) {
                $confNoByFlightNumber = $seatsByFlightNumber = [];

                break;
            }
            $confNoByFlightNumber[$flightNumber] = $airlineConfirmation;

            $seatNumbers = array_filter($this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Seat Number'))}]", $row, "/{$this->opt($this->t('Seat Number'))}\s*[:]+[\s(]*(\d+\s*[A-Z])(?:\s*\)|$)/"));

            if (count($seatNumbers) > 0) {
                if (array_key_exists($flightNumber, $seatsByFlightNumber)) {
                    $confNoByFlightNumber = $seatsByFlightNumber = [];

                    break;
                }
                $seatsByFlightNumber[$flightNumber] = $seatNumbers;
            }

            $ticketNumbers = array_filter($this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Ticket Number'))}]", $row, "/{$this->opt($this->t('Ticket Number'))}\s*[:]+\s*({$this->patterns['eTicket']})$/"));

            if (count($ticketNumbers) > 0) {
                $tickets = array_merge($tickets, $ticketNumbers);
            }
        }

        $segments = $this->http->XPath->query("descendant::tr[ *[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('From'))}] and *[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('To'))}] ]", $root);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // flight

            $flightText = implode("\n", $this->http->FindNodes('ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]', $segment));

            if (preg_match("/^\s*(?<airline>.{2,}?)(?:[ ]+{$this->opt($this->t('Flight'))})?[ ]+(?<number>\d+)\n/", $flightText, $m)) { // row 1
                $s->airline()->name($m['airline'])->number($m['number']);

                if (array_key_exists($m['number'], $confNoByFlightNumber)) {
                    $s->airline()->confirmation($confNoByFlightNumber[$m['number']]);
                }

                if (array_key_exists($m['number'], $seatsByFlightNumber)) {
                    $s->extra()->seats($seatsByFlightNumber[$m['number']]);
                }
            }

            $date = null;

            if (preg_match("/^.{2,}\n+(.*\d.*)/", $flightText, $m)) { // row 2
                $date = strtotime($m[1]);
            }

            if (preg_match("/^.{2,}\n+.*\d.*\n+(.{2,})/", $flightText, $m)) { // row 3
                if (preg_match("/^{$this->opt($this->t('Operated by'))}\s+(?<operator>.{2,}?)\s+-\s+(?<cabin>.{2,})$/", $m[1], $m2)) {
                    $s->airline()->operator($m2['operator']);
                    $s->extra()->cabin($m2['cabin']);
                } else {
                    $s->extra()->cabin($m[1]);
                }
            }

            if (preg_match("/^.{2,}\n+.*\d.*\n+.{2,}\n+(.{2,})/", $flightText, $m)) { // row 4
                if (preg_match("/non[- ]*stop/i", $m[1])) {
                    $s->extra()->stops(0);
                } elseif (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Stop'))}/i", $m[1], $m2)) {
                    $s->extra()->stops($m2[1]);
                }
            }

            /*
                To
                Heathrow (LHR)
                4:15pm
                Arrives Saturday, Jun 03
            */
            $pattern = "/^\s*(?:{$this->opt($this->t('From'))}|{$this->opt($this->t('To'))})\n+(?<airport>.{3,})\n+(?<time>{$this->patterns['time']}).*(?:\n+{$this->opt($this->t('Arrives'))}[ ]+(?<date>.{3,}?))?\s*$/u";

            // departure

            $departureTexts = [];

            $departureRows = $this->http->XPath->query("*[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]", $segment);

            foreach ($departureRows as $depRow) {
                $departureTexts[] = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $depRow));
            }

            $departure = implode("\n", $departureTexts);

            if (preg_match($pattern, $departure, $matches)) {
                if (preg_match('/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/', $matches['airport'], $m)) {
                    $s->departure()->name($m['name'])->code($m['code']);
                } elseif (preg_match('/^[\s(]*([A-Z]{3})[)\s]*$/', $matches['airport'], $m)) {
                    $s->departure()->code($m[1]);
                } else {
                    $s->departure()->name($matches['airport']);
                }

                if ($date) {
                    $s->departure()->date(strtotime($matches['time'], $date));
                }
            }

            // arrival

            $arrivalTexts = [];

            $arrivalRows = $this->http->XPath->query("*[normalize-space()][2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]", $segment);

            foreach ($arrivalRows as $arrRow) {
                $arrivalTexts[] = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $arrRow));
            }

            $arrival = implode("\n", $arrivalTexts);

            if (preg_match($pattern, $arrival, $matches)) {
                if (preg_match('/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/', $matches['airport'], $m)) {
                    $s->arrival()->name($m['name'])->code($m['code']);
                } elseif (preg_match('/^[\s(]*([A-Z]{3})[)\s]*$/', $matches['airport'], $m)) {
                    $s->arrival()->code($m[1]);
                } else {
                    $s->arrival()->name($matches['airport']);
                }

                if (!empty($matches['date'])) {
                    if (!empty($s->getDepDate())) {
                        $dateArr = EmailDateHelper::parseDateRelative($matches['date'], $s->getDepDate(), true, '%D%, %Y%');
                        $s->arrival()->date(strtotime($matches['time'], $dateArr));
                    }
                } elseif ($date) {
                    $s->arrival()->date(strtotime($matches['time'], $date));
                }
            }
        }

        $travellers = [];
        $travellerRows = $this->http->XPath->query("descendant::tr[ *[normalize-space()][1][{$this->eq($this->t('Traveler(s)'))}] ]/following-sibling::tr[normalize-space()]", $root);

        foreach ($travellerRows as $tRow) {
            if ($this->http->XPath->query("descendant-or-self::*[{$this->contains($this->t('Detailed Traveler Information'))}]", $tRow)->length > 0) {
                break;
            }
            $travellers[] = $this->http->FindSingleNode("*[1]/descendant::*[count(tr[not(.//tr) and normalize-space()])=2][1]/tr[not(.//tr) and normalize-space()][1]", $tRow, true, "/^{$this->patterns['travellerName']}$/u");
        }

        $f->general()->travellers($travellers, true);

        if (count($tickets) > 0) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        if ($segments->length > 0) {
            $f->general()->noConfirmation();
        }
    }

    private function parseFlight2(Email $email, \DOMNode $root, ?string $status, ?int $bookingDate): void
    {
        $f = $email->add()->flight();

        if ($status) {
            $f->general()->status($status);
        }

        if ($bookingDate) {
            $f->general()->date($bookingDate);
        }

        $confNoByFlightNumber = $seatsByFlightNumber = $tickets = [];

        $detailedInfoRows = $this->http->XPath->query("descendant::tr[{$this->eq($this->t('Detailed Traveler Information'))}]/following-sibling::tr[ descendant-or-self::tr/*[normalize-space()][1][{$this->eq($this->t('Flight Number'))}] ]", $root);

        foreach ($detailedInfoRows as $row) {
            $flightNumber = $this->http->FindSingleNode("descendant-or-self::tr[ *[normalize-space()][1][{$this->eq($this->t('Flight Number'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]", $row, true, '/(?:^|.{2}\s+)(\d+)$/');

            if (!$flightNumber) {
                continue;
            }

            $airlineConfirmation = $this->http->FindSingleNode("descendant-or-self::tr[ *[normalize-space()][2][{$this->eq($this->t('Airline Confirmation #'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", $row, true, '/^[A-Z\d]{5,}$/');

            if (!$airlineConfirmation) {
                continue;
            }

            if (array_key_exists($flightNumber, $confNoByFlightNumber)) {
                $confNoByFlightNumber = $seatsByFlightNumber = [];

                break;
            }
            $confNoByFlightNumber[$flightNumber] = $airlineConfirmation;

            $seatNumbers = array_filter($this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Seat Number'))}]", $row, "/{$this->opt($this->t('Seat Number'))}\s*[:]+[\s(]*(\d+\s*[A-Z])(?:\s*\)|$)/"));

            if (count($seatNumbers) > 0) {
                if (array_key_exists($flightNumber, $seatsByFlightNumber)) {
                    $confNoByFlightNumber = $seatsByFlightNumber = [];

                    break;
                }
                $seatsByFlightNumber[$flightNumber] = $seatNumbers;
            }

            $ticketNumbers = array_filter($this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Ticket Number'))}]", $row, "/{$this->opt($this->t('Ticket Number'))}\s*[:]+\s*({$this->patterns['eTicket']})$/"));

            if (count($ticketNumbers) > 0) {
                $tickets = array_merge($tickets, $ticketNumbers);
            }
        }

        $this->logger->warning("descendant::tr[ *[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('From'))}] and *[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('To'))}] ]");
        $segments = $this->http->XPath->query("descendant::tr[ *[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('From'))}] and *[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1][{$this->eq($this->t('To'))}] ]", $root);
        //$segments = $this->http->XPath->query("descendant::tr[ *[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1][(normalize-space()='From')] and *[normalize-space()][2]]", $root);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // flight

            $flightText = implode("\n", $this->http->FindNodes('ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]', $segment));
            $this->logger->debug($flightText);

            if (preg_match("/^\s*(?<airline>.{2,}?)(?:[ ]+{$this->opt($this->t('Flight'))})?[ ]+(?<number>\d+)\n/", $flightText, $m)) { // row 1
                $s->airline()->name($m['airline'])->number($m['number']);

                if (array_key_exists($m['number'], $confNoByFlightNumber)) {
                    $s->airline()->confirmation($confNoByFlightNumber[$m['number']]);
                }

                if (array_key_exists($m['number'], $seatsByFlightNumber)) {
                    $s->extra()->seats($seatsByFlightNumber[$m['number']]);
                }
            }

            $date = null;

            if (preg_match("/^.{2,}\n+(.*\d.*)/", $flightText, $m)) { // row 2
                $date = strtotime($m[1]);
            }

            if (preg_match("/^.{2,}\n+.*\d.*\n+(.{2,})/", $flightText, $m)) { // row 3
                if (preg_match("/^(?<aircraft>.+)\s*\-\s*(?<cabin>\D+)$/mu", $m[1], $m2)) {
                    if (trim($m2['aircraft']) !== 'null') {
                        $s->extra()
                            ->aircraft($m2['aircraft']);
                    }

                    $s->extra()->cabin($m2['cabin']);
                }
            }

            if (preg_match("/^.{2,}\n+.*\d.*\n+.{2,}\n+(.{2,})/", $flightText, $m)) { // row 4
                if (preg_match("/non[- ]*stop/i", $m[1])) {
                    $s->extra()->stops(0);
                } elseif (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Stop'))}/i", $m[1], $m2)) {
                    $s->extra()->stops($m2[1]);
                }
            }

            /*
                To
                Heathrow (LHR)
                4:15pm
                Arrives Saturday, Jun 03
            */
            $pattern = "/^\s*(?:{$this->opt($this->t('From'))}|{$this->opt($this->t('To'))})\n+(?<airport>.{3,})\n+(?<date>.*\d{4})\s*(?<time>{$this->patterns['time']}).*/u";
            $this->logger->debug($pattern);

            // departure

            $departureTexts = [];

            $departureRows = $this->http->XPath->query("*[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]", $segment);

            foreach ($departureRows as $depRow) {
                $departureTexts[] = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $depRow));
            }

            $departure = implode("\n", $departureTexts);

            $this->logger->error($departure);

            if (preg_match($pattern, $departure, $matches)) {
                if (preg_match('/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/', $matches['airport'], $m)) {
                    $s->departure()->name($m['name'])->code($m['code']);
                } elseif (preg_match('/^[\s(]*([A-Z]{3})[)\s]*$/', $matches['airport'], $m)) {
                    $s->departure()->code($m[1]);
                } else {
                    $s->departure()->name($matches['airport']);
                }

                $s->departure()->date(strtotime($matches['date'] . ',' . $matches['time']));
            }

            // arrival

            $arrivalTexts = [];

            $arrivalRows = $this->http->XPath->query("*[normalize-space()][2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]", $segment);

            foreach ($arrivalRows as $arrRow) {
                $arrivalTexts[] = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $arrRow));
            }

            $arrival = implode("\n", $arrivalTexts);

            if (preg_match($pattern, $arrival, $matches)) {
                if (preg_match('/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/', $matches['airport'], $m)) {
                    $s->arrival()->name($m['name'])->code($m['code']);
                } elseif (preg_match('/^[\s(]*([A-Z]{3})[)\s]*$/', $matches['airport'], $m)) {
                    $s->arrival()->code($m[1]);
                } else {
                    $s->arrival()->name($matches['airport']);
                }

                $s->arrival()->date(strtotime($matches['date'] . ', ' . $matches['time']));
            }
        }

        $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Seat Number:')]/preceding::text()[normalize-space()][1]");

        $f->general()->travellers(array_unique($travellers), true);

        if (count($tickets) > 0) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        if ($segments->length > 0) {
            $f->general()->noConfirmation();
        }
    }

    private function parseRental(Email $email, \DOMNode $root, ?string $status, ?int $bookingDate): void
    {
        $car = $email->add()->rental();

        if ($status) {
            $car->general()->status($status);
        }

        if ($bookingDate) {
            $car->general()->date($bookingDate);
        }

        $xpathRefNumbers = "descendant::*[count(tr[normalize-space()])=2][1]";

        $referenceNumber1 = $this->http->FindSingleNode($xpathRefNumbers . "/tr[normalize-space()][2]/*[1]", $root, true, '/^[-A-Z\d]{5,}$/');

        if ($referenceNumber1) {
            $referenceNumber1Title = $this->http->FindSingleNode($xpathRefNumbers . "/tr[normalize-space()][1]/*[1]", $root, true, '/^(.+?)[\s:：]*$/u');
            $car->general()->confirmation($referenceNumber1, $referenceNumber1Title);
        }

        $referenceNumber2 = $this->http->FindSingleNode($xpathRefNumbers . "/tr[normalize-space()][2]/*[2]", $root, true, '/^[-A-Z\d]{5,}$/');

        if ($referenceNumber2) {
            $referenceNumber2Title = $this->http->FindSingleNode($xpathRefNumbers . "/tr[normalize-space()][1]/*[2]", $root, true, '/^(.+?)[\s:：]*$/u');
            $car->general()->confirmation($referenceNumber2, $referenceNumber2Title);
        }

        $xpathNextTable = 'following-sibling::table[normalize-space()][1]';

        $carModel = $this->http->FindSingleNode($xpathNextTable . "/descendant::tr[not(.//tr) and normalize-space()][1][count(*[normalize-space()])=1]", $root);
        $car->car()->model($carModel);

        // pickUp

        $xpathPickUp = $xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Pick-Up'))}] ]";

        $pickUp = implode("\n", $this->http->FindNodes($xpathPickUp . "/*[normalize-space()][2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]", $root));

        if (preg_match("/^\s*(.*\d.*)\n/", $pickUp, $m)) { // row 1
            $car->pickup()->date2($m[1]);
        }

        if (preg_match("/^\s*.*\d.*\n+(.{3,})/", $pickUp, $m)) { // row 2
            $car->pickup()->location($m[1]);
        }

        $hoursPickUp = $this->http->FindSingleNode($xpathPickUp . "/following-sibling::tr[ *[normalize-space()][1][{$this->eq($this->t('Hours of Operation'))}] ]/*[normalize-space()][2]", $root);

        $phonePickUp = $this->http->FindSingleNode($xpathPickUp . "/following-sibling::tr[ *[normalize-space()][1][{$this->eq($this->t('Phone Number'))}] ]/*[normalize-space()][2]", $root, true, "/^{$this->patterns['phone']}$/");

        $car->pickup()->openingHours($hoursPickUp)->phone($phonePickUp);

        // dropOff

        $xpathDropOff = $xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Return'))}] ]";

        $dropOff = implode("\n", $this->http->FindNodes($xpathDropOff . "/*[normalize-space()][2]/descendant::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]", $root));

        if (preg_match("/^\s*(.*\d.*)\n/", $dropOff, $m)) { // row 1
            $car->dropoff()->date2($m[1]);
        }

        if (preg_match("/^\s*.*\d.*\n+(.{3,})/", $dropOff, $m)) { // row 2
            $car->dropoff()->location($m[1]);
        }

        $hoursDropOff = $this->http->FindSingleNode($xpathDropOff . "/following-sibling::tr[ *[normalize-space()][1][{$this->eq($this->t('Hours of Operation'))}] ]/*[normalize-space()][2]", $root);

        $phoneDropOff = $this->http->FindSingleNode($xpathDropOff . "/following-sibling::tr[ *[normalize-space()][1][{$this->eq($this->t('Phone Number'))}] ]/*[normalize-space()][2]", $root, true, "/^{$this->patterns['phone']}$/");

        $car->dropoff()->openingHours($hoursDropOff)->phone($phoneDropOff);

        $company = $this->http->FindSingleNode($xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][{$this->eq($this->t('Company'))}] ]/*[normalize-space()][2]", $root);
        $car->extra()->company($company);

        $carType = $this->http->FindSingleNode($xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][{$this->eq($this->t('Vehicle Type'))}] ]/*[normalize-space()][2]", $root);
        $car->car()->type($carType);

        $driver = $this->http->FindSingleNode($xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][{$this->eq($this->t('Driver Information'))}] ]/*[normalize-space()][2]", $root, true, "/^({$this->patterns['travellerName']})(?:\s*,\s*\d{1,3})?$/u");
        $car->general()->traveller($driver, true);
    }

    private function parseEvent(Email $email, \DOMNode $root, ?string $status, ?int $bookingDate): void
    {
        $ev = $email->add()->event();
        $ev->place()->type(Event::TYPE_EVENT);

        if ($status) {
            $ev->general()->status($status);
        }

        if ($bookingDate) {
            $ev->general()->date($bookingDate);
        }

        $confirmation = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Reference Number'))}]/following-sibling::tr[normalize-space()][1]", $root, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Reference Number'))} and following-sibling::tr[normalize-space()]]", $root, true, '/^(.+?)[\s:：]*$/u');
            $ev->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathNextTable = 'following-sibling::table[normalize-space()][1]';

        $eventName = $this->http->FindSingleNode($xpathNextTable . "/descendant::tr[not(.//tr) and normalize-space()][1]", $root);
        $address = null;

        $linkVoucher = $this->http->FindSingleNode($xpathNextTable . "/descendant::tr[{$this->eq($this->t('View/Print Voucher'))}]/descendant::a[normalize-space(@href)]/@href", $root);

        if ($linkVoucher) {
            $this->http2 = clone $this->http;
            $this->http2->GetURL($linkVoucher);

            $address = implode(' ', $this->http2->FindNodes("//*[contains(@class,'location-points-detail')]/descendant::text()[normalize-space()]"));

            if (!$address) {
                $address = implode(' ', $this->http2->FindSingleNode("//*[{$this->eq($this->t('Meeting Point'))}]]/following-sibling::*[normalize-space()][1]"));
            }

            if (!$address) {
                $address = $this->http2->FindSingleNode("//text()[{$this->starts($this->t('Departure Point:'))}]", null, true, "/{$this->opt($this->t('Departure Point:'))}/");
            }
        }

        $ev->place()->name($eventName)->address($address);

        $date = strtotime($this->http->FindSingleNode($xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Date'))}] ]/*[normalize-space()][2]", $root, true, '/^.*\d.*$/'));

        $time = $this->http->FindSingleNode($xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Option'))}] ]/*[normalize-space()][2]", $root, true, "/\b{$this->patterns['time']}$/u");

        if ($date && $time) {
            $ev->booked()->start(strtotime($time, $date))->noEnd();
        }

        $travellers = array_filter($this->http->FindNodes($xpathNextTable . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest(s)'))}] ]/ancestor::table[1]/descendant::tr[not(.//tr)]/*[last()]", $root, "/^({$this->patterns['travellerName']})(?:\s*,\s*\d{1,3})?$/u"));
        $ev->general()->travellers(array_unique($travellers), true);

        $cancellation = implode(' ', $this->http->FindNodes($xpathNextTable . "/descendant::*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}] ]/tr[normalize-space()][2]/descendant::text()[normalize-space()]", $root));
        $ev->general()->cancellation($cancellation);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['bookingDate']) || empty($phrases['orderTotal'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['bookingDate'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['orderTotal'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
