<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary3 extends \TAccountChecker
{
    public $mailFiles = "priceline/it-389377929.eml, priceline/it-394003135.eml, priceline/it-395905535.eml, priceline/it-396391497.eml, priceline/it-396500186.eml, priceline/it-562700179.eml, priceline/it-562730817.eml, priceline/it-562759843.eml, priceline/it-563433750.eml, priceline/it-563460275.eml, priceline/it-633069168.eml, priceline/it-650065363.eml, priceline/it-650373193.eml, priceline/it-653534562.eml, priceline/it-653838900.eml, priceline/it-848994981.eml, priceline/it-854607195.eml, priceline/it-854927522.eml, priceline/it-859610351.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            // hotel
            'hotelConfirmation'  => ['Hotel confirmation number:', 'Hotel confirmation number :'],
            'checkIn'            => ['Check-in'],
            'checkOut'           => ['Check-out'],
            'getDirections'      => ['GET DIRECTIONS', 'get directions', 'Get Directions', 'Get directions'],
            'statusPhrases'      => ['Congrats, your trip on', 'Congrats, your rental car for'],
            'Your flight to'     => ['Your flight to', 'Your trip to'],
            'statusVariants'     => ['confirmed'],
            'Total charged'      => ['Total charged', 'Total Charged', 'Total Cost:', 'Estimated Total:', 'Estimated Total', 'Total Due'],
            // flight
            'passengers'                                     => ['Passengers:', 'Passengers :'],
            'Passengers, Tickets, and Confirmation Numbers:' => ['Passengers, Tickets, and Confirmation Numbers:', 'Passengers and Tickets Numbers:', 'Passengers and Ticket Numbers'],
            'Taxes and Fees:'                                => ['Taxes and Fees:', 'Taxes and fees:'],
            'Pick-up information'                            => ['Pick-up information'],
            'Ticket Number:'                                 => ['Ticket Number:', 'Ticket Numbers:'],
            'Total charged:'                                 => ['Total charged:', 'Total Charged:'],
        ],
    ];

    private $xpath = [
        'airportCode' => 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"',
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    private $keywords = [
        'sixt' => [
            'Sixt Rent a Car',
        ],
        'hertz' => [
            'Hertz Corporation',
        ],
        'dollar' => [
            'Dollar Rent A Car',
        ],
        'perfectdrive' => [
            'Budget Rent a Car',
        ],
        'rentacar' => [
            'Enterprise Rent-A-Car',
        ],
    ];

    private $year = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travel.priceline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $subjects = [
            'Your priceline itinerary for',
            'Your priceline itinerary -',
            'Su itinerario de Priceline para',
            'Seu itineário Priceline para ',
        ];

        foreach ($subjects as $sub) {
            if (stripos($headers['subject'], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".priceline.com/") or contains(@href,"links.priceline.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This is a transactional email from priceline")]')->length === 0
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
        $email->setType('YourItinerary3' . ucfirst($this->lang));

        if (preg_match("/\b(2\d{3})\b/", implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]")), $m)) {
            $this->year = $m[1];
        } elseif (preg_match("/\b(20\d{2})\b/", implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Your flight to'))}]/following::text()[normalize-space()][1]")), $m)) {
            $this->year = $m[1];
        }

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}\s+.{6,}?\s+{$this->opt($this->t('is'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
        }

        $otaDesc = $otaConfNumber = null;

        $otaConfirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Priceline Trip Number'))} and not(*[normalize-space()][2])]/*[normalize-space()][1]")
            ?? $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Priceline Trip Number'))}])[1]");

        if (preg_match("/^(?<desc>{$this->opt($this->t('Priceline Trip Number'))})[:\s]+(?<number>[-\d]{5,})\D*$/", $otaConfirmation, $m)) {
            $otaDesc = $m['desc'];
            $otaConfNumber = $m['number'];
        }

        if (empty($otaDesc) && empty($otaConfNumber)
            && preg_match("/\((?<desc>Trip ?#|Número de viagem Priceline:|Número de viaje de Priceline:) *(?<number>[\d\-]{8,})\)\D*$/", $parser->getSubject(), $m)) {
            $otaDesc = $m['desc'];
            $otaConfNumber = $m['number'];
        }

        if (preg_match("/^\s*\d+(\-\d+){2,5}\D*$/", $otaConfNumber, $m)) {
            $email->ota()->confirmation($otaConfNumber, $otaDesc);
        }

        // hotel

        if ($this->http->XPath->query("//tr[{$this->eq($this->t('checkIn'))}]/following::tr[{$this->eq($this->t('checkOut'))}]")->length > 0) {
            $h = $email->add()->hotel();
            $h->general()->noConfirmation();

            if (!empty($status)) {
                $h->general()->status($status);
            }
            $this->parseHotel($h);
        }

        // rental

        if ($this->http->XPath->query("//tr[{$this->eq($this->t('PICK UP'))}]/following::tr[{$this->eq($this->t('DROP OFF'))}]")->length > 0) {
            $r = $email->add()->rental();

            if (!empty($status)) {
                $r->general()->status($status);
            }
            $this->parseRental($r);
        }

        // flight

        $flightSegments = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][count(descendant::text()[normalize-space()])=2 and count(descendant::text()[{$this->xpath['airportCode']}])=2] ]");

        // collect segments for it-848994981.eml
        if ($flightSegments->length === 0) {
            $flightSegments = $this->http->XPath->query("//text()[{$this->eq($this->t('Confirmation number:'))}]/ancestor::div[{$this->contains($this->t('Flight'))}][2]");
        }

        if ($flightSegments->length > 0) {
            $f = $email->add()->flight();
            $f->general()->noConfirmation();

            if (!empty($status)) {
                $f->general()->status($status);
            }
            $this->parseFlight($f, $flightSegments);
        }

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

    private function parseHotel(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        // examples: it-396391497.eml, it-396500186.eml

        $totalCharged = null;
        $totalTexts = array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('Total charged'))}]/*[normalize-space()][1]", null, "/{$this->opt($this->t('Total charged'))}[:\s]+(.*\d.*)$/"));

        if (count(array_unique($totalTexts)) === 1) {
            $totalCharged = array_shift($totalTexts);
        }

        if (preg_match("/^(?<currencyCode>[A-Z]{3})[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/", $totalCharged, $matches)
            || preg_match('/^(?:(?<currency>[^\-\d)(]+?)[ ]*)?(?<amount>\d[,.‘\'\d ]*)$/u', $totalCharged, $matches)
        ) {
            // CAD 221.97 (USD )    |    2092.70    |    $135.96
            if (empty($matches['currencyCode'])) {
                $currency = empty($matches['currency']) ? null : $matches['currency'];
            } else {
                $currency = $matches['currencyCode'];
            }
            $currencyCode = !empty($currency) && preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency, false, true)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $roomSubtotal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Subtotal:'))}] ]/*[normalize-space()][2]", null, true, "/^(.*?\d.*?)(?:\s*\/\s*{$this->opt($this->t('night'))})?$/");

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/u', $roomSubtotal, $m)
                || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $roomSubtotal, $m)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $roomSubtotal, $m)
            ) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Taxes and Fees:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/u', $taxes, $m)
                || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
            ) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $fee = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Hotel Fee*:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/u', $fee, $m)
                || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $fee, $m)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $fee, $m)
            ) {
                $h->price()->fee('Hotel Fee', PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $hotelName = $this->http->FindSingleNode("//tr[ following-sibling::tr[{$this->eq($this->t('getDirections'))}] ][position()!=2][following::td[not(.//td)][position() < 7][{$this->eq($this->t('checkIn'))}]]");
        $address = $this->http->FindSingleNode("//tr[ following-sibling::tr[{$this->eq($this->t('getDirections'))}] ][position()!=1][contains(normalize-space(), ' ')][following::td[not(.//td)][position() < 7][{$this->eq($this->t('checkIn'))}]]");

        if (empty($hotelName) && empty($address) && empty($this->http->FindSingleNode("//*[{$this->contains($this->t('getDirections'))}]"))) {
            $rows = $this->http->FindNodes("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::*[{$this->contains($this->t('hotelConfirmation'))}][1][descendant::text()[normalize-space()][3][{$this->starts($this->t('hotelConfirmation'))}]][descendant::text()[normalize-space()][1][ancestor::a]]/descendant::text()[normalize-space()][position() = 1 or position()=2]");

            if (empty($rows)) {
                $rows = $this->http->FindNodes("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::*[{$this->contains($this->t('hotelConfirmation'))}][1][descendant::tr[not(.//tr)][normalize-space()][3][{$this->starts($this->t('hotelConfirmation'))}]][descendant::text()[normalize-space()][1][ancestor::a]]/descendant::text()[normalize-space()][position() = 1 or position()=2]");
            }

            if (count($rows) == 2) {
                $hotelName = $rows[0];
                $address = $rows[1];
            }
        }
        $phone = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('Hotel Phone Number:'))}]", null, true, "/^{$this->opt($this->t('Hotel Phone Number:'))}[:\s]*({$this->patterns['phone']})$/");
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone, false, true);

        $roomConfirmations = [];
        $roomConfirmationsText = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('hotelConfirmation'))}]", null, true, "/^{$this->opt($this->t('hotelConfirmation'))}[:\s]*(.+)$/");

        if (preg_match_all("/{$this->opt($this->t('Room'))}\s+(\d{1,3})\s*[:]+\s*([-A-z\d]{5,40})(?:[ (]|$)/", $roomConfirmationsText, $roomConfMatches)) {
            $roomConfirmations = array_combine($roomConfMatches[1], $roomConfMatches[2]);
        }

        $hConfSubRow = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('hotelConfirmation'))}]/following-sibling::tr[normalize-space()]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Room(s)'))}/", $hConfSubRow, $m)) {
            $h->booked()->rooms($m[1]);
        }

        $dateCheckIn = strtotime($this->http->FindSingleNode("//tr[{$this->eq($this->t('checkIn'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.+ \d{4}$/"));
        $dateCheckOut = strtotime($this->http->FindSingleNode("//tr[{$this->eq($this->t('checkOut'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.+ \d{4}$/"));
        $timeCheckIn = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkIn'))}]/following-sibling::tr[normalize-space()][2]", null, true, "/^(?:.+? )?({$this->patterns['time']})$/");
        $timeCheckOut = $this->http->FindSingleNode("//tr[{$this->eq($this->t('checkOut'))}]/following-sibling::tr[normalize-space()][2]", null, true, "/^(?:.+? )?({$this->patterns['time']})$/");

        if ($dateCheckIn && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        } elseif (!empty($dateCheckIn)) {
            $h->booked()->checkIn($dateCheckIn);
        }

        if ($dateCheckOut && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        } elseif (!empty($dateCheckOut)) {
            $h->booked()->checkOut($dateCheckOut);
        }

        /*
            $ 315.67/night
        */
        $roomPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Price:'))}] ]/*[normalize-space()][2]", null, true, "/^[^\-\d)(]+?[ ]*\d[,.‘\'\d ]*\s*\/\s*{$this->opt($this->t('night'))}$/u");

        $roomTypesText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[{$this->eq($this->t('Room(s)'))}][following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Room Type:'))}]]/following-sibling::tr[normalize-space()]//td[not(.//td)][not({$this->starts($this->t('Reservation Name:'))})]"));

        if (empty($roomTypesText)) {
            $roomTypesText = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[not(.//tr) and {$this->starts($this->t('Room Type:'))}]"));
        }
        $roomTypesParts = $this->splitText($roomTypesText, "/[ ]*(\b{$this->opt($this->t('Room'))}\s+\d{1,3}\s*[:]+\s*.{2,}?)/", true);

        foreach ($roomTypesParts as $rtPart) {
            $room = $h->addRoom();

            if (preg_match("/^{$this->opt($this->t('Room'))}\s+(\d{1,3})\s*[:]+\s*(.{2,}?)[ ]*$/", $rtPart, $matches)) {
                if (preg_match("/^(?<type>.{1,50}?)\s+-\s+(?<desc>.+)$/", $matches[2], $m)) {
                    $room->setType($m['type'])->setDescription($m['desc']);
                } elseif (strlen($matches[2]) > 50) {
                    $room->setDescription($matches[2]);
                } else {
                    $room->setType($matches[2]);
                }

                $matches[1] = (int) $matches[1];

                if (!empty($roomConfirmations[$matches[1]])) {
                    $room->setConfirmation($roomConfirmations[$matches[1]]);
                }
            }

            if ($roomPrice) {
                $room->setRate($roomPrice);
            }
        }

        $reservationNames = $this->http->FindNodes("//tr/*[not(.//tr) and {$this->starts($this->t('Reservation Name:'))}]", null, "/^{$this->opt($this->t('Reservation Name:'))}[:\s]*(.{2,})$/");
        $travellersText = '';

        foreach ($reservationNames as $reservationName) {
            if (preg_match("/^(?<name>.{2,}?)\s*,\s*(?<adults>\d{1,3})\s*{$this->opt($this->t('Adult(s)'))}$/", $reservationName, $m)) {
                $travellersText .= ',' . $m['name'];
                $h->booked()->guests($m['adults']);
            }
        }

        $travellersText = trim($travellersText, ' ,');

        if ($travellersText && preg_match("/^[[:alpha:]][-,.\'’[:alpha:] ]*[[:alpha:]]$/u", $travellersText)) {
            $travellers = array_unique(preg_split('/(\s*,\s*)+/', $travellersText));
            $travellers = array_filter(preg_replace("/^\s*Commencement Group\s*$/", '', $travellers));

            if (!empty($travellers)) {
                $h->general()->travellers($travellers, true);
            }
        }

        $cancellation = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('Cancellation Policy:'))}]", null, true, "/^{$this->opt($this->t('Cancellation Policy:'))}[:\s]*(.{2,})$/")
            ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation Policy:'))}]/following-sibling::tr[starts-with(normalize-space(),'*')]", null, true, "/^[*\s]*(.{2,})$/")
            ?? $this->http->FindSingleNode("//text()[(normalize-space()='Hotel Contact Information')]/following::text()[starts-with(normalize-space(), 'Cancellation Policy:')][1]/following::text()[normalize-space()][1]", null, true, "/^[*\s]*(.{2,})$/")
        ;

        $h->general()->cancellation($cancellation);

        if (preg_match("/^You (?i)may cancell? free of charge until\s+(?<prior>\d{1,3} days?)\s+before arrival\s*\./", $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior']);
        } elseif (preg_match("/^This (?i)booking is fully refundable if you cancell? before\s+(?<date>.{0,15}\d.{0,15})\s+(?<time>{$this->patterns['time']})\./", $cancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
        } elseif (
            preg_match("/^This (?i)booking is Non-Refundable and cannot be amended or modified\./", $cancellation)
            || preg_match("/your Priceline hotel reservation is non-refundable/", $cancellation)
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function parseFlight(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $segments): void
    {
        // examples: it-394003135.eml, it-395905535.eml, it-389377929.eml

        $travellers = $tickets = [];
        $passengersText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('passengers'))}] ]/*[normalize-space()][2]"));
        $passengerRows = preg_split('/\s*\n+\s*/', trim($passengersText));

        foreach ($passengerRows as $pRow) {
            if (preg_match("/^{$this->patterns['travellerName']}$/u", $pRow)) {
                $travellers[] = $pRow;
            } elseif (preg_match("/^(?:{$this->opt($this->t('Ticket Number'))}|[A-Z]{3}[ ]*->[ ]*[A-Z]{3})[: ]*({$this->patterns['eTicket']})$/", $pRow, $m)) {
                $tickets[] = $m[1];
            } else {
                $travellers = $tickets = [];

                break;
            }
        }

        if (empty($passengersText) && empty($travellers) && empty($tickets)) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t("Ticket Number:"))}]/preceding::text()[normalize-space()][1]");
            $tickets = array_filter(preg_split("/\s*,\s*/", implode(",", $this->http->FindNodes("//text()[{$this->eq($this->t("Ticket Number:"))}]/ancestor::tr[1]", null, "/{$this->opt($this->t('Ticket Number:'))}\s*(.+)/"))));
        }

        if (empty($passengersText) && empty($travellers) && empty($tickets)) {
            // Passengers, Tickets, and Confirmation Numbers:
            $passengerRows = $this->http->XPath->query("//tr[not(.//tr)][{$this->eq($this->t('Passengers, Tickets, and Confirmation Numbers:'))}]/following-sibling::tr[normalize-space()]");

            foreach ($passengerRows as $pRow) {
                if (count($this->http->FindNodes("*[not(.//tr)]//text()[normalize-space()]", $pRow)) === 1
                    && preg_match("/^{$this->patterns['travellerName']}$/u", trim($pRow->nodeValue))) {
                    $travellers[] = $pRow->nodeValue;
                } elseif (preg_match("/^[^:]+:[^:]+$/u", $pRow->nodeValue)) {
                    continue;
                } else {
                    $travellers = [];

                    break;
                }
            }
        }

        // Collect tickets for it-848994981.eml
        if (empty($tickets)) {
            $tickets = [];
            $passengerRows = $this->http->XPath->query("//text()[{$this->contains($this->t('Ticket Number:'))}]");

            foreach ($passengerRows as $pRow) {
                $ticketText = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $pRow, true, "/^\s*(\d+(?:[,]\s*\d+)*)\s*$/");
                $ticketNumbers = preg_split('/[,\s*]/', $ticketText);
                $traveller = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $pRow, true, "/^\s*(\D+)\s*$/");

                foreach ($ticketNumbers as $ticketNumber) {
                    if (!empty($traveller) && !empty($ticketNumber)) {
                        $tickets[] = [$ticketNumber, false, $traveller];
                    }
                }
            }
        }

        if (!empty($tickets)) {
            if (!is_array($tickets[0])) {
                $f->issued()->tickets($tickets, false);
            } else {
                foreach ($tickets as $ticket) {
                    $f->issued()->ticket($ticket[0], $ticket[1], $ticket[2]);
                }
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        $currentDate = null;
        $flightType2 = false;

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = null;
            $dateVal = $this->http->FindSingleNode("preceding-sibling::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(count(descendant::text()[normalize-space()])=2 and count(descendant::text()[{$this->xpath['airportCode']}])=2)] ][1]/*[normalize-space()][1]", $root, true, "/^.*\d.*$/");

            if (empty($dateVal)) {
                $dateValText = implode("\n", $this->http->FindNodes("preceding::tr[normalize-space()][1]//text()[normalize-space()]", $root));

                // Collect date for it-848994981.eml
                if (empty($dateValText)) {
                    $dateValText = implode("\n", $this->http->FindNodes("./preceding-sibling::div[normalize-space()][last()]/descendant::text()[normalize-space()]", $root));
                }

                if (preg_match("/\s+•\s*(.+)\n[\dhm ]+$/", $dateValText, $m)) {
                    $dateVal = $m[1];
                    $flightType2 = true;
                } elseif ($flightType2 === true && preg_match("/\s+{$this->opt($this->t('Flight'))}\s+(\d+)/", $dateValText, $m)) {
                    $dateVal = $currentDate;
                } else {
                    $dateVal = $currentDate = null;
                }
            }

            if (preg_match("/^(?<wday>[-[:alpha:]]+)[, ]+(?<date>[[:alpha:]]+[-, ]+\d{1,2}|\d{1,2}[-, ]+[[:alpha:]]+)$/u", $dateVal, $m)) {
                $weekDateNumber = WeekTranslate::number1($m['wday'], $this->lang);
                $date = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ', ' . $this->year, $weekDateNumber);
            } else {
                $date = strtotime($dateVal);
            }

            if ($flightType2 === true) {
                $currentDate = $dateVal;
            }

            $segLeftText = $this->htmlToText($this->http->FindHTMLByXpath("*[normalize-space()][1]", null, $root));
            $segRightText = $this->htmlToText($this->http->FindHTMLByXpath("*[normalize-space()][2]", null, $root));

            // Collect segments it-848994981.eml
            if (stripos($segRightText, "\n") === false) {
                $segRightText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

                if (preg_match("/^\s*(?<leftText>[A-Z]{3}\s+[A-Z]{3})\s*(?<rightText>.+)\s*$/s", $segRightText, $m)) {
                    $segLeftText = $m['leftText'];
                    $segRightText = $m['rightText'];
                    $segLeftText = preg_replace("/\s+/", ' ', $segLeftText);
                    $segRightText = preg_replace("/({$this->opt($this->t('Confirmation number:'))})\s+/", '$1 ', $segRightText);
                    $flightType2 = true;
                }
            }

            // $this->logger->debug($segLeftText);
            // $this->logger->debug($segRightText);

            if (preg_match("/^\s*([A-Z]{3})[-> ]+([A-Z]{3})[ ]*(?:\n|$)/", $segLeftText, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            if (preg_match("/^\s*(.{2,}?)[ ]+{$this->opt($this->t('to'))}[ ]+(.{2,}?)[ ]*\n/i", $segRightText, $m)) {
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
            }

            if ($date && preg_match("/^[ ]*({$this->patterns['time']})[ ]*-[ ]*({$this->patterns['time']})[ ]*(?<overnight>\W+Arrives the next day)? *$/m", $segRightText, $m)) {
                $s->departure()->date(strtotime($m[1], $date));
                $s->arrival()->date(strtotime($m[2], $date));

                if (!empty($s->getArrDate()) && !empty($m['overnight'])) {
                    $s->arrival()->date(strtotime("+ 1 day", $s->getArrDate()));
                }
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Operated by'))}[ ]+(.{2,}?)[ ]*$/im", $segRightText, $m)) {
                $s->airline()->operator($m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Confirmation number:'))}[ ]*([A-Z\d]{5,7})[ ]*$/im", $segRightText, $m)) {
                $s->airline()
                    ->confirmation($m[1]);
            }

            if ($flightType2 !== true) {
                if (preg_match("/^[ ]*(\S.+?)[ ]+{$this->opt($this->t('Flight'))}[ ]+(\d+)[ ]*$/im", $segRightText, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                if (preg_match("/^[ ]*Non[- ]*stop[ ]*$/im", $segRightText, $m)) {
                    $s->extra()->stops(0);
                }

                if (preg_match("/^[ ]*(?<cabin>.{3,}?)[ ]+Class[ ]+-[ ]+(?<aircraft>.{2,}?)[ ]*$/im", $segRightText, $m)) {
                    $s->extra()->cabin($m['cabin']);

                    if (!preg_match("/(?:\bUnknown\b)/i", $m['aircraft'])) {
                        $s->extra()->aircraft($m['aircraft']);
                    }
                }
            } else {
                // Nonstop • 1h 54m • Economy Class
                // American Airlines • Flight 922 • Airbus A321
                // or American Airlines Flight 922 • Airbus A321
                // or American Airlines Flight 922
                if (preg_match("/^\s*(?<al>\S.+?)[ ]*(?:[( ]+{$this->opt($this->t('operated by'))}[ ]+(?<op>\S.+?)[ )]+)?(?:•[ ]*)?{$this->opt($this->t('Flight'))}[ ]+(?<fn>\d+)(?:[ ]*•[ ]*(?<aircraft>.+))?[ ]*$/im", $segRightText, $m)
                    || preg_match("/^[ ]*{$this->opt($this->t('Flight'))}[ ]+(?<fn>\d+)[ ]*• *(?<aircraft>.+)$/im", $segRightText, $m)
                ) {
                    $s->airline()
                        ->number($m['fn']);

                    if (!empty($m['al'])) {
                        $s->airline()
                            ->name($m['al']);
                        $conf = $this->http->FindSingleNode("//text()[{$this->eq(preg_replace("/(.+)/", $m['al'] . ' $1', $this->t('Confirmation Number:')))}]/following::text()[normalize-space()][1]",
                            null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

                        if (!empty($conf)) {
                            $s->airline()
                                ->confirmation($conf);
                        }
                    } else {
                        $s->airline()
                            ->noName();
                    }

                    if (!empty($m['op'])) {
                        $s->airline()
                            ->operator($m['op']);
                    }

                    if (isset($m['aircraft']) && !preg_match("/(?:\bUnknown\b)/i", $m['aircraft'])) {
                        $s->extra()->aircraft($m['aircraft']);
                    }
                }

                if (preg_match("/^[ ]*Non[- ]*stop[ ]* • *(?<duration>[\dhm ]+?)[ ]* • *(?<cabin>.{3,}?)[ ]+Class *$/im", $segRightText, $m)) {
                    $s->extra()
                        ->stops(0)
                        ->cabin($m['cabin'])
                        ->duration($m['duration'])
                    ;
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total charged:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $2920.42
            $matches['amount'] = preg_replace("/^\s*((?:\d{1,3},?)*\d{1,3}\.\d{2})(?:9{3,}|0{3,})\d$/", '$1', $matches['amount']);

            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()
                ->currency($matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $ticketCostsText = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Ticket Cost:'))}] ]/*[normalize-space()][2]");

            $cost = [];

            foreach ($ticketCostsText as $value) {
                if (!empty($currencyCode) && preg_match('/^(?:' . preg_quote($currencyCode, '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/u', $value, $m)
                    || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $value, $m)
                    || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $value, $m)
                ) {
                    $cost[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($cost) == count($ticketCostsText)) {
                $f->price()
                    ->cost(array_sum($cost));
            }

            $taxesText = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Taxes and Fees:'))}] ]/*[normalize-space()][2]", null, '/^.*\d.*$/');

            $tax = [];

            foreach ($taxesText as $value) {
                if (!empty($currencyCode) && preg_match('/^(?:' . preg_quote($currencyCode, '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/u', $value, $m)
                    || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $value, $m)
                    || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $value, $m)
                ) {
                    $tax[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($tax) == count($taxesText)) {
                $f->price()
                    ->tax(array_sum($tax));
            }
        }
    }

    private function parseRental(\AwardWallet\Schema\Parser\Common\Rental $r): void
    {
        $totalCharged = null;
        $totalTexts = array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('Total charged'))}]/*[normalize-space()][1]", null, "/{$this->opt($this->t('Total charged'))}[:\s]+(.*\d.*)$/"));

        if (empty($totalTexts)) {
            $totalTexts = array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('Total charged'))}]", null, "/{$this->opt($this->t('Total charged'))}[:\s]+(.*\d.*)$/"));
        }

        if (count(array_unique($totalTexts)) === 1) {
            $totalCharged = array_shift($totalTexts);
        }

        $currencyCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Prices are in')]", null, true, "/Prices are in ([A-Z]{3})\s*$/");

        if (preg_match("/^(?<currencyCode>[A-Z]{3})[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/", $totalCharged, $matches)
            || preg_match('/^(?:(?<currency>[^\-\d)(]+?)[ ]*)?(?<amount>\d[,.‘\'\d ]*)$/u', $totalCharged, $matches)
        ) {
            // CAD 221.97 (USD )    |    2092.70    |    $135.96
            if (empty($matches['currencyCode'])) {
                $currency = empty($matches['currency']) ? null : $matches['currency'];
            } else {
                $currency = $matches['currencyCode'];
            }

            if (empty($currencyCode)) {
                $currencyCode = !empty($currency) && preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            }

            $r->price()
                ->currency($currencyCode ?? $currency, false, true)
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $subtotal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Subtotal:'))}] ]/*[normalize-space()][2]", null, true, "/^(.*?\d.*?)(?:\s*\/\s*{$this->opt($this->t('night'))})?$/");

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/u', $subtotal, $m)
                || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $subtotal, $m)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $subtotal, $m)
            ) {
                $m['amount'] = preg_replace("/^\s*((?:\d{1,3},?)*\d{1,3}\.\d{2})(?:9{3,}|0{3,})\d$/", '$1', $m['amount']);

                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Fees:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:[ ]*\(|$)/u', $taxes, $m)
                || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
            ) {
                $r->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $company = $this->http->FindSingleNode("//td[{$this->eq($this->t('Rental Car Contact Information'))}]/following::td[not(.//td)][normalize-space()][1]");

        if (!empty($company)) {
            $r->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq(preg_replace('/(.+)/', $company . ' $1', $this->t('Confirmation Number:')))}]/following::text()[normalize-space()][1]"));
            $provider = $this->getRentalProviderByKeyword($company);

            if (!empty($provider)) {
                $r->setProviderCode($provider);
            }
        }

        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Driver Name:'))}]/following::text()[normalize-space()][1]"));

        $datePickUp = $this->normalizeDate($this->http->FindSingleNode("//tr[{$this->eq($this->t('PICK UP'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.+ \d{4}$/"));
        $dateDropOff = $this->normalizeDate($this->http->FindSingleNode("//tr[{$this->eq($this->t('DROP OFF'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.+ \d{4}$/"));
        $timePickUp = $this->http->FindSingleNode("//tr[{$this->eq($this->t('PICK UP'))}]/following-sibling::tr[normalize-space()][2]", null, true, "/^(?:.+? )?({$this->patterns['time']})$/");
        $timeDropOff = $this->http->FindSingleNode("//tr[{$this->eq($this->t('DROP OFF'))}]/following-sibling::tr[normalize-space()][2]", null, true, "/^(?:.+? )?({$this->patterns['time']})$/");

        if ($datePickUp && $timePickUp) {
            $r->pickup()->date(strtotime($timePickUp, $datePickUp));
        }

        if ($dateDropOff && $timeDropOff) {
            $r->dropoff()->date(strtotime($timeDropOff, $dateDropOff));
        }

        $r->pickup()
            ->location($this->re("/^[\s,]*{$this->opt($this->t('Pick-up:'))}[\s,]*(.+)/", implode(', ', $this->http->FindNodes("//td[{$this->eq($this->t('Pick-up information'))}]/following::td[not(.//td)][normalize-space()][1]//text()[normalize-space()]"))));

        $r->dropoff()->same();

        $r->car()
            ->model($this->http->FindSingleNode("//text()[{$this->eq($this->t('Car Type:'))}]/ancestor::td[not(.//td)][normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Car Type:'))}]]/descendant::text()[normalize-space()][2]"), true, true);

        $img = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PICK UP'))}]/preceding::img[1][@alt='[CAR_TYPE]']/@src[contains(., '/vehicles/')]");

        if (!empty($img)) {
            $r->car()
                ->image($img);
        }
    }

    private function getRentalProviderByKeyword(?string $keyword): ?string
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['hotelConfirmation']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['hotelConfirmation'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->eq($phrases['passengers'])}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->eq($phrases['Passengers, Tickets, and Confirmation Numbers:'])}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->eq($phrases['Pick-up information'])}]")->length > 0
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

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date in = '.print_r( $date,true));
        $in = [
            //dom., marzo 10, 2024
            "/^\s*[[:alpha:]\-]+[.]?[,\s]\s*([[:alpha:]]+)\s*(\d{1,2})\s*,\s*(\d{4})\s*$/ui",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } else {
                foreach (['es', 'pt'] as $lang) {
                    if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $lang)) {
                        $date = str_replace($m[1], $en, $date);

                        break;
                    }
                }
            }
        }
        // $this->logger->debug('$date out = '.print_r( $date,true));

        return strtotime($date);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
        $s = preg_replace('/<tr(?: [^>]*)?>/i', "\n", $s); // opening tags
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
