<?php

namespace AwardWallet\Engine\spicejet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingPNR extends \TAccountChecker
{
    public $mailFiles = "spicejet/it-74484282.eml, spicejet/it-190171572.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'itinerary@spicejet.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], 'SpiceJet Booking PNR') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, 'SpiceJet Limited') !== false
            && $this->http->XPath->query('//tr[ not(.//tr) and *[starts-with(normalize-space(),"PNR:")] and following-sibling::tr[*[starts-with(normalize-space(),"Booking Ref")] and following-sibling::tr[*[starts-with(normalize-space(),"Booked on")]]] ]')->length > 0
            && $this->http->XPath->query('//tr/*[ descendant::a[contains(normalize-space(),"Flight Status")] and following-sibling::*/descendant::a[contains(normalize-space(),"Check-in Now")] ]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@spicejet.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $nodes = $this->http->XPath->query('//tr[ *[not(.//tr) and starts-with(normalize-space(),"PNR:")] ]/..');
        $it = $email->add()->flight();

        if ($nodes->length > 0) {
            $pnr = $this->http->FindSingleNode('tr[contains(., "PNR")]', $nodes->item(0), true, '/PNR\s*:\s*([A-Z\d]{6})(?:\s*\/|$)/');
            $ref = $this->http->FindSingleNode('tr[contains(normalize-space(), "Booking Ref")]', $nodes->item(0), true, '/Booking Ref\.\s*No\s*:\s*(\d{5,})$/i');
            $date = $this->http->FindSingleNode('tr[contains(normalize-space(), "Booked on")]', $nodes->item(0), true, '/Booked on\s*:\s*([a-z]{3}, [a-z]{3}\. \d{1,2}, \d{4} \d{1,2}:\d{2})/i');
            $status = $this->http->FindSingleNode('tr[contains(., "Status")]', $nodes->item(0), true, '/Status\s*:\s*(.+)/i');
        }

        if (isset($pnr, $ref, $date, $status)) {
            $it->general()->confirmation($pnr, 'PNR')
                ->confirmation($ref, 'Booking Ref')
                ->date(strtotime($date))
                ->status($status);

            if (preg_match("/Booking PNR\s+{$pnr}\s+-\s+Ticket Number\s+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})\s*:\s*for/i", $parser->getSubject(), $m)) {
                // SpiceJet Booking PNR YGECUT - Ticket Number 140912350604 : for MR. SHOAIB
                $it->issued()->ticket($m[1], false);
            }
        }

        $codes = $seats = [];
        $nodes = $this->http->XPath->query('//tr[ not(.//tr) and *[1][normalize-space()="Passenger Name"] ]');

        if ($nodes->length > 0) {
            $header = $nodes->item(0);

            $paxRows = $this->http->XPath->query('following-sibling::tr[*[4] and normalize-space()]', $header);

            foreach ($paxRows as $paxRow) {
                $paxValue = $this->htmlToText($this->http->FindHTMLByXpath('*[1][normalize-space()]', null, $paxRow));

                $names = preg_split("/(?:[ ]*\n[ ]*){2,}/", trim($paxValue));

                foreach ($names as $name) {
                    $name = preg_replace("/(Student ID Card.+)/", "", $name);

                    if (preg_match('/FREQUENT FLYER\s*[:]+\s*(\d+)/i', $name, $m) > 0) {
                        $name = preg_replace('/\s*FREQUENT FLYER\s*[:]+\s*\d+/i', '', $name);
                        $it->program()->account($m[1], false);
                    }
                    $name = preg_replace('/\s*PASSPORT\s*[:]+.*/i', '', $name);

                    if (preg_match("/^({$patterns['travellerName']})[ ]*\n+[ ]*\([ ]*Infant\b/i", $name, $m)) {
                        $it->general()->infant($m[1], true);
                    } elseif (preg_match("/^{$patterns['travellerName']}$/", $name, $m)) {
                        $it->general()->traveller(preg_replace("/^(Mr\.|Mrs\.|Ms\.)/iu", "", $name), true);
                    } elseif (!empty($name)) {
                        $this->logger->debug('Wrong passenger name!');
                        $email->add()->hotel(); // for 100% fail
                    }
                }
            }

            $nextRows = $this->http->XPath->query('following-sibling::tr[normalize-space()]', $header);

            foreach ($nextRows as $row) {
                $flightVal = $servicesVal = null;

                if ($this->http->XPath->query('*', $row)->length === 3) { // it-190171572.eml
                    $flightVal = implode(' ', $this->http->FindNodes('*[1]/descendant::text()[normalize-space()]', $row));
                    $servicesVal = implode(' ', $this->http->FindNodes('*[3]/descendant::text()[normalize-space()]', $row));
                } elseif ($this->http->XPath->query('*', $row)->length === 4) { // it-74484282.eml
                    $flightVal = implode(' ', $this->http->FindNodes('*[2]/descendant::text()[normalize-space()]', $row));
                    $servicesVal = implode(' ', $this->http->FindNodes('*[4]/descendant::text()[normalize-space()]', $row));
                }

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*\(\s*(?<code1>[A-Z]{3})\s*-\s*(?<code2>[A-Z]{3})\s*\)$/', $flightVal, $m)) {
                    $key = $m['name'] . $m['number'];
                    $codes[$key] = [$m['code1'], $m['code2']];

                    if (preg_match('/Seat\s+(?<seat>\d+[A-Z])\b/i', $servicesVal, $m2)) {
                        if (array_key_exists($key, $seats)) {
                            $seats[$key][] = $m2['seat'];
                        } else {
                            $seats[$key] = [$m2['seat']];
                        }
                    }
                }
            }
        }

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $segments = $this->http->XPath->query("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][1][{$xpathTime}] and *[normalize-space()][3][{$xpathTime}] ]");

        foreach ($segments as $root) {
            $seg = $it->addSegment();

            $td1 = implode("\n", $this->http->FindNodes('*[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()]', $root));
            $td2 = implode("\n", $this->http->FindNodes('*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()]', $root));
            $td3 = implode("\n", $this->http->FindNodes('*[normalize-space()][3]/descendant::tr[not(.//tr) and normalize-space()]', $root));

            /*
                Delhi (T1D)
                Mon Sep. 26, 2022 9:35 Hrs
            */
            $pattern = "/^"
                . "(?<name>.{3,}?)(?:\s*\(\s*T(?<terminal>\d[A-Z\d]*)\s*\))?\n"
                . "(?:.*Hrs\s)?(?<dateTime>.{6,}?)(?:\s*Hrs)?"
                . "$/i";

            $this->logger->debug($td1);

            if (preg_match($pattern, $td1, $m)) {
                $seg->departure()->name($m['name'])->date2($m['dateTime'])->strict();

                if (array_key_exists('terminal', $m) && $m['terminal'] !== '') {
                    $seg->departure()->terminal($m['terminal']);
                }
            }

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $td2, $m)) {
                $seg->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match($pattern, $td3, $m)) {
                $seg->arrival()->name($m['name'])->date2($m['dateTime'])->strict();

                if (array_key_exists('terminal', $m) && $m['terminal'] !== '') {
                    $seg->arrival()->terminal($m['terminal']);
                }
            }

            if ($seg->getAirlineName() && $seg->getFlightNumber()) {
                $key = $seg->getAirlineName() . $seg->getFlightNumber();

                if (array_key_exists($key, $codes)) {
                    $seg->departure()->code($codes[$key][0]);
                    $seg->arrival()->code($codes[$key][1]);
                }

                if (array_key_exists($key, $seats)) {
                    foreach ($seats[$key] as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->starts('Seat ' . $seat)}]/ancestor::tr[1][{$this->contains($seg->getDepCode() . '-' . $seg->getArrCode())}]/descendant::td[string-length()>2][1]")
                            ?? $this->http->FindSingleNode("//text()[{$this->starts('Seat ' . $seat)}]/ancestor::tr[1][{$this->contains($seg->getDepCode() . ' - ' . $seg->getArrCode())}]/descendant::td[string-length()>2][1]")
                            ?? $this->http->FindSingleNode("//text()[{$this->starts('Seat ' . $seat)}]/ancestor::tr[2][{$this->contains($seg->getDepCode() . '-' . $seg->getArrCode())}]/descendant::td[string-length()>2][1]");

                        if (preg_match("/{$seg->getDepCode()}\s*\-\s*{$seg->getArrCode()}/", $pax)) {
                            $pax = $this->http->FindSingleNode("//text()[{$this->starts('Seat ' . $seat)}]/ancestor::tr[1][{$this->contains($seg->getDepCode() . '-' . $seg->getArrCode())}]/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'Passenger Name'))][1]/descendant::td[string-length()>2][1]")
                            ?? $this->http->FindSingleNode("//text()[{$this->starts('Seat ' . $seat)}]/ancestor::tr[1][{$this->contains($seg->getDepCode() . ' - ' . $seg->getArrCode())}]/ancestor::table[1]/descendant::tr[not(contains(normalize-space(), 'Passenger Name'))][1]/descendant::td[string-length()>2][1]");
                        }

                        if (!empty($pax)) {
                            $seg->extra()
                                ->seat($seat, true, true, preg_replace("/^(Mr\.|Mrs\.|Ms\.)/iu", "", preg_replace("/(Student ID Card.+)/", "", $pax)));
                        } else {
                            $seg->extra()
                                ->seat($seat);
                        }
                    }
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode('//tr[ *[2][contains(normalize-space(),"Total Price")] ]/following-sibling::tr[normalize-space()][1]/*[2]');

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalPrice, $matches)) {
            // 8,417 INR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $it->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
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
