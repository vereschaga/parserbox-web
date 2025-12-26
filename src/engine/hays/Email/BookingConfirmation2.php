<?php

namespace AwardWallet\Engine\hays\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "hays/it-53450868.eml";
    public $From = '@hays-travel.co.uk';
    public $Subject = ['Booking Confirmation'];
    public $travellers;
    public $flightSegNum = 0;
    public $reservDate;
    private $Prov = 'hays-travel';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $description = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Summary')]/ancestor::table[1]/following::table[starts-with(normalize-space(), 'Booking Reference')][1]/descendant::td[starts-with(normalize-space(), 'Booking Reference')]");
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Summary')]/ancestor::table[1]/following::table[starts-with(normalize-space(), 'Booking Reference')][1]/descendant::td[starts-with(normalize-space(), 'Booking Reference')]/following-sibling::td[1]"), $description);

        $totalPrice = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pricing')]/following::tr[starts-with(normalize-space(), 'Your Total Holiday Cost')]/td[2]");

        $this->reservDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Summary')]/ancestor::table[1]/following::table[starts-with(normalize-space(), 'Booking Reference')][1]/descendant::td[starts-with(normalize-space(), 'Issue Date')]/following-sibling::td[1]");

        if (!empty($totalPrice)) {
            $email->price()
                ->currency($this->normalizeCurrency($this->re('/(.)/u', $totalPrice)))
                ->total($this->normalizePrice($this->re('/.(\d.+)/', $totalPrice)));
        }

        $this->travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Booking Summary')]/ancestor::table[1]/following::tr[starts-with(normalize-space(), 'Passenger Names')]/following-sibling::tr/td[starts-with(normalize-space(), 'Mr')][1]");

        //HOTEL
        if (!empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Accommodation')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table"))) {
            $urlSegment = "//text()[starts-with(normalize-space(), 'Your Accommodation')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table";
            $this->parseHotel($email, $urlSegment);
        }

        //FLIGHT
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Your Flight Details')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table"))) {
            $urlSegment = "//text()[starts-with(normalize-space(), 'Your Flight Details')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table";
            $this->parseFlight($email, $urlSegment);
        }

        //PARKING
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Car Parking')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table"))) {
            $urlSegment = "//text()[starts-with(normalize-space(), 'Car Parking')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table";
            $this->parseParking($email, $urlSegment);
        }

        //TRANSFER
        if (!empty($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Transfers')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table"))) {
            $urlSegment = "//text()[starts-with(normalize-space(), 'Transfers')]/ancestor::table[1]/ancestor::tr[1]/following::tr[1]/descendant::table";
            $this->parseTransfer($email, $urlSegment);
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hays Travel')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Hays Travel Online acts as a disclosed agent')]")->length > 0) {
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

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function parseParking(Email $email, $urlSegment)
    {
        // Segments roots
        $this->logger->debug('XPATH for PARKING ' . $urlSegment);
        $segments = $this->http->XPath->query($urlSegment);

        // Parse segments
        foreach ($segments as $root) {
            $p = $email->add()->parking();

            $p->setReservationDate($this->normalizeDate($this->reservDate));

            $description = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Ticket Number:')]", $root);

            $p->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Ticket Number:')]/following-sibling::td[1]", $root), $description)
                ->travellers($this->travellers, true);

            $p->place()
                ->location($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Location:')]/following-sibling::td[1]", $root));

            $pickUpDate = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'From:')]/following-sibling::td[1]", $root);
            $dropOffDate = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'To:')]/following-sibling::td[1]", $root);

            $p->booked()
                ->start($this->normalizeDate($pickUpDate))
                ->end($this->normalizeDate($dropOffDate))
                ->plate($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Car Registration:')]/following-sibling::td[1]", $root))
                ->car($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Car Details:')]/following-sibling::td[1]", $root));
        }
    }

    private function parseFlight(Email $email, $urlSegment)
    {
        // Segments roots
        $this->logger->debug('XPATH for FLIGHT ' . $urlSegment);
        $segments = $this->http->XPath->query($urlSegment);

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

            $confNumber = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Airline Reference:')]/following-sibling::td[1]", $root);
            $description = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Airline Reference:')]", $root);

            if (!empty($confNumber)) {
                $s->setConfirmation($confNumber, $description);
            }

            $airlineName = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Departure Date:')]/following-sibling::td[2]", $root, true, "/([A-Z]{2,3})\d+/");

            if (!empty($airlineName)) {
                $s->airline()
                    ->name($airlineName);
            } else {
                $s->airline()
                ->noName();
            }

            $airlineNumber = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Departure Date:')]/following-sibling::td[2]", $root, true, "/[A-Z]{2,3}(\d+)/");

            if (!empty($airlineNumber)) {
                $s->airline()
                    ->number($airlineNumber);
            } else {
                $s->airline()
                    ->noNumber();
            }

            $depCode = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Departure Airport:')]/following-sibling::td[1]", $root, true, "/[(](.+)[)]/");
            $depDate = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Departure Date:')]/following-sibling::td[1]", $root);
            $depTime = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Departure Date:')]/following-sibling::td[3]", $root);

            $s->departure()
                ->code($depCode)
                ->date($this->normalizeDate($depDate . ' ' . $depTime));

            $arrCode = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Arrival Airport:')]/following-sibling::td[1]", $root, true, "/[(](.+)[)]/");
            $arrDate = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Departure Date:')]/following-sibling::td[4]", $root, true, "/ (\d+\/\d+\/\d{4}) /");
            $arrTime = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Departure Date:')]/following-sibling::td[4]", $root, true, "/([\d:]+)/");

            if (empty($arrDate)) {
                $arrDate = $depDate;
            }

            $s->arrival()
                ->code($arrCode)
                ->date($this->normalizeDate($arrDate . ' ' . $arrTime));

            $cabin = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Class:')]/following-sibling::td[1]", $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }
    }

    private function parseHotel(Email $email, $urlSegment)
    {
        // Segments roots
        $this->logger->debug('XPATH for HOTEL ' . $urlSegment);
        $segments = $this->http->XPath->query($urlSegment);

        // Parse segments
        foreach ($segments as $root) {
            $h = $email->add()->hotel();

            $h->setReservationDate($this->normalizeDate($this->reservDate));

            $h->hotel()
                ->noAddress();

            $hotelName = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Hotel:')]/following-sibling::td[1]", $root);
            $h->hotel()
                ->name($hotelName);

            $confirmation = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Supplier:')]/following-sibling::td[1]", $root, true, "/(\d+)/");
            $description = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Supplier:')]/following-sibling::td[1]", $root, true, "/(.+)\s+[(]/");

            $h->general()
                ->travellers($this->travellers, true)
                ->confirmation($confirmation, $description);

            $checkIn = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Check In:')]/following-sibling::td[1]", $root));
            $checkOut = $this->normalizeDate($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Check Out:')]/following-sibling::td[1]", $root));

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

            $h->addRoom()
                ->setType($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Room(s):')]/following-sibling::td[1]", $root, true, "/(.+)\s*[-]/"));

            $adultCount = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Room(s):')]/following-sibling::td[1]", $root, true, "/Adults[:]\s*(\d+)/");
            $childrenCount = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Room(s):')]/following-sibling::td[1]", $root, true, "/Children[:]\s*(\d+)/");
            $infantsCount = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Room(s):')]/following-sibling::td[1]", $root, true, "/Infants[:]\s*(\d+)/");

            if ($adultCount > 0) {
                $h->booked()
                    ->guests($adultCount);
            }

            if (($childrenCount > 0) | ($infantsCount > 0)) {
                $h->booked()
                    ->kids($childrenCount + $infantsCount);
            } elseif (($childrenCount == 0 && empty($infantsCount)) | (empty($childrenCount) && $infantsCount == 0)) {
                $h->booked()
                    ->kids(0);
            }
        }
    }

    private function parseTransfer(Email $email, $urlSegment)
    {
        $this->logger->debug('XPATH for TRANSFER ' . $urlSegment);
        $segments = $this->http->XPath->query($urlSegment);

        foreach ($segments as $root) {
            $t = $email->add()->transfer();

            $confNumber = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Supplier Ref:')]/following-sibling::td[1]", $root);
            $description = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Supplier Ref:')]", $root);

            if (!empty($confNumber)) {
                $t->general()->confirmation($confNumber, $description);
            }
            $ts = $t->addSegment();

            $ts->departure()
                ->name($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Pick Up:')]/following-sibling::td[1]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Date:')]/following-sibling::td[1]", $root)));
            $ts->arrival()
                ->noDate()
                ->name($this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'Drop Off:')]/following-sibling::td[1]", $root));
        }
    }
}
