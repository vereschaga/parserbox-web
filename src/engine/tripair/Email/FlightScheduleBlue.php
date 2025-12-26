<?php

namespace AwardWallet\Engine\tripair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class FlightScheduleBlue extends \TAccountChecker
{
    public $mailFiles = "tripair/it-108806044.eml, tripair/it-11996426.eml, tripair/it-40884801.eml, tripair/it-41980269.eml, tripair/it-42228880.eml, tripair/it-42320902.eml, tripair/it-42321708.eml, tripair/it-424144247.eml, tripair/it-4819113.eml, tripair/it-49957461.eml, tripair/it-534746385.eml, tripair/it-60812230.eml, tripair/it-6566906.eml, tripair/it-6657845.eml, tripair/it-73514139.eml, tripair/it-97720789.eml";
    public $lang;
    public static $dict = [
        'en' => [
            'Airline Reference:' => ['Airline Reference:', 'Confirmation:', 'Airline Ref:'],
            'Flight:'            => 'Flight:',
            'Depart'             => 'Depart',
            'Pick up'            => 'Pick up',
            'Class:'             => ['Class:', 'Cabin Class:'],
            'Confirmation:'      => 'Confirmation:',
            'Hotel:'             => 'Hotel:',
            'Check Out'          => 'Check Out',
            'CANCEL'             => ['CANCEL', 'CXL'],
            'TOTAL PRICE IS'     => ['TOTAL PRICE IS', 'APPROX TOTAL'],
        ],
    ];

    private $reBody = [
        'en'  => ['Ticketing Information', 'Depart'],
        'en1' => ['Payment Summary', 'Depart'],
        'en2' => ['Trip Locator:', 'RATE'],
        'en3' => ['Trip Locator:', 'Depart'],
        'en4' => ['Airline Reference:', 'Depart'],
        'en5' => ['Airline Ref', 'Depart'],
    ];
    private $code = null;
    private static $providers = [
        'chase' => [
            'from' => ['Chase Ultimate Rewards Travel', '@travelemail.res12.com'],
            'subj' => [
                'Ultimate Rewards Travel Confirmation',
                'Travel Reservation Center',
            ],
            'body' => [
                '//a[contains(@href,"//ultimaterewardstravel.chase.com") or contains(@href,"//www.chase.com")]',
                'Chase Travel on your',
                'To contact Chase,',
                'Chase Privacy Operations',
                'JP Morgan Chase & Co',
                'Thank you for choosing Eagle Points',
            ],
        ],
        'tripair' => [
            'from' => ['@tripair.com'],
            'subj' => [
                'Tripair - Flight Booking Information',
                'Tripair - Urgent notification',
            ],
            'body' => [
                '//node()[contains(.,"tripair@tripair.com")]',
                '//a[contains(@href,"//www.tripair.com")]',
            ],
        ],
        'otg' => [
            'from' => ['@ovationtravel.com'],
            'subj' => [
                'Itinerary for',
            ],
            'body' => [
                "//img[contains(@alt, 'Ovation Travel Group Email')]",
                '@ovationtravel.com',
            ],
        ],
        'priceline' => [
            'from' => ['@priceline.com'],
            'subj' => [
                'Itinerary for',
            ],
            'body' => [
                '@priceline.com',
            ],
        ],
        'capitalcards' => [
            'from' => ['Capital One Travel'],
            'subj' => [
                'Capital One Travel Reservation Trip ID',
            ],
            'body' => [
                'email was sent by: Capital One Travel',
                'trip through Capital One Travel',
                'Thank you for choosing Capital One Travel',
            ],
        ],
        'citybank' => [
            'from' => ['Travel Rewards Center'],
            'subj' => [
                'your flight is booked! Your Trip ID is',
            ],
            'body' => [
                'This is a message from cxLoyalty Travel Center for ThankYou',
                'To verify a seat request, or if seat assignments are not available, please contact the airline directly for assistance',
            ],
        ],
        'worldspan' => [ // always last on the list because default provider!
            'from' => [
                '@worldspan.com',
                '@fugazitravel.com',
                '@ciaobambino.com', // not worldspan
                '@ntmllc.com', // not worldspan
            ],
            'subj' => [
                'Itinerary for',
                'Eticket for',
                'E-tickets issued for',
            ],
            'body' => [
                '//a[contains(@href,"worldspan.com")] | //text()[contains(normalize-space(),"Mardi Gras Travel")]',
                '//tr[normalize-space()][ descendant-or-self::*[contains(@style,"#0080FF") or contains(@style,"#0080ff")] ]/following-sibling::tr[normalize-space()][1][ descendant-or-self::*[contains(@style,"#BEEAE9") or contains(@style,"#beeae9")] ]',
                '@worldspan.com',
                '@iflybusiness.com', 'Ciao Bambino!', 'MAD Travel', // not worldspan
                '@mardigrastravel.org',
                '@talmaus.com', '@TALMAUS.COM',
            ],
        ],
    ];
    private $tripNum;
    private $tripLoc;
    private $pax = [];
    private $xpath = [
        'time' => '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))',
        'bold' => '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);

        $email->setType('FlightScheduleBlue' . ucfirst($this->lang));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq(['Amount Billed to Card:', 'Total Cash Payment:']) . "]/ancestor::td[1]/following-sibling::td[1]"));

        /** @var Flight $r */
        if (null !== ($r = $this->getFlightIfOne($email))) {
            $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('Electronic Ticket'))}]/ancestor::td[1]/following-sibling::td[1]");

            if (!empty($tickets)) {
                $r->issued()
                    ->tickets($tickets, false);
            }
        }
        $node = $this->http->FindSingleNode("//text()[" . $this->eq(['Points Redeemed:', 'Total Rewards:']) . "]/ancestor::td[1]/following-sibling::td[1]");

        if (!empty($node) && stripos($node, 'PaymentPoints') == false) {
            $email->price()
                ->spentAwards($node);
        }

        if ($tot['Total'] !== null) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        } else {
            $sums = array_filter($this->http->FindNodes("//tr[ *[6][{$this->eq($this->t('Amount'))}] ]/following-sibling::tr[normalize-space()]/*[6]", null, "/^\d[,.\'\d ]*$/"));

            if (count($sums)) {
                $email->price()->total(array_sum($sums));
                $currency = $this->http->FindSingleNode("//tr[ *[7][{$this->eq($this->t('Currency'))}] ]/following-sibling::tr[normalize-space()][1]/*[7]", null, false, "/^[A-Z]{3}$/");

                if ($currency) {
                    $email->price()->currency($currency);
                }
            }
        }

        if (null !== ($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    $this->code = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null === $this->getProvider($parser)) {
            return false;
        }

        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailFromProvider($parser->getCleanFrom());

        if (!empty($this->code)) {
            return $this->code;
        }
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->code)) {
            return $this->code;
        }

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $this->tripNum = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Trip ID:'))}])[1]/following::text()[normalize-space(.)!=''][1]");
        $this->tripLoc = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Trip Locator:'))}])[1]/following::text()[normalize-space(.)!=''][1]");
        $travellerVal = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Trip Locator:'))}][1]/ancestor::td[ count(preceding-sibling::td[normalize-space()])=1 ][1]/preceding-sibling::td[normalize-space()][1]", null, true, '/^[,\s]*(.{2,}?)[,\s]*$/');

        $this->pax = $this->niceTravellersNames(preg_split('/(\s*,\s*)+/', $travellerVal, -1, PREG_SPLIT_NO_EMPTY));

        // flights & trains
        $xpath = "//table[({$this->starts($this->t('Flight:'))}) and ({$this->contains($this->t('Depart'))}) and ({$this->contains($this->t('Arrive'))})]"
            // it-60812230.eml
            . " | //table[descendant::tr[normalize-space()][1]/*[{$this->eq($this->t('Air'))}] and ({$this->contains($this->t('Depart'))}) and ({$this->contains($this->t('Arrive'))})][count(descendant::text()[{$this->xpath['time']}])>1][not(contains(normalize-space(),'DEPART DATE-'))]";
        $flights = $this->http->XPath->query($xpath);

        if ($flights->length > 0) {
            $this->logger->debug("[XPATH-flights] " . $xpath);
            $this->parseFlights($flights, $email);
        }

        // hotels
        $xpath = "//table[({$this->starts($this->t('Hotel:'))}) and ({$this->contains($this->t('Check In:'))}) and ({$this->contains($this->t('Check Out'))})]"
            // it-60812230.eml
            . " | //table[descendant::tr[normalize-space()][1]/*[{$this->eq($this->t('Hotel'))}] and ({$this->contains($this->t('Check Out'))})]";
        $hotels = $this->http->XPath->query($xpath);

        if ($hotels->length > 0) {
            $this->logger->debug("[XPATH-hotels] " . $xpath);
            $this->parseHotels($hotels, $email);
        }

        // Car
        $xpath = "//table[({$this->starts($this->t('Car:'))}) and ({$this->contains($this->t('Pick up'))}) and ({$this->contains($this->t('Drop Off'))})]";
        $cars = $this->http->XPath->query($xpath);

        if ($cars->length > 0) {
            $this->logger->debug("[XPATH-car] " . $xpath);
            $this->parseCars($cars, $email);
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Citi Card Payment')]/following::text()[normalize-space()][1]", null, true, "/^\S([\d\.]+)/");
        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Citi Card Payment')]/following::text()[normalize-space()][1]", null, true, "/^(\S)[\d\.]+/");

        if (!empty($total) && !empty($currency)) {
            $email->price()
                ->total($total)
                ->currency($currency);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Points')]/following::text()[normalize-space()][1]");

        if (!empty($spentAwards)) {
            $email->price()
                ->spentAwards($spentAwards);
        }
    }

    private function parseCars(\DOMNodeList $segments, Email $email): void
    {
        foreach ($segments as $root) {
            $r = $email->add()->rental();

            if (!empty($this->tripNum)) {
                $r->ota()->confirmation($this->tripNum, rtrim($this->t('Trip ID:'), ': '));
            } elseif (!empty($this->tripLoc)) {
                $r->ota()->confirmation($this->tripLoc, rtrim($this->t('Trip Locator:'), ': '));
            }
            $confirmation = $this->http->FindSingleNode("descendant::tr/*[not(.//tr) and {$this->starts($this->t('Confirmation:'))}]", $root)
                ?? $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Car:'))}] ]/*[normalize-space()][2]", $root);

            if (!empty($confirmation)) {
                if (preg_match("/^({$this->opt($this->t('Confirmation:'))})\s*([-A-z\d]{5,})\D*$/", $confirmation, $m)) {
                    $r->general()->confirmation($m[2], rtrim($m[1], ': '));
                } elseif (preg_match("/^[-A-z\d]{5,}$/", $confirmation)) {
                    $r->general()->confirmation($confirmation);
                }
            } elseif ($this->http->XPath->query("//text()[{$this->starts($this->t('Confirmation:'))}]", $root)) {
                $r->general()
                    ->noConfirmation();
            }

            $r->general()
                ->status($this->http->FindSingleNode("./descendant::tr[./td[1][.//img] and count(./td)>=4]/td[4]/descendant::text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space()!=''][1]",
                    $root))
                ->cancellation($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('CANCEL'))}]",
                    $root, true), false, true);

            if (count($this->pax) > 0) {
                $r->general()->travellers($this->pax, true);
            }
            $r->extra()->company($this->http->FindSingleNode("descendant::*[{$this->xpath['bold']} and {$this->eq($this->t('Tel'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()]",
                $root));
            $phone = $this->http->FindSingleNode("descendant::*[{$this->xpath['bold']} and {$this->eq($this->t('Tel'))}]/following::text()[normalize-space()][1]",
                $root, false, '/^[:\s]*([+(\d][-. \d)(]{5,}[\d)])$/');

            if (!empty($phone)) {
                $r->pickup()->phone($phone);
            }

            // Thursday, 12 December 2019 12:42 PM
            $pattern = '[-[:alpha:]]{5,}\s*,\s*\d{1,2}\s+[[:alpha:]]+\s+\d{4}\s*\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?';

            $pickup = implode("\n",
                $this->http->FindNodes("descendant::*[{$this->xpath['bold']} and {$this->eq($this->t('Pick up'))}]/ancestor::td[1]/descendant::text()[normalize-space() and not({$this->eq($this->t('Pick up'))})]",
                    $root));

            if (preg_match("/^(?<location>[\s\S]{3,}?)\s+(?<date>{$pattern})$/u", $pickup, $m)
                || preg_match("/^(?<date>{$pattern})$/u", $pickup, $m)
            ) {
                if (!empty($m['location'])) {
                    $r->pickup()->location(preg_replace('/\s+/', ' ', $m['location']));
                } else {
                    $r->pickup()->noLocation();
                }
                $r->pickup()->date2($m['date']);
            }

            $dropoff = implode("\n",
                $this->http->FindNodes("descendant::*[{$this->xpath['bold']} and {$this->eq($this->t('Drop Off'))}]/ancestor::td[1]/descendant::text()[normalize-space() and not({$this->eq($this->t('Drop Off'))})]",
                    $root));

            if (preg_match("/^(?<location>[\s\S]{3,}?)\s+(?<date>{$pattern})$/u", $dropoff, $m)
                || preg_match("/^(?<date>{$pattern})$/u", $dropoff, $m)
            ) {
                if (!empty($m['location'])) {
                    $r->dropoff()->location(preg_replace('/\s+/', ' ', $m['location']));
                } else {
                    $r->dropoff()->noLocation();
                }
                $r->dropoff()->date2($m['date']);
            }
            $carType = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->starts($this->t('Car Type:'))}]",
                $root, true, "/{$this->opt($this->t('Car Type:'))}\s*(.{2,})/");

            if (!empty($carType)) {
                $r->car()
                    ->type($carType);
            }

            $tot = $this->getTotalCurrency($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('TOTAL PRICE IS'))}]",
                $root, false, "/{$this->opt($this->t('TOTAL PRICE IS'))}\s+(.+?)(?:\s+{$this->opt($this->t('INCLUDES TAXES-FEES-SURCHARGES'))})?$/"));

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    private function parseHotels(\DOMNodeList $segments, Email $email): void
    {
        foreach ($segments as $root) {
            $r = $email->add()->hotel();

            if (!empty($this->tripNum)) {
                $r->ota()
                    ->confirmation($this->tripNum, rtrim($this->t('Trip ID:'), ': '));
            }

            if (!empty($this->tripLoc)) {
                $r->ota()
                    ->confirmation($this->tripLoc, rtrim($this->t('Trip Locator:'), ': '));
            }

            // Confirmation: 26919SC001517    |    Confirmation: 2943176.HOTEL RESERVATION
            // Confirmation: 2943176-    |    Confirmation: FELIX MULLEN-CARA
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Confirmation:'))}]/ancestor::*[../self::tr][1]", $root, true, '/:\s*([A-Z\d][-A-Z\d *]{3,}[A-Z\d])(?:\s*[-.$]|$)/')
                ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Confirmation:'))}]/ancestor::td[1]", $root, true, '/:\s*([-.A-Z\d]+)$/')
            ;

            if ($confirmation) {
                $r->general()->confirmation($confirmation, rtrim($this->t('Confirmation:'), ': '), null, '/^[-.?\w\/\\ *]+$/u');
            } elseif ($this->http->XPath->query("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Hotel:'))}] and *[normalize-space()][2][{$this->eq($this->t('Confirmation:'))}] ]", $root)->length > 0) {
                $r->general()->noConfirmation();
            }

            $r->general()->cancellation($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('CANCEL'))}]", $root), false, true);

            $status = $this->http->FindSingleNode("descendant::tr[td[1][.//img] and count(td)>=4]/td[4]/descendant::text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space()][1]", $root);

            if ($status) {
                $r->general()->status($status);
            }

            if (count($this->pax) > 0) {
                $r->general()->travellers($this->pax, true);
            }

            $account = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Frequent Guest:'))}]", $root, true, "/{$this->opt($this->t('Frequent Guest:'))}\s*(\d+)/");

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }
//            $dates = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Hotel:'))}]/ancestor::td[1]", $root, true, '/(\d{1,2}\s+[^\d\s]+\s+\d{4}\s*\-\s*\d{1,2}\s+[^\d\s]+\s+\d{4})/');

            $hotelName = $this->http->FindSingleNode("descendant::tr[td[1][.//img] and count(td)>=2]/td[2]/descendant::text()[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Remarks:'))}]/following-sibling::tr[normalize-space()][1][not(descendant::text()[not({$this->xpath['bold']})])]", $root);

            if (empty($hotelName) || stripos($hotelName, 'Tel:') !== false) {
                $hotelName = $this->http->FindSingleNode("./descendant::tr[./td[1][.//img] and count(./td)>=4]/td[2]/descendant::text()[normalize-space()!=''][position()>1]/preceding::strong[1]", $root);
            }

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Check Out:')]/preceding::text()[normalize-space()][1]", $root, true, "/([A-Z\s]+)/");
            }

            $r->hotel()->name($hotelName);

            $address = implode(" ",
                $this->http->FindNodes("./descendant::tr[./td[1][.//img] and count(./td)>=2]/td[2]/descendant::text()[normalize-space()!=''][position()>1]",
                    $root));

            if (empty($address)) {
                $address = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'Tel:')][1]/preceding::strong[1]/ancestor::td[1]", $root);
            }

            if (!empty($address)) {
                $r->hotel()
                    ->address($address);
            } else {
                $r->hotel()
                    ->noAddress();
            }
            $phone = $this->http->FindSingleNode("./descendant::tr[./td[1][.//img] and count(./td)>=4]/td[3]/descendant::text()[{$this->eq($this->t('Tel:'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (!empty($phone)) {
                $r->hotel()->phone($phone);
            }

            $fax = $this->http->FindSingleNode("./descendant::tr[./td[1][.//img] and count(./td)>=4]/td[3]/descendant::text()[{$this->eq($this->t('Fax:'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (!empty($fax)) {
                $r->hotel()->fax($fax);
            }

            $r->booked()
                ->guests($this->http->FindSingleNode("./descendant::tr[./td[1][.//img] and count(./td)>=4]/td[4]/descendant::text()[{$this->contains($this->t('Guest(s)'))}]",
                    $root, false, "/\b(\d{1,3})\s*{$this->opt($this->t('Guest(s)'))}/"), false, true)
                ->rooms($this->http->FindSingleNode("./descendant::tr[./td[1][.//img] and count(./td)>=4]/td[4]/descendant::text()[{$this->contains($this->t('Room(s)'))}]",
                    $root, false, "/\b(\d{1,3})\s*{$this->opt($this->t('Room(s)'))}/"), false, true)
                ->checkIn2($this->http->FindSingleNode("descendant::tr[td[1][.//img] and count(td)>=4]/td[4]/descendant::text()[{$this->eq($this->t('Check In:'))}]/following::text()[normalize-space()][1]", $root) ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check In:'))}]/following::text()[normalize-space()][1]", $root) ?? $this->http->FindSingleNode("descendant::tr[normalize-space()][1]/*[1][not(contains(.,':') or contains(.,' - '))]", $root))
                ->checkOut2($this->http->FindSingleNode("descendant::tr[td[1][.//img] and count(td)>=4]/td[4]/descendant::text()[{$this->eq($this->t('Check Out:'))}]/following::text()[normalize-space()][1]", $root) ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check Out:'))}]/following::text()[normalize-space()][1]", $root));

            $tot = $this->getTotalCurrency($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Cost:'))}]/following::text()[normalize-space()][1]", $root));

            if ($tot['Total'] !== null) {
                // 0.00 USD
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            $roomDescription = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Room Description:'))}]", $root, true, "/{$this->opt($this->t('Room Description:'))}\s*(.+)/");

            $roomRate = null;
            $rates = $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('RATE'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            foreach ($rates as $rate) {
                if (preg_match("#^(\d[\d,.]*)\s*([A-Z]{3})$#", $rate, $m)) {
                    if (!isset($cur) || $cur === null) {
                        $cur = $m[2];
                    } elseif (isset($cur) && $cur !== $m[2]) {
                        $sum = $cur = null;

                        break;
                    }
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
                    $sum[] = PriceHelper::parse($m[1], $currencyCode);
                }
            }

            if (isset($cur, $sum) && $cur !== null && $sum !== null) {
                $avg = round(array_sum($sum) / count($sum), 2);
                $roomRate = $avg . ' ' . $cur . ' per night';
                $sum = $cur = null;
            }

            if ($roomDescription || $roomRate) {
                $room = $r->addRoom();

                if ($roomDescription) {
                    $room->setDescription($roomDescription);
                }

                if ($roomRate) {
                    $room->setRate($roomRate);
                }
            }

            $this->detectDeadLine($r);
        }
    }

    private function parseFlights(\DOMNodeList $segments, Email $email): void
    {
        $airs = [];

        foreach ($segments as $segment) {
            // Airline Reference: M73QFP    |    Airline Ref: AMERICAN CF QIHEXC
            $rl = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Airline Reference:'))}]/ancestor::tr[1]",
                $segment, true, '/:\s*(?:\b.{2,} )?([A-Z\d]{5,7})$/');

            if (empty($rl)) {
                $rl = CONFNO_UNKNOWN;
            }
            $airs[$rl][] = $segment;
        }

        foreach ($airs as $rl => $roots) {
            $r = null;

            $accounts = [];
            $passengers = [];
            $tickets = [];

            foreach ($roots as $root) {
                $airlineFull = $airline = $flightNumber = null;

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)[\s*]*$/',
                            $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Depart'))}]/ancestor::td[1]/preceding-sibling::td[normalize-space()][1]", $root), $m)
                    || preg_match("/^(?<nameFull>.{2,}?)\s+(?i){$this->opt($this->t('Flight'))}(?-i)\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/",
                            $this->http->FindSingleNode(".//td[normalize-space(following-sibling::td[1])='{$this->t('Status:')}']", $root), $m)
                    || preg_match("/(?:^|(?<nameFull>.{2,}?\s+)?\s*(?i){$this->opt($this->t('Flight'))}(?-i)\s+-\s+)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)$/",
                        $this->http->FindSingleNode("descendant::tr[ *[1][{$this->starts($this->t('Depart'))}] ]/preceding-sibling::tr[normalize-space()][1]/*[1]", $root), $m) // it-60812230.eml
                ) {
                    // LH0803*    |    Lufthansa - Flight LH 0803    |    Lufthansa Flight - LH 0803
                    if (!empty($m['nameFull'])) {
                        $airlineFull = $m['nameFull'];
                    }
                    $airline = empty($m['name']) ? 'noName' : $m['name'];
                    $flightNumber = $m['number'];
                }

                if (!$airlineFull) { // it-97720789.eml
                    $airlineFull = $this->http->FindSingleNode("descendant::tr[ *[2][{$this->starts($this->t('Depart'))}] ]/preceding-sibling::tr[normalize-space()][1]/*[2]", $root);
                }

                $footnotes = array_filter($this->http->FindNodes("descendant::tr[ *[{$this->eq($this->t('Passenger(s)'))}] ][1]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr/descendant::td[normalize-space()][1]", $root, "/\*$/"));

                if (count($footnotes) > 0) {
                    $ff = [];

                    if ($airlineFull
                        && preg_match_all("/(?<trName>.+?)\s*-\s*(?<ffName>{$this->opt([$airlineFull, strtoupper($airlineFull)])})\s+(?<number>[-A-z\d]{5,})(?:\s*[,;]|$)/m", implode("\n", $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('Frequent Flyer'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]", $root)), $ffMatches)
                    ) {
                        // JAMES.H SCHLOEMER - Swiss International Airlines UATF679298, ANDREA SCHLOEMER - Swiss International Airlines UAUBN67271
                        foreach ($ffMatches[0] as $i => $v) {
                            $accounts[$ffMatches['number'][$i]] = ['trName' => $this->niceTravellersNames($ffMatches['trName'][$i]), 'ffName' => $ffMatches['ffName'][$i]];
                        }
                        $ff = $ffMatches['number'];
                    }

                    if (count($ff) === 0) {
                        $ff = array_filter($this->http->FindNodes("ancestor::table[{$this->contains($this->t('Airline Reference'))}][1]/descendant::td[not(.//td) and {$this->contains($this->t('Frequent Flyer'))}]/following-sibling::td[1]", $root, "/([\w\-]+)\s*$/"));

                        foreach ($ff as $p) {
                            $accounts[$p] = null;
                        }
                    }

                    if (count($ff) === 0) {
                        $ff = $this->http->FindNodes("ancestor::table[{$this->contains($this->t('Airline Reference'))}][1]/descendant::td[not(.//td) and {$this->contains($this->t('Frequent Flyer'))}]", $root, "/([\w\-]+)\s*$/");

                        foreach ($ff as $p) {
                            $accounts[$p] = null;
                        }
                    }
                }

                // TODO: create private method isTrain($airline, $airlineFull)
                $isTrain = $airlineFull === 'Amtrak' || $airline === '2V';

                if ($r === null) {
                    if ($isTrain) {
                        $r = $email->add()->train(); // it-97720789.eml
                    } else {
                        $r = $email->add()->flight();
                    }
                }

                $s = $r->addSegment();

                if ($isTrain) {
                    $s->extra()->number($flightNumber);
                } else {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                    if ($airline === 'noName') {
                        $s->airline()->noName();
                    } else {
                        $s->airline()->name($airline);
                    }
                    $s->airline()->number($flightNumber);
                }

                $date = strtotime($this->normalizeDate(
                    $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Flight:'))}]/ancestor::td[1]", $root, true, '/(\d{1,2}\s+[[:alpha:]]{3,}[,\s]+\d{4})/u')
                    ?? $this->http->FindSingleNode("descendant::tr[normalize-space()][1]/*[1][not(contains(.,':') or contains(.,' - '))]", $root)
                ));
                $route = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Flight:'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]",
                    $root);

                if (preg_match("/([^\n]+?)\s+\(([A-Z]{3})\)\s+{$this->opt($this->t('to'))}\s+([^\n]+?)\s+\(([A-Z]{3})\)/",
                    $route, $matches)) {
                    $s->departure()
                        ->name($matches[1])
                        ->code($matches[2]);
                    $s->arrival()
                        ->name($matches[3])
                        ->code($matches[4]);
                } elseif (preg_match("/[^\(\)]*\(([A-Z]{3})\)\s+{$this->opt($this->t('to'))}\s+[^\(\)]*\(([A-Z]{3})\)/",
                    $route, $matches)) {
                    // Seoul (ICN) to (CXR)
                    $s->departure()
                        ->code($matches[1]);
                    $s->arrival()
                        ->code($matches[2]);
                } elseif (!$route) {
                    // it-60812230.eml
                    $codeDep = $this->http->FindSingleNode("descendant::tr/*[1][{$this->starts($this->t('Depart'))}]", $root, true, "/{$this->opt($this->t('Depart'))}[:\s]+([A-Z]{3})$/");

                    if (!empty($codeDep)) {
                        $s->departure()->code($codeDep);
                    }
                    $codeArr = $this->http->FindSingleNode("descendant::tr/*[1][{$this->starts($this->t('Arrive'))}]", $root, true, "/{$this->opt($this->t('Arrive'))}[:\s]+([A-Z]{3})$/");

                    if (!empty($codeArr)) {
                        $s->arrival()->code($codeArr);
                    }
                }

                $operator = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('OPERATED BY'))}]", $root,
                    true, "#OPERATED BY\s+(.+)#");

                if (!empty($operator)) {
                    $s->airline()->operator($operator);
                }

                $depText = implode("\n", $this->http->FindNodes(".//text()[{$this->starts($this->t('Depart'))}]/ancestor::td[1]//text()[normalize-space()]", $root));
                $timeDep = $this->normalizeTime($this->re('/(?:\s+\d{4}|\))\s+(.*\d{1,2}:\d{2}.*\s*(?:AM|PM)?)(?:\s+|$)/i', $depText));

                if (!$timeDep) {
                    $timeDep = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->starts($this->t('Depart'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", $root, true, "/^\d{1,2}[:]+\d{1,2}(?:\s*[AaPp]\.?[Mm]\.?)?$/");
                }

                if ($date && $timeDep) {
                    $s->departure()->date(strtotime($timeDep, $date));
                }

                $depTerm = $this->re('/Terminal\s+([A-Z\d]+)/i', $depText);

                if (!empty($depTerm)) {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                    $s->departure()->terminal($depTerm);
                }

                $arrText = implode("\n", $this->http->FindNodes(".//text()[{$this->starts($this->t('Arrive'))}]/ancestor::td[1]//text()[normalize-space()]", $root));

                $timeArr = $this->normalizeTime($this->re('/(?:\s+\d{4}|\))\s+(.*\d{1,2}:\d{2}.*\s*(?:AM|PM)?)(?:\s+|$)/i', $arrText));

                $arrdate = strtotime($this->normalizeDate($this->re('/\n\s*(.*\d{4}.*)\s*\n([\s\S]+?\d{1,2}:\d{2}.*\s*(?:AM|PM)?)\s+/', $arrText)));

                if (!empty($arrdate)) {
                    $date = $arrdate;
                }

                if (!$timeArr) {
                    $timeArr = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->starts($this->t('Arrive'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", $root, true, "/^\d{1,2}[:]+\d{1,2}(?:\s*[AaPp]\.?[Mm]\.?)?$/");
                }

                if ($date && $timeArr) {
                    $s->arrival()->date(strtotime($timeArr, $date));
                }

                $arrTerm = $this->re('/Terminal\s+([A-Z\d]+)/i', $arrText);

                if (!empty($arrTerm)) {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                    $s->arrival()->terminal($arrTerm);
                }

                $status = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);
                $s->extra()->status($status, false, true);

                $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Class:'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root);

                if (preg_match("#^([A-Z]{1,2})\s*\-\s*(.+)$#", $node, $m)) {
                    $s->extra()
                        ->cabin($m[2])
                        ->bookingCode($m[1]);
                } elseif (!empty($node) && preg_match('/[\w\/]+[ ]+\w+/', $node)) {
                    $s->extra()
                        ->cabin($node);
                } else {
                    $s->extra()
                        ->cabin($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Depart'))}]/ancestor::td[1]//text()[{$this->eq($this->t('Class:'))}]/following::text()[normalize-space(.)!=''][1]",
                            $root), true, true);
                }
                $s->extra()
                    ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space(.)!=''][1]",
                        $root, true, '/^([hm\d\s]+)$/'), false, true)
                    ->miles($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Miles Flown:'))}]/following::text()[normalize-space(.)!=''][1]",
                        $root, true, '/(\d+)/'), false, true)
                    ->meal($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Meal Service:'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('Passenger(s)'))}) and not({$this->contains($this->t('OPERATED BY'))}) and ancestor::tr[1][{$this->contains($this->t('Meal Service:'))}]]",
                        $root), false, true)
                ;

                if (!$isTrain) {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                    $s->extra()->aircraft($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Aircraft:'))}]/following::text()[normalize-space()][1]",
                    $root), false, true);
                }

                $seats = [];
                $passengerRows = $this->http->XPath->query(".//text()[{$this->starts($this->t('Passenger(s)'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][count(./td)>2]",
                    $root);

                foreach ($passengerRows as $passengerRow) {
                    $passengerName = $this->http->FindSingleNode("./td[1]", $passengerRow, null, "/^(.+?)\s*(?:{$this->opt($this->t('Ticket Number'))}.+)?$/");
                    $tickets[] = $this->http->FindSingleNode("./td[1]", $passengerRow, null, "/{$this->opt($this->t('Ticket Number'))}\s*:\s*(\d{8,})\s*$/");
                    $passengerName = $this->niceTravellersNames(preg_replace(['/(\.MR|\.MS)\s+/', '/[*]/'], [' ', ''], $passengerName));
                    $passengers[] = $passengerName;

                    if (($seat = $this->http->FindSingleNode("td[2]", $passengerRow, true, '/^(\d+[A-Z])(?:$|\s)/'))) {
                        $seats[] = $seat;
                        $s->extra()
                            ->seat($seat, true, true, $passengerName);
                    }
                }

                if (count($seats) === 0) {
                    // SEATS 31C 31D RESERVED (it-60812230.eml)
                    // SEATS 2E AND 2F
                    $seatsValue = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Remarks:'))}]/following-sibling::tr[{$this->contains($this->t('SEATS'))}]", $root, true, "/{$this->opt($this->t('SEATS'))}\s+(\d+[A-Z\d ]*?[A-Z])(?:\s+RESERVED|$)/");

                    if ($seatsValue) {
                        $seats = preg_split('/\s+|AND/', $seatsValue);
                    }

                    if (count($seats)) {
                        $s->extra()->seats($seats);
                    }
                }
            }
            $passengers = array_values(array_unique(array_filter($passengers)));

            if (count($passengers) === 0 && count($this->pax) > 0) {
                $passengers = $this->pax;
            }

            if (count($passengers) > 0) {
                $r->general()->travellers($this->niceTravellersNames($passengers), true);
            }

            foreach ($accounts as $account => $accParams) {
                $r->program()->account($account, false, $accParams['trName'] ?? null, $accParams['ffName'] ?? null);
            }

            $tickets = array_values(array_unique(array_filter($tickets)));

            if (!empty($tickets)) {
                $r->issued()->tickets($tickets, false);
            }

            if (!empty($this->tripNum)) {
                $r->ota()
                    ->confirmation($this->tripNum, rtrim($this->t('Trip ID:'), ': '));
            }

            if (!empty($this->tripLoc) && $this->tripNum !== $this->tripLoc) {
                $r->ota()
                    ->confirmation($this->tripLoc, rtrim($this->t('Trip Locator:'), ': '));
            }

            if ($rl === CONFNO_UNKNOWN) {
                $r->general()->noConfirmation();
            } else {
                $r->general()->confirmation($rl);
            }
        }
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 26 July, 2019
            '/^(\d{1,2})\s+([[:alpha:]]{3,})[,\s]+(\d{2,4})$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $text);
    }

    private function normalizeTime(?string $time)
    {
//        $this->logger->debug('$time = '.print_r( $time,true));
        $time = str_replace(['오전', '오후'], ['AM', 'PM'], $time);
        $time = preg_replace([
            "/^\s*([ap]m)\s*(\d{1,2}:\d{2}):\d{2}\s*$/i",
        ], [
            "$2 $1",
        ], $time);

        return $time;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Airline Reference:'], $words['Depart'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Airline Reference:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Depart'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (isset($words['Confirmation:'], $words['Check Out'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Confirmation:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Check Out'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (isset($words['Confirmation:'], $words['Pick up'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Confirmation:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Pick up'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^CANCEL PERMITTED UP TO (?<prior>\d+) DAYS? BEFORE ARRIVAL. [\d\.]+ CANCEL FEE PER ROOM./i",
            $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');

            return;
        }

        if (preg_match("/^CXL (?<h>\d{2})(?<min>\d{2}) HTL TIME ON (?<d>\d+)\s*(?<m>\w+?)\s*(?<y>\d{2})\-CXL FEE FULL STAY\-INCL TAX/i",
            $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m['d'] . ' ' . $m['m'] . ' 20' . $m['y'] . ', ' . $m['h'] . ':' . $m['min']));

            return;
        }
    }

    private function getFlightIfOne(Email $email): ?Flight
    {
        $flights = [];

        foreach ($email->getItineraries() as $i => $it) {
            if ($it->getType() === 'flight') {
                $flights[] = $i;
            }
        }

        if (count($flights) == 1) {
            /** @var Flight $r */
            $r = $email->getItineraries()[array_shift($flights)];

            return $r;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function niceTravellersNames($travellers)
    {
        $travellers = str_replace('.', ' ', $travellers);

        return $travellers;
    }
}
