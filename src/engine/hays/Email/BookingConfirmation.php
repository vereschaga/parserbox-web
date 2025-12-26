<?php

namespace AwardWallet\Engine\hays\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "hays/it-49421335.eml, hays/it-49722896.eml, hays/it-53620774.eml, hays/it-54372161.eml, hays/it-55570255.eml";
    public $From = '@hays-travel.co.uk';
    public $Subject = ['Booking Confirmation - HAY-', 'Hays Travel Booking Confirmation'];
    public $travellers;
    public $flightSegNum = 0;
    public $reservDate;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $description = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your reservation has been confirmed')]", null, true, '/[,]\s+your\s+(\D+)\s+is/');
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Overview')]/following::td[starts-with(normalize-space(), 'Booking Reference')]/following-sibling::td[normalize-space()][1]"), $description);

        $totalPrice = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pricing')]/following::tr[starts-with(normalize-space(), 'Your Total Holiday Cost')]/td[2]");

        if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // £3,768.00
            $email->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }

        $this->reservDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Overview')]/following::td[starts-with(normalize-space(), 'Issue Date')]/following-sibling::td[normalize-space()][1]");

        $this->travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passenger Information')]/following::table[contains(normalize-space(), 'Passenger Names')]/following-sibling::table/descendant::td[.//img]/following-sibling::td[1][count(./following-sibling::td[normalize-space()!=''])=1]");

        if (empty($this->travellers)) {
            $this->travellers = $this->http->FindNodes("//img[contains(@src, '/docicons/Ms.PNG') or contains(@src, '/docicons/Mr.PNG') or contains(@src, '/docicons/Mrs.PNG') or contains(@src, '/docicons/Miss.PNG')]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        }
        //HOTEL
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Multi Package Details')]/following::tr[starts-with(normalize-space(), 'Accommodation')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Multi Package Details')]/following::tr[starts-with(normalize-space(), 'Accommodation')]";
            $this->parseHotel2($email, $xpathSegment);
        }

        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Package Details')]/following::td[starts-with(normalize-space(), 'Hotel Name')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Package Details')]/following::td[starts-with(normalize-space(), 'Hotel Name')]";
            $this->parseHotel($email, $xpathSegment);
        }

        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Hotel Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Hotel')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Hotel Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Hotel')]";
            $this->parseHotel($email, $xpathSegment);
        }

        //FLIGHT
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Package Details')]/following::td[starts-with(normalize-space(), 'Flight')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Package Details')]/following::td[starts-with(normalize-space(), 'Flight')]";
            $this->parseFlight($email, $xpathSegment);
        }

        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Flight')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Flight')]";
            $this->parseFlight($email, $xpathSegment);
        }

        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Flight Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Flight')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Flight Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Flight')]";
            $this->parseFlight($email, $xpathSegment);
        }

        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Multi Package Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Flight')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Multi Package Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Flight')]";
            $this->parseFlight($email, $xpathSegment);
        }

        //СRUISE
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Cruise Line')]"))) {
            $portNodes = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Cruise Itinerary')]/ancestor::table[1]/following::tr[contains(normalize-space(), 'Date') and contains(normalize-space(), 'Port') and contains(normalize-space(), 'Arrive')]/following-sibling::tr");
            $portNodes = array_unique($portNodes);

            foreach ($portNodes as $portNode) {
                if (preg_match('/\s+([-]\s+[-])/', $portNode)) {
                    // 05 Apr 2020	At Sea	-	-
                    continue;
                }

                if (preg_match('/^\d+\s+\S+\s+\d+\s+\D+\s+\d+[:]\d+\s+(\d+[:]\d+)/', $portNode)) {
                    // 08 Apr 2020	Madeira, Portugal	08:59	15:00
                    $this->parseCruise($email);

                    break;
                }
            }
            $this->logger->debug('Segments CRUISE not found!');

            return $email;
        }

        //PARKING
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Car Parking Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Car Details')]"))) {
            $xpathSegment = "//text()[starts-with(normalize-space(), 'Your Car Parking Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Car Details')]";
            $this->parseParking($email, $xpathSegment);
        }

        //TRANSFER
        if (!empty($this->http->FindNodes("//tr[starts-with(normalize-space(), 'Your Transfer Details')]"))) {
            $xpathSegment = "//tr[starts-with(normalize-space(), 'Your Transfer Details')]";
            $this->parseTransfer($email, $xpathSegment);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hays Travel')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'act as a disclosed agent')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'www.haystravel.co.uk')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]hays[-]travel\.co\.uk/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->Subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
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
            "#^(\d+)\s+(\d+)\s+(\d+)\s+[(](\d+[:]\d+)[)]#", // 08 Apr 2020	08:59
            "#^(\d+)[/](\d+)[/](\d+)#", // 03/01/2020
        ];
        $out = [
            "$1.$2.$3 $4",
            "$3-$2-$1 $4",
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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

    private function parseParking(Email $email, $xpathSegment)
    {
        // Segments root
        $this->logger->debug('XPATH for PARKING ' . $xpathSegment);
        $segments = $this->http->XPath->query($xpathSegment);

        // Parse segments
        foreach ($segments as $root) {
            $p = $email->add()->parking();

            $p->setReservationDate($this->normalizeDate($this->reservDate));

            $description = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Reference')]", $root);

            $p->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Reference')]/following::td[1]", $root), $description)
                ->travellers($this->travellers, true);

            $p->place()
                ->location($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Location')]/following::td[1]", $root, true, "/[(](.+)[)]/"));

            $pickUpDate = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'From')]/following::td[1]", $root);
            $dropOffDate = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'To')]/following::td[1]", $root);

            $p->booked()
                ->start($this->normalizeDate($pickUpDate))
                ->end($this->normalizeDate($dropOffDate))
                ->plate($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Car Registration')]/following::td[1]", $root))
                ->car($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Car Details')]/following::td[1]", $root));
        }
    }

    private function parseFlight(Email $email, $xpathSegment)
    {
        // Segments roots
        $this->logger->debug('XPATH for FLIGHT ' . $xpathSegment);
        $segments = $this->http->XPath->query($xpathSegment);

        // Parse segments
        foreach ($segments as $root) {
            if ($this->flightSegNum == 0) {
                $f = $email->add()->flight();
                $f->general()
                    ->travellers($this->travellers, true);
                $f->general()
                    ->noConfirmation();
                $f->setReservationDate($this->normalizeDate($this->reservDate));
                $this->flightSegNum = 1;
            }
            $s = $f->addSegment();

            $confNumber = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[starts-with(normalize-space(), 'Flight')]/following-sibling::tr[contains(normalize-space(), 'Reference')]/descendant::td[starts-with(normalize-space(), 'Reference')]/following-sibling::td[1]", $root, true, '/(.+)/');
            $description = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[starts-with(normalize-space(), 'Flight')]/following-sibling::tr[contains(normalize-space(), 'Reference')]/descendant::td[starts-with(normalize-space(), 'Reference')]", $root);

            if (!empty($confNumber)) {
                $s->setConfirmation($confNumber, $description);
            }

            $airlineName = $this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/Flight\s+(\D+)\d+/");

            if (empty($airlineName)) {
                $airlineName = $this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/Flight\s+([A-Z]{2,3})/");
            }

            if (!empty($airlineName)) {
                $s->airline()
                    ->name($airlineName);
            } else {
                $s->airline()
                ->noName();
            }

            $airlineNumber = $this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/Flight\s+(\d+)/");

            if (empty($airlineNumber)) {
                $airlineNumber = $this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/Flight\s+\D+(\d+)/");
            }

            if (!empty($airlineNumber)) {
                $s->airline()
                    ->number($airlineNumber);
            } else {
                $s->airline()
                    ->noNumber();
            }

            $depCode = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root, true, "/[(]([A-Z]{3})[)]/");
            $depDate = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[4]", $root));

            if (empty($depCode)) {
                $depCode = $this->http->FindSingleNode("./following::tr[starts-with(normalize-space(), 'Departs')][1]/td[2]", $root, true, "/[(]([A-Z]{3})[)]/");
                $depDate = $this->normalizeDate($this->http->FindSingleNode("./following::tr[starts-with(normalize-space(), 'Departs')][1]/td[4]", $root));
            }
            $s->departure()
                ->code($depCode)
                ->date($depDate);

            $arrCode = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]/td[2]", $root, true, "/[(]([A-Z]{3})[)]/");
            $arrDate = $this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]/td[4]", $root));

            if (empty($arrCode)) {
                $arrCode = $this->http->FindSingleNode("./following::tr[starts-with(normalize-space(), 'Arrives')][1]/td[2]", $root, true, "/[(]([A-Z]{3})[)]/");
                $arrDate = $this->normalizeDate($this->http->FindSingleNode("./following::tr[starts-with(normalize-space(), 'Arrives')][1]/td[4]", $root));
            }
            $s->arrival()
                ->code($arrCode)
                ->date($arrDate);

            $cabin = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), 'Class')][1]/td[4]", $root);

            if (empty($cabin)) {
                $cabin = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), 'Class')][1]/td[2]", $root);
            }

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }
    }

    private function parseHotel2(Email $email, $xpathSegment)
    {
        // Segments roots
        $this->logger->debug('XPATH for HOTEL2 ' . $xpathSegment);
        $segments = $this->http->XPath->query($xpathSegment);

        // Parse segments
        foreach ($segments as $root) {
            $h = $email->add()->hotel();

            $h->setReservationDate($this->normalizeDate($this->reservDate));

            $h->general()
                ->travellers($this->travellers, true)
                ->noConfirmation();

            $h->hotel()
                ->address($this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Hotel')][1]/td[starts-with(normalize-space(), 'Resort')][1]/following-sibling::td[1]", $root))
                ->name($this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Hotel')][1]/td[2]", $root));

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Check In')][1]/td[2]", $root)))
                ->guests($this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Adults')][1]/td[2]", $root));

            $duration = $this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Check In')][1]/td[starts-with(normalize-space(), 'Duration')][1]/following-sibling::td[1]", $root, null, "/^\s*(\d+) night/");

            if (!empty($duration) && !empty($h->getCheckInDate())) {
                $h->booked()->checkOut(strtotime("+" . $duration . " day", $h->getCheckInDate()));
            }

            $h->addRoom()
                ->setType($this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Room Type')][1]/td[2]", $root))
                ->setDescription($this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Room Type')][1]/td[starts-with(normalize-space(), 'Board Basis')]/following-sibling::td[1]", $root));

            $childrenCount = $this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Adults')][1]/td[starts-with(normalize-space(), 'Children')]/following-sibling::td[1]", $root);
            $infantCount = $this->http->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(), 'Infants')][1]/td[2]", $root);

            if ($childrenCount > 0 || $infantCount > 0) {
                $h->booked()
                    ->kids($childrenCount + $infantCount);
            } elseif ($childrenCount == 0 && empty($infantCount) || empty($childrenCount) && $infantCount == 0) {
                $h->booked()
                    ->kids(0);
            }
        }
    }

    private function parseHotel(Email $email, $xpathSegment)
    {
        // Segments roots
        $this->logger->debug('XPATH for HOTEL' . $xpathSegment);
        $segments = $this->http->XPath->query($xpathSegment);

        // Parse segments
        foreach ($segments as $root) {
            $h = $email->add()->hotel();

            $h->setReservationDate($this->normalizeDate($this->reservDate));

            $h->hotel()
                ->noAddress();

            $hotelName = $this->http->FindSingleNode("./following-sibling::td[1]", $root);
            $h->hotel()
                ->name($hotelName);

            $confirmation = $this->http->FindSingleNode("./preceding::td[starts-with(normalize-space(), 'Reference')][1]/following::td[1]", $root, true);
            $description = $this->http->FindSingleNode("./preceding::td[starts-with(normalize-space(), 'Reference')][1]", $root);

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Hotel Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Reference')]/following-sibling::td[1]");
                $description = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Hotel Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Reference')]");
            }
            $h->general()
                ->travellers($this->travellers, true)
                ->confirmation($confirmation, $description);

            $checkIn = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Hotel Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Check In')]/following-sibling::td[1]"));
            $checkOut = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Hotel Details')]/ancestor::table[1]/following-sibling::table[1]/descendant::td[starts-with(normalize-space(), 'Check Out')]/following-sibling::td[1]"));

            if (!empty($checkIn)) {
                $h->booked()
                    ->checkIn($checkIn)
                    ->checkOut($checkOut);
            }

            if (empty($checkIn)) {
                $h->booked()
                    ->noCheckIn()
                    ->noCheckOut();
            }

            $rooms = count($this->http->FindNodes("./ancestor::table[1]/descendant::tr[starts-with(normalize-space(), 'Room')]/td[1][not(contains(normalize-space(), 'Room Type'))]", $root));

            if ($rooms === 0) {
                $rooms = count($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Hotel Details')]/following::tr[starts-with(normalize-space(), 'Room')]/td[1][not(contains(normalize-space(), 'Room Type'))]"));
            }
            $h->booked()
                ->rooms($rooms);

            $xpathRoom = "./ancestor::table[1]/descendant::td[starts-with(normalize-space(), 'Room')][not(contains(normalize-space(), 'Room Type') or contains(normalize-space(), 'Room Only') or contains(normalize-space(), 'Room View'))]";
            $segments = $this->http->XPath->query($xpathRoom, $root);

            /*if($segments->length == 0){
                $xpath = "//text()[starts-with(normalize-space(), 'Your Hotel Details')]/following::tr[starts-with(normalize-space(), 'Room')]/td[1][not(contains(normalize-space(), 'Room Type'))]";
                $segments = $this->http->XPath->query($xpath);
            }*/

            if ($segments->length === 0) {
                $this->logger->debug('Segments HOTEL not found!');
            }
            // Parse segments
            $adultCount = 0;
            $childrenCount = 0;
            $infantsCount = 0;

            foreach ($segments as $rootRoom) {
                $h->addRoom()
                    ->setType($this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[2]", $rootRoom))
                    ->setDescription($this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[4]", $rootRoom));

                $adultCount = $adultCount + $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]/td[2]", $rootRoom);
                $childrenCount = $childrenCount + $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]/td[4]", $rootRoom);
                $infantsCount = $infantsCount + $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[3]/td[2]");
            }

            if ($adultCount > 0) {
                $h->booked()
                    ->guests($adultCount);
            }

            if (($childrenCount > 0) || ($infantsCount > 0)) {
                $h->booked()
                    ->kids($childrenCount + $infantsCount);
            } elseif (($childrenCount == 0 && empty($infantsCount)) || (empty($childrenCount) && $infantsCount == 0)) {
                $h->booked()
                    ->kids(0);
            }
        }
    }

    private function parseCruise(Email $email): void
    {
        $c = $email->add()->cruise();
        $c->setReservationDate($this->normalizeDate($this->reservDate));
        $description = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Reference')]");
        $c->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Reference')]/following-sibling::td[normalize-space()][1]"), $description)
            ->travellers($this->travellers, true);
        $c->details()
            ->description($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Voyage Reference')]/following-sibling::td[normalize-space()][1]"))
            ->ship($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Cruise Ship')]/following-sibling::td[normalize-space()][1]"))
            ->roomClass($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Cabin Name')]/following-sibling::td[normalize-space()][1]"))
            ->deck($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Cabin Grade')]/following-sibling::td[normalize-space()][1]"))
            ->room($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Cabin Number')]/following-sibling::td[normalize-space()][1]"));

        $setName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Embarkation')]/following-sibling::td[normalize-space()][1]", null, true, "/(\S)\s+[-]/");

        if (empty($setName)) {
            $setName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Embarkation')]/following-sibling::td[normalize-space()][1]", null, true, "/(\D+)\s+/");
        }

        $setAshore = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Disembarkation')]/following-sibling::td[normalize-space()][1]", null, true, "/\s+(\d{2}\s+\S+\s\d{4})/");
        $setAboard = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Cruise Details')]/following::td[starts-with(normalize-space(), 'Embarkation')]/following-sibling::td[normalize-space()][1]", null, true, "/\s+(\d{2}\s+\S+\s\d{4})/");

        $portNodes = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Cruise Itinerary')]/ancestor::table[1]/following::tr[contains(normalize-space(), 'Date') and contains(normalize-space(), 'Port') and contains(normalize-space(), 'Arrive')]/following-sibling::tr[normalize-space()]");
        $portNodes = array_unique($portNodes);
        $portNodesRowCount = 1;

        foreach ($portNodes as $portNode) {
            //Bad Segment
            if (!empty($this->re('/\s+([-]\s+[-])/', $portNode)) && ($portNodesRowCount > 1 && $portNodesRowCount < count($portNodes))) { // 05 Apr 2020	At Sea	-	-
                $portNodesRowCount = $portNodesRowCount + 1;

                continue;
            }
            //Frist Segment
            if (!empty($this->re('/^\d+\s+\S+\s+\d+\s+\D+\s+[-]\s+(\d+[:]\d+)/', $portNode)) && $portNodesRowCount == 1) { //08 Apr 2020	Madeira, Portugal	08:59	-
                $s = $c->addSegment();
                $date = $this->re("/^(\d+\s+\S+\s+\d+)\s+/", $portNode);
                $timeAboard = $this->re("/^\d+\s+\S+\s+\d+\s+\D+\s+[-]\s+(\d+[:]\d+)/", $portNode);
                $s->setName($this->re("/^\d+\s+\S+\s+\d+\s+(\D+)\s+[-]\s+\d+[:]\d+/", $portNode));
                $s->setAboard($this->normalizeDate($date . ' ' . $timeAboard));
            } elseif (!empty($this->re('/\s+([-]\s+[-])/', $portNode)) && $portNodesRowCount == 1) {
                $s = $c->addSegment();
                $s->setName($setName);
                $s->setAboard($this->normalizeDate($setAboard));
            }
            //Normal Segmnet
            if (!empty($this->re('/^\d+\s+\S+\s+\d+\s+\D+\s+\d+[:]\d+\s+(\d+[:]\d+)/', $portNode))) { //08 Apr 2020	Madeira, Portugal	08:59	15:00
                $s = $c->addSegment();
                $date = $this->re("/^(\d+\s+\S+\s+\d+)\s+/", $portNode);
                $timeAshore = $this->re("/^\d+\s+\S+\s+\d+\s+\D+\s+(\d+[:]\d+)\s+\d+[:]\d+/", $portNode);
                $timeAboard = $this->re("/^\d+\s+\S+\s+\d+\s+\D+\s+\d+[:]\d+\s+(\d+[:]\d+)/", $portNode);
                $s->setName($this->re("/^\d+\s+\S+\s+\d+\s+(\D+)\s+\d+[:]\d+\s+\d+[:]\d+/", $portNode));
                $s->setAboard($this->normalizeDate($date . ' ' . $timeAboard));
                $s->setAshore($this->normalizeDate($date . ' ' . $timeAshore));
            }
            //Last Segment
            if (!empty($this->re('/^\d+\s+\S+\s+\d+\s+\D+\s+(\d+[:]\d+)\s+[-]/', $portNode)) && $portNodesRowCount == count($portNodes)) { //08 Apr 2020	Madeira, Portugal	-	15:00
                $s = $c->addSegment();
                $date = $this->re("/^(\d+\s+\S+\s+\d+)\s+/", $portNode);
                $timeAshore = $this->re("/^\d+\s+\S+\s+\d+\s+\D+\s+(\d+[:]\d+)\s+[-]/", $portNode);
                $s->setName($this->re("/^\d+\s+\S+\s+\d+\s+(\D+)\s+\d+[:]\d+\s+[-]/", $portNode));
                $s->setAshore($this->normalizeDate($date . ' ' . $timeAshore));
            } elseif (!empty($this->re('/\s+([-]\s+[-])/', $portNode)) && $portNodesRowCount == count($portNodes)) {
                $s = $c->addSegment();
                $s->setName($setName);
                $s->setAshore($this->normalizeDate($setAshore));
            }
            $portNodesRowCount = $portNodesRowCount + 1;
        }
    }

    private function parseTransfer(Email $email, $xpathSegment)
    {
        $this->logger->debug('XPATH for TRANSFER ' . $xpathSegment);
        $segments = $this->http->XPath->query($xpathSegment);

        foreach ($segments as $root) {
            $t = $email->add()->transfer();

            $confNumber = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]/descendant::tr[contains(normalize-space(), 'Reference')][1]/td[4]", $root);
            $description = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]/descendant::td[contains(normalize-space(), 'Reference')][1]", $root);

            if (!empty($confNumber)) {
                $t->general()->confirmation($confNumber, $description);
            }
            $ts = $t->addSegment();

            $ts->departure()
                ->name($this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]/descendant::tr[contains(normalize-space(), 'Pick Up')][1]/td[2]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]/descendant::tr[contains(normalize-space(), 'Date')][1]/td[2]", $root)));
            $ts->arrival()
                ->noDate()
                ->name($this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1]/descendant::tr[contains(normalize-space(), 'Drop Off')][1]/td[4]", $root));
        }
    }
}
