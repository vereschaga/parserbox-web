<?php

namespace AwardWallet\Engine\preferred\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2010402 extends \TAccountChecker
{
    public $mailFiles = "preferred/it-1.eml, preferred/it-1585005.eml, preferred/it-1593570.eml, preferred/it-2010402.eml, preferred/it-2193366.eml, preferred/it-2193539.eml, preferred/it-2904182.eml, preferred/it-42265383.eml, preferred/it-6656774.eml";

    public static $dictionary = [
        "en" => [
            'Arrive:' => ['Arrive:', 'Arrival:'],
        ],
    ];
    private $subjects = [
        'Your reservation for',
    ];

    private $detects = [
        'Preferred Hotel Group',
        'Thank you for choosing Hotel Inglaterra - Preferred LIFESTYLE Collection',
        'Thank you for choosing Il Salviatino - Preferred LEGEND Collection',
        'Thank you for choosing Bernini Palace Hotel',
        'To view, change, or cancel your itinerary',
        'Your reservation is scheduled for',
    ];

    private $lang = 'en';

    private $provider = "Preferred";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $c = explode('\\', __CLASS__);
        $email->setType(end($c) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Your Preferred Hotel Group Itinerary') !== false) {
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->provider)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect) || $this->http->XPath->query("//text()[contains(., '{$detect}')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@preferredhotelgroup.com') !== false;
    }

    private function parseEmail(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        if ($otaConfirmation = $this->http->FindSingleNode("//text()[" . $this->contains(["Itinerary #:", "Confirmation Number:"]) . "]/following-sibling::a[contains(@href,'confirm=')]")) {
            $email->ota()->confirmation($otaConfirmation, $this->http->FindSingleNode("//text()[" . $this->contains(["Itinerary #:", "Confirmation Number:"]) . "][following-sibling::a[contains(@href,'confirm=')]]", null, true, '/^(.+?)[\s:：]*$/u'));
        } elseif ($otaConfirmation = $this->http->FindSingleNode("(//text()[" . $this->eq(["Itinerary #:", "Confirmation Number:"]) . "])[1]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,})\s*$/")) {
            $email->ota()->confirmation($otaConfirmation, $this->http->FindSingleNode("//text()[" . $this->contains(["Itinerary #:", "Confirmation Number:"]) . "][1]", null, true, '/^(.+?)[\s:：]*$/u'));
        } elseif ($otaConfirmation = $this->http->FindSingleNode("//text()[" . $this->starts(["Itinerary #:", "Confirmation Number:"]) . "][1]", null, true, "/^\s*(?:Itinerary #:|Confirmation Number:)\s*([A-Z\d]{5,})\s*$/")) {
            $email->ota()->confirmation($otaConfirmation, $this->http->FindSingleNode("//text()[" . $this->contains(["Itinerary #:", "Confirmation Number:"]) . "]", null, true, "/^\s*(Itinerary #|Confirmation Number):\s*[A-Z\d]{5,}\s*$/"));
        }

        $xpath = "//text()[contains(normalize-space(),'Your reservation is scheduled for')]/ancestor::table[1]";
        $hotels = $this->http->XPath->query($xpath);
        $hotelsCount = $hotels->length;

        if ($hotelsCount == 0) {
            $xpath = "//tr[td[2][descendant::text()[normalize-space()][1][normalize-space()='Guest Information:']] and descendant::td[1][contains(., 'Night')]]";
            $hotels = $this->http->XPath->query($xpath);
        }

        $this->logger->debug("Found {$hotels->length} hotels");

        if (0 === $hotelsCount) {
            $this->logger->debug("Hotels did not found by xpath: {$xpath}");
        }

        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            // General
            $pax = $this->http->FindSingleNode("descendant::text()[{$this->starts('Guest Information')}]/following::text()[normalize-space()][1]", $hotel, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if (!$pax) {
                $pax = $this->http->FindSingleNode("(//text()[{$this->starts('Hello')}][1])[1]", $hotel, true, '/Hello\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:[,!]+|$)/u');
            }

            if ($pax) {
                $h->addTraveller(preg_replace("/^(?:Mrs|Mr|Ms)[\s\.]/", "", $pax));
            }

            if ($confNo = $this->http->FindSingleNode("descendant::text()[{$this->starts('Confirmation #')}][1]/following::text()[normalize-space()][1]", $hotel, true, '/^[-A-Z\d]{5,}$/')) {
                $h->general()->confirmation($confNo);
            } elseif (empty($this->http->FindSingleNode("descendant::text()[{$this->starts('Confirmation #')}][1]", $hotel))) {
                $h->general()->noConfirmation();
            }

            // Hotel

            $hName = $this->http->FindSingleNode('following::text()[normalize-space()][1]', $hotel);

            if (stripos($hName, 'Information') !== false) {
                $hName = $this->http->FindSingleNode("./following::text()[contains(normalize-space(),'View Map')][1]/ancestor::table[1]/descendant::text()[normalize-space()][1]", $hotel);
            }

            if ($hName && $this->http->XPath->query('//*[contains(normalize-space(),"' . $hName . '")]')->length > 1) {
                $h->hotel()->name($hName);
            }

            if ($checkIn = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Arrive:'))}][1])[1]", $hotel, true, "/{$this->opt($this->t('Arrive:'))}[ ]*(\w+, \w+ \d+, \d+)/")) {
                $h->booked()
                    ->checkIn(strtotime($checkIn));
            }

            if ($checkOut = $this->http->FindSingleNode("(//text()[{$this->starts('Depart:')}][1])[1]", $hotel, true, '/Depart\:[ ]*(\w+, \w+ \d+, \d+)/')) {
                $h->booked()
                    ->checkOut(strtotime($checkOut));
            }

//            $addressTexts = $this->http->FindNodes("following::span[position()<3][{$this->contains('View Map')}]/descendant::text()[normalize-space()]", $hotel);
//            $address = implode(' ', $addressTexts);
//            $address = preg_replace("/\s*View Map\s*/i", '', $address);
//            if ( !empty($h->getHotelName()) && false !== stripos($address, $h->getHotelName()) )
//                $h->hotel()->noAddress();
//            else
//                $h->hotel()->address($address);
            $addressUrl = $this->http->FindSingleNode("(following::a[{$this->contains('View Map')} and contains(@href,'maps.google.com')]/@href)[1]", $hotel);
            $addressUrl = parse_url($addressUrl);

            if (isset($addressUrl['query'])) {
                parse_str($addressUrl['query'], $params);

                if (empty($params['q']) && !empty($params['url']) && stripos($params['url'], 'maps.google.com') !== false) {
                    $addressUrl = parse_url($params['url']);

                    if (!empty($addressUrl['query'])) {
                        parse_str($addressUrl['query'], $params);
                    }
                }

                if (isset($params['q'])) {
                    $h->hotel()->address(preg_replace('/\s+/', ' ', $params['q']));
                }
            }

            if (empty($h->getAddress()) && !empty($h->getHotelName())) {
                $address = implode(", ", $this->http->FindNodes("following::text()[{$this->eq($h->getHotelName())}][1]/ancestor::*[{$xpathBold}]/following-sibling::*[not(self::br) and normalize-space()][1][self::span]/descendant::text()[normalize-space() and not({$this->contains('View Map')})]", $hotel, '/^[, ]*(.{2,}?)[, ]*$/'));

                if (!empty($address)) {
                    $h->hotel()->address($address);
                }
            }

            $guests = $this->http->FindSingleNode("(.//text()[{$this->starts('Adults:')}][1])[1]/following::text()[normalize-space()][1]", $hotel, true, '/^\d{1,3}$/');

            if ($guests !== null) {
                $h->booked()->guests($guests);
            }

            $kids = $this->http->FindSingleNode("(.//text()[{$this->starts('Children:')}][1])[1]/following::text()[normalize-space()][1]", $hotel, true, '/^(\d{1,3})(?:\s*\(|$)/');

            if ($kids !== null) {
                $h->booked()->kids($kids);
            }

            $rooms = $this->http->FindSingleNode("(.//text()[{$this->starts('Rooms:')}][1])[1]/following::text()[normalize-space()][1]", $hotel, true, '/^\d{1,3}$/');

            if ($rooms !== null) {
                $h->booked()->rooms($rooms);
            }

            if ($cancel = $this->http->FindSingleNode('following::table[normalize-space()][1]', $hotel, true, '/[ ]*CANNOT CANCEL[ ]*/')) {
                $h->general()
                    ->cancellation($cancel);
            } elseif ($cancel = $this->http->FindSingleNode('following::table[normalize-space()][2]', $hotel, true, '/(Reservations? must be cancelled.+)/')) {
                $h->general()
                    ->cancellation($cancel);
            } elseif ($cancel = $this->http->FindSingleNode("following::table[normalize-space()][position()<4][not(.//table)][contains(normalize-space(),'Subtotal:')][1]/following::text()[normalize-space()][position()<3][contains(normalize-space(),'cancel') or contains(normalize-space(),'Cancel')][1]", $hotel, true, '/(In case of cancellation.+|Reservations must be cancelled.+|All reservations must be guaranteed.+)/')) {
                $h->general()->cancellation($cancel);
            }

            if (preg_match('/Reservations? must be cancelled[ ]*at least[ ]*(?<prior>\d{1,3} days?) prior to arrival by (?<hour>\d{1,2}[ ]*[ap]m) local hotel time to the avoid first night charge/i', $cancel, $m)
                || preg_match('/Reservations? must be cancelled by (?<hour>\d{1,2}[ ]*[ap]m) local time (?<prior>\d{1,3} hours?) prior to arrival to avoid a 1 night plus tax fee\./i', $cancel, $m)
                || preg_match('/In case of cancellation,\s*advise the hotel (?<prior>\d{1,3} days?) before the arrival date within (?<hour>\d{1,2}[ ]*[ap]m) local time\./i', $cancel, $m)
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' -1 day', $m['hour']);
            }

            if (preg_match('/Cancel (\d+ hours) prior to (\d+[ap]m) day of arrival/i', $cancel, $m)) {
                $h->booked()->deadlineRelative($m[1], $m[2]);
            }

            if (preg_match('/Enjoy flexibility with this rate with no charges if changes are made more than (\d+\s*hours?) prior to arrival date/i', $cancel, $m)) {
                $h->booked()->deadlineRelative($m[1]);
            }

            $info1 = '';
            $infoCells = $this->http->XPath->query("following::table[normalize-space()][1]/descendant::td[not(.//td) and normalize-space()]", $hotel);

            foreach ($infoCells as $infoCell) {
                $infoHtml = $this->http->FindHTMLByXpath('.', null, $infoCell);
                $info1 .= $this->htmlToText($infoHtml) . "\n";
            }

            if (stripos($info1, 'Information') !== false) {
                $info1 = $this->http->FindSingleNode("following::tr[contains(normalize-space(), 'Room:')][1]", $hotel);
            }

            $room = $h->addRoom();

            if (preg_match("/^[ ]*Rate:[ ]*(.{2,})$/m", $info1, $m)) {
                $room->setRateType($m[1]);
            }

            $type = $this->http->FindSingleNode("(./following::table[1]//*[contains(text(), 'Room:')])[1]", $hotel);

            if (empty($type)) {
                $type = $this->http->FindSingleNode("following::tr[contains(normalize-space(), 'Room:')][1]/descendant::text()[normalize-space()][1]", $hotel);
            }
            $type = preg_replace('/Room:/i', '', $type);

            if (!empty($type)) {
                $room->setType($type);
            }

            $desc = $this->re("/{$type}\s*([\s\S]+?)\s*Price Breakdown/", $info1);

            if (empty($desc) && stripos($info1, 'Room:') !== false) {
                $desc = $this->re("/{$type}\s*(.+)/", $info1);
            }

            if (!empty(trim($desc))) {
                $room->setDescription(preg_replace('/\s+/', ' ', $desc));
            }

            $payment = '';
            $paymentCells = $this->http->XPath->query("following::table[normalize-space()][position()<4][not(.//table)][contains(normalize-space(),'Subtotal:')][1]/descendant::td[not(.//td) and normalize-space()]", $hotel);

            if ($paymentCells->length == 0) {
                $paymentCells = $this->http->XPath->query("following::table[normalize-space()][not(.//table)][contains(normalize-space(),'Subtotal:')][1]/descendant::td[not(.//td) and normalize-space()]", $hotel);
            }

            foreach ($paymentCells as $paymentCell) {
                $paymentHtml = $this->http->FindHTMLByXpath('.', null, $paymentCell);
                $payment .= $this->htmlToText($paymentHtml) . "\n";
            }
            $payment = str_replace(',', '', $payment);

            $cost = $this->re('/Subtotal:\s*[A-Z]{3}\s*([\d.,]+)/s', $payment);
            $tax = $this->re('/Taxes:\s*[A-Z]{3}\s*([\d.,]+)/s', $payment);
            $tot = $this->re('/Reservation Total:\s*([A-Z]{3}\s*[\d.,]+)/s', $payment);

            if (isset($cost) && isset($tax) && !empty($tot)) {
                $h->price()
                    ->cost($cost)
                    ->tax($tax)
                    ->total($this->re('/([\d\.]+)/', $tot))
                    ->currency($this->currency($tot));
            }
        }

        $data = [];
        // collect data for compare
        /** @var \AwardWallet\Schema\Parser\Common\Hotel $itinerary */
        foreach ($email->getItineraries() as $key => $itinerary) {
            $pax = implode(', ', array_map(function ($e) { return $e[0]; }, $itinerary->getTravellers()));
            $data[$key] = [
                $itinerary->getHotelName(),
                $itinerary->getCheckInDate(),
                $itinerary->getCheckOutDate(),
                $pax,
                !empty($itinerary->getPrice()) ? $itinerary->getPrice()->getTotal() : null,
                $itinerary->getGuestCount(),
                $itinerary->getKidsCount(),
                $itinerary->getTravellers(),
                $itinerary->getConfirmationNumbers(),
            ];
        }

        $data = array_map("unserialize", array_unique(array_map("serialize", $data)));

        // remove duplicate itineraries
        foreach ($email->getItineraries() as $key => $itinerary) {
            if (empty($data[$key])) {
                $conf = $itinerary->getConfirmationNumbers()[0][0];

                if (1 === count($data)) {
                    $email->getItineraries()[0]->general()->confirmation($conf);
                }
                $email->removeItinerary($itinerary);
            }
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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
