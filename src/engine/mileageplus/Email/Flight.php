<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-208272831.eml, mileageplus/it-36428025.eml, mileageplus/it-42211199.eml, mileageplus/it-42390235.eml, mileageplus/it-42406619.eml, mileageplus/it-42719017.eml, mileageplus/it-59865564.eml";

    private $lang = 'en';

    private $detects = [
        'Thank you for choosing',
        'processed your cancellation', 'processed your cancelation',
        'Your reservation has been cancelled', 'Your reservation has been canceled',
        'MileagePlus Accrual Details', 'Traveler Details',
    ];

    private $from = '/[@.]united\.com/i';

    private $prov = "//a[contains(@href,'.united.com/') or contains(@href,'www.united.com')]"
        . " | //img[contains(@src,'united.com')]"
        . " | //text()[contains(normalize-space(),'United Airlines') or contains(translate(.,' ',''),'Class:United')]"
    ;

    private $subject;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $c = explode('\\', __CLASS__);
        $email->setType(end($c) . ucfirst($this->lang));

        $xpathTime = '(starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';
        $flightRoots = $this->http->XPath->query("//tr[ *[1][{$xpathTime}] and *[3][{$xpathTime}] ]/ancestor::*[ descendant::tr[normalize-space()='Purchase Summary'] ][1]");

        if ($flightRoots->length < 2) {
            $this->parseFlight($email);
        } else {
            foreach ($flightRoots as $fRoot) {
                $this->parseFlight($email, $fRoot);
            }
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'eTicket Itinerary and Receipt for Confirmation') !== false
            || stripos($headers['subject'], 'Your reservation has been canceled') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (0 === $this->http->XPath->query($this->prov)->length) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(),\"" . $detect . "\")]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseFlight(Email $email, ?\DOMNode $fRoot = null): void
    {
        $f = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("descendant::tr[normalize-space()='Confirmation Number:' and not(.//tr)][1]/following-sibling::tr[normalize-space()][1]", $fRoot, true, '/^[A-Z\d]{5,9}$/');

        if (empty($confNo) && (
                preg_match("/eTicket Itinerary and Receipt for Confirmation\s+([A-Z\d]{5,9})/", $this->subject, $m)
                || preg_match("/Your reservation has been cancell?ed\s*\(\s*([A-Z\d]{5,9})\s*\)/", $this->subject, $m)
            )
        ) {
            $confNo = $m[1];
        }

        if (empty($confNo)
            && empty($this->http->FindSingleNode("descendant::*[{$this->contains(['Confirmation', 'confirmation'])}]", $fRoot))
        ) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confNo);
        }

        $xpathTravelers = "descendant::tr[normalize-space()='Traveler Details']/following-sibling::tr";

        $travellerRows = $this->http->XPath->query($xpathTravelers, $fRoot);
        $currentTraveller = null;
        $parsedSeats = [];

        for ($i = 0; $i < $travellerRows->length; $i++) {
            $tds = $this->http->FindNodes('td', $travellerRows->item($i));
            // old format - full name without '/' separator in the name
            if (count($tds) === 1 && preg_match('/^[a-z\s]+$/i', $tds[0]) > 0) {
                $currentTraveller = $tds[0];
                $f->general()->traveller($tds[0]);

                continue;
            }
            $row = implode(' ', $tds);

            if (preg_match('/^[A-Z]+\/[A-Z]+/', $row, $m) > 0) {
                $f->general()->traveller($m[0]);
                $currentTraveller = $m[0];
            }

            if (!empty($currentTraveller)) {
                if (preg_match('/eTicket number:\s+(\d+)/', $row, $m) > 0) {
                    $f->issued()->ticket($m[1], false, $currentTraveller);
                }

                if (preg_match('/([A-Z]{3}-[A-Z]{3})\s+(\d+[A-Z])/', $row, $m) > 0) {
                    $parsedSeats[$m[1]][] = [$m[2], $currentTraveller];
                }

                if (preg_match('/(Frequent Flyer|MileagePlus number):\s+([-A-Z]*X+\d+)/', $row, $m) > 0) {
                    $f->program()->account($m[2], true, $currentTraveller);
                }
            }
        }

        $total = $this->http->FindSingleNode("descendant::td[normalize-space()='Total:' and not(.//td)][preceding::tr[1][contains(normalize-space(),'Total Per Passenger')]]/following-sibling::td[normalize-space()][1]", $fRoot);

        if (empty($total)) {
            $total = $this->http->FindSingleNode("descendant::td[normalize-space()='Total:' and not(.//td)]/following::text()[normalize-space()][1]", $fRoot);
        }

        if (isset($total)) {
            if (preg_match('/^\s*(\d[\d, ]*? miles)/', $total, $m)) {
                $f->price()
                    ->spentAwards($m[1]);
            }
            // 2469.22 USD
            if (preg_match('/([\d.]+)\s*([A-Z]{3})/', $total, $m)) {
                $f->price()
                    ->total($m[1])
                    ->currency($m[2]);
            }

            if (count($f->getTravellers()) == 1) {
                $baseFare = $this->http->FindSingleNode("descendant::tr[starts-with(normalize-space(),'Airfare:') and not(.//tr)]/td[normalize-space()][2]", $fRoot);

                if (preg_match('/([\d.]+)\s*([A-Z]{3})/', $baseFare, $m) && $f->getPrice() && $f->getPrice()->getCurrencyCode() == $m[2]) {
                    $f->price()->cost($m[1]);
                }
                $fees = $this->http->XPath->query("descendant::tr[starts-with(normalize-space(),'Airfare:') and not(.//tr)]/following-sibling::tr[normalize-space()][not(contains(.,'Total'))]", $fRoot);

                foreach ($fees as $fee) {
                    $name = $this->http->FindSingleNode('td[1]', $fee, true, '/^(.+?)[\s:]*$/');
                    $charge = $this->http->FindSingleNode('td[2]', $fee, true, '/([\d\.]+)/');

                    if (!empty($name) && $charge !== null) {
                        $f->price()->fee($name, $charge);
                    }
                }
            }
        }

        if ($date = $this->http->FindSingleNode('descendant::tr[normalize-space()="Purchase Summary" and not(.//tr)]/following-sibling::tr/td[contains(normalize-space(),"Date of purchase")]/following-sibling::td[string-length()>0][1]', $fRoot, true, '/^[\w\d,\s]+\d{4}$/')) {
            $f->general()->date($this->normalizeDate($date));
        }

        $xpath = "descendant::tr[starts-with(normalize-space(),'Flight') and contains(.,' of ') and not(.//tr) and following-sibling::tr[2]/td[1][contains(.,':')]]";
        $roots = $this->http->XPath->query($xpath, $fRoot);

        if (0 === $roots->length) {
            $xpath = "descendant::tr[starts-with(normalize-space(),'Flight') and contains(.,' of ') and not(.//tr) and following-sibling::tr[2]/td[1][contains(.,'(')]]";
            $roots = $this->http->XPath->query($xpath, $fRoot);
        }

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            if (preg_match("/Flight \d{1,3} of \d{1,3} (?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)(?:\D|$)/", $root->nodeValue, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            if (preg_match("/(?:Class|Cabin)[:\s]+(.+?)\s*$/", $root->nodeValue, $m)) {
                if (preg_match('/^(.{2,}?)\s*\(\s*([A-Z]{1,2})\s*\)$/', $m[1], $matches)) {
                    // Economy (L)
                    $s->extra()
                        ->cabin($matches[1])
                        ->bookingCode($matches[2]);
                } elseif (preg_match('/^\(?\s*([A-Z]{1,2})\s*\)?$/', $m[1], $matches)) {
                    // (L)    |    L
                    $s->extra()->bookingCode($matches[1]);
                } else {
                    // Economy
                    $s->extra()->cabin($m[1]);
                }
            }

            $operator = $this->http->FindSingleNode("following-sibling::tr[starts-with(normalize-space(), 'Flight Operated by')]/td[1]", $root, true, "/{$this->opt('Flight Operated by')}\s*(.+)/");

            if (!empty($operator)) {
                $operator = trim(preg_replace("/dba.+/", "", $operator), '.');

                if (preg_match("/operated by/iu", $operator)) {
                    $operator = $this->re("/^(\D+)\s*operated by/", $operator);
                }

                if (strlen($operator) >= 50) {
                    $operator = $this->re("/^(\D+)\./", $operator);
                }

                if (strlen($operator) >= 50) {
                    $operator = $this->re("/^(\D+)\s*for/", $operator);
                }

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }
            }

            $dDate = str_replace(',', '', $this->http->FindSingleNode('following-sibling::tr[1]/td[1]', $root));
            $aDate = str_replace(',', '', $this->http->FindSingleNode('following-sibling::tr[1]/td[last()]', $root));

            if (empty($aDate) && !empty($dDate)) {
                $aDate = $dDate;
            }

            $dTime = $this->http->FindSingleNode('following-sibling::tr[2]/td[normalize-space()][1]', $root);
            $aTime = $this->http->FindSingleNode('following-sibling::tr[2]/td[normalize-space()][last()]', $root);

            $this->logger->debug('$dTime = ' . print_r($dTime, true));

            if (preg_match("/[ ]+\([A-Z]{3}\)\s*$/", $dTime) && preg_match("/[ ]+\([A-Z]{3}\)\s*$/", $aTime)) {
                $dName = $dTime;
                $aName = $aTime;

                $s->departure()
                    ->day($this->normalizeDate($dDate))
                    ->noDate()
                ;
                $s->arrival()
                    ->day($this->normalizeDate($dDate))
                    ->noDate()
                ;
            } else {
                $s->departure()
                    ->date($this->normalizeDate($dDate . ', ' . $dTime));

                $s->arrival()
                    ->date($this->normalizeDate($aDate . ', ' . $aTime));

                $dName = $this->http->FindSingleNode('following-sibling::tr[3]/td[1]', $root, true, '/.+[ ]+\([A-Z]{3}\)/');
                $aName = $this->http->FindSingleNode('following-sibling::tr[3]/td[string-length()>0][last()]', $root, true, '/.+[ ]+\([A-Z]{3}\)/');
            }

            $s->departure()
                ->name($this->re('/(.+)[ ]+\([A-Z]{3}\)/', $dName))
                ->code($this->re('/.+[ ]+\(([A-Z]{3})\)/', $dName))
            ;

            $s->arrival()
                ->name($this->re('/(.+)[ ]+\([A-Z]{3}\)/', $aName))
                ->code($this->re('/.+[ ]+\(([A-Z]{3})\)/', $aName))
            ;

            $dCode = $s->getDepCode();
            $aCode = $s->getArrCode();

            if (!empty($dCode) && !empty($aCode)) {
                if (isset($parsedSeats[$dCode . '-' . $aCode])) {
                    foreach ($parsedSeats[$dCode . '-' . $aCode] as $seatRow) {
                        $s->addSeat($seatRow[0], false, false, $seatRow[1]);
                    }
                }
            }

            $status = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'processed your cancellation') or contains(normalize-space(),'processed your cancelation')]", $fRoot, true, "/processed your (cancell?ation)/")
            ?? $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'reservation has been cancelled') or contains(normalize-space(),'reservation has been canceled')]", $fRoot, true, "/reservation has been (cancell?ed)\s*(?:[,.;!?]|$)/");

            if ($status) {
                $f->general()
                    ->status($status)
                    ->cancelled();
            }

            foreach ($f->getSegments() as $seg) {
                if ($s->getId() !== $seg->getId() && serialize($s->toArray()) == serialize($seg->toArray())) {
                    $f->removeSegment($s);

                    break;
                }
            }
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\w+)\s*(\d+)\s*(\d{4})\,\s*([\d\:]+\s*A?P?M)$#", //Sun Sep 12 2021, 09:40 AM
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})$#", //Sun Sep 12 2021, 09:40 AM
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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
}
