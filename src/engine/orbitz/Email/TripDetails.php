<?php

namespace AwardWallet\Engine\orbitz\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class TripDetails extends \TAccountChecker
{
    public $mailFiles = "orbitz/it-22.eml, orbitz/it-2122519.eml, orbitz/it-2192126.eml, orbitz/it-2367367.eml, orbitz/it-2423536.eml, orbitz/it-2423539.eml, orbitz/it-2427291.eml, orbitz/it-2210356.eml, orbitz/it-2210665.eml, orbitz/it-2212823.eml, orbitz/it-2217357.eml, orbitz/it-2224181.eml, orbitz/it-2253226.eml, orbitz/it-2253227.eml, orbitz/it-2253228.eml, orbitz/it-2256022.eml, orbitz/it-2107957.eml, orbitz/it-11.eml, orbitz/it-1844700.eml, orbitz/it-1991464.eml, orbitz/it-1991742.eml, orbitz/it-3105699.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'otaNumber' => [
                'Booking record locator:',
                'Orbitz record locator:', 'Orbitz Record Locator:',
                'CheapTickets record locator:', 'CheapTickets Record Locator:',
            ],
            'confNumber'              => ['Airline record locator:', 'Hotel Confirmation number:', 'Confirmation number:'],
            'Loyalty program'         => ['Loyalty program', 'Loyalty programs:'],
            'dep'                     => ['Depart', 'Check-in', 'Pick-up'],
            'arr'                     => ['Arrive', 'Check-out', 'Drop-off'],
            'datePrefix'              => ['Leave', 'Return', 'Flight'],
            'statusVariants'          => ['confirmed', 'cancelled', 'canceled'],
            'feesNames'               => ['Trip Protector'],
            'Total'                   => ['Total', 'Total trip cost'],
            'Total charges:'          => ['Total charges:', 'Total charges :'],
            'Car rental cost summary' => ['Car rental cost summary', 'Car cost summary'],
            'cancelledPhrases'        => [
                'This reservation has been cancelled.', 'This reservation has been canceled.',
                ', this reservation has beeb cancelled please', ', this reservation has beeb canceled please',
            ],
        ],
    ];

    private $subjects = [
        'en' => ['Trip Summary -', 'Trip Details -', 'Your Itinerary'],
    ];

    private $detectors = [
        'en' => ['Flight reservation', 'Hotel reservation', 'Car rental reservation'],
    ];

    private $providerCode = '';

    private $xpath = [
        'bold' => '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])',
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?',
        'phone'         => '[+(\d][-. \d)(]{5,}[\d)]',
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
        'programNames'  => '(?:United Airlines Mileage Plus|Delta Air Lines SkyMiles|CEA Eastern Miles|US Airways Dividend Miles|JetBlue TrueBlue|Marriott Rewards)',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@orbitz.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format and Language
        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('TripDetails' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        // FLIGHTS
        $flightNodes = $this->http->XPath->query("//div[{$this->eq($this->t('Flight reservation'))}]/following-sibling::div[normalize-space()][1]");
        $flightPriceNodes = $this->http->XPath->query("//h3[{$this->eq($this->t('Flight cost summary'))}]/following-sibling::div[normalize-space()][1]");

        foreach ($flightNodes as $key => $fNode) {
            $f = $email->add()->flight();
            $this->parseFlight($f, $fNode);

            if ($flightNodes->length === $flightPriceNodes->length && !empty($flightPriceNodes->item($key))) {
                $this->parseFlightPrice($f, $flightPriceNodes->item($key));
            }
        }

        // HOTELS
        $hotelNodes = $this->http->XPath->query("//div[{$this->eq($this->t('Hotel reservation'))}]/following-sibling::div[normalize-space()][1]");
        $hotelPriceNodes = $this->http->XPath->query("//h3[{$this->eq($this->t('Hotel cost summary'))}]/following-sibling::div[normalize-space()][1]");

        foreach ($hotelNodes as $key => $hNode) {
            $h = $email->add()->hotel();
            $this->parseHotel($h, $hNode);

            if ($hotelNodes->length === $hotelPriceNodes->length && !empty($hotelPriceNodes->item($key))) {
                $this->parseHotelPrice($h, $hotelPriceNodes->item($key));
            }
        }

        // CARS
        $carNodes = $this->http->XPath->query("//div[{$this->eq($this->t('Car rental reservation'))}]/following-sibling::div[normalize-space()][1]");
        $carPriceNodes = $this->http->XPath->query("//h3[{$this->eq($this->t('Car rental cost summary'))}]/following-sibling::div[normalize-space()][1]");

        foreach ($carNodes as $key => $cNode) {
            $car = $email->add()->rental();
            $this->parseCar($car, $cNode);

            if ($carNodes->length === $carPriceNodes->length && !empty($carPriceNodes->item($key))) {
                $this->parseCarPrice($car, $carPriceNodes->item($key));
            }
        }

        // Cancelled reservations
        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            if (count($email->getItineraries()) === 1) {
                // it-2192126.eml
                $email->getItineraries()[0]->general()->cancelled();
            } elseif (count($email->getItineraries()) > 1) {
                $this->logger->debug('Cancelled reservation not found!');
                $email->add()->flight(); // for 100% failed
            }
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

    public static function getEmailProviders()
    {
        return ['orbitzbus', 'orbitz', 'cheaptickets'];
    }

    private function parseFlight(Flight $f, \DOMNode $rootNode): void
    {
        $otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('otaNumber'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/^[A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('otaNumber'))} and ancestor::*[{$this->xpath['bold']}]]", $rootNode, true, '/^(.+?)[\s:：]*$/u');
            $f->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $travellers = $accountNumbers = [];
        $travellerRows = $this->http->XPath->query("descendant::tr[ *[1][{$this->eq($this->t('Traveler(s)'))}] and *[2][{$this->eq($this->t('Frequent flier details'))}] ]/following-sibling::tr[normalize-space() and count(*)=2]", $rootNode);

        foreach ($travellerRows as $tRow) {
            $travellers[] = $this->http->FindSingleNode('*[1]', $tRow, true, "/^{$this->patterns['travellerName']}$/u");
            $ffNumber = implode(' ', $this->http->FindNodes('*[2]/descendant::text()[normalize-space()]', $tRow));

            if (preg_match_all("/{$this->patterns['programNames']}[ ]+(?-i)([-A-Z\d]{5,})(?: |$)/i", $ffNumber, $m)) {
                $accountNumbers = array_merge($accountNumbers, $m[1]);
            }
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        if (count($accountNumbers)) {
            $f->program()->accounts(array_unique($accountNumbers), false);
        }

        $PNRsByAirline = $usedPNRs = [];
        $PNRsHtml = $this->http->FindHTMLByXpath("descendant::tr[ *[1][{$this->eq($this->t('Airline record locator:'))}] ]/*[2]", null, $rootNode);
        $PNRsText = $this->htmlToText($PNRsHtml);

        if (preg_match_all("/^[ ]*(?<airline>.{2,}?)[ ]*-[ ]*(?<pnr>[A-Z\d]{5,})[ ]*$/m", $PNRsText, $airlineMatches, PREG_SET_ORDER)) {
            // United Airlines - E8QJ4C
            foreach ($airlineMatches as $m) {
                $PNRsByAirline[$m['airline']] = $m['pnr'];
            }
        }

        if (count($PNRsByAirline) === 0) {
            // it-2427291.eml
            $itineraryNoteHtml = $this->http->FindHTMLByXpath("//text()[ {$this->eq($this->t('Itinerary note'))} and ancestor::*[{$this->xpath['bold']}] ]/ancestor::*[(self::p or self::div) and count(descendant::text()[normalize-space()])>1][1]");
            $itineraryNoteText = $this->htmlToText($itineraryNoteHtml);

            if (preg_match_all("/^[ ]*(?<airline>.{2,}?)[ ]+{$this->opt($this->t('Confirmation Number for'))}[ ]+(?<name>{$this->patterns['travellerName']})[ ]*-[ ]*(?<pnr>[A-Z\d]{5,})[ ]*$/m", $itineraryNoteText, $airlineMatches, PREG_SET_ORDER)) {
                // United Airlines Confirmation Number for Scott - K21M3M
                foreach ($airlineMatches as $m) {
                    if (stripos(implode(' ', $travellers), $m['name']) !== false) {
                        $PNRsByAirline[$m['airline']] = $m['pnr'];
                    }
                }
            }
        }

        $ticketNumbersHtml = $this->http->FindHTMLByXpath("descendant::tr[ *[1][{$this->eq($this->t('Ticket numbers:'))}] ]/*[2]", null, $rootNode);
        $ticketNumbersText = $this->htmlToText($ticketNumbersHtml);
        $ticketNumbers = array_filter(preg_split('/[ ]*\n+[ ]*/', $ticketNumbersText),
            function ($item) { return preg_match("/^\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}$/", $item) > 0; }
        );

        if (count($ticketNumbers)) {
            $f->issued()->tickets($ticketNumbers, false);
        }

        $totalCost = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Total flight cost:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1]", $rootNode);

        if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})$/', $totalCost, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalCost, $m)
        ) {
            // $4,639.20 USD    |    4,639.20 $
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }

        $reservationDate = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation date:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/.{6,}/');

        if ($reservationDate) {
            $f->general()->date2($reservationDate);
        }

        $segments = $this->http->XPath->query("descendant::tr[ *[1][{$this->starts($this->t('dep'))}] and following-sibling::tr[*[1][{$this->starts($this->t('arr'))}]] ]", $rootNode);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $dateValue = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::h3 ][1]/preceding-sibling::h3", $segment, true, "/^(?:{$this->opt($this->t('datePrefix'))}(?:[ ]+\d{1,3})?[: ]+)?(.{6,})$/");
            $date = strtotime($dateValue);

            $xpathFlight = "ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[ descendant::text()[normalize-space()][1][ancestor::*[{$this->xpath['bold']}]] ][1]";

            $flight = $this->http->FindSingleNode($xpathFlight . "/descendant::text()[normalize-space()][1]", $segment);

            if (preg_match('/^(?<name>.{2,}?)\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (!empty($PNRsByAirline[$m['name']])) {
                    $s->airline()->confirmation($PNRsByAirline[$m['name']]);
                    $usedPNRs[$m['name']] = $PNRsByAirline[$m['name']];
                }
            }

            // Business | Z | ZNC7NS | Boeing 747-400 Passenger (744) | Meal | 7hr 35min | 3851 miles
            $extra = implode(' ', $this->http->FindNodes($xpathFlight . "/descendant::text()[normalize-space()][position()>1]", $segment));
            $extraParts = preg_split('/\s*\|\s*/', $extra);

            if (!empty($extraParts[0]) && preg_match("/^(?:Economy|Business)$/i", $extraParts[0])) {
                $s->extra()->cabin($extraParts[0]);
            }

            if (!empty($extraParts[1]) && preg_match("/^[A-Z]{1,2}$/", $extraParts[1])) {
                $s->extra()->bookingCode($extraParts[1]);
            }

            if (preg_match("/\|([^|]*\b(?:McDonnell Douglas|De Havilland|Embraer|Jet|Boeing|Airbus)\b[^|]*)\|/i", $extra, $m)) {
                $s->extra()->aircraft(trim($m[1]));
            }

            if (count($extraParts) > 2 && !empty($extraParts[count($extraParts) - 3])
                && preg_match("/\b(?:Meal|Food|Snack|Dinner|Breakfast|Lunch)\b/i", $extraParts[count($extraParts) - 3])
            ) {
                $s->extra()->meal($extraParts[count($extraParts) - 3]);
            }

            if (count($extraParts) > 1 && !empty($extraParts[count($extraParts) - 2])
                && preg_match("/^\d[\d hrmin]+$/i", $extraParts[count($extraParts) - 2])
            ) {
                // 7hr 35min    |    35min
                $s->extra()->duration($extraParts[count($extraParts) - 2]);
            }

            if (count($extraParts) > 0 && !empty($extraParts[count($extraParts) - 1])
                && preg_match("/^\d+\s*{$this->opt($this->t('miles'))}$/i", $extraParts[count($extraParts) - 1])
            ) {
                // 3851 miles
                $s->extra()->miles($extraParts[count($extraParts) - 1]);
            }

            $operatorText = implode(' ', $this->http->FindNodes("ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/{$this->opt($this->t('Operated by'))}[: \/]+(.+?)(?:\s+{$this->opt($this->t('DBA'))}\s|\s*--[^-]|\s*[,.;!?]|$)/i", $operatorText, $m)) {
                // Operated by: SKYWEST DBA UNITED EXPRESS -- UA 6290    |    NORTHWEST AIRLINES -- NW 1149
                $s->airline()->operator($m[1]);
            }

            $timeDep = $this->http->FindSingleNode("*[2]", $segment, true, "/^{$this->patterns['time']}$/i");
            $s->departure()->date(strtotime($timeDep, $date));

            // Los Angeles, CA    Los Angeles International (LAX)
            $patternAirport = "/[ ]{4,}(?<name>.{3,}?)[ ]*\([ ]*(?<code>[A-Z]{3})[ ]*\)$/";

            $airportDep = implode('    ', $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($patternAirport, $airportDep, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code']);
            } else {
                $s->departure()->name($airportDep);
            }

            $xpathArr = "following-sibling::tr[*[1][{$this->starts($this->t('arr'))}]]";

            $timeArr = $this->http->FindSingleNode($xpathArr . "/*[2]", $segment, true, "/^{$this->patterns['time']}$/i");
            $s->arrival()->date(strtotime($timeArr, $date));

            $airportArr = implode('    ', $this->http->FindNodes($xpathArr . "/*[3]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($patternAirport, $airportArr, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code']);
            } else {
                $s->arrival()->name($airportArr);
            }

            $seatsText = implode(' ', $this->http->FindNodes($xpathArr . "/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/{$this->opt($this->t('Seat:'))}\s*(?-i)(\d[\dA-Z, ]*[A-Z])(?:\s*[;|]|$)/i", $seatsText, $m)) {
                $s->extra()->seats(preg_split('/[,\s]*,[,\s]*/', $m[1]));
            }

            if (preg_match("/{$this->opt($this->t('Your flight is'))}\s+({$this->opt($this->t('statusVariants'))})\s*(?:[.;!]|$)/", $seatsText, $m)) {
                $s->extra()->status($m[1]);
            }
        }

        $unusedPNRs = array_diff_key($PNRsByAirline, $usedPNRs);

        if (count($unusedPNRs)) {
            // it-2212823.eml, it-2210356.eml
            foreach ($unusedPNRs as $airline => $pnr) {
                $f->general()->confirmation($pnr, $airline);
            }
        } elseif (count($PNRsByAirline)
            || $this->http->XPath->query("descendant::*[{$this->contains($this->t('Airline record locator:'))}]", $rootNode)->length === 0
                && $this->http->XPath->query("//*[{$this->contains($this->t('Confirmation Number for'))}]")->length === 0
        ) {
            // it-2224181.eml
            $f->setNoConfirmationNumber(true);
        }
    }

    private function parseFlightPrice(Flight $f, \DOMNode $rootNode): void
    {
        $totalPrice = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Total'))}] ]/*[2]", $rootNode, true, '/^(.+?)[\s*]*$/');

        if (preg_match('/^(?<currency2>[^\d)(]*?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
            || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)
        ) {
            // $512.00 USD    |    512.00 $    |    $512.00
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);

            if (empty($m['currency2'])) {
                $m['currency2'] = $m['currency'] === 'USD' ? '$' : $m['currency'];
            }

            $costValues = [];
            $costRows = $this->http->XPath->query("descendant::tr[ normalize-space() and following-sibling::tr[*[1][{$this->eq($this->t('Orbitz for Business fees'))}]] or *[1][{$this->contains($this->t('Adult'))}] ]", $rootNode);

            foreach ($costRows as $cRow) {
                $costCharge = $this->http->FindSingleNode('*[2]', $cRow);

                if (preg_match('/^(?<currency2>[^\d)(]*?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})$/', $costCharge, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $costCharge, $matches)
                    || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $costCharge, $matches)
                ) {
                    if (empty($matches['currency2'])) {
                        $matches['currency2'] = $matches['currency'] === 'USD' ? '$' : $matches['currency'];
                    }

                    if ($matches['currency'] === $m['currency'] || $matches['currency2'] === $m['currency2']) {
                        $costValues[] = $this->normalizeAmount($matches['amount']);
                    } else {
                        $costValues = [];

                        break;
                    }
                }
            }

            if (count($costValues)) {
                $f->price()->cost(array_sum($costValues));
            }

            $feeRows = $this->http->XPath->query("descendant::tr[ normalize-space() and preceding-sibling::tr[*[1][{$this->eq($this->t('Orbitz for Business fees'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('Total'))}]] ]", $rootNode);

            if ($feeRows->length === 0) {
                $feeRows = $this->http->XPath->query("descendant::tr[ *[1][{$this->eq($this->t('feesNames'))}] and following-sibling::tr[*[1][{$this->eq($this->t('Total'))}]] ]", $rootNode);
            }

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $feeCharge, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $feeCharge, $matches)
                    || preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $matches)
                ) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                    $f->price()->fee($feeName, $this->normalizeAmount($matches['amount']));
                }
            }

            if ($feeRows->length === 0) {
                $taxes = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Total service fee:'))}] ]/*[2]", $rootNode);

                if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $taxes, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $taxes, $matches)
                    || preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $taxes, $matches)
                ) {
                    $f->price()->tax($this->normalizeAmount($matches['amount']));
                }
            }
        }
    }

    private function parseHotel(Hotel $h, \DOMNode $rootNode): void
    {
        $otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('otaNumber'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/^[A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('otaNumber'))} and ancestor::*[{$this->xpath['bold']}]]", $rootNode, true, '/^(.+?)[\s:：]*$/u');
            $h->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('confNumber'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('confNumber'))} and ancestor::*[{$this->xpath['bold']}]]", $rootNode, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        } elseif ($this->http->XPath->query("descendant::text()[{$this->eq($this->t('otaNumber'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[ normalize-space() and ancestor::*[{$this->xpath['bold']}] ][1][{$this->eq($this->t('Reservation made for:'))}]", $rootNode)->length > 0) {
            // it-2256022.eml
            $h->general()->noConfirmation();
        }

        $madeFor = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation made for:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, "/^{$this->patterns['travellerName']}$/");
        $h->general()->traveller($madeFor);

        $reservationDate = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation date:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/.{6,}/');

        if ($reservationDate) {
            $h->general()->date2($reservationDate);
        }

        // it-2107957.eml
        $accountNumbers = [];
        $loyaltyProgram = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Loyalty program'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode);
        $programAccounts = preg_split('/\s*[,]+\s*/', $loyaltyProgram, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($programAccounts as $pAccount) {
            if (preg_match("/^(?:{$this->patterns['programNames']})?\s*([-A-Z\d]{5,})$/", $pAccount, $m)) {
                $accountNumbers[] = $m[1];
            } else {
                $accountNumbers = [];

                break;
            }
        }

        if (count($accountNumbers)) {
            $h->program()->accounts(array_unique($accountNumbers), false);
        }

        $totalCharges = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Total charges:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1]", $rootNode);

        if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})(?:[ ]*\(|$)/', $totalCharges, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalCharges, $m)
        ) {
            // $152.65 USD    |    152.65 $    |    338.14 USD (taxes not included)
            $h->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }

        $xpathHotel = "descendant::tr[ *[1][{$this->starts($this->t('dep'))}] ]/ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()]";
        $hotelName = $this->http->FindSingleNode($xpathHotel . '/*[1]', $rootNode);
        $address = $this->http->FindSingleNode($xpathHotel . '/*[2]', $rootNode);
        $phone = $this->http->FindSingleNode($xpathHotel . "/*[3]/descendant::text()[{$this->eq($this->t('Phone:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, "/^{$this->patterns['phone']}$/");
        $fax = $this->http->FindSingleNode($xpathHotel . "/*[3]/descendant::text()[{$this->eq($this->t('Fax:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, "/^{$this->patterns['phone']}$/");
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone)
            ->fax($fax);

        $checkInDate = strtotime($this->http->FindSingleNode("descendant::tr[ *[1][{$this->starts($this->t('dep'))}] ]/*[2]", $rootNode, true, '/.{6,}/'));
        $checkInTime = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->starts($this->t('dep'))}] ]/*[3]", $rootNode, true, "/^{$this->patterns['time']}$/i");
        $h->booked()->checkIn(strtotime($this->normalizeTime($checkInTime), $checkInDate));

        $checkOutDate = strtotime($this->http->FindSingleNode("descendant::tr[ *[1][{$this->starts($this->t('arr'))}] ]/*[2]", $rootNode, true, '/.{6,}/'));
        $checkOutTime = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->starts($this->t('arr'))}] ]/*[3]", $rootNode, true, "/^{$this->patterns['time']}$/i");
        $h->booked()->checkOut(strtotime($this->normalizeTime($checkOutTime), $checkOutDate));

        $roomDescription = implode('; ', $this->http->FindNodes("descendant::text()[normalize-space()][not(ancestor::*[{$this->xpath['bold']}])][ ancestor::p[1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Room description:'))} and ancestor::*[{$this->xpath['bold']}]]] ]", $rootNode));
        $roomDescription = preg_replace("/^{$this->opt($this->t('Room description:'))}\s*/", '', $roomDescription);

        if ($roomDescription) {
            $room = $h->addRoom();
            $room->setDescription($roomDescription);
        }

        $guestCount = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Total guests:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/^(\d{1,3})\b/');
        $roomsCount = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Total rooms:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/^(\d{1,3})\b/');
        $h->booked()
            ->guests($guestCount, false, true)
            ->rooms($roomsCount, false, true);

        $cancellation = implode(' ', $this->http->FindNodes("descendant::tr[ *[1][{$this->eq($this->t('Cancellation:'))}] ]/ancestor::table[1]/descendant::tr[not(.//tr)]/*[position()>1]", $rootNode));

        if (!$cancellation) {
            $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Cancellation:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, "/.*cancel.*/i");
        }

        if ($cancellation) {
            $h->general()->cancellation($cancellation);
        }

        if ($cancellation) {
            $h->booked()->parseNonRefundable('Non-refundable'); // it-22.eml
        }
    }

    private function parseHotelPrice(Hotel $h, \DOMNode $rootNode): void
    {
        $totalPrice = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Total charges'))}] ]/*[2]", $rootNode, true, '/^(.+?)[\s*]*$/');

        if (preg_match('/^(?<currency2>[^\d)(]*?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
            || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)
        ) {
            // $78.38 USD    |    78.38 $    |    $78.38
            $h->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);

            if (empty($m['currency2'])) {
                $m['currency2'] = $m['currency'] === 'USD' ? '$' : $m['currency'];
            }

            $baseRate = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Hotel room cost'))}] ]/*[2]", $rootNode);

            if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $baseRate, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $baseRate, $matches)
                || preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseRate, $matches)
            ) {
                $h->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $taxes = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Hotel taxes and fees'))}] ]/*[2]", $rootNode);

            if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $taxes, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $taxes, $matches)
                || preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $taxes, $matches)
            ) {
                $h->price()->tax($this->normalizeAmount($matches['amount']));
            }
        }
    }

    private function parseCar(Rental $car, \DOMNode $rootNode): void
    {
        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('confNumber'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('confNumber'))} and ancestor::*[{$this->xpath['bold']}]]", $rootNode, true, '/^(.+?)[\s:：]*$/u');
            $car->general()->confirmation($confirmation, $confirmationTitle);
        }

        $driver = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Primary driver:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, "/^{$this->patterns['travellerName']}$/");
        $car->general()->traveller($driver);

        $reservationDate = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation date:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/.{6,}/');

        if ($reservationDate) {
            $car->general()->date2($reservationDate);
        }

        $loyaltyProgram = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Loyalty program'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, '/^[-A-Z\d ]{5,}$/');

        if ($loyaltyProgram) {
            $car->program()->account($loyaltyProgram, false);
        }

        $company = implode(' ', $this->http->FindNodes("descendant::tr[ *[1][{$this->starts($this->t('dep'))}] ]/ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()]/descendant::text()[normalize-space() and not(ancestor::*[{$this->contains(['carClass', 'carclass'], '@class')}] or {$this->eq(['Full Size', 'Compact'])})]", $rootNode));

        if (($code = $this->normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        $xpathPickUp = "descendant::tr[ *[1][{$this->starts($this->t('dep'))}] ]";
        $dateTimePickUp = $this->normalizeDateTime($this->http->FindSingleNode($xpathPickUp . "/*[2]", $rootNode, true, '/.{6,}/'));
        $locationPickUp = $this->http->FindSingleNode($xpathPickUp . "/*[3]/descendant::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode);
        $phonePickUp = $this->http->FindSingleNode($xpathPickUp . "/*[3]/descendant::text()[{$this->eq($this->t('Phone:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode);
        $hoursPickUp = $this->http->FindSingleNode($xpathPickUp . "/*[3]/descendant::text()[{$this->eq($this->t('Hours:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode);
        $car->pickup()
            ->date2($dateTimePickUp)
            ->location($locationPickUp)
            ->phone($phonePickUp, false, true)
            ->openingHours($hoursPickUp, false, true);

        $xpathDropOff = "descendant::tr[ *[1][{$this->starts($this->t('arr'))}] ]";
        $dateTimeDropOff = $this->normalizeDateTime($this->http->FindSingleNode($xpathDropOff . "/*[2]", $rootNode, true, '/.{6,}/'));
        $locationDropOff = $this->http->FindSingleNode($xpathDropOff . "/*[3]/descendant::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode);
        $car->dropoff()->date2($dateTimeDropOff);

        if (preg_match("/\b{$this->opt($this->t('same'))}\b/i", $locationDropOff)) {
            $car->dropoff()->same();
        } else {
            $phoneDropOff = $this->http->FindSingleNode($xpathDropOff . "/*[3]/descendant::text()[{$this->eq($this->t('Phone:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode);
            $hoursDropOff = $this->http->FindSingleNode($xpathDropOff . "/*[3]/descendant::text()[{$this->eq($this->t('Hours:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode);
            $car->dropoff()
                ->location($locationDropOff)
                ->phone($phoneDropOff, false, true)
                ->openingHours($hoursDropOff, false, true);
        }

        $carModel = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Car details:'))} and ancestor::*[{$this->xpath['bold']}]]/following::text()[normalize-space()][1][not(ancestor::*[{$this->xpath['bold']}])]", $rootNode, true, "/^(.+\({$this->opt($this->t('or similar'))}\))/i");
        $car->car()->model($carModel, false, true);
    }

    private function parseCarPrice(Rental $car, \DOMNode $rootNode): void
    {
        $totalPrice = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Total'))}] ]/*[2]", $rootNode, true, '/^(.+?)[\s*]*$/');

        if (preg_match('/^(?<currency2>[^\d)(]*?)[ ]*(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[A-Z]{3})(?:[ ]*\+|[ *]*$)/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
            || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)
        ) {
            // $78.38 USD    |    78.38 $    |    $78.38    |    2,316.00 MAD + $2.00 USD*    |    $204.40 USD*
            $car->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);

            if (empty($m['currency2'])) {
                $m['currency2'] = $m['currency'] === 'USD' ? '$' : $m['currency'];
            }

            $baseRate = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Base rate'))}] ]/*[2]", $rootNode);

            if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $baseRate, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $baseRate, $matches)
                || preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseRate, $matches)
            ) {
                $car->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $feeRows = $this->http->XPath->query("descendant::tr[ normalize-space() and preceding-sibling::tr[*[1][{$this->eq($this->t('Taxes and fees'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('Total'))}]] ]", $rootNode);

            foreach ($feeRows as $feeRow) {
                $feeParts = preg_split('/\s*@\s*/', $this->http->FindSingleNode('.', $feeRow));

                if (count($feeParts) !== 2) {
                    continue;
                }

                if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $feeParts[1], $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $feeParts[1], $matches)
                    || preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeParts[1], $matches)
                ) {
                    $car->price()->fee($feeParts[0], $this->normalizeAmount($matches['amount']));
                }
            }

            if ($feeRows->length === 0) {
                $taxes = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t('Taxes and fees'))}] ]/*[2]", $rootNode);

                if (preg_match('/^[^\d)(]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $taxes, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')$/', $taxes, $matches)
                    || preg_match('/^(?:' . preg_quote($m['currency'], '/') . '|' . preg_quote($m['currency2'], '/') . ')[ ]*(?<amount>\d[,.\'\d ]*)$/', $taxes, $matches)
                ) {
                    $car->price()->tax($this->normalizeAmount($matches['amount']));
                }
            }
        }
    }

    private function assignProvider($headers): bool
    {
        if (strpos($headers['from'], 'CheapTickets') !== false
            || stripos($headers['from'], '@cheaptickets.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".cheaptickets.com/") or contains(@href,"www.cheaptickets.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"This message has been sent from CheapTickets") or contains(.,"CheapTickets.com")]')->length > 0
        ) {
            $this->providerCode = 'cheaptickets';

            return true;
        }

        if (strpos($headers['from'], 'Orbitz For Business') !== false
            || stripos($headers['from'], '@orbitzforbusiness.net') !== false
            || $this->http->XPath->query('//a[contains(@href,".orbitzforbusiness.net/") or contains(@href,"www.orbitzforbusiness.net")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for booking on Orbitz for Business") or contains(normalize-space(),"This message has been sent from Orbitzforbusiness") or contains(normalize-space(),"Thank you for choosing Orbitz for Business") or contains(normalize-space(),"To contact Orbitz for Business,") or contains(.,"m.orbitzforbusiness.net") or contains(.,"@orbitzforbusiness.net")]')->length > 0
        ) {
            $this->providerCode = 'orbitzbus';

            return true;
        }

        if (stripos($headers['from'], '@orbitz.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".orbitz.com/") or contains(@href,"www.orbitz.com") or contains(@href,"price.orbitz.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"This message has been sent from Orbitz.com") or contains(.,"m.orbitz.com")]')->length > 0
        ) {
            $this->providerCode = 'orbitz';

            return true;
        }

        return false;
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

                if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['dep']) || empty($phrases['arr'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->starts($phrases['dep'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($phrases['arr'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'thrifty'      => ['Thrifty Car Rental'],
            'perfectdrive' => ['Budget'],
            'rentacar'     => ['Enterprise'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function normalizeDateTime(?string $s): string
    {
        // it-3105699.eml
        $s = preg_replace("/(:\d{2}\s*[AP]M)(?:\s*[AP]M)+/i", '$1', $s); // 5:00 PM PM  ->  5:00 PM
        // it-22.eml
        $s = preg_replace("/(:\d{2}\s*[AP]M)\s*\(?[ ]*(?-i)[A-Z]{3,}[ ]*\)?$/i", '$1', $s); // 5:00 PM PST

        return $s;
    }

    private function normalizeTime(?string $s): string
    {
        if (preg_match('/^(?:12)?\s*noon$/i', $s)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1];
        } // 21:51 PM    ->    21:51
        $s = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $s); // 00:25 AM    ->    00:25
        $s = preg_replace('/(\d)[ ]*-[ ]*(\d)/', '$1:$2', $s); // 01-55 PM    ->    01:55 PM
        $s = str_replace(['午前', '午後', '下午'], ['AM', 'PM', 'PM'], $s); // 10:36 午前    ->    10:36 AM

        return $s;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
}
