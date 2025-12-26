<?php

namespace AwardWallet\Engine\railbookers\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: create new parser `Invoice2Pdf` for it-445806541.eml and it-650966993-amtrak.eml

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "railbookers/it-265696452.eml, railbookers/it-265850921.eml, railbookers/it-267852329.eml, railbookers/it-425626484.eml, railbookers/it-435594930.eml, railbookers/it-445806541.eml, railbookers/it-574528328.eml, railbookers/it-650966993-amtrak.eml, railbookers/it-687240563.eml";
    public $pdfNamePattern = ".*\.pdf";

    public $hotelRegion = [];
    public $lang = '';
    public static $dictionary = [
        'en' => [
        ],
    ];
    private $providerCode = '';

    private $detectSubjectRe = [
        // en
        '/Itinerary for .+ \| Reloc: [A-Z\d]{5,7} \| Depart: .+/',
    ];

    private $patterns = [
        'date'  => '\b\d{1,2}[-. ]+[[:alpha:]]+[-,. ]+\d{2,4}\b', // 25 Mar 24
        'date2' => '\b[-[:alpha:]]+ ?, ?\d{1,2} [[:alpha:]]+ ?, ?\d{2,4}\b', // Friday, 22 March, 2024
        'time'  => '\b\d{1,2}[:：]\d{2}(?:\s*[AaPp](?:\.\s*)?[Mm]\b\.?)?', // 4:19PM    |    2:00 p. m.
        'time2' => '\b\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\b\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@expresstickets.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjectRe as $dSubject) {
            if (preg_match($dSubject, $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($text && $this->assignProviderPdf($text) && $this->detectPdf($text)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $i => $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }

            if ($this->detectPdf($text) == true) {
                if ($this->assignProviderPdf($text)) {
                    $this->logger->debug('pdf-' . $i . ' provider: ' . $this->providerCode);
                }

                $this->parseEmailPdf($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    public function detectPdf($text): bool
    {
        if ($this->strposAll($text, ['Rail Travel', 'Train Information:']) !== false) {
            $this->lang = 'en';

            return true;
        }

        return false;
    }

    private function assignProviderPdf($text): bool
    {
        if (stripos($text, 'Thank you for choosing Amtrak Vacations') !== false || stripos($text, 'www.amtrakvacations.com/') !== false) {
            $this->providerCode = 'amtrak';

            return true;
        }

        if (strpos($text, 'Railbookers') !== false || stripos($text, 'www.railbookers.com/') !== false) {
            $this->providerCode = 'railbookers';

            return true;
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null): void
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $email->ota()
            ->confirmation($this->re("/\s+(?:Booking Reference|Reference Number) ?:? *(\d{5,})(?: {3,}|\n)/", $textPdf));

        $textPdf = preg_replace("/\n.*Page \d+ of \d+\n/", "\n", $textPdf);
        $its = $this->split("/\n[ ]*((?:Accommodation|Rail Travel|Payment Summary|LOCAL CONTACT INFORMATION|TRAVEL VOUCHER|Other|Train Information[ ]*:|HOTEL[ ]*:.+|ACTIVITIES[ ]*:.+|Train from .{7,} \d{1,2}[ ]*(?i)Passenger(?-i))\n)/", $textPdf);

        $dateVal = '';

        foreach ($its as $i => $part) {
            $partPos = strpos($textPdf, $part);

            if ($partPos && preg_match("/\n[ ]*({$this->patterns['date2']})\s*$/u", substr($textPdf, 0, $partPos), $m)) {
                $dateVal = $m[1];
            }

            if (preg_match("/^\s*Accommodation/", $part)) {
                $this->logger->debug('part-' . $i . ': HOTELs');
                $this->parseHotels($email, $part);

                continue;
            }

            if (preg_match("/^\s*HOTEL[ ]*:.+\n[\s\S]+\bCheck In[ ]*:(?:[ ]*\d|\n)/", $part)) {
                $this->logger->debug('part-' . $i . ': HOTEL_2');
                $this->parseHotel2($email, $part, $dateVal);

                continue;
            }

            if (preg_match("/^\s*TRAVEL VOUCHER\n/", $part) && stripos($part, 'Checking In:') !== false
                && stripos($part, 'Checking out:') !== false
            ) {
                $this->logger->debug('part-' . $i . ': HOTEL_3');
                $this->parseHotel3($email, $part, $dateVal);

                continue;
            }

            if (preg_match("/^\s*(?:Rail Travel)/", $part)) {
                $this->logger->debug('part-' . $i . ': TRAIN');
                $this->parseTrain($email, $part);

                continue;
            }

            if (preg_match("/^\s*Train Information[ ]*:/", $part)) {
                $this->logger->debug('part-' . $i . ': TRAIN_2');
                $this->parseTrain2($email, $part);

                continue;
            }

            if (preg_match("/^\s*Payment Summary/", $part)) {
                $totalPrice = $this->re("/\n *Total {10,}(.+)/", $part);

                if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d]{1,5}?)\s*$#", $totalPrice, $m)
                    || preg_match("#^\s*(?<currency>[^\d]{1,5}?)\s*(?<amount>\d[\d\., ]*)\s*$#", $totalPrice, $m)
                ) {
                    $currency = $this->currency($m['currency']);
                    $email->price()
                        ->total(PriceHelper::parse($m['amount']), $currency)
                        ->currency($currency)
                    ;
                }
            }
        }

        $bookingDate = strtotime($this->normalizeDate($this->re("/\sBooking Date[: ]*(.*(?:\D|\b)\d{4})\n/", $textPdf)));

        if (empty($bookingDate)) {
            $bookingDate = strtotime($this->normalizeDate($this->re("/Reference Number\s*:\s*\d{5,}\n.*[ ]{2}(\d{1,2}[, ]*[[:alpha:]]+[, ]*\d{4})\n/u", $textPdf)));
        }

        $travellers = [];
        $travellersText = $this->re("/\nPassengers\n+((?:.+ {3,}.*\b\d{4}\b.*\n+)+)/", $textPdf);

        if (!empty($travellersText)) {
            $travellers = preg_replace("/^ *(\w.+?) {3,}.+$/m", '$1', array_filter(explode("\n", $travellersText)));
        }

        if (empty($travellersText)) {
            $travellersText = $this->re("/\n {0,5}Passenger Name\(s\)\n{1,2}((?:.*\n){1,20}?)\n(?:[ ]*KNOW BEFORE YOU GO\n|\n\n)/i", $textPdf);

            if (!empty($travellersText)) {
                $travellers = preg_split("/\s{3,}/", trim($travellersText));
            }
        }

        if (count($travellers) == 0 && preg_match_all("/Prepared for:\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]{4,}Reference Number\:/", $textPdf, $m)) {
            $travellers = $m[1];
        }

        foreach ($email->getItineraries() as $it) {
            if (!empty($bookingDate)) {
                $it->general()
                    ->date($bookingDate);
            }
            $it->general()
                ->travellers(array_unique(preg_replace("/^(?:MR\.|MRS\.)\s*/", "", $travellers)), true);
        }
    }

    private function parseHotels(Email $email, $text): void
    {
        $segments = $this->split("/(.+ +Checking In)/", $text);

        foreach ($segments as $hText) {
            $h = $email->add()->hotel();

            $h->general()
                ->noConfirmation();

            $pos = $this->rowColumnPositions($this->inOneRow($hText));

            if (isset($pos[2]) && preg_match("/^.{{$pos[2]}} *Checking In/", $hText)) {
                unset($pos[1]);
                $pos = array_values($pos);
            }

            if (isset($pos[3]) && preg_match_all("/^.{{$pos[3]}}(.+)/m", $hText, $matches)
                && strlen(implode('', array_map('trim', $matches[1])) < 10)
            ) {
                unset($pos[3]);
                $pos = array_values($pos);
            }

            if (count($pos) == 3 && preg_match("/^.{{$pos[1]}} *Checking In/", $hText)) {
                unset($pos[2]);
                $pos = array_values($pos);
            }

            if (count($pos) !== 2) {
                break;
            }
            $pos = array_values($pos);
            $table = $this->createTable($hText, $pos, true);

            if (stripos($table[0], 'Checking In') == false) {
                if (preg_match("/^([\s\S]+) +(Room Quantity +\d+)\s*$/", $table[0], $m)) {
                    $table[0] = $m[1];
                    $table[1] .= "\n" . $m[2];
                }

                $nameCount = substr_count($this->re("/^([\s\S]+?\n).* +Checking Out/", $hText), "\n");

                if (preg_match("/^(?<name>(.*\n){" . (!empty($nameCount) ? $nameCount : '1') . "})(?<address>[\s\S]+?)(?<phone>\n[\d\+\-\(\) ]{6,})?\s*$/", $table[0], $m)) {
                    $h->hotel()
                        ->name(preg_replace('/\s+/', ' ', $m['name']))
                        ->address(preg_replace("/\s*\n\s*/", ' ', trim($m['address'])))
                        ->phone(trim($m['phone'] ?? ''), true, true);
                }

                $h->booked()
                    ->checkIn(strtotime($this->re("/Checking In *(.+)/", $table[1])))
                    ->checkOut(strtotime($this->re("/Checking Out *(.+)/", $table[1])))
                    ->rooms($this->re("/Room Quantity *(\d+)(?:\n|$)/", $table[1]))
                ;
                $h->addRoom()
                    ->setType(preg_replace("/\s*\n\s*/", ' ',
                        trim($this->re("/Room Type *([\s\S]+)\n\s*Room Quantity/", $table[1]))));
            } else {
                $table[0] = str_replace(['Checking In', 'Checking Out', 'No. Of Nights', 'Room Type', 'Room Quantity'], '', $table[0]);

                if (preg_match("/^(?<hotelName>.+)[ ]{10,}\n(?<address>(?:.+\n){1,})\n*(?<phone>[+]\d+)\s*$/", $table[0], $m)) {
                    $h->hotel()
                        ->name($m['hotelName'])
                        ->address(preg_replace("/(?:\s+|\n)/", " ", $m['address']))
                        ->phone($m['phone']);
                }

                if (preg_match("/^\s+(?<checkIn>.+\d{4})\n+\s*(?<checkOut>.+\d{4})\n\s*\d+\s*(?<roomType>(?:.+\n){1,})\s+(?<roomCount>\d+)/", $table[1], $m)) {
                    $h->booked()
                        ->checkIn(strtotime($m['checkIn']))
                        ->checkOut(strtotime($m['checkOut']))
                        ->rooms($m['roomCount']);

                    $h->addRoom()
                        ->setType(str_replace("\n", " ", $m['roomType']));
                }
            }

            if (!empty($h->getAddress()) && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                if (preg_match("/ (FR|ES|UK|DE|CH|IT|CZ|AT|FRANCE|AUSTRIA|UNITED KINGDOM|SPAIN|ITALY|IE|IRELAND|SWITZERLAND|CH)\s*$/", $h->getAddress())) {
                    $this->hotelRegion['europe'][] = ['start' => $h->getCheckInDate(), 'end' => $h->getCheckOutDate()];
                } else {
                    $this->hotelRegion['null'][] = ['start' => $h->getCheckInDate(), 'end' => $h->getCheckOutDate()];
                }
            }
        }
    }

    private function parseHotel2(Email $email, $text, string $dateVal): void
    {
        // examples: it-650966993-amtrak.eml
        $h = $email->add()->hotel();

        $firstRow = $this->re("/^\s*HOTEL[ ]*:[ ]*(\S.+)\n/i", $text);
        $firstRowParts = preg_split('/[ ]{2,}/', $firstRow);
        $h->hotel()->name($firstRowParts[0])->noAddress();

        $dateCheckIn = strtotime($this->normalizeDate($dateVal));
        $nights = $this->re("/^[ ]*Duration[ ]*:[ ]*(\d{1,3})[ ]*Nights?(?:[ ]{2}|$)/im", $text);

        if ($dateCheckIn && $nights !== null) {
            $dateCheckOut = strtotime("+{$nights} days", $dateCheckIn);
        } else {
            $dateCheckOut = 0;
        }

        if ($dateCheckIn && preg_match("/^(?:[ ]*|.{15,} )Check[- ]In[ ]*:[ ]*({$this->patterns['time2']})$/im", $text, $m)) {
            $dateCheckIn = strtotime($m[1], $dateCheckIn);
        }

        if ($dateCheckOut && preg_match("/^(?:[ ]*|.{15,} )Check[- ]Out[ ]*:[ ]*({$this->patterns['time2']})$/im", $text, $m)) {
            $dateCheckOut = strtotime($m[1], $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $h->general()->noConfirmation();
    }

    private function parseHotel3(Email $email, $text, string $dateVal): void
    {
        $h = $email->add()->hotel();

        $h->general()->noConfirmation();

        // Hotel
        $hotelInfoBlock = $this->re("/(?:^|\n)\s*Booking Reference[ ]*:[ ]*.+\n+([\S\s]+?)\n+ *Please Provide the Following Services:/", $text);
        $hotelInfo = $this->createTable($hotelInfoBlock, $this->rowColumnPositions($this->inOneRow($hotelInfoBlock)))[0] ?? '';

        if (preg_match("/^\s*(?<name>\S.+)\n\s*(?<address>\S[\s\S]+?)\s*\n\s*Local Phone: *(?<phone>\S.+)?/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', $m['address']))
                ->phone($m['phone'] ?? null, true, true)
            ;
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->re("/^ *Checking In: *(\S.+)$/m", $text)))
            ->checkOut(strtotime($this->re("/^ *Checking out: *(\S.+)$/m", $text)));

        // Rooms
        if (preg_match("/^ *Quantity: *(\d+) *(\S.+)$/m", $text, $m)) {
            $h->booked()
                ->rooms($m[1]);
            $h->addRoom()
                ->setType($m[2]);
        }
    }

    private function parseTrain(Email $email, $text): void
    {
        // examples: it-265696452.eml, it-265850921.eml, it-267852329.eml, it-425626484.eml, it-435594930.eml, it-574528328.eml
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation();
        $classTable = $this->re("/\n *Route +Travel Class(\n[\s\S]+)\n *Your train times are scheduled as follows:/", $text);
        $nameTest = implode(' - ', $this->res("/^ {0,20}(\w.+?)(?: {3,}|$)/m", $classTable));
        $names = array_unique(preg_split("/\s+-\s+/", $nameTest));
        $names = preg_replace("/^(.{12}).*/", '$1', $names);
        $names = preg_replace("/^(.+[a-z])\-.+/u", '$1', $names);

        $segmentsText = preg_replace("/^[\s\S]+?\n *Train +From .* Depart .+/", '', $text);
        $segmentsText = preg_replace("/^Train +From .* Depart .+\n+/m", '', $segmentsText);

        /*Other Rail Services
        Date                Description                                                              Qty
        17 Aug 23           4 Day Swiss Pass Standard Class                                          4 Adults*/
        $segmentsText = preg_replace("/Other Rail Services\nDate.+\n.+/", '', $segmentsText);

        $segments = $this->split("/\n*(.+\b\d{4}\b.+\b\d{4}\b\s*[\d\:]*)/", "\n" . $segmentsText . "\n");

        if (count($segments) === 1 && !preg_match("/\b\d{4}\b.+\b\d{4}\b/", $segmentsText)
            && preg_match("/\b\d{2}\\/\d{2}\\/\d{2}\b.*\b\d{2}\\/\d{2}\\/\d{2}\b/", $segmentsText)
        ) {
            $segments = $this->split("/\n*(.+\b\d{2}\\/\d{2}\\/\d{2}\b.+\b\d{2}\\/\d{2}\\/\d{2}\b\s*[\d\:]*)/", "\n" . $segmentsText . "\n");
        }
        // $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $tText) {
            $pos = [];

            if (!empty($names) && preg_match("/^(?<p3>(?<p2>(?<p1>(?<p0>.+ ){$this->opt($names)}.*? ){$this->opt($names)}.*? )\d{1,2} [[:alpha:]]{3,}.*? )\d{1,2} [[:alpha:]]{3,}.*?/u", $tText, $m)) {
                $pos = [0, mb_strlen($m['p0']),  mb_strlen($m['p1']),  mb_strlen($m['p2']),  mb_strlen($m['p3'])];
            } elseif (!empty($names) && preg_match("/^(?<p3>(?<p2>(?<p1>(?<p0>.+ ){$this->opt($names)}.*? ){$this->opt($names)}.*? )\d{2}\\/\d{2}\\/\d{2}.*? )\d{2}\\/\d{2}\\/\d{2}.*?/u", $tText, $m)) {
                $pos = [0, mb_strlen($m['p0']),  mb_strlen($m['p1']),  mb_strlen($m['p2']),  mb_strlen($m['p3'])];
            }

            $s = $t->addSegment();

            if (empty($pos)) {
                break;
            }

            $table = $this->createTable($tText, $pos);
            $table = array_map('trim', preg_replace("/\s*\n\s*/", ' ', $table));
            $table[3] = preg_replace("/^(\d{2})\\/(\d{2})\\/(\d{2}\s+)/", '$1.$2.20$3', $table[3] ?? '');
            $table[4] = preg_replace("/^(\d{2})\\/(\d{2})\\/(\d{2}\s+)/", '$1.$2.20$3', $table[4] ?? '');

            $s->departure()
                ->name($table[1] . ', Europe') //added Europe because GeoTip don't work
                ->date(strtotime($table[3]));

            $s->arrival()
                ->name($table[2] . ', Europe')
                ->date(strtotime($table[4]));

            if (count($this->hotelRegion) === 1) {
                if (!isset($this->hotelRegion['null'])) {
                    $s->departure()
                        ->geoTip(array_key_first($this->hotelRegion));
                    $s->arrival()
                        ->geoTip(array_key_first($this->hotelRegion));
                }
            } elseif (!empty($s->getDepDate()) && !empty($s->getArrDate()) && count($this->hotelRegion) > 1) {
                $start = strtotime('00:00', $s->getDepDate());
                $end = strtotime('00:00', $s->getArrDate());
                $regions = [];

                foreach ($this->hotelRegion as $re => $allDates) {
                    foreach ($allDates as $dates) {
                        if (($start >= $dates['start'] && $start <= $dates['end'])
                            || ($end >= $dates['start'] && $end <= $dates['end'])
                        ) {
                            $regions[] = $re;
                        }
                    }
                }
                $regions = array_unique($regions);

                if (count($regions) === 1 || (count($regions) === 2 && in_array('null', $regions))) {
                    if ($regions[0] !== 'null') {
                        $s->departure()
                            ->geoTip(array_key_first($this->hotelRegion));
                        $s->arrival()
                            ->geoTip(array_key_first($this->hotelRegion));
                    }
                }
            }

            if (!empty($table[1]) && !empty($table[2])
                && preg_match("/\n *" . preg_quote($table[1]) . " *- *" . preg_quote($table[2]) . " {3,}(.+)/",
                    $classTable, $m)
            ) {
                $s->extra()
                    ->cabin($m[1]);
            }

            $table[0] = preg_replace("/^\s*open\s*$/i", '', $table[0] ?? '');

            if (!empty($table[0])) {
                if (preg_match("/^([A-Z]{2,}\D*)(\d+)$/", $table[0], $m)) {
                    $s->extra()
                        ->service($m[1])
                        ->number($m[2]);
                } else {
                    $s->extra()
                        ->number($table[0]);
                }
            } else {
                $s->extra()
                    ->noNumber();
            }
        }
    }

    private function parseTrain2(Email $email, $text): void
    {
        // examples: it-445806541.eml, it-650966993-amtrak.eml
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation();

        $segmentsText = $this->re("/^[ ]*Train Information\s*:\n+([ ]*Train\s*From\s*To\s*Depart.+Seats\n[\s\S]+?{$this->patterns['time']}[\s\S]*?)\n+[ ]*SUMMARY OF QUOTE$/m", $text);

        $tablePos = [0];

        if (preg_match("/^(((((.+ )From[ ]+)To[ ]+)Depart[ ]+)Arrive[ ]+)Seats\n/", $segmentsText, $matches)) {
            $tablePos[1] = mb_strlen($matches[5]);
            $tablePos[2] = mb_strlen($matches[4]);
            $tablePos[3] = mb_strlen($matches[3]);
            $tablePos[4] = mb_strlen($matches[2]);
            $tablePos[5] = mb_strlen($matches[1]);
        }

        $segments = $this->split("/(?:^|\n)(\s*(?:\d{2,4})?.*\s\d{2}\s+\d{2}.*\n\s+.*{$this->patterns['time']})/", "\n" . $segmentsText . "\n");

        foreach ($segments as $i => $segText) {
            $s = $t->addSegment();

            if (count($tablePos) > 2 && preg_match("/^(.{" . ((int) $tablePos[1] - 2) . "}[ ]{2}[[:upper:]][- [:upper:]]{3,20}[[:upper:]] )[[:alpha:]][^[:upper:]]{2}.{0,19} {$this->patterns['date']}/mu", $segText, $matches)) {
                /*
                    it-445806541.eml:
                    6109        PARIS GARE DE LYON Aix En Provence TGV        25 Mar 24        25 Mar 24        1st class
                */
                $tablePos2 = mb_strlen($matches[1]);

                if ($tablePos[2] > $tablePos2) {
                    $tablePos[2] = $tablePos2;
                }
            }

            if (preg_match("/^(((.{15,} ){$this->patterns['date']}[ ]+){$this->patterns['date']}[ ]*)/mu", $segText, $matches)
                || preg_match("/^(((.{15,} ){$this->patterns['time']}[ ]+){$this->patterns['time']}[ ]*)/m", $segText, $matches)
            ) {
                $tablePos[3] = mb_strlen($matches[3]);
                $tablePos[4] = mb_strlen($matches[2]);
                $tablePos[5] = mb_strlen($matches[1]);
            }

            $table = $this->createTable($segText, $tablePos, true);

            if (count($table) !== 6) {
                $this->logger->debug('Wrong train segment-' . $i . '!');

                continue;
            }

            $table = array_map('trim', $table);

            if (empty($table[0])) {
                $s->extra()->noNumber();
            } else {
                $s->extra()->number($table[0]);
            }

            $s->departure()->name(preg_replace('/\s+/', ' ', $table[1]));
            $s->arrival()->name(preg_replace('/\s+/', ' ', $table[2]));

            $dateDep = strtotime(preg_replace('/\s+/', ' ', $table[3]));
            $dateArr = strtotime(preg_replace('/\s+/', ' ', $table[4]));

            $s->departure()->date($dateDep);

            if ($dateDep && $dateArr && $dateDep === $dateArr) {
                $s->arrival()->noDate();
            } else {
                $s->arrival()->date($dateArr);
            }

            $s->extra()->cabin(preg_replace('/\s+/', ' ', $table[5]));
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function strposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = strpos($text, $n);

                if ($pos !== false) {
                    return $pos;
                }
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
        }

        return false;
    }

    // additional methods

    private function createTable(?string $text, $pos = [], $correct = false): array
    {
        $ds = 5;
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($correct == true) {
                    if ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p + 1, 1))))) {
                        $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                        if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                            $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                            $pos[$k] = $p - strlen($m[2]) - 1;

                            continue;
                        } else {
                            $str = mb_substr($row, $p, $ds, 'UTF-8');

                            if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
                                $pos[$k] = $p + strlen($m[1]) + 1;

                                continue;
                            } elseif (preg_match("#^\s+(.*)#", $str, $m)) {
                                $cols[$k][] = trim($m[1] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                                $row = mb_substr($row, 0, $p, 'UTF-8');

                                continue;
                            } elseif (!empty($str)) {
                                $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                                if (preg_match("#(\S*)\s+(\S*)$#", $str, $m)) {
                                    $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                                    $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');
                                    $pos[$k] = $p - strlen($m[2]) - 1;

                                    continue;
                                }
                            }
                        }
                    }
                }

                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 16 March, 2024    |    Monday, 15 April, 2024
            '/^\s*(?:[-[:alpha:]]+[, ]+)?(\d{1,2})[, ]*([[:alpha:]]+)[, ]*(\d{4})\s*$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $text);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'US$'=> 'USD',
            'CA$'=> 'CAD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
