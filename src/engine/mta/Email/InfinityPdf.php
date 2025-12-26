<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Schema\Parser\Email\Email;

//similar format AsiaEscapePdf.php, QantasHolidaysPdf.php
class InfinityPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-16672394.eml, mta/it-19462719.eml, mta/it-19772846.eml, mta/it-215701795.eml, mta/it-22902427.eml, mta/it-27324223.eml, mta/it-28819009.eml, mta/it-33287153.eml, mta/it-33514880.eml, mta/it-44259177.eml";

    public $reFrom = ["MTA Travel", "mtatravel.com.au", "infinityholidays.com.au"];

    public $reBody = [
        'en'  => ['PREPARED', 'YOUR TRAVEL ITINERARY'],
        'en2' => ['PREPARED', 'YOUR HOLIDAY ITINERARY'],
        'en3' => ['PREPARED', 'YOUR VACATION ITINERARY'],
    ];
    public $reSubject = [
        '#[A-Z\d\/]{6,} Booking itinerary: .+? for \w+\/\w+ \w+#',
        '#[A-Z\d\/]{6,} Vouchers and itinerary for \w+\/\w+ \w+#',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".+\.pdf"; //".+_itn\.pdf";
    public static $dict = [
        'en' => [
            'YOUR TRAVEL ITINERARY'       => ['YOUR TRAVEL ITINERARY', 'YOUR HOLIDAY ITINERARY', 'YOUR VACATION ITINERARY'],
            'INFINITY HOLIDAYS REFERENCE' => ['INFINITY HOLIDAYS REFERENCE', 'INFINITY CRUISE REFERENCE', 'Booking'],
            //for Transfer - not for Rental
            'P/UP' => ['P/UP', 'P/U'],
            'DROP' => ['DROP', 'D/O'],
        ],
    ];
    private $pax;
    private $dateRes;
    private $onlyTransfers;
    private $addressesHotels = [];
    private $phonesHotels = [];
    private $keywords = [
        'hertz' => [
            'HERTZ AUSTRALIA',
        ],
        'alamo' => [
            'ALAMO - USA',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }

                    if (!$this->parseEmail($text, $email)) {
                        return null;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Infinity Holidays') !== false || stripos($text, 'GOGO Vacations') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 4; //transfer | rental | hotel | flight
        $cnt = count(self::$dict) * $types;

        return $cnt;
    }

    private function parseEmail($textPDF, Email $email)
    {
        if (!empty($str = strstr($textPDF, $this->t('HELPFUL HINTS'), true))) {
            $textPDF = $str;
        } else {
            $isformat = false;

            if (is_array($this->t('INFINITY HOLIDAYS REFERENCE'))) {
                foreach ($this->t('INFINITY HOLIDAYS REFERENCE') as $value) {
                    if (strpos($textPDF, $value) !== false) {
                        $isformat = true;

                        break;
                    }
                }
            } else {
                if (strpos($textPDF, $this->t('INFINITY HOLIDAYS REFERENCE')) !== false) {
                    $isformat = true;
                }
            }

            if ($isformat == false) {
                $this->logger->debug('other format');

                return false;
            }
        }
        $node = $this->re("#{$this->opt($this->t('YOUR TRAVEL ITINERARY'))}\s+(.+?)\s+DEPARTING#s", $textPDF);

        if (preg_match_all("#^ *(\w.+?)(?: {2,}|$)#m", $node, $m)) {
            $this->pax = $m[1];
        }
        $this->dateRes = strtotime($this->re("#Date of issue:\s+(.+)#", $textPDF));
        $email->ota()
            ->confirmation($this->re("#{$this->opt($this->t('INFINITY HOLIDAYS REFERENCE'))}[: ]+([\w\/\-]{5,})#",
                $textPDF),
                $this->re("#({$this->opt($this->t('INFINITY HOLIDAYS REFERENCE'))})[: ]+[\w\/\-]{5,}#", $textPDF));

        $textPDF = preg_replace("#\n *(?:http:\/\/www\.infinityholidays\.com\.au\s+)?Reference Number[^\n]+\n[^\n]+\n[^\n]+?Date of Issue[^\n]+#s",
            '', $textPDF);
//        $textPDF = preg_replace("#\n *Reference Number[^\n]+\n[^\n]+\n[^\n]+?Date of Issue[^\n]+#s",
//            '', $textPDF);

        //grouping segments & added date on it
        $arrs = $this->splitter("#^( *\w+ \w+ \d+ \d{4} *)$#m", "ControlStr\n" . $textPDF);
        $arr = [];
        $segs = [];

        foreach ($arrs as $root) {
            $dateStr = $this->re("#(.+)#", $root);
            $newRoot = preg_replace("#(.+)(\n+)(^ +(?:\w[A-z ]+ +(?:Confirmed|Confirmation.+)|Flight\s+Departing|Agent Ticketed Flight\s+Departing))#m",
                '$1' . "\n{$dateStr}\n" . '$3',
                $root);
            $newRoot = preg_replace("#({$dateStr})(\n)($dateStr)#", '$1', $newRoot);

            $arr = array_merge($arr, $this->splitter("#^( *\w+ \w+ \d+ \d{4} *)$#m", "ControlStr\n" . $newRoot));
        }

        for ($i = 0; $i < count($arr); $i++) {
            if (preg_match("#^ +(?:Flight +Confirmed|Flight\s+Departing|Agent Ticketed Flight\s+Departing)#m", $arr[$i])
                && (strpos($arr[$i], 'Arriving') === false)
                && isset($arr[$i + 1])
                && (strpos($arr[$i + 1], 'Arriving') !== false)
            ) {
                $segs[] = $arr[$i] . $arr[$i + 1];
                $i++;
            } else {
                $segs[] = $arr[$i];
            }
        }

        //check if only Transfers
        $this->onlyTransfers = true;

        foreach ($segs as $root) {
            if (preg_match("#^ *(.+?) +Confirmed#m", $root, $m) && $m[1] !== 'Transfer') {
                $this->onlyTransfers = false;

                break;
            }
        }

        //main parsing
        foreach ($segs as $i => $root) {
            //accommodation, flights, transfers, car hire  and tours
            if (preg_match("#^ *Accommodation +(?:Confirmed|Confirmation)#m", $root)) {
                if (!$this->parseHotel($root, $email)) {
                    return false;
                }

                continue;
            }

            if (preg_match("#^ *Transfer +Confirmed#m", $root)) {
                if (!$this->parseTransfer($root, $email)) {
                    return false;
                }

                continue;
            }

            if (preg_match("#^ *(?:Flight +Confirmed|Flight\s+Departing|Agent Ticketed Flight\s+Departing)#m", $root)) {
                if (!$this->parseFlight($root, $email)) {
                    return false;
                }

                continue;
            }

            if (preg_match("#^ *Car Rental +(?:Confirmed|Confirmation)#m", $root)) {
                if (!$this->parseRental($root, $email)) {
                    return false;
                }

                continue;
            }

            if (preg_match("#^ *(?:Tickets|Tour) +Confirmed#m", $root)) {
                $this->logger->info("skip events (tickets|tour): need example with time start or location");

                continue;
            }

            if (preg_match("#^ *Own arrangements\s+(?:from .+? to .+|in .+? for .+)#m", $root)) {
                $this->logger->info("skip Own arrangements");

                continue;
            }

            if (preg_match("#^(.*\n){1,3} *TripSecure\b.*\s+TRIP SECURE CREDIT for \d+#", $root)) {
                $this->logger->info("skip Trip Secure");

                continue;
            }
            $this->logger->debug("other format rootSegment: {$i}");
//            $this->logger->debug('$root = '.print_r( $root,true));
            return false;
        }

        return true;
    }

    private function parseRental($textPDF, Email $email)
    {
        $info = strstr($textPDF, 'Pick up from', true);
        $details = strstr($textPDF, 'Vehicle details:');

        if (empty($info) || empty($details)) {
            $this->logger->debug("other format rental");

            return false;
        }
        $node = strstr($textPDF, 'Vehicle details:', true);
        $table = $this->re("#^([ ]*Pick up from.+)#ms", $node);

        $arr = $this->splitCols($table, $this->colsPos($table));

        if (count($arr) !== 2) {
            $this->logger->debug("other format rental (table pickup/dropoff)");

            return false;
        }
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $r = $email->add()->rental();
        $r->general()
            ->status('Confirmed')
            ->date($this->dateRes);

        if (preg_match("#Confirmation No: ([\w\-]+)#", $textPDF, $m)) {
            $r->general()
                ->confirmation($m[1]);
        } else {
            $r->general()
                ->noConfirmation();
        }

        if ($leadPax = trim($this->re("#Lead Passenger: (.+)#", $details))) {
            $r->general()
                ->traveller($leadPax);
        } else {
            $r->general()
                ->travellers($this->pax);
        }

        $r->extra()->company(trim($this->re("#(.+)\s*$#", $info)));

        if (!empty($keyword = $r->getCompany())) {
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->extra()->company($keyword);
            }
        }

        $model = $this->re("#Car Rental.+\s+(.+?)\s*(?:for\s+\d+\s+days?|\n)#", $info);

        if (preg_match("#Vehicle details:\s+([^\n]+)\s+(.+?)\n[ ]*\n#s", $details, $m)) {
            if (empty($model)) {
                $model = trim($m[1], ' •');
            }
            $type = implode(", ", array_filter(array_map(function ($s) {
                return trim($s, ' •');
            }, explode("\n", $m[2]))));
            $r->car()
                ->model($model)
                ->type($type);
        }

        if (preg_match("#Pick up from\s+(.+?)\s*(?:Tel:\s*([^\n]+)|$)#s", $arr[0], $m)) {
            $r->pickup()
                ->location(preg_replace("#\s+#", ' ', $m[1]));

            if (isset($m[2]) && !empty($m[2])) {
                $r->pickup()->phone($m[2]);
            }
        }

        if (preg_match("#Drop off to\s+(.+?)\s*(?:Tel:\s*([^\n]+)|$)#s", $arr[1], $m)) {
            $r->dropoff()
                ->location(preg_replace("#\s+#", ' ', $m[1]));

            if (isset($m[2]) && !empty($m[2])) {
                $r->dropoff()->phone($m[2]);
            }
        }

        if (preg_match("#Vehicle to be picked up at (\d+:\d+(?:(?i)[ ]*[ap]m)?) on (\w+ \d+) in#", $info, $m)) {
            $datePU = strtotime($m[2], $date);
            $r->pickup()->date(strtotime($m[1], $datePU));
        }

        if (preg_match("#Vehicle to be returned at (\d+:\d+(?:(?i)[ ]*[ap]m)?) on (\w+ \d+) in#", $info, $m)) {
            $dateDO = strtotime($m[2], $date);

            if (isset($datePU) && $dateDO < $datePU) {
                $dateDO = strtotime("+1 year", $dateDO);
            }
            $r->dropoff()->date(strtotime($m[1], $dateDO));
        }

        return true;
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function parseHotel($textPDF, Email $email)
    {
        $arr = $this->splitter("#(\n\n)#", $textPDF);
        $arr = array_values(array_filter($arr, function ($s) {
            return !empty(trim($s));
        }));

        if (count($arr) < 3) {
            $this->logger->debug("other format hotel");

            return false;
        }

        if (preg_match("#^\s*\*\*.+\*\*\s*$#s", $arr[1])) {
            unset($arr[1]);
            $arr = array_values($arr);
        }

        if (stripos(trim($arr[1]), 'ETA ') === 0 || stripos(trim($arr[1]), 'INCLUDES ') === 0 || strpos(trim($arr[1]), 'KING BED') > 0) {
            unset($arr[1]);
            $arr = array_values($arr);
        }

        if (stripos(trim($arr[3] ?? ''), 'Booking Details') === 0 && preg_match("/^\s*.+:/", $arr[1])) {
            unset($arr[1]);
            $arr = array_values($arr);
        }

        if (trim($arr[2]) == 'Booking Details') {
            $arr[3] = $arr[2] . "\n". $arr[3];
            unset($arr[2]);
            $arr = array_values($arr);
        }

        if (stripos(trim($arr[2]), 'Booking Details') !== 0) {
            $h = $email->add()->hotel();
            $this->logger->debug("error in hotel format");

            return false;
        }

        $h = $email->add()->hotel();
        $h->general()
            ->status('Confirmed')
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("#Confirmation No: ([\w\-\/]+)#", $textPDF, $m)) {
            $h->general()
                ->confirmation($m[1]);
        } else {
            $h->general()
                ->noConfirmation();
        }

        if (preg_match("#(\d+) Adults?(?:, (\d+) Child.*?)? room#i", $arr[0], $m)) {
            $h->booked()->guests($m[1]);

            if (isset($m[2])) {
                $h->booked()->kids($m[2]);
            }
        }

        if (preg_match("#^\s*(\S[^\n]+)\n\s*(\S.+?)\s*(?:Tel: +([\d\+\-\(\) ]+)|$)#s", trim($arr[1]), $m)) {
            $h->hotel()
                ->name($m[1])
                ->address(trim(preg_replace("#\s+#", ' ', $m[2])));

            if (!isset($this->addressesHotels[$m[1]])) {
                $this->addressesHotels[$m[1]] = trim(preg_replace("#\s+#", ' ', $m[2]));
            }

            if (isset($m[3]) && !empty($m[3])) {
                $h->hotel()
                    ->phone(trim($m[3]));

                if (!isset($this->phonesHotels[$m[1]])) {
                    $this->phonesHotels[$m[1]] = trim($m[3]);
                }
            }
        } elseif (preg_match("#^([^\n]+)$#", trim($arr[1]), $m)) {
            $h->hotel()
                ->name($m[1]);

            if (isset($this->addressesHotels[$m[1]])) {
                $h->hotel()
                    ->address($this->addressesHotels[$m[1]]);
            } else {
                $h->hotel()
                    ->noAddress();
            }

            if (isset($this->phonesHotels[$m[1]])) {
                $h->hotel()
                    ->phone($this->phonesHotels[$m[1]]);
            }
        }

        if (preg_match("#^\s*([A-Z][A-Z\d\W\s]+)\n([\s\S]+?)\n\s*(:?In a|\d x )#", trim($arr[0]), $m)
                || preg_match("#^\s*(.+(?:\n\s*\W.+)?)\n([\s\S]+?)\n\s*(?:In a|\d x )#", trim($arr[0]), $m)) {
            $h->addRoom()
                ->setType((trim(preg_replace("#\s+#", ' ', $m[1]))))
                ->setDescription(trim(preg_replace("#\s+#", ' ', $m[2])));
        }

        $checkIn = trim($this->re("#{$this->opt($this->t('Check-In'))}: +(.+?(?:\d+:\d+)?(?:\s*[AaPp][Mm])?)\s*(?:hrs|\n)#", $arr[2]));
        $checkOut = $this->re("#{$this->opt($this->t('Check-Out'))}: +(.+?(?:\d+:\d+)?(?:\s*[AaPp][Mm])?)\s*(?:hrs|$)#", $arr[2]);

        if (empty($checkIn) && empty($checkOut)) {
            $checkIn = trim($this->re("#{$this->opt($this->t('Check-In'))}: +(.+?(?:\d+:\d+)?(?:\s*[AaPp][Mm])?)\s*(?:hrs|\n)#", $arr[3]));
            $checkOut = $this->re("#{$this->opt($this->t('Check-Out'))}: +(.+?(?:\d+:\d+)?(?:\s*[AaPp][Mm])?)\s*(?:hrs|$)#", $arr[3]);
        }
        $h->booked()
            ->checkIn(strtotime($checkIn))
            ->checkOut(strtotime($checkOut)); //Check-Out: July 28 2018 12:00 hrs (midday)

        return true;
    }

    private function parseTransfer($textPDF, Email $email)
    {
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $node = $this->re("#Transfer.+\s+[^\n]+\n(.+)#", $textPDF);
        $points = array_map("trim", explode('-', $node));

        if (count($points) !== 2) {
            if (preg_match("#({$this->opt($this->t('P/UP'))}: .+) ({$this->opt($this->t('DROP'))}: .+)#",
                $node, $m)) {
                $points[0] = $m[1];
                $points[1] = $m[2];
            } else {
                $this->logger->debug("skip transfer - other format transfer");

                return true;
            }
        }
        $dep = trim(str_replace($this->t('P/UP'), '', $points[0]), " :");
        $arr = trim(str_replace($this->t('DROP'), '', $points[1]), " :");

        if (!$this->onlyTransfers) {
            if (preg_match("#^[A-Z\d]{2,}\d+ ETA#", $points[0]) || preg_match("#^[A-Z\d]{2,}\d+ ETD#", $points[1])) {
                $this->logger->info("skip transfer with no airport info");

                return true;
            }

            if (preg_match("#^ARRIVE \w{2}\d+ \d+:\d+(?:\s*[ap]m)?$#i", $points[0])) {
                $this->logger->info("skip transfer with no airport info");

                return true;
            }

            if (!preg_match("#\d{2}[:\.]?\d{2}#", $dep) && !preg_match("#\d{2}[:\.]?\d{2}#", $arr)) {
                $this->logger->info("skip transfer with no times");

                return true;
            }

            if (preg_match("#^(\d{2})[:\.]?(\d{2}(?:[ap]m)?)\s*BOAT TO#i",
                    $dep) || preg_match("#^(\d{2})[:\.]?(\d{2}(?:[ap]m)?)\s*BOAT TO#i", $arr)
            ) {
                $this->logger->info("skip transfer - not car, boat");

                return true;
            }
        }//otherwise parse -> to junk

        $t = $email->add()->transfer();
        $t->general()
            ->noConfirmation()
            ->status('Confirmed')
            ->travellers($this->pax)
            ->date($this->dateRes);
        $s = $t->addSegment();

        if (preg_match("#^(.+)\s*(\d{2})[:\.]?(\d{2})$#", $dep, $m)) {
            $s->departure()
                ->name($m[1])
                ->date(strtotime($m[2] . ':' . $m[3], $date));
        } elseif (preg_match("#^(\d{2})[:\.]?(\d{2}(?:[ap]m)?)\s*(.+)$#i", $dep, $m)) {
            $s->departure()
                ->name($m[3])
                ->date(strtotime($this->correctTimeString($m[1] . ':' . $m[2]), $date));
        } else {
            $s->departure()
                ->name($dep)
                ->noDate();
        }

        if (preg_match("#^(.+)\s*(\d{2})[:\.]?(\d{2})$#", $arr, $m)) {
            $s->arrival()
                ->name($m[1])
                ->date(strtotime($m[2] . ':' . $m[3], $date));
        } elseif (preg_match("#^(\d{2})[:\.]?(\d{2}(?:[ap]m)?)\s*(.+)$#i", $arr, $m)) {
            $s->arrival()
                ->name($m[3])
                ->date(strtotime($this->correctTimeString($m[1] . ':' . $m[2]), $date));
        } else {
            $s->arrival()
                ->name($arr)
                ->noDate();
        }

        return true;
    }

    private function parseFlight($textPDF, Email $email)
    {
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $f = $email->add()->flight();
        $confNo = $this->re("#Reservation Number: +.*?\b([A-Z\d]{5,})\b *\n#", $textPDF);

        if (empty($confNo)) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confNo);
        }

        if (strpos($textPDF, 'Confirmed') !== false) {
            $f->general()
                ->status('Confirmed');
        }
        $f->general()
            ->travellers($this->pax)
            ->date($this->dateRes);
        $s = $f->addSegment();

        if (preg_match("#Departing +Arriving\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s+Departs\s+(.+)\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s+Arrives\s(.+)#",
            $textPDF, $m)) {
            $s->departure()
                ->name($m[2])
                ->noCode()
                ->date(strtotime($m[1], $date));
            $s->arrival()
                ->name($m[4])
                ->noCode()
                ->date(strtotime($m[3], $date));
        } else {
            if (preg_match("#Departing\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s+Departs\s+(.+)#", $textPDF, $m)) {
                $s->departure()
                    ->name($m[2])
                    ->noCode()
                    ->date(strtotime($m[1], $date));
            }
            $dateArr = strtotime($this->re("#(.+)\s+Flight\s+Arriving#", $textPDF));

            if (preg_match("#Arriving\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s+Arrives\s+(.+)#", $textPDF, $m)) {
                $s->arrival()
                    ->name($m[2])
                    ->noCode()
                    ->date(strtotime($m[1], $dateArr));
            }
        }

        if (preg_match("#Departure Terminal:[ ]+([\w ]+?)(?:[ ]{3,}|\n|Arrival Terminal|$)#", $textPDF, $m)) {
            $s->departure()->terminal(trim($m[1]));
        }

        if (preg_match("#Arrival Terminal:[ ]+([\w ]+?)(?:[ ]{3,}|\n|$)#", $textPDF, $m)) {
            $s->arrival()->terminal(trim($m[1]));
        }

        if (preg_match("#.+ flight ([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[ ]*(.*)#", $textPDF, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);

            if (!empty($m[3])) {
                if (preg_match("#^([A-Z]{1,2}) CLASS (ECONOMY)#", $m[3], $mm)) {
                    $s->extra()
                        ->bookingCode($mm[1])
                        ->cabin($mm[2]);
                } elseif (preg_match("#^([A-Z]{1,2})$#", $m[3], $mm)) {
                    $s->extra()->bookingCode($mm[1]);
                } else {
                    $s->extra()->cabin($this->re("#^(\w+)#", $m[3]));
                }
            }
        }

        if (preg_match("#\n[ ]*Booking Class:[ ]+([A-Z]{1,2})\s*(?:\n|$)#", $textPDF, $m)) {
            $s->extra()->bookingCode($m[1]);
        }

        if (preg_match("#\s+REQ SEATS:[ ]+([\dA-Z]+)\s*(?:\n|$)#", $textPDF, $m)) {
            if (count($this->pax) == 1 && preg_match("/^\d{1,3}[A-Z]$/", $m[1])) {
                $s->extra()
                    ->seat($m[1]);
            } elseif (count($this->pax) == 2 && preg_match("/^(\d{1,3})([A-Z]{2})$/", $m[1], $mat)) {
                $seats = str_split($mat[2]);
                $seats = preg_replace("/(.+)/", $mat[1] . "$1", $seats);
                $s->extra()
                    ->seats($seats);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function correctTimeString($time)
    {
        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }

    private function rowColsPos($row)
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

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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
}
