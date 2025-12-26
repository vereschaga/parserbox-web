<?php

namespace AwardWallet\Engine\travelinc\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FinalItinerary extends \TAccountChecker
{
    public $mailFiles = "travelinc/it-52139180.eml";
    public $reFrom = '/[@]travelinc\.com/';
    public $Subject = ['FINAL ITINERARY'];
    public $travellers;
    public $ticketNumber;
    public $ffNbr;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Agency Locator')]/following::text()[1]"));

        $this->travellers = $this->http->FindNodes("//tr[starts-with(normalize-space(), 'eItinerary Traveler(s)')]/following-sibling::tr[normalize-space()]");

        $this->ticketNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Ticket Number Is')]", null, true, "/Ticket\s+Number\s+Is[-\s]+(\d{3}[- ]*\d{5,}[- ]*\d{1,2})(?:[,.;!]|$)/");
        $this->ffNbr = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Flyer Nbr Is')]", null, true, "/Flyer\s+Nbr\s+Is[-\s]+(\d{5,})(?:[,.;!]|$)/");

        //HOTEL
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'HOTEL')]"))) {
            $this->parseHotel($email);
        }

        //CAR
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'CAR')]"))) {
            $this->parseCar($email);
        }

        //FLIGHT
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'AIR')]"))) {
            $this->parseFlights($email);
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query("//img[contains(@src, 'CAR')]")->length > 0
             || $this->http->XPath->query("//img[contains(@src, 'Hotel')]")->length > 0
             || $this->http->XPath->query("//img[contains(@src, 'Air')]")->length > 0)
             && $this->http->XPath->query("//text()[contains(normalize-space(), 'eItinerary Traveler')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reFrom, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->Subject as $re) {
            if (stripos($headers["subject"], $re) !== false
                && preg_match($this->reFrom, $headers["from"]) > 0) {
                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+).(\d+).(\d+)\s+[(](\d+[:]\d+)[)]#", // 30/12/2019 (14:10)
            "#^\D{3}[,]\s+(\D{3})\s+(\d+)\s(\d+)\s+(.+)#", // Tue, Jan 21 2020 3:00 PM
            "#^(\d+).(\d+).(\d+)\s+(.+)#", // 01/22/2020 12:00 PM
        ];
        $out = [
            "$1.$2.$3 $4",
            "$2.$1.$3 $4",
            "$2.$1.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        $this->logger->debug($cancellationText);
        //48 Hr Cancellation Required
        if (!empty($dateDeadline = strtotime("-{$this->re('/^(\d+) Hr Cancellation Required$/', $cancellationText)} hours", $h->getCheckInDate()))
            || !empty($dateDeadline = strtotime("-{$this->re('/^Cancel Permitted Up To (\d+) Days Before Arrival[.] \d+[.]\d+ Cancel Fee Per Room[.]/', $cancellationText)} days", $h->getCheckInDate()))
            || !empty($dateDeadline = strtotime("-{$this->re('/^(\d+)hrs Prior To Checkin To Avoid 1NT Fee/', $cancellationText)} hours", $h->getCheckInDate()))
            || !empty($dateDeadline = strtotime("-{$this->re('/^(\d+)hrs Prior To Check In To Avoid 1NT Fee/', $cancellationText)} hours", $h->getCheckInDate()))
        ) {
            $h->booked()
                ->deadline($dateDeadline);

            return true;
        } else {
            if (preg_match("/CXL (\d{1,2}) Day Prior To Arrival/", $cancellationText, $m)) {
                $h->booked()
                    ->deadline(strtotime("-" . $m[1] . " day", $h->getCheckInDate()));
            }
        }

        /*if (preg_match("#cancel no refund#i", $cancellationText)) {
            $h->booked()
                ->nonRefundable();
            return true;
        }*/

        return false;
    }

    private function parseCar(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(), 'CAR')]";
        $this->logger->debug('XPATH for CAR ' . $xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $c = $email->add()->rental();
            $c->setCompany($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'CAR')]/following::td[normalize-space()][1]", $root));

            $confNo = implode(' ', $this->http->FindNodes("./ancestor::tr[1]/*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?:^|[←\s])(Conf. #)\s*([-A-Z\d]{5,})(?:\s|$)/", $confNo, $m)) {
                // ← Conf. # 1513898692EXSEL
                $c->general()->confirmation($m[2], $m[1]);
            }

            $c->general()->travellers($this->travellers, true);

            $c->car()
                ->type($this->http->FindSingleNode("./ancestor::td[1]/following::td[5]/descendant::text()[starts-with(normalize-space(), 'Phone')]/preceding::text()[normalize-space()][1]", $root));
            $pickUpDate = $this->http->FindSingleNode("./ancestor::td[1]", $root, true, '/CAR.+(\D{3}[,]\s+\D{3}\s+\d{2}\s+\d{4})/s');
            $pickUpTime = $this->http->FindSingleNode("./ancestor::td[1]/following::td[3]/descendant::text()[contains(.,'Pick-up')]/preceding::text()[normalize-space()][1]", $root, true, '/(\d+:\d+\s*(?:AM|PM))/');

            $dropOffDate = $this->http->FindSingleNode("./ancestor::td[1]/following::td[3]/descendant::text()[contains(.,'Drop-off')]/preceding::text()[2]", $root);
            $dropOffTime = $this->http->FindSingleNode("./ancestor::td[1]/following::td[3]/descendant::text()[contains(.,'Drop-off')]/preceding::text()[1]", $root, true, '/(\d+:\d+\s*(?:AM|PM))/');

            $c->pickup()
                ->date($this->normalizeDate($pickUpDate . ' ' . $pickUpTime))
                ->location($this->http->FindSingleNode("./ancestor::td[1]/following::td[5]/descendant::text()[normalize-space()][1]", $root))
                ->phone($this->http->FindSingleNode("./ancestor::td[1]/following::td[5]/descendant::text()[starts-with(normalize-space(), 'Phone')]/following::text()[1]", $root));

            $c->dropoff()
                ->date($this->normalizeDate($dropOffDate . ' ' . $dropOffTime))
                ->same();
        }
    }

    private function parseFlights(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(), 'AIR')]";
        $this->logger->debug('XPATH for FLIGHT ' . $xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $f = $email->add()->flight();

            $confNo = implode(' ', $this->http->FindNodes("./ancestor::tr[1]/*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?:^|[←\s])(Conf. #)\s*([A-Z\d]{5,})(?:\s|$)/", $confNo, $m)) {
                // ← Conf. # VZNZ38
                $f->general()->confirmation($m[2], $m[1]);
            }

            $f->general()->travellers($this->travellers, true);

            if (!empty($this->ticketNumber)) {
                $f->issued()->ticket($this->ticketNumber, false);
            }

            if (!empty($this->ffNbr)) {
                $f->program()->account($this->ffNbr, false);
            }

            $s = $f->addSegment();

            $airlineName = $this->http->FindSingleNode("./following::td[1]", $root, true, "/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+#/");

            if (empty($airlineName)) {
                $airlineName = $this->http->FindSingleNode("./following::td[1]", $root, true, "/(.+)\s+[#]/");
            }

            if (!empty($airlineName)) {
                $s->airline()
                    ->name($airlineName);
            } else {
                $s->airline()
                ->noName();
            }

            $airlineNumber = $this->http->FindSingleNode("./following::td[1]", $root, true, "/[#](\d+)/");

            if (!empty($airlineNumber)) {
                $s->airline()
                    ->number($airlineNumber);
            } else {
                $s->airline()
                ->noNumber();
            }

            $depDate = $this->http->FindSingleNode("./ancestor::td[1]", $root, true, '/AIR.+(\D{3}[,]\s+\D{3}\s+\d{2}\s+\d{4})/s');
            $depTime = $this->http->FindSingleNode("./following::td[normalize-space()][3]", $root, true, '/(\d+:\d+\s*(?:AM|PM))/');
            $depTerminal = $this->http->FindSingleNode("./following::td[normalize-space()][4]/descendant::tr[ *[normalize-space()][1][starts-with(normalize-space(),'Departure')] ]/*[normalize-space()][1]", $root, true, '/Terminal\s*\(\s*([^)(]+?)\s*\)/');

            $arrDate = $this->http->FindSingleNode("./ancestor::td[1]", $root, true, '/AIR.+(\D{3}[,]\s+\D{3}\s+\d{2}\s+\d{4})/s');
            $arrTime = $this->http->FindSingleNode("./following::td[normalize-space()][4]", $root, true, '/Arrival\s+\D+(\d+:\d+\s*(?:AM|PM))/');
            $arrTerminal = $this->http->FindSingleNode("./following::td[normalize-space()][4]/descendant::tr[ *[normalize-space()][2][starts-with(normalize-space(),'Arrival')] ]/*[normalize-space()][2]", $root, true, '/Terminal\s*\(\s*([^)(]+?)\s*\)/');

            $depCode = $this->http->FindSingleNode("./following::td[normalize-space()][4]", $root, true, '/([A-Z]{2,3})\s+[→]/');

            $layoverNodes = $this->http->FindNodes("./following::td[5]/descendant::text()[contains(normalize-space(), 'Layover in')]", $root);

            if (count($layoverNodes) === 0) {
                $duration = $this->http->FindSingleNode("./following::td[normalize-space()][3]", $root, true, '/Departure\s+(\d+h\s+\d+m)/');

                if (!empty($duration)) {
                    $s->setDuration($duration);
                }

                $arrCode = $this->http->FindSingleNode("./following::td[normalize-space()][4]", $root, true, '/[→]\s+([A-Z]{2,3})/');

                $s->departure()
                    ->code($depCode)
                    ->date($this->normalizeDate($depDate . ' ' . $depTime));

                if (!empty($depTerminal) && strcasecmp($depTerminal, 'Unassigned') !== 0) {
                    $s->departure()->terminal($depTerminal);
                }

                $s->arrival()
                    ->code($arrCode)
                    ->date($this->normalizeDate($arrDate . ' ' . $arrTime));

                if (!empty($arrTerminal) && strcasecmp($arrTerminal, 'Unassigned') !== 0) {
                    $s->arrival()->terminal($arrTerminal);
                }
            }

            if (count($layoverNodes) === 1) {
                $xpathLayover = "./following::td[normalize-space()][4]/descendant::text()[contains(normalize-space(),'Layover')]";
                $arrCode = $this->http->FindSingleNode($xpathLayover, $root, true, '/Layover.+[(](.+)[)]\s+/');

                $s->departure()
                    ->code($depCode)
                    ->date($this->normalizeDate($depDate . ' ' . $depTime));

                if (!empty($depTerminal) && strcasecmp($depTerminal, 'Unassigned') !== 0) {
                    $s->departure()->terminal($depTerminal);
                }

                $s->arrival()
                    ->code($arrCode)
                    ->date($this->normalizeDate($arrDate . ' ' . $arrTime));

                if (!empty($arrTerminal) && strcasecmp($arrTerminal, 'Unassigned') !== 0) {
                    $s->arrival()->terminal($arrTerminal);
                }

                $s = $f->addSegment();

                $airlineName = $this->http->FindSingleNode("./following::td[1]", $root, true, "/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+#/");

                if (!empty($airlineName)) {
                    $s->airline()
                        ->name($airlineName);
                } else {
                    $s->airline()
                    ->noName();
                }

                $airlineNumber = $this->http->FindSingleNode("./following::td[1]", $root, true, "/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+#[ ]*(\d+)/");

                if (!empty($airlineNumber)) {
                    $s->airline()
                        ->number($airlineNumber);
                } else {
                    $s->airline()
                    ->noNumber();
                }

                $depDate = $this->http->FindSingleNode("./ancestor::td[1]", $root, true, '/AIR.+(\D{3}[,]\s+\D{3}\s+\d{2}\s+\d{4})/s');
                $depTime = $this->http->FindSingleNode($xpathLayover . "/following::td[2]", $root, true, '/Departure\s+(\d+:\d+\s*(?:AM|PM))/');
                $depTerminal = $this->http->FindSingleNode($xpathLayover . "/following::tr[normalize-space()][1]/*[normalize-space()][1][starts-with(normalize-space(),'Departure')]", $root, true, '/Terminal\s*\(\s*([^)(]+?)\s*\)/');

                $arrDate = $this->http->FindSingleNode("./ancestor::td[1]", $root, true, '/AIR.+(\D{3}[,]\s+\D{3}\s+\d{2}\s+\d{4})/s');
                $arrTime = $this->http->FindSingleNode($xpathLayover . "/following::td[3]", $root, true, '/Arrival\s+\D+(\d+:\d+\s*(?:AM|PM))/');
                $arrTerminal = $this->http->FindSingleNode($xpathLayover . "/following::tr[normalize-space()][1]/*[normalize-space()][2][starts-with(normalize-space(),'Arrival')]", $root, true, '/Terminal\s*\(\s*([^)(]+?)\s*\)/');

                $depCode = $arrCode;
                $arrCode = $this->http->FindSingleNode("./following::td[normalize-space()][4]", $root, true, '/[→]\s+([A-Z]{2,3})/');

                $s->departure()
                    ->code($depCode)
                    ->date($this->normalizeDate($depDate . ' ' . $depTime));

                if (!empty($depTerminal) && strcasecmp($depTerminal, 'Unassigned') !== 0) {
                    $s->departure()->terminal($depTerminal);
                }

                $s->arrival()
                    ->code($arrCode)
                    ->date($this->normalizeDate($arrDate . ' ' . $arrTime));

                if (!empty($arrTerminal) && strcasecmp($arrTerminal, 'Unassigned') !== 0) {
                    $s->arrival()->terminal($arrTerminal);
                }
            }
        }
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(), 'HOTEL')]";
        $this->logger->debug('XPATH for HOTEL ' . $xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $h = $email->add()->hotel();
            $h->hotel()
                ->address($this->http->FindSingleNode("./following::td[normalize-space()][4]/div[1]", $root))
                ->phone($this->http->FindSingleNode("./following::td[normalize-space()][4]/descendant::text()[starts-with(normalize-space(),'Phone')]/following::text()[1]", $root))
                ->fax($this->http->FindSingleNode("./following::td[normalize-space()][4]/descendant::text()[starts-with(normalize-space(),'Fax')]/following::text()[1]", $root));

            $h->hotel()
                ->name($this->http->FindSingleNode("./following::td[normalize-space()][1]", $root));

            $confNo = implode(' ', $this->http->FindNodes("./ancestor::tr[1]/*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?:^|[←\s])(Conf. #)\s*(\d{5,}(?:[A-Z]+|))(?:\s|$)/", $confNo, $m)) {
                // ← Conf. # 93770128 $HG$
                // ← Conf. # 3188446871NSCONF $GI$"
                $h->general()->confirmation($m[2], $m[1]);
            }

            $h->general()->travellers($this->travellers, true);

            $checkInDate = $this->http->FindSingleNode("./ancestor::td[1]", $root, true, '/HOTEL.+(\D{3}[,]\s+\D{3}\s+\d{2}\s+\d{4})/s');
            $checkInTime = $this->http->FindSingleNode("./following::td[normalize-space()][3]/descendant::text()[contains(.,'Check-in')]/preceding::text()[1]", $root, true, '/(\d+:\d+\s*(?:AM|PM))/');

            $checkOutDate = $this->http->FindSingleNode("./following::td[normalize-space()][3]/descendant::text()[contains(.,'Check-out')]/preceding::text()[2]", $root);
            $checkOutTime = $this->http->FindSingleNode("./following::td[normalize-space()][3]/descendant::text()[contains(.,'Check-out')]/preceding::text()[1]", $root, true, '/(\d+:\d+\s*(?:AM|PM))/');

            $h->booked()
                ->CheckIn($this->normalizeDate($checkInDate . ' ' . $checkInTime))
                ->CheckOut($this->normalizeDate($checkOutDate . ' ' . $checkOutTime));

            $h->booked()
                ->rooms($this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]/descendant::text()[contains(normalize-space(), 'room')]", $root, true, '/(\d+)\s+room/'));

            //48 Hr Cancellation Required
            if (!empty($cancellation = $this->http->FindSingleNode("./following::td[normalize-space()][4]/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'Cancellation Required') or contains(normalize-space(), 'Days Before Arrival') or contains(normalize-space(), 'hrs Prior To')  or contains(normalize-space(), 'Day Prior To Arrival')]", $root))) {
                $this->detectDeadLine($h, $cancellation);
                $h->general()
                    ->cancellation($cancellation);
            }

            $description = $this->http->FindSingleNode("./following::td[normalize-space()][4]/ancestor::tr[1]/descendant::text()[contains(normalize-space(), 'Cancellation Required') or contains(normalize-space(), 'Days Before Arrival') or contains(normalize-space(), 'hrs Prior To')]/following::text()[normalize-space()][1]", $root);

            if (!empty($description)) {
                $h->addRoom()
                    ->setDescription($description);
            }
        }
    }
}
