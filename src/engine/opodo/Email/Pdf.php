<?php

namespace AwardWallet\Engine\opodo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Pdf extends \TAccountChecker
{
    public $mailFiles = "opodo/it-30751948.eml";

    private $from = '/[@\.]travelinc\.com/i';

    private $detects = [
        'THIS MESSAGE CONFIRMS THAT YOUR RESERVATION',
    ];

    private $prov = 'travelinc';

    private $lang = 'en';

    private $subjects = [
        '',
    ];

    private $checkOutDates = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 === count($pdfs)) {
            return null;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                $this->parseEmail($email, $body);

                break;
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 === count($pdfs)) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email, string $text): void
    {
        $f = $email->add()->flight();

        if ($conf = $this->re('/Record Locator[ ]*\:[ ]*(\w+)/i', $text)) {
            $f->ota()
                ->confirmation($conf);
        }

        $f->general()
            ->noConfirmation();

        $sText = $this->cutText('Record Locator', 'Additional Information', $text);

        $hotels = [];
        $travellers = [];
        $tickets = [];
        $ffNums = [];
        $bigSegments = $this->splitter('/(\w+,[ ]*\w+[ ]+\d{1,2}[ ]+\d{2,4}[ ]*.*)/', $sText);

        foreach ($bigSegments as $smallSegmentsText) {
            $key = $this->re('/([^\n]+)/', $smallSegmentsText);

            if (false !== stripos($smallSegmentsText, 'check-out') && preg_match('/check-out[ ]*.+[ ]*Confirmation No\. ([A-Z\d]+)/', $smallSegmentsText, $m)) {
                $this->checkOutDates[$m[1]] = $this->normalizeDate($this->re('/(\w+, \w+ \d{1,2} \d{2,4})/', $key));
            }

            $smallSegments = $this->splitter('/(\d{1,2}:\d{2} [AP]M[ ]+.*[ ]*Confirmation No.+)/', $smallSegmentsText);

            foreach ($smallSegments as $segment) {
//                FLIGHT
                if (false !== stripos($segment, 'depart')) {
                    $s = $f->addSegment();

                    $date = $this->normalizeDate($this->re('/(\w+, \w+ \d{1,2} \d{2,4})/', $key));

                    if (preg_match('/(\d{1,2}:\d{2} [AP]M)[ ]*(.+)[ ]*\(([A-Z]{3})\) to (.+) \(([A-Z]{3})\)[ ]*[—-][ ]*Confirmation No\.[ ]*([A-Z\d]{5,9})/iu', $segment, $m)) {
                        $dTime = $m[1];
                        $s->departure()
                            ->name($m[2])
                            ->code($m[3]);
                        $s->arrival()
                            ->name($m[4])
                            ->code($m[5]);
                        $s->airline()
                            ->confirmation($m[6]);
                    }

                    $re = '/depart[ ]+Takeoff[ ]*\:[ ]*\d+\:\d+[ ]?[AP]M[ ]?(?:Terminal[ ]*\:[ ]*(?<DTerm>\w+))?\s+(?<FName>[A-Z ]+?)\s{2,}[a-z ,-]+\s{2,}.*Landing[ ]*\:[ ]*(?<ATime>\d{1,2}:\d{2}[ ]*[AP]M)[ ]*(?:[\(]*Terminal[ ]*\:[ ]*(?<ATerm>\w+))?/i';
                    $re2 = '/depart[ ]+Takeoff[ ]*\:[ ]*\d+\:\d+[ ]?[AP]M[ ]?(?:Terminal[ ]*\:[ ]*(?<DTerm>\w+))?\s{2,}[a-z ,-]+\s{2,}\s+(?<FName>[A-Z ]+?)Landing[ ]*\:[ ]*(?<ATime>\d{1,2}:\d{2}[ ]*[AP]M)[ ]*(?:[\(]*Terminal[ ]*\:[ ]*(?<ATerm>\w+))?/iu';

                    if (preg_match($re, $segment, $m) || preg_match($re2, $segment, $m)) {
                        $s->departure()
                            ->terminal($m['DTerm'], true);
                        $s->airline()
                            ->name($m['FName']);
                        $aTime = $m['ATime'];
                        $s->arrival()
                            ->terminal($m['ATerm'], true, true);
                    }

                    if (preg_match('/Flight (\d+)[ ]+(.+?) [\[\(]*[ ]([A-Z])[ ]*[\]\(]*[ ]Class[ ]*[\s\S]+?\|[ ]*(\d{1,2}h \d{1,2})/', $segment, $m)) {
                        $s->airline()
                            ->number($m[1]);
                        $s->extra()
                            ->cabin($m[2])
                            ->bookingCode($m[3])
                            ->duration($m[4]);
                    } elseif (preg_match('/(.+?)[ ]*[\[\(]*[ ]([A-Z])[ ]*[\]\(]*[ ]Class[ ]*[\s\S]+Flight (\d+)[\s\S]+\|[ ]*(\d{1,2}h\s*\d{1,2}m)/i', $segment, $m)) {
                        $s->extra()
                            ->cabin($m[1])
                            ->bookingCode($m[2])
                            ->duration(preg_replace('/\s+/', ' ', $m[4]));
                        $s->airline()
                            ->number($m[3]);
                    }

                    $re3 = '/depart[ ]+(?<FName>[A-Z ]+?)\s{2,}Takeoff[ ]*\:[ ]*\d+\:\d+[ ]?[AP]M[ ]?(?:Terminal[ ]*\:[ ]*(?<DTerm>\w+))?\s+Flight (?<FNum>\d+)\s+[a-z ,-]+\s+OPERATED BY (?<Wetlease>.+) DBA\s+Landing[ ]*\:[ ]*(?<ATime>\d{1,2}:\d{2}[ ]*[AP]M)[ ]*(?:[\(]*Terminal[ ]*\:[ ]*(?<ATerm>\w+))?/iu';

                    if (empty($s->getFlightNumber()) && empty($s->getAirlineName()) && preg_match($re3, $segment, $m)) {
                        $s->airline()
                            ->name($m['FName'])
                            ->number($m['FNum'])
                            ->operator($m['Wetlease'])
                            ->wetlease();
                        $s->departure()
                            ->terminal($m['DTerm'], true, true);
                        $s->arrival()
                            ->terminal($m['ATerm'], true, true);
                        $aTime = $m['ATime'];
                    }

                    if (preg_match_all('/([a-z\.]+)\s{2,}([\d\-]+)\s{2,}([\dA-Z]{1,5})\s{2,}(\d+)/i', $segment, $m)) {
                        $travellers = array_merge($travellers, $m[1]);
                        $tickets = array_merge($tickets, $m[2]);
                        $s->extra()
                            ->seats($m[3]);
                        $ffNums = array_merge($ffNums, $m[4]);
                    }

                    if (!empty($date) && !empty($dTime) && !empty($aTime)) {
                        $s->departure()
                            ->date(strtotime($dTime, $date));
                        $s->arrival()
                            ->date(strtotime($aTime, $date));
                    }

//                 HOTEL
                } elseif (false !== stripos($segment, 'check-in')) {
                    $hotels[$key][] = $segment;

                    continue;
                }
            }
        }

        $travellers = array_filter(array_unique($travellers));
        $tickets = array_filter(array_unique($tickets));
        $ffNums = array_filter(array_unique($ffNums));

        foreach ($travellers as $traveller) {
            $f->addTraveller($traveller);
        }

        foreach ($tickets as $ticket) {
            $f->addTicketNumber($ticket, false);
        }

        foreach ($ffNums as $ffNum) {
            $f->addAccountNumber($ffNum, false);
        }

        if (0 === count($f->getConfirmationNumbers()) && 0 === count($f->getSegments())) {
            $email->removeItinerary($f);
            $this->logger->debug('Flight Itinerary removed');
        }

        foreach ($hotels as $date => $hs) {
            foreach ($hs as $h) {
                $this->hotel($email, $h, $date);
            }
        }
    }

    private function hotel(Email $email, string $text, string $dateStr)
    {
        $date = $this->normalizeDate($this->re('/(\w+, \w+ \d{1,2} \d{2,4})/', $dateStr));

        $h = $email->add()->hotel();

        if (preg_match('/(\d{1,2}:\d{2}[ ]*[AP]M)[ ]*(.+)[ ]*Confirmation No\. ([\dA-Z]+)/', $text, $m)) {
            $checkInTime = $m[1];
            $h->hotel()
                ->name($m[2]);
            $h->general()
                ->confirmation($m[3]);
        }

        if (!empty($date) && !empty($checkInTime)) {
            $h->booked()
                ->checkIn(strtotime($checkInTime, $date));
        }

        if (!empty($h->getConfirmationNumbers()[0][0]) && !empty($this->checkOutDates[$h->getConfirmationNumbers()[0][0]])) {
            $h->booked()
                ->checkOut($this->checkOutDates[$h->getConfirmationNumbers()[0][0]]);
        } else {
            $h->booked()
                ->noCheckOut();
        }

        $h->hotel()
            ->address($this->re('/Address[ ]*\:[ ]*(.+?)\s{2,}/', $text))
            ->phone($this->re('/Phone[ ]*\:[ ]*(.+)/', $text));

        $h->booked()
            ->rooms($this->re('/Rooms[ ]*\:[ ]*(\d{1,2})/', $text));

        $r = $h->addRoom();
        $r->setDescription($this->re('/Room Desc[ ]*\:[ ]*(.+)/', $text), false, true);

        $h->general()
            ->cancellation($this->re('/Cancel Policy[ ]*\:[ ]*(.+)/', $text));

        $ff = $this->re('/Frequent Guest \#[ ]*\:[ ]*/', $text);

        if (!empty($ff)) {
            $h->addAccountNumber($ff, false);
        }

        if ($rate = $this->re('/Nightly Rate[ ]*\:[ ]*([^\n]+)/', $text)) {
            $r->setRate($rate);
        }
    }

    private function normalizeDate(?string $s)
    {
//        $this->logger->debug($s);
        $in = [
            '/^\w+, (\w+) (\d{1,2}) (\d{2,4})$/',
        ];
        $out = [
            '$2 $1 $3',
        ];
        $r = preg_replace($in, $out, $s);
//        $this->logger->debug($r);
        return strtotime($r);
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }
}
