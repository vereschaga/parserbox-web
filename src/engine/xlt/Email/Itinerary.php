<?php

namespace AwardWallet\Engine\xlt\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "xlt/it-35555245.eml, xlt/it-36780535.eml, xlt/it-36780537.eml, xlt/it-36780551.eml, xlt/it-41509653.eml, xlt/it-44128482.eml, xlt/it-72039134.eml";

    private static $dict = [
        'en' => [
            'Flight'      => 'Flight',
            'From'        => 'From',
            'Departs'     => 'Departs',
            'Dropoff At'  => 'Dropoff At',
            'Pickup From' => 'Pickup From',
        ],
    ];

    private $detects = [
        'en'  => ['Electronic Ticket Receipt and Itinerary', 'Flight Details'],
        'en2' => ['FLIGHT DETAILS', 'DETAILS OF TRAVEL ARRANGEMENTS'],
        'en3' => ['Your Travel Itinerary', 'Important Notice For Travellers With Electronic Tickets'],
    ];

    private $pdfNamePattern = '(?:ETICKET RECEIPT.*|Travel Document|.*)\.pdf';

    private $lang = '';

    private $prov = 'XL EMBASSY';

    private $pdfs = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $this->pdfs = $pdfs;

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                    && $this->detectBody($text)
                    && $this->assignLangText($text)
                ) {
                    $anchor = false;

                    if (false !== stripos($text, 'Flight Details') && false !== stripos($text, 'Agent Details')) {
                        $anchor = $this->parseEmailPdf($text, $email, $parser);
                    } elseif (false !== stripos($text, 'FLIGHT DETAILS')
                        && (false !== stripos($text, 'IMPORTANT BOOKING INFORMATION')
                            || false !== stripos($text, 'IMPORTANT NOTICES'))
                    ) {
                        $anchor = $this->parseEmailPdf2($text, $email);
                    } elseif (false !== stripos($text, 'Your Travel Itinerary') && false !== stripos($text,
                            'Important Notice For Travellers With Electronic Tickets')) {
                        $anchor = $this->parseEmailPdf3($text, $email, $parser);
                    }

                    if (false === $anchor) {
                        return $email;
                    }

                    if (count($email->getItineraries()) > 0) {
                        $type = 'Pdf';
                    }
                }
            }
        }

        if (!isset($type)) {
            if (!$this->assignLangHttp()) {
                $this->logger->debug('can\'t determine a language');

                return $email;
            }
            $this->parseEmail($email);
            $type = 'Html';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//node()[{$this->contains($this->prov)}]")->length > 0 || $this->http->XPath->query("//node()[contains(normalize-space(.), 'Thanks for making your reservation with us! ')]")->length > 0) {
            return $this->assignLangHttp();
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                $this->detectBody($text)
                && $this->assignLangText($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    // it-41509653.eml
    private function parseEmail(Email $email): bool
    {
        $r = $email->add()->rental();

        if ($conf = $this->getNode('Confirmation Number:')) {
            $r->general()
                ->confirmation($conf);
        }

        if (($pickUpDate = $this->getNode('Arriving:')) && ($pd = $this->normalizeDate($pickUpDate))) {
            $r->pickup()
                ->date(strtotime($pd));
        }

        if ($pl = $this->getNode('Pickup From:')) {
            $r->pickup()
                ->location($pl);
        }

        if (($dropOffDate = $this->getNode('At:')) && ($dd = $this->normalizeDate($dropOffDate))) {
            $r->dropoff()
                ->date(strtotime($dd));
        }

        if ($dl = $this->getNode('Dropoff At:')) {
            $r->dropoff()
                ->location($dl);
        }

        if ($type = $this->getNode('Vehicle Type:')) {
            $r->setCarType($type);
        }

        $total = $this->getNode('Total Price:');

        if (preg_match('/(\D+)[ ]*(?<amount>\d[,.\'\d ]*)/', $total, $m)) {
            $r->price()->total($this->normalizeAmount($m['amount']));
        }

        if (empty($r->getPickUpDateTime()) && empty($r->getPickUpLocation()) && empty($r->getDropOffLocation())) {
            return false;
        }

        return true;
    }

    private function normalizeDate(string $s)
    {
        $in = [
            '/^\w+ (\w+) (\d{1,2}) (\d{2,4}) - (\d{1,2}:\d{2} [AP]M)/', // Sunday July 21 2019 - 03:05 PM
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        return preg_replace($in, $out, $s);
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

    private function getNode(string $s, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("//b[{$this->eq($s)}]/following-sibling::span[normalize-space(.)][1]", null,
            true, $re);
    }

    // it-35555245.eml
    private function parseEmailPdf(string $text, Email $email, PlancakeEmailParser $parser): bool
    {
        $this->logger->debug('Found PDF type 1');
        $f = $email->add()->flight();

        $mainInfo = $this->findCutSection($text, 'Electronic Ticket Receipt and Itinerary', 'Flight Details');

        $confNos = [];

        if (preg_match_all('/Airline Reference[ ]+([A-Z\d]{5,9})/', $mainInfo, $m)) {
            $confNos = $m[1];
        }
        $confNos = array_filter(array_unique($confNos));

        foreach ($confNos as $confNo) {
            $f->general()
                ->confirmation($confNo);
        }

        $ticketNumbers = [];

        if (preg_match_all('/Electronic Ticket Number[ ]+(\d+)/', $mainInfo, $m)) {
            $ticketNumbers = $m[1];
        }
        $ticketNumbers = array_filter(array_unique($ticketNumbers));
        $f->setTicketNumbers($ticketNumbers, false);

        $passengers = [];

        if (preg_match_all('/Passenger Name[ ]+([A-Z\/]+)/', $mainInfo, $m)) {
            $passengers = $m[1];
        }
        $passengers = array_filter(array_unique($passengers));

        foreach ($passengers as $passenger) {
            $f->addTraveller($passenger);
        }

        $codes = [];

        if (preg_match_all('/(?<an>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+(?<fn>\d+).+(?<dcode>[A-Z]{3})(?:\([A-Z\d]{1,5}\))?[ ]+(?<acode>[A-Z]{3})/',
            $mainInfo, $m)) {
            foreach ($m['an'] as $i => $an) {
                $codes[$an . $m['fn'][$i]] = [$m['dcode'][$i], $m['acode'][$i]];
            }
        }

        $flightText = $this->findCutSection($text, 'Flight Details', ['General Remarks', 'Important Notice For Travellers With Electronic Tickets', 'Agent Details']);
        $flightNames = array_keys($codes);
        $flights = $this->splitter('/((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+[ ]+\w+,[ ]+[A-Z].+)/', $flightText);

        foreach ($flights as $i => $flight) {
            $j = 1;
            $table = $this->splitCols($flight);
            // booking code and departure airport or air info and date are sticking together in a letter
            if (8 === count($table)) {
                $pdfText = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($this->pdfs)),
                    \PDF::MODE_COMPLEX);
                $nbsp = chr(194) . chr(160);
                $pdfText = str_replace($nbsp, '', $pdfText);
                $pdf = clone $this->http;
                $pdf->SetEmailBody($pdfText);
                $bc = $pdf->FindSingleNode("(//p[{$this->eq($flightNames[$i])}])[{$j}]/following-sibling::p[normalize-space(.)][4]",
                    null, true, '/^\s*[A-Z]\s*$/');
                $depName = $pdf->FindSingleNode("(//p[{$this->eq($flightNames[$i])}])[{$j}]/following-sibling::p[normalize-space(.)][6]");
                // checking
                if ($bc && $depName && false !== strpos($table[2], $bc . $depName)) {
                    array_splice($table, 2, 1, [$bc, $depName]);
                } elseif (
                    ($fi = $pdf->FindSingleNode("(//p[{$this->eq($flightNames[$i])}])[{$j}]"))
                    && ($dt = $pdf->FindSingleNode("(//p[{$this->eq($flightNames[$i])}])[{$j}]/following-sibling::p[normalize-space(.)][2]"))
                    && ($dts = preg_replace('/\s+/', '\s+', $dt))
                    && preg_match("/{$fi}\s*{$dts}/", $table[0])
                ) {
                    array_splice($table, 0, 1, [$fi, $dt]);
                }

                $j++;
            }

            $table = $this->splitColsTable($table);

            if (count($table) > 10 && count($table) < 8) {
                $this->logger->alert("Incorrect segment table");

                return false;
            }

            $s = $f->addSegment();

            if (preg_match('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/', $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $date = strtotime($this->ns($this->re('/(\w+,\s+\d{1,2}\s*\w+\s*\d{2,4})/', $table[1])));

            if (!empty($bc = $this->re("/^([A-Z])\s/", $this->ns($table[2])))) {
                $s->extra()
                    ->bookingCode(trim($bc));
            }

            $re = '/(.+)\s+Terminal ([A-Z\d]{1,5})/s';

            if (preg_match($re, $table[3], $m)) {
                $s->departure()
                    ->name($this->ns($m[1]))
                    ->terminal($m[2]);
            } else {
                $s->departure()
                    ->name($this->ns(preg_replace("/\s{3,4}(.+)/s", "", $table[3])));
            }

            if (preg_match($re, $table[4], $m)) {
                $s->arrival()
                    ->name($this->ns($m[1]))
                    ->terminal($m[2]);
            } else {
                $s->arrival()
                    ->name($this->ns($table[4]));
            }

            if (($depTime = $this->re('/(\d{1,2}:\d{2})/', $table[5])) && ($depDate = strtotime($depTime, $date))) {
                $s->departure()
                    ->date($depDate);
            }

            if (($depTime = $this->re('/(\d{1,2}:\d{2})/', $table[6])) && ($depDate = strtotime($depTime, $date))) {
                $s->arrival()
                    ->date($depDate);
            }

            if (isset($codes[$s->getAirlineName() . $s->getFlightNumber()])) {
                $s->departure()
                    ->code($codes[$s->getAirlineName() . $s->getFlightNumber()][0]);
                $s->arrival()
                    ->code($codes[$s->getAirlineName() . $s->getFlightNumber()][1]);
            } else {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }

            $s->setStatus($this->re('/^[ ]*(\w+)\s*/', $table[7]));
        }

        return true;
    }

    // it-36780551.eml, it-72039134.eml
    private function parseEmailPdf2(string $text, Email $email): bool
    {
        $this->logger->debug('Found PDF type 2');
        $f = $email->add()->flight();

        $mainInfo = $this->findCutSection($text, 'Booking Confirmation', 'FLIGHT DETAILS');

        if (preg_match("/^[ ]*(Confirmation Reference Number):[ ]*(\w+)$/m", $mainInfo, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match('/^[ ]*(Agency Reference Number):[ ]*(\w+)$/m', $mainInfo, $m)) {
            $f->ota()->confirmation($m[2], $m[1]);
        }

        $paxText = $this->findCutSection($mainInfo,
            'We have pleasure in confirming the following travel arrangements on behalf of', 'Please ensure');

        if (preg_match_all('/\*[ ]+([A-Z\/]+)/', $paxText, $m)) {
            foreach ($m[1] as $pax) {
                $f->general()
                    ->traveller($pax);
            }
        }

        if (preg_match_all('/(?:Adult|Child)[ ]+(?-i)\b([A-Z\d]{5,9})\b[ ]*/i', $paxText, $m)) {
            foreach ($m[1] as $acc) {
                $f->addAccountNumber($acc, false);
            }
        }

        $PNRsByAirline = [];
        $airlineReferencesText = $this->findCutSection($text, 'Airline References', ['Cost Summary', 'IMPORTANT BOOKING INFORMATION', 'IMPORTANT NOTICES', "\n\n"]);

        if (preg_match_all('/^[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+.*[ ]+Reference No:[ ]*(?<pnr>[-A-Z\d]{5,})$/m', $airlineReferencesText, $airlineMatches, PREG_SET_ORDER)) {
            // 4Z   South African Airlink   Reference No: P26CB3
            foreach ($airlineMatches as $m) {
                $PNRsByAirline[$m['airline']] = $m['pnr'];
            }
        }

        $terms = [];
        $termsInfo = $this->findCutSection($text, 'Flight Information', 'City Code References');
        preg_match_all('/(?<fc>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<fn>\d+)[ ]+(?i)Departs Terminal:[ ]*(?<dterm>[A-z\d][A-z\d ]*)?[ ]*Arrives Terminal:[ ]*(?<aterm>[A-z\d][A-z\d ]*)?[ ]*No Stops/', $termsInfo, $terminalMatches, PREG_SET_ORDER);

        foreach ($terminalMatches as $m) {
            $terms[$m['fc'] . $m['fn']] = [
                'dterm' => (empty($m['dterm']) ? null : $m['dterm']),
                'aterm' => (empty($m['aterm']) ? null : $m['aterm']),
            ];
        }

        $flightText = $this->findCutSection($text, 'FLIGHT DETAILS', 'Flight Information');
        $flightText = preg_replace('/^(\s*\S.+?\n)\n.+/s', '$1', $flightText);
        $segments = $this->splitter('/(\w+[ ]+\d{1,2} \w+ \d{2,4}[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+.+)/', $flightText);

        if (0 === count($segments)) {
            return false;
        }

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            /*
              Mon   13 May 19   SA8841   JNB Johannesburg   MQP Nelspruit   11:05   11:50   Confirmed   G - Economy

              or

              Mon   13 May 19   SA8841   JNB Johannesburg   MQP Nelspruit   11:05   11:50   Confirmed   Economy
            */
            $re = '/^[ ]*\w+[ ]+(?<date>\d{1,2} \w+ \d{2,4})[ ]+(?<fn>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<ff>\d+)[ ]+(?<dcode>[A-Z]{3})[ ]+(?<dname>.+)[ ]+(?<acode>[A-Z]{3})[ ]+(?<aname>.+)[ ]+(?<dtime>\d{1,2}:\d{2})[ ]+(?<atime>\d{1,2}:\d{2})[ ]+(?<status>\w+)[ ]+(?:(?<bc>[A-Z]{1,2})[ ]*-[ ]*)?(?<cabin>\w+)[ ]*$/m';

            if (preg_match($re, $segment, $m)) {
                $date = strtotime($m['date']);
                $s->airline()
                    ->name($m['fn'])
                    ->number($m['ff']);
                $s->departure()
                    ->code($m['dcode'])
                    ->name($m['dname'])
                    ->date(strtotime($m['dtime'], $date));
                $s->arrival()
                    ->code($m['acode'])
                    ->name($m['aname'])
                    ->date(strtotime($m['atime'], $date));
                $s->extra()
                    ->status($m['status'])
                    ->cabin($m['cabin']);

                if (!empty($m['bc'])) {
                    $s->extra()->bookingCode($m['bc']);
                }

                $key = $m['fn'] . $m['ff'];

                if (!empty($terms[$key])) {
                    $s->departure()->terminal($terms[$key]['dterm'], false, true);
                    $s->arrival()->terminal($terms[$key]['aterm'], false, true);
                }

                if (!empty($PNRsByAirline[$m['fn']])) {
                    $s->airline()->confirmation($PNRsByAirline[$m['fn']]);
                }
            }
        }

        $сostSummaryText = $this->findCutSection($text, 'Cost Summary', ['IMPORTANT NOTICES', 'IMPORTANT BOOKING INFORMATION', "\n\n"]);

        if (preg_match("/^[ ]*APPROXIMATE TOTAL[ ]+(?<currency>[A-Z]{3})[ ]+(?<amount>\d[,.\'\d]*)$/m", $сostSummaryText, $m)) {
            // APPROXIMATE TOTAL   NAD   3623.00
            $email->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }

        return true;
    }

    // it-44128482.eml
    private function parseEmailPdf3(string $text, Email $email, PlancakeEmailParser $parser): bool
    {
        $this->logger->debug('Found PDF type 3');
        $mainInfo = $this->findCutSection($text, 'Your Travel Itinerary',
            'Important Notice For Travellers With Electronic Tickets');

        $pdfText = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($this->pdfs)), \PDF::MODE_COMPLEX);
        $pdf = clone $this->http;
        $pdf->SetEmailBody($pdfText, true);
        $paxs = $pdf->FindNodes("//p[normalize-space(.)='Travellers']/following-sibling::p[contains(normalize-space(.), 'Adult -') and contains(normalize-space(.), '*')][normalize-space(.)]",
            null, '/\*[ ]*(.+)[ ]*\(.+\)/');

        $itineraries = $this->splitter('/\n[ ]*(\w+, \d{1,2} \w+ \d{2,4})/', $mainInfo);
        $anchor = false;

        foreach ($itineraries as $itinerary) {
            if (false !== stripos($itinerary, 'Flight') && false !== stripos($itinerary,
                    'Departs') && false === stripos($itinerary, 'Travel Agency and IATA Number')) {
                $anchor = $this->flight($email, $itinerary, $paxs);
            }

            if (false !== stripos($itinerary, 'Hotel') && false !== stripos($itinerary, 'Check In')) {
                $anchor = $this->hotel($email, $itinerary, $paxs);
            }

            if (false !== strpos($itinerary, 'Other') || false !== stripos($itinerary,
                    'Travel Agency and IATA Number')) {
                continue;
            }
        }

        if (false === $anchor) {
            return false;
        }

        return true;
    }

    private function flight(Email $email, string $text, array $passengers = []): bool
    {
        $f = $email->add()->flight();

        if (preg_match_all('/\*[ ]+([A-Z\/]+)/', $text, $m)) {
            foreach ($m[1] as $pax) {
                $f->general()
                    ->traveller($pax);
            }
        }

        if (preg_match_all('/(\d{5,16})[ ]*\(Electronic\)/', $text, $m)) {
            foreach ($m[1] as $ticket) {
                $f->addTicketNumber($ticket, false);
            }
        }

        $date = strtotime($this->re('/\w+,[ ]+(\d{1,2} \w+ \d{2,4})/', $text));

        if ($conf = $this->re('/Confirmation Number For[ ]+.+[ ]{2,}(\w+)/', $text)) {
            $f->general()
                ->confirmation($conf);
        }

        $s = $f->addSegment();

        if (preg_match('/Flight[ ]+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)[ ]*\-[ ]*.+[ ]{2,}\w+[ ]*\-[ ]*(\w+)/', $text, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
            $s->setStatus($m[3]);
        }

        if (preg_match('/Class[ ]+([A-Z])[ ]*\-[ ]*(\w+)/', $text, $m)) {
            $s->extra()
                ->bookingCode($m[1])
                ->cabin($m[2]);
        }

        $re = '[ ]*(?<time>\d{1,2}:\d{2})[ ]+(?<name>.+)[ ]{2,}(?<code>[A-Z]{3})[ ]*(?:Terminal[ ]*(?<term>[A-Z\d]{1,5}))?';

        if (preg_match("/Departs{$re}/", $text, $m)) {
            $s->departure()
                ->date(strtotime($m['time'], $date))
                ->name($m['name'])
                ->code($m['code']);

            if (!empty($m['term'])) {
                $s->departure()
                    ->terminal($m['term']);
            }
        }

        if (preg_match("/Arrives{$re}/", $text, $m)) {
            $s->arrival()
                ->date(strtotime($m['time'], $date))
                ->name($m['name'])
                ->code($m['code']);

            if (!empty($m['term'])) {
                $s->arrival()
                    ->terminal($m['term']);
            }
        }

        if ($duration = $this->re('/Flying Time[ ]*(\d{1,2}:\d{2})/', $text)) {
            $s->extra()
                ->duration($duration);
        }

        if ($airbus = $this->re('/Equipment Airbus Industrie[ ]+(.+)/', $text)) {
            $s->extra()
                ->aircraft($airbus);
        }

        if ($meal = $this->re('/Meal[ ]+(.+)/', $text)) {
            $s->extra()
                ->meal($meal);
        }

        if (empty($s->getFlightNumber()) && empty($s->getDepDate()) && empty($s->getArrDate())) {
            return false;
        }

        return true;
    }

    private function hotel(Email $email, string $text, array $passengers = []): bool
    {
        $h = $email->add()->hotel();

        foreach ($passengers as $passenger) {
            $h->addTraveller($passenger);
        }

        if ($conf = $this->re('/Confirmation Number For[ ]+.+[ ]{2,}(\w+)/', $text)) {
            $h->general()
                ->confirmation($conf);
        }

        if (preg_match('/Hotel[ ]+(.+)[ ]{2}[A-Z]+[ ]*\-[ ]*(?:Status)?[ ]*(\w+)/', $text, $m)) {
            $h->hotel()
                ->name($m[1]);
            $h->setStatus($m[2]);
        }

        if (preg_match('/City[ ]+(.+)[ ]{2,}Rate[ ]*([A-Z]{3})[ ]*([\d\.]+)/', $text, $m)) {
            $h->hotel()
                ->address($m[1]);
            $r = $h->addRoom();
            $r->setRate($m[3]);
            $h->price()
                ->currency($m[2]);

            if ($type = $this->re('/Room Type[ ]+(.+)/', $text)) {
                $r->setType($type);
            }
        }

        if ($adults = $this->re('/Guests[ ]+(\d{1,2})/', $text)) {
            $h->booked()
                ->guests($adults);
        }

        if (preg_match('/Check In[ ]+Date[ ]+\w+, (\d{1,2} \w+ \d{2,4}),?[ ]*(?:Check In From[ ]+(\d{1,2}[\:\d]{0,3}[ ]*[AP]M)?)?/i',
            $text, $m)) {
            $checkIn = strtotime($m[1]);

            if (!empty($m[2])) {
                $checkIn = strtotime($m[2], $checkIn);
            }
            $h->booked()
                ->checkIn($checkIn);
        }

        if (preg_match('/Check\s+Date[ ]+\w+, (\d{1,2} \w+ \d{2,4}),?[ ]*(?:Check Out Before[ ]+(\d{1,2}[\:\d]{0,3}[ ]*[AP]M)?)?/',
            $text, $m)) {
            $checkOut = strtotime($m[1]);

            if (!empty($m[2])) {
                $checkOut = strtotime($m[2], $checkOut);
            }
            $h->booked()
                ->checkOut($checkOut);
        }

        if (empty($h->getHotelName()) && empty($h->getCheckInDate()) && empty($h->getCheckOutDate())) {
            return false;
        }

        return true;
    }

    private function ns(?string $s = null): ?string
    {
        return preg_replace('/\s+/', ' ', $s);
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function detectBody($body)
    {
        if (isset($this->detects)) {
            foreach ($this->detects as $lang => $detect) {
                if (stripos($body, $detect[0]) !== false && stripos($body, $detect[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangText($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Flight'], $words['From'])) {
                if (stripos($body, $words['Flight']) !== false && stripos($body, $words['From']) !== false) {
                    $this->lang = $lang;

                    return true;
                } elseif (isset($words['Departs']) && false !== stripos($body,
                        $words['Flight']) && false !== stripos($body, $words['Departs'])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangHttp()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Pickup From"], $words["Dropoff At"])) {
                if (
                    $this->http->XPath->query("//*[{$this->contains($words["Pickup From"])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words["Dropoff At"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function findCutSection($input, $searchStart, $searchFinish): ?string
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return null;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
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

    private function splitColsTable($table)
    {
        if (preg_match("/^([A-Z\d]+)\s([\w]+,\s\d{1,2}\s[A-z]{3}\s\d{2,4})/", $table[0], $m)) {
            unset($table[0]);
            array_unshift($table, $m[1], $m[2]);
        }

        return $table;
    }
}
