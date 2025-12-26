<?php

namespace AwardWallet\Engine\princess\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parser mta/POCruisesPdf (in favor of princess/Itinerary)

// parsers with similar formats: royalcaribbean/It2, royalcaribbean/AgentGuestBooking, celebritycruises/InvoiceAgentGuestPdf

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "princess/it-1.eml, princess/it-15982072.eml, princess/it-2.eml, princess/it-22214769.eml, princess/it-23022650.eml, princess/it-23110582.eml, princess/it-30320094.eml, princess/it-3390612.eml, princess/it-55127340.eml, princess/it-712884842.eml, princess/it-713891663.eml, princess/it-725622758.eml, princess/it-875748661.eml";

    public $reFrom = ["reservations@princesscruises.com"];
    public $reBody = [
        'en'  => ['Deck Group', 'BOOKING'],
        'en2' => ['Ship / Registry', 'Itinerary Change'],
        'en3' => ['Ship / Registry', 'BOOKING CONFIRMATION'],
        'en4' => ['Ship / Registry', 'FINAL PAYMENT NOTICE'],
        'en5' => ['Ship / Registry', 'IMPORTANT NOTICES'],
        'en6' => ['Voyage', 'Ship'],
        'en7' => ['Ship / Registry', 'Cancellation Notification'],
    ];
    public $reSubject = [
        'Booking Confirmation (Travel Agent Copy)',
        'Booking Confirmation (Passenger Copy)',
        'Deposit Confirmation (Travel Agent Copy)',
        'Deposit Confirmation (Passenger Copy)',
        'Final Payment Notice (Travel Agent Copy)',
        'Final Payment Notice (Passenger Copy)',
        'Itinerary Change',
        ' - Cancellation Notification',
    ];
    public $lang = '';

    public $subject = '';
    public $pdfNamePattern_1 = "(?:Booking[\s\-]*Confirmation|Deposit[\s\-]*Confirmation|Final[\s\-]*Payment[\s\-]*Notice|CancellationNotiсe)[\s\-]*[A-Z\d]+\.pdf";
    public $pdfNamePattern_2 = "(?:Itinerary[\s\-]*Change?|CancellationNotiсe)[\s\-]*[A-Z\d]+\.pdf";
    public static $dict = [
        'en' => [
            'cancelledTextHtml'           => ['Cancellation Notification', 'REFERENCED BOOKING HAS BEEN CANCELLED'],
            'Taxes, Fees & Port Expenses' => [
                'Taxes, Fees & Port Expenses',
                'NCF',
                "Incl'd Gov't Taxes & Fees",
                "Req'd Cruise Fees & Expen",
                "Included Air Fees:",
                "Commission (Standard)",
                "Commission (Override)",
                "Vacation Protection",
                "Miscellaneous",
                "Mods",
                "Gov't Taxes & Fees",
                "Commission GST NZ",
            ],
        ],
    ];
    private $pdfNamePattern_11 = '(?:[\d\-]+\s+[A-Z\s]+)[\s\-]*[A-Z\d\-]+\.pdf';

    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $NBSP = chr(194) . chr(160);
        $this->http->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($this->http->Response['body'])));

        $this->subject = $parser->getSubject();
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        $type = '';
        $flagNotInBody = false;

        // Detecting Language
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language in html-body!");
            $flagNotInBody = true;
        }

        if (!$flagNotInBody) {
            // it-725622758.eml
            if ($this->http->FindSingleNode("(//text()[{$this->contains($this->t('cancelledTextHtml'))}])[1]")
                || $this->http->XPath->query("//text()[normalize-space()='Gifts and Services Summary']")->length > 0
                || $this->http->XPath->query("//node()[{$this->eq(['Shore Excursion Payment Due', 'Shore Excursion Summary'])}]")->length > 0
            ) {
                // short format
                $this->parseEmail_3($email);
                $type = 'Html3';
            } else {
                if ($this->parseEmail_1($email)) {
                    $type = 'Html1';
                } elseif ($this->parseEmail_2($email)) {
                    $type = 'Html2';
                } elseif ($this->http->XPath->query("//*[contains(text(), 'BOOKING') and contains(text(), 'ITINERARY')]")->length == 0
                    && $this->http->XPath->query("//tr[*[normalize-space() = 'Description'] and *[normalize-space() = 'Start'] and *[normalize-space() = 'End']]")->length == 0
                    && $this->http->XPath->query("//*[normalize-space() = 'HOTEL INFORMATION']")->length > 0
                ) {
                    $this->parseEmail_4($email);
                    $type = 'Html4';
                } else {
                    $flagNotInBody = true;
                }
            }
        }

        if ($flagNotInBody) {
            $type = null;
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern_1);

            if (0 === count($pdfs)) {
                $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern_11);
            }

            if (isset($pdfs) && count($pdfs) > 0) {
                $type = 'Pdf1';
            } else {
                $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern_2);

                if (isset($pdfs) && count($pdfs) > 0) {
                    $type = 'Pdf2';
                }
            }
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            foreach ($pdfs as $pdf) {
                if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    // Detecting Provider
                    $this->assignProvider(null, $textPdf);

                    // Detecting Language
                    if (!$this->assignLang($textPdf)) {
                        $this->logger->debug("Can't determine a language in pdf-attach!");

                        continue;
                    }

                    if (empty($type)) {
                        if (strpos($textPdf, 'BOOKING ITINERARY')) {
                            $type = 'Pdf1';
                        }
                    }

                    if ($type === 'Pdf2' && preg_match("#Date +Description.+\n\s*[[:alpha:]]{3} \d{2} {2,}#", $textPdf)) {
                        $type = 'Pdf1';
                    }

                    switch ($type) {
                        case 'Pdf1':
                            // examples: it-23110582.eml
                            $this->parseEmailPdf_1($textPdf, $email);
//                            $this->logger->alert('pdf1');
                            break;

                        case 'Pdf2':
                            $this->parseEmailPdf_2($textPdf, $email);
//                            $this->logger->alert('pdf2');
                            break;
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        if (!$this->providerCode) {
            $this->providerCode = 'princess';
        }

        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignProvider($parser->getHeaders()) && $this->assignLang()) {
            return true;
        }
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignProvider(null, $textPdf) && $this->assignLang($textPdf)) {
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
                    if (stripos($headers["subject"], $reSubject) !== false) {
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
        $cntFormats = 4; // 2 formats by body + 2 formats by attach
        $cnt = $cntFormats * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return ['princess', 'carnival', 'pocruises'];
    }

    private function parseEmailPdf_1($textPDF, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $r = $email->add()->cruise();

        $textPDF = preg_replace("#[^\n]+Page +\d+ +of +\d+.+?^ {0,15}BOOKING [^\n]+#sm", '',
            $textPDF);

        $textInfo = strstr($textPDF, 'PASSENGERS', true);

        if (!$textInfo) {
            $textInfo = strstr($textPDF, 'GUESTS', true);
        }

        $r->general()
            ->confirmation($this->re("#^\s*(?:BOOKING CONFIRMATION|FINAL PAYMENT NOTICE|UPGRADE NOTICE|DEPOSIT CONFIRMATION|Itinerary Change).*\s+BOOKING\s+(\w+)#", $textInfo))
            ->date(strtotime($this->re("#^\s*(?:BOOKING CONFIRMATION|FINAL PAYMENT NOTICE|UPGRADE NOTICE|DEPOSIT CONFIRMATION|Itinerary Change).*\s+BOOKING\s+\w+.*[ ]{2,}(.+?)\n#", $textInfo)));

        $textPrice = $this->findСutSection($textPDF, 'PASSENGERS', 'BOOKING ITINERARY');

        if (!$textPrice) {
            $textPrice = $this->findСutSection($textPDF, 'GUESTS', 'BOOKING ITINERARY');
        }

        $sum = $this->amount($this->re("#^ *{$this->opt($this->t('Total Fare'))}[\s:]+.+ +(\d[\d\,\. ]+?) *\n#m", $textPrice));
        $cur = $this->currency($this->re("/^[ ]*All amounts are quoted in\s*(.+?)\.?(?:[ ]{2}|[ ]*$)/m", $textPrice));

        if (!empty($sum) && !empty($cur)) {
            $r->price()
                ->total($sum)
                ->currency($cur);

            foreach (self::$dict[$this->lang]['Taxes, Fees & Port Expenses'] as $feeName) {
                $fee = $this->re("/\s+{$this->opt($this->t($feeName))}.+[ ]{2,}([\d\.\,\']+)\n/", $textPrice);

                if ($fee !== null) {
                    $r->price()
                        ->fee($feeName, PriceHelper::parse($fee, $cur));
                }
            }
        }

        $pax = [];
        $node = $this->re("#^ *Name: +(.+?)(?:[ ]{3,}Totals|$)#m", $textPrice);

        if (!empty($node)) {
            $pax = array_filter(preg_split("#\s{3,}#", trim($node)));
        } else {
            $last = array_filter(preg_split("#\s{3,}#", trim($this->re("#^ *Last Name: +(.+?)(?:[ ]{3,}Totals|$)#m", $textPrice))));
            $first = array_filter(preg_split("#\s{3,}#", trim($this->re("#^ *First\/Middle Name: +(.+?)(?:[ ]{3,}Totals|$)#m", $textPrice))));

            if (count($last) !== count($first)) {
                // Last Name:                         Mrs Ware             Mr Ware                                                                                                Totals
                // First/Middle Name:                 Karen Lee Rodney Arthur James

                $lastText = $this->re("#^( *Last Name: +.+?)(?:[ ]{3,}Totals|$)#m", $textPrice);

                if (preg_match("/^( *Last Name:)/", $lastText, $m)) {
                    $lastText = str_replace($m[1], str_pad('', strlen($m[1]), ' '), $lastText);
                }
                $firstText = $this->re("#^( *First\/Middle Name: +.+?)(?:[ ]{3,}Totals|$)#m", $textPrice);

                if (preg_match("/^( *First\/Middle Name:)/", $firstText, $m)) {
                    $firstText = str_replace($m[1], str_pad('', strlen($m[1]), ' '), $firstText);
                }
                $last = preg_split("#\s{3,}#", $lastText, null, PREG_SPLIT_OFFSET_CAPTURE);
                $first = preg_split("#\s{3,}#", $firstText, null, PREG_SPLIT_OFFSET_CAPTURE);
                $pos = [];

                $positionsArray = [];
                $wrongText = '';

                if (count($last) > count($first)) {
                    $positionsArray = $last;
                    $wrongText = $firstText;
                } elseif (count($last) > count($first)) {
                    $positionsArray = $first;
                    $wrongText = $lastText;
                }

                foreach ($positionsArray as $v) {
                    $pos[] = $v[1] + strlen($v[0]);
                }

                foreach ($pos as $pi => $p) {
                    for ($i = $p; $i < $p + 5; $i++) {
                        $sym = mb_substr($wrongText, $i, 1);

                        if ($sym == false || $sym == ' ') {
                            $pos[$pi] = $i;

                            break;
                        }
                    }
                }

                $last = array_filter(array_map('trim', $this->splitCols($lastText, $pos)));
                $first = array_filter(array_map('trim', $this->splitCols($firstText, $pos)));
            }

            if (count($last) == count($first)) {
                foreach ($last as $key => $value) {
                    $pax[] = $value . ' ' . $first[$key];
                }
            }
        }

        if (count($pax) > 0) {
            $r->general()->travellers($this->nicePax($pax));
        }

        $node = $this->re("#^ *Member Number: +(.+)#m", $textPrice);
        $arr = explode("|", preg_replace("#\s{3,}#", '|', $node));
        $acc = [];

        foreach ($arr as $name) {
            if ($name) {
                $acc[] = $name;
            }
        }
        $acc = array_unique($acc);

        if (count($acc) > 0) {
            if (count($pax) === count($acc)) {
                foreach ($acc as $key => $account) {
                    $r->program()
                        ->account($account, false, $this->nicePax($pax[$key]));
                }
            } else {
                $r->program()->accounts($acc, false);
            }
        }

        $date = $this->re("#Embarkation[ :]+[ ]*(.*?)\s*/#", $textInfo);
        $year = date('Y', strtotime($date));

        $r->details()
            ->ship($this->re("#Ship / Registry[ :]+[ ]*(.+?)(?: {3,}|\n)#", $textInfo))
            ->deck($this->re("#Deck Group[ :]+[ ]*(.+?)(?: {3,}|\n)#", $textInfo), false, true)
            ->room($this->re("#Stateroom / Cat[ :]+[ ]*(.*?)\s*/#", $textInfo));

        $node = $this->re("#Voyage / Dest[ :]+[ ]*(.+?)(?: {3,}|\n)#", $textInfo);

        if (empty($node)) {
            $node = $this->re("#Cruisetour[ :]+[ ]*(.+?)(?: {3,}|\n)#", $textInfo);
        }
        $list = preg_split('#\s+/\s*#', $node);

        if (isset($list[0])) {
            $r->details()->shipCode($list[0]);
        }

        if (isset($list[1])) {
            $r->details()->description($list[1]);
        }

        $textIt = $this->findСutSection($textPDF, 'BOOKING ITINERARY', 'NOTICES');

        if (empty($textIt)) {
            $textIt = $this->re("/BOOKING ITINERARY(.+)COMMENTS/su", $textPDF);
        }

        if (empty($textIt)) {
            $textIt = $this->re("/BOOKING ITINERARY(.+)\n *IMPORTANT NOTICE/su", $textPDF);
        }

        if (!empty($str = strstr($textIt, 'COMMENTS', true))) {
            $textIt = $str;
        }

        if (!empty($str = strstr($textIt, 'Passports are required', true))) {
            $textIt = $str;
        }

        $textIt = $this->re("#Date +Description[^\n]+\n(.+)#s", $textIt);
        $textIt = preg_replace("#\n[ ]*BOOKING ITINERARY\n#", "\n", $textIt);
        $textIts = preg_split("#\n[ ]*Date +Description[^\n]+\n*#", $textIt);
        $segments = ['', ''];

        foreach ($textIts as $key => $ti) {
            $node = $this->re("#^(.{20,} {4,})\w{3} \d{1,2}(?: .+|\n)#m", $ti);
            $pos = [0, strlen($node) - 1];

            $arr = $this->splitCols($ti, $pos);

            if (!empty($arr[0])) {
                $arr[0] = preg_replace("#^\s*\n([ ]*\S)#", '$1', $arr[0]);
                $arr[0] = preg_replace("#(\S[ ]*\n)\s*$#", '$1', $arr[0]);
                $segments[0] .= $arr[0] ?? '';
            }

            if (!empty($arr[1])) {
                $arr[1] = preg_replace("#^\s*\n([ ]*\S)#", '$1', $arr[1]);
                $arr[1] = preg_replace("#(\S[ ]*\n)\s*$#", '$1', $arr[1]);
                $segments[1] .= $arr[1] ?? '';
            }
        }
        $segmentsAll = $this->splitter("#^[ ]*(\w{3} \d+)#m", "ControlStr\n" . implode('', $segments));
        //$this->logger->debug('$segmentsAll = ' . print_r($segmentsAll, true));
        $start = true;
        $isCruise = false;
        $overnight = false;
        $hotels = [];

        foreach ($segmentsAll as $key => $sText) {
            if (preg_match("/^\w+\s+\d+\n\s+\D/", $sText)) {
                $sText = str_replace("\n", "", $sText);
            } elseif (preg_match("/\-\s*\n\s*\d+\s*Nights?/", $sText)) {
                $sText = preg_replace("/\n\s*/", " ", $sText);
            }

            $table = explode("|", preg_replace("# {3,}#", '|', $this->re("#(.+)#", $sText)));

            if (count($table) < 2) {
                continue;
            }

            $table[1] .= ' ' . $this->re("#[^\n]+\n(.+)#s", $sText);

            if (preg_match("/(?:Tour|Coach:|Rail:)/u", $table[1])) {
                continue;
            }

            if (count($table) <= 2) {
                if (preg_match("#(.+) - (\d{1,2})\s+Nights?\s*$#u", $table[1], $m)
                    && !preg_match("/ Accommodations$/", $m[1])
                ) {
                    $h = $email->add()->hotel();
                    $h->general()
                        ->noConfirmation()
                        ->travellers($pax);
                    $h->hotel()
                        ->name($m[1])
                        ->noAddress();
                    $h->booked()
                        ->checkIn(strtotime($table[0] . ' ' . $year))
                        ->checkOut((!empty($h->getCheckInDate()) ? strtotime("+" . $m[2] . "day", $h->getCheckInDate()) : false));
                    $hotels[] = ['checkout' => $h->getCheckOutDate(), 'name' => $h->getHotelName()];
                }

                continue;
            }

            if (trim($table[2]) == 'At Sea' && empty($table[3])) {
                continue;
            }

            $date = $table[0];

            if ($isCruise == false && preg_match("#^\s*Rail: (.+) To (.+?)\s*$#", $table[1], $m) && !empty($table[2]) && !empty($table[3])) {
                $t = $email->add()->train();
                $t->general()
                    ->noConfirmation()
                    ->travellers($pax);
                $s = $t->addSegment();
                $s->extra()->noNumber();
                $s->departure()
                    ->name($m[1])
                    ->date(strtotime($date . " $year, " . $table[2]));
                $s->arrival()
                    ->name($m[2])
                    ->date(strtotime($date . " $year, " . $table[3]));

                continue;
            } elseif ($isCruise == false && preg_match("#^\s*Transfer: (.+) To (.+?)\s*$#", $table[1], $m) && !empty($table[2]) && !empty($table[3])) {
                /* skip transfer: not enough data for searching address by google
                 $t = $email->add()->transfer();
                 $t->general()
                     ->noConfirmation()
                     ->travellers($pax);
                 $s = $t->addSegment();
                 if (in_array($m[1], ['Hotel', 'Lodge']) && !empty($hotels)) {
                     foreach ($hotels as $hotel) {
                         if (!empty($hotel['checkout']) && $hotel['checkout'] === strtotime($date . " " . $year)
                                 && stripos($hotel['name'], $m[1]) !== false) {
                             $s->departure()
                                 ->name($hotel['name']);
                         }
                     }
                 }
                 $s->departure()
                     ->name($s->getDepName()??$m[1])
                     ->date(strtotime($date . " $year, " . $table[2]));

                 if (in_array($m[2], ['Hotel', 'Lodge']) && $key < (count($segmentsAll)-1)) {
                     for ($i = 1; $i < 3; $i++) {
                         if (!isset($segmentsAll[$key+$i])) {
                             break;
                         }
                         $tableH = explode("|", preg_replace("# {3,}#", '|', $this->re("#(.+)#", $segmentsAll[$key+$i])));
                         $tableH[1] .= ' ' . $this->re("#[^\n]+\n(.+)#s", $segmentsAll[$key+$i]);
                         if (preg_match("#(.+) - (\d{1,2})\s+Nights?\s*$#", $tableH[1], $mat) && stripos($mat[1], $m[2]) !== false
                                 && $date === $tableH[0]) {
                             $s->arrival()
                                 ->name($mat[1]);
                             break;
                         }
                     }

                 }
                 $s->arrival()
                     ->name($s->getArrName()??$m[2])
                     ->date(strtotime($date . " $year, " . $table[3]));*/
                $this->logger->warning('skip transfer: not enough data for searching address by google');

                continue;
            } elseif ($isCruise == false && preg_match("#^\s*Coach: (.+) To (.+?)\s*$#", $table[1], $m) && !empty($table[2]) && !empty($table[3])) {
                /*skip transfer: not enough data for searching address by google
                $t = $email->add()->transfer();
                $t->general()
                    ->noConfirmation()
                    ->travellers($pax);
                $s = $t->addSegment();
                $s->departure()
                    ->name($m[1])
                    ->date(strtotime($date . " $year, " . $table[2]));
                $s->arrival()
                    ->name($m[2])
                    ->date(strtotime($date . " $year, " . $table[3]));*/
                $this->logger->warning('skip transfer: not enough data for searching address by google');

                continue;
            }

            $port = preg_replace("#\s+#", ' ', preg_replace(["#\s+Check.*#s", '/\s*Tender Required.*/s', '/\s*Overnight.*/s', '/\s*MENTS.*/s', '/\s*BOOKING ITINERARY.*/s', '/\s*\bate.*/s', '/\s+(?:Departs After|Water Shuttle|Wheelchair Access).*/'], ['', '', '', '', '', '', ''], $table[1]));

            if ($isCruise === true && $overnight === false) {
                $s = $r->addSegment();
                $s->setName($port);
            }

            if (!isset($table[3]) && !empty($table[2])) {
                $dt = strtotime($date . " $year, " . $table[2]);

                if ($start) {
                    $s = $r->addSegment();
                    $s->setName($port);
                    $s->setAboard($dt);
                    $start = false;
                    $isCruise = true;
                } elseif (preg_match("#\b(Overnight|Departs After Midnight)\b#i", $table[1])) {
                    $s->setAshore($dt);
                    $overnight = true;
                } elseif ($overnight === true) {
                    $s->setAboard($dt);
                    $overnight = false;
                } elseif ($isCruise === true) {
                    $s->setAshore($dt);
                    $isCruise = false;
                } else {
                    $s = $r->addSegment();
                    $this->logger->debug('check type of all segments');

                    return $email;
                }
            } elseif ($isCruise === true) {
                if ($dtAboard = strtotime($date . " $year, " . $table[3])) {
                    $s->setAboard($dtAboard);
                }

                if ($dtAshore = strtotime($date . " $year, " . $table[2])) {
                    $s->setAshore($dtAshore);
                }
            }
        }

        return true;
    }

    private function parseEmailPdf_2($textPDF, Email $email)
    {
        $this->logger->debug(__FUNCTION__);
        $r = $email->add()->cruise();

        $textPDF = preg_replace("#[^\n]+Page +\d+ +of +\d+.+?^ *BOOKING CONFIRMATION[^\n]+\s+BOOKING [^\n]+#sm", '',
            $textPDF);

        $textInfo = strstr($textPDF, 'IMPORTANT NOTICES', true);

        $r->general()
            ->noConfirmation()
            ->date(strtotime($this->re("# {5,}.+? {5,}([^\n]+)\s+[^\n]+{$this->opt($this->t('Voyage / Dest'))}#",
                $textInfo)));

        $date = $this->re("#Embarkation: *(.*?)\s*/#", $textInfo);
        $year = date('Y', strtotime($date));

        $r->details()
            ->ship($this->re("#Ship / Registry: +(.+?)(?: {3,}|\n)#", $textInfo))
            ->deck($this->re("#Deck Group: +(.+?)(?: {3,}|\n)#", $textInfo), false, true)
            ->room($this->re("#Stateroom / Cat: +(.*?)\s*/#", $textInfo), false, true);

        $node = $this->re("#Voyage / Dest *: +(.+?)(?: {3,}|\n)#", $textInfo);

        if (empty($node)) {
            $node = $this->re("#Cruisetour *: +(.+?)(?: {3,}|\n)#", $textInfo);
        }
        $list = preg_split('#\s+/\s*#', $node);

        if (isset($list[0])) {
            $r->details()
                ->shipCode($list[0]);
        }

        if (isset($list[1])) {
            $r->details()
                ->description($list[1]);
        }

        $textIt = $this->findСutSection($textPDF, 'Itinerary', 'IMPORTANT NOTICE:');
        $textIt = $this->re("#Date +Description[^\n]+\n(.+)#s", $textIt);
        $node = $this->re("# {4,}(\w{3} +\w{3} \d+)#", $textIt);
        $pos = [0, strpos($textIt, $node) - 1];
        $arr = $this->splitCols($textIt, $pos);
        $start = true;

        foreach ($arr as $text) {
            $mas = $this->splitter("#^(\w{3} +\w{3} \d+)#m", "ControlStr\n" . $text);

            foreach ($mas as $sText) {
                $str = $this->re("#(.+)#", $sText);
                $strExt = $this->re("#[^\n]+\n(.+)#s", $sText);
                $table = explode("|", preg_replace("# {4,}#", '|', $str));

                if (count($table) <= 3) {
                    continue;
                }
                $table[2] .= ' ' . $strExt;
                $s = $r->addSegment();

                $weeknum = WeekTranslate::number1(WeekTranslate::translate($table[0], $this->lang));
                $str = $table[1] . " " . $year;
                $dt = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
                $port = preg_replace("#\s+#", ' ', preg_replace("#\s+Check.*#", '', $table[2]));
                $s->setName($port);

                if (preg_match("#^\d+:\d+\s*[AP]$#", $table[3])) {
                    $table[3] .= 'M';
                }

                if (!isset($table[4])) {
                    if ($start) {
                        $s->setAboard(strtotime($table[3], $dt));
                        $start = false;
                    } else {
                        $s->setAshore(strtotime($table[3], $dt));
                    }
                } else {
                    if (preg_match("#^\d+:\d+\s*[AP]$#", $table[4])) {
                        $table[4] .= 'M';
                    }
                    $s->setAboard(strtotime($table[4], $dt));
                    $s->setAshore(strtotime($table[3], $dt));
                }
            }
        }

        return true;
    }

    private function parseEmail_1(Email $email)
    {
        $this->logger->debug(__FUNCTION__);
        $xpath = "//*[contains(text(), 'BOOKING') and contains(text(), 'ITINERARY')]/ancestor::table[2]/descendant::tr[1]/following::tr[1]/td//tr[not(.//*[position()=1 and contains(text(), 'Date')])]";
        $rows = $this->http->XPath->query($xpath);

        if ($rows->length === 0) {
            $this->logger->debug('other format: can\'t find segments in Html body (format 1)');

            return false;
        }

//        $this->logger->alert('email 1');

        $r = $email->add()->cruise();

        $r->general()
            ->confirmation($this->http->FindSingleNode("(//*[contains(text(), 'BOOKING') and not(contains(text(), 'CONFIRMATION')) and not(contains(text(), 'ITINERARY'))])[1]",
                null, true, "#^\s*BOOKING\s+([\d\w]+)#"))
            ->date(strtotime($this->http->FindSingleNode("(//*[contains(text(), 'BOOKING') and not(contains(text(), 'CONFIRMATION')) and not(contains(text(), 'ITINERARY'))])[1]/ancestor::td[1]/following-sibling::td[position()=last()]")));

        $sum = $this->amount($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Fare')]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][last()]",
            null, true, "#^\s*(\d[\d\,\. ]+)\s*$#"));
        $cur = $this->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'All amounts are quoted in')]/ancestor::tr[1]",
            null, true, "#All amounts are quoted in\s*(.+?)[\s\.]*$#"));

        if (!empty($sum) && !empty($cur)) {
            $email->price()
                ->total($sum)
                ->currency($cur);

            $feeNodes = $this->http->XPath->query("//td[{$this->starts($this->t('Taxes, Fees & Port Expenses'))}]/ancestor::tr[1]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = trim($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $feeRoot), ':');
                $feeSumm = $this->http->FindSingleNode("./descendant::text()[normalize-space()][last()]", $feeRoot, true, "/^([\d\.\,\']+)$/");

                if (!empty($feeName) && $feeSumm !== null) {
                    $email->price()
                        ->fee($feeName, PriceHelper::parse($feeSumm, $cur));
                }
            }
        }

        $pax = [];
        $pax = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following-sibling::tr/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));

        if (empty($pax)) {
            $last = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following-sibling::tr/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'Last Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));
            $first = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following-sibling::tr/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'First/Middle Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));

            if (!empty($last) && empty($first)) {
                $first = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following::tr[normalize-space()][position()<5]/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'First/Middle Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));
            }

            if (count($last) == count($first)) {
                foreach ($last as $key => $value) {
                    $pax[] = $first[$key] . ' ' . preg_replace("/^(?:Mrs\s|Mr\s|Ms\s|Miss\s)/", "", $value);
                }
            }
        }

        if (count($pax) > 0) {
            $r->general()->travellers(array_unique($pax));
        }

        $acc = [];

        foreach ($this->http->FindNodes("//*[contains(text(), 'PASSENGERS')]/ancestor::table[1]/descendant::tr//*[contains(text(),'Member Number:')]/ancestor-or-self::td[1]/following-sibling::td") as $name) {
            if ($name) {
                $acc[] = $name;
            }
        }
        $acc = array_unique($acc);

        if (count($acc) > 0) {
            $r->program()
                ->accounts($acc, false);
        }

        $date = $this->http->FindSingleNode("(//*[contains(text(), 'Embarkation')])[1]/ancestor::td[1]/following-sibling::td[1]",
            null, true, "#^(.*?)\s*/#");
        $year = date('Y', strtotime($date));

        $r->details()
            ->ship(
                $this->http->FindSingleNode("(//td[normalize-space() = 'Ship / Registry:'])[1]/following-sibling::td[1]") ??
                $this->http->FindSingleNode("(//*[(starts-with(text(), 'Ship') and not(contains(., ':')))])[1]/ancestor::td[1]/following-sibling::td[1]")
            )
            ->deck($this->http->FindSingleNode("(//*[contains(text(), 'Deck Group')])[1]/ancestor::td[1]/following-sibling::td[1]"), false, true)
            ->room($this->http->FindSingleNode("(//*[contains(text(), 'Stateroom / Cat')])[1]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "#^(.*?)\s*/#"))
            ->roomClass($this->http->FindSingleNode("(//*[contains(text(), 'Stateroom / Cat')])[1]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "#^(.*?)\s*/#"));

        $list = preg_split('#\s+/\s*#',
            $this->http->FindSingleNode("(//*[contains(text(), 'Voyage / Dest') or contains(text(), 'Cruisetour')])[1]/ancestor::td[1]/following-sibling::td[1]"));

        if (isset($list[0])) {
            $r->details()
                ->shipCode($list[0]);
        }

        if (isset($list[1])) {
            $r->details()
                ->description($list[1]);
        }

        $isCruise = false;
        $overnight = false;

        foreach ($rows as $i => $row) {
            $date = $this->http->FindSingleNode('./td[1]', $row);
            $name = implode(' ', $this->http->FindNodes('./td[2]//text()[normalize-space()]', $row));

            $depTime = $this->http->FindSingleNode('.', $row, true, '/\s+(?:Depart|Embark)\s*(\d.+)$/i')
                ?? $this->http->FindSingleNode('td[4]', $row, true, '/^\d.+$/');

            $arrTime = $this->http->FindSingleNode('.', $row, true, '/\s+(?:Arrive|Disembark)\s*(\d.+)$/i')
                ?? $this->http->FindSingleNode('td[3]', $row, true, '/^\d.+$/');

            if (!$depTime && !$arrTime) {
                if ($this->http->XPath->query("*[normalize-space()][last()][normalize-space()='Embark']", $row)->length > 0) {
                    $depTime = '00:00';
                } elseif ($this->http->XPath->query("*[normalize-space()][last()][normalize-space()='Disembark']", $row)->length > 0) {
                    $arrTime = '00:00';
                } elseif ($this->http->XPath->query("*[normalize-space()][last()][normalize-space()='Full Day']", $row)->length > 0) {
                    // it-15982072.eml
                    $arrTime = '00:00';
                    $depTime = '23:59';
                }
            }

            if (!$depTime && !$arrTime) {
                if (preg_match("#(.+) - (\d{1,2})\s+Nights?\s*$#", $name, $m)) {
                    $h = $email->add()->hotel();
                    $h->general()
                        ->noConfirmation()
                        ->travellers($pax);
                    $h->hotel()
                        ->name($m[1])
                        ->noAddress();
                    $h->booked()
                        ->checkIn(strtotime($date . ' ' . $year))
                        ->checkOut((!empty($h->getCheckInDate()) ? strtotime("+" . $m[2] . "day", $h->getCheckInDate()) : false));
                }

                continue;
            }

            if ($isCruise == false && preg_match("#^\s*Rail: (.+) To (.+?)\s*$#", $name, $m) && !empty($depTime) && !empty($arrTime)) {
                $t = $email->add()->train();
                $t->general()
                    ->noConfirmation()
                    ->travellers($pax);
                $segTrain = $t->addSegment();
                $segTrain->extra()->noNumber();
                $segTrain->departure()
                    ->name($m[1])
                    ->date(strtotime($date . " $year, " . $arrTime));
                $segTrain->arrival()
                    ->name($m[2])
                    ->date(strtotime($date . " $year, " . $depTime));

                continue;
            } elseif ($isCruise == false && preg_match("#^\s*Transfer: (.+) To (.+?)\s*$#", $name, $m) && !empty($depTime) && !empty($arrTime)) {
                /*skip transfer: not enough data for searching address by google
                $t = $email->add()->transfer();
                $t->general()
                    ->noConfirmation()
                    ->travellers($pax);
                $s = $t->addSegment();

                if (in_array($m[1], ['Hotel', 'Lodge'])) {
                    $hName = $this->http->FindSingleNode("(".$xpath."[normalize-space(td[1]) = '".$date."'][following::tr[normalize-space(td[2]) = '".$name."']][contains(normalize-space(td[2]), '".$m[1]."')])[last()]/td[2]", $row, true, "#(.*\b".$m[1]."\b.*?)\s+-\s+\d+\s+Nights?\s*$#");
                    if (!empty($hName)) {
                        $s->departure()
                            ->name($hName);
                    }
                }
                $s->departure()
                    ->name($s->getDepName()??$m[1])
                    ->date(strtotime($date . " $year, " . $arrTime));

                if (in_array($m[2], ['Hotel', 'Lodge'])) {
                    $hName = $this->http->FindSingleNode("(".$xpath."[normalize-space(td[1]) = '".$date."'][preceding::tr[normalize-space(td[2]) = '".$name."']][contains(normalize-space(td[2]), '".$m[2]."')])[1]/td[2]", $row, true, "#(.*\b".$m[2]."\b.*?)\s+-\s+\d+\s+Nights?\s*$#");
                    if (!empty($hName)) {
                        $s->arrival()
                            ->name($hName);
                    }
                }
                $s->arrival()
                    ->name($s->getArrName()??$m[2])
                    ->date(strtotime($date . " $year, " . $depTime));*/
                $this->logger->warning('skip transfer: not enough data for searching address by google');

                continue;
            } elseif ($isCruise == false && preg_match("#^\s*Coach: (.+) To (.+?)\s*$#", $name, $m) && !empty($depTime) && !empty($arrTime)) {
                /*skip transfer: not enough data for searching address by google
                $t = $email->add()->transfer();
                $t->general()
                    ->noConfirmation()
                    ->travellers($pax);
                $s = $t->addSegment();
                $s->departure()
                    ->name($m[1])
                    ->date(strtotime($date . " $year, " . $arrTime));
                $s->arrival()
                    ->name($m[2])
                    ->date(strtotime($date . " $year, " . $depTime));*/
                $this->logger->warning('skip transfer: not enough data for searching address by google');

                continue;
            } // Event - 43467150
            elseif (preg_match('/Table For \d+/', $name)) {
                continue;
            } elseif (preg_match('/Check In Begins At (\d+:\d.+)/', $name, $m)) {
                $arrTime = $m[1];
                $isCruise = true;
            }

            $port = preg_replace([
                '/\s*Guests Are Required To.*/is',
                '/\s*Tender Required.*/is',
                '/\s*Overnight.*/s',
                '/\s+Check.*/s',
            ], '', $name);
            //$this->logger->error("isCruise:$isCruise, overnight:$overnight, arrTime:$arrTime, depTime:$depTime - $port");

            if (empty($port)) {
                $this->logger->alert("Segment-$i: port name not found!");

                return false;
            }

            if ($isCruise === true && $overnight === false) {
                $s = $r->addSegment();
                $s->setName($port);
            }

            if (empty($arrTime) && !empty($depTime)) {
                if (!isset($s)) {
                    $s = $r->addSegment();
                    $s->setName($port);
                    $s->setAboard(strtotime($date . " $year, " . $depTime));
                    $isCruise = true;
                } elseif ($overnight === true) {
                    $s->setAboard(strtotime($date . " $year, " . $depTime));
                    $overnight = false;
                } else {
                    $s = $r->addSegment();
                    $this->logger->debug('check type of all segments');

                    return $email;
                }
            } elseif (!empty($arrTime) && empty($depTime)) {
                if (preg_match("#\bOvernight\b#i", $name)
                    || preg_match("/Departs After Midnight/i", $name)
                    // 43413023
                    || preg_match('/Ship Departs At \d+:\d/', $name, $m)
                    ) {
                    $s->setAshore(strtotime($date . " $year, " . $arrTime));
                    $overnight = true;
                } elseif ($isCruise === true) {
                    $s->setAshore(strtotime($date . " $year, " . $arrTime));
                    $isCruise = false;
                } else {
                    $s = $r->addSegment();
                    $this->logger->debug('check type of all segments 2');

                    return $email;
                }
            } elseif ($isCruise === true) {
                if ($dtAboard = strtotime($date . " $year, " . $depTime)) {
                    $s->setAboard($dtAboard);
                }

                if ($dtAshore = strtotime($date . " $year, " . $arrTime)) {
                    $s->setAshore($dtAshore);
                }
            }
        }

        return true;
    }

    private function parseEmail_2(Email $email)
    {
        $this->logger->debug(__METHOD__);
        $rows = $this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary'))}]/ancestor::table[1]/descendant::tr[1]/following::tr[1]/td//tr[not(.//*[position()=1 and contains(text(), 'Date')])]");

        if ($rows->length === 0) {
            $this->logger->debug('other format: can\'t find segments in body (format 2)');

            return false;
        }
//        $this->logger->alert('email 2');

        $r = $email->add()->cruise();

        $r->general()
            ->noConfirmation();

        $traveller = join(' ', array_reverse($this->http->FindNodes("//table//tr[td[div[contains(., 'Last Name')]]][1]/following::tr/td[position()=5 or position()=6]")));

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller);
        }

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Issue Date:')]/following::text()[normalize-space()][1]");

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Voyage / Dest'))}]/ancestor::*[1]/preceding::tr[1]/td[last()]");
        }

        if (!empty($date)) {
            $r->general()
                ->date(strtotime($date));
        }

        $date = $this->http->FindSingleNode("(//*[contains(text(), 'Embarkation')])[1]/ancestor::td[1]/following-sibling::td[1]",
            null, true, "#^(.*?)\s*/#");
        $year = date('Y', strtotime($date));

        $r->details()
            ->ship($this->http->FindSingleNode("(//*[contains(text(), 'Ship')])[1]/ancestor::td[1]/following-sibling::td[1]"))
            ->deck($this->http->FindSingleNode("(//*[contains(text(), 'Deck Group')])[1]/ancestor::td[1]/following-sibling::td[1]"),
                false, true)
            ->room($this->http->FindSingleNode("(//*[contains(text(), 'Stateroom / Cat')])[1]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "#^(.*?)\s*/#"), false, true);

        $list = preg_split('#\s+/\s*#',
            $this->http->FindSingleNode("(//text()[({$this->contains($this->t('Voyage / Dest'))})  or ({$this->contains($this->t('Cruisetour'))})])[1]/ancestor::td[1]/following-sibling::td[1]"));

        if (isset($list[0])) {
            $r->details()
                ->shipCode($list[0]);
        }

        if (isset($list[1])) {
            $r->details()
                ->description($list[1]);
        }

        foreach ($rows as $row) {
            $depTime = $this->http->FindSingleNode("td[5]", $row);
            $arrTime = $this->http->FindSingleNode("td[4]", $row);

            if (!$depTime && !$arrTime) {
                continue;
            }

            $s = $r->addSegment();
            $port = $this->http->FindSingleNode("(./td[3]//text()[normalize-space(.)!=''])[1]", $row);
            $port = preg_replace("#\s+Check.*#", '', $port);
            $s->setName($port);

            $weeknum = WeekTranslate::number1(WeekTranslate::translate($this->http->FindSingleNode("td[1]", $row),
                $this->lang));
            $str = $this->http->FindSingleNode("td[2]", $row) . " " . $year;
            $dt = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);

            if (preg_match("#^\d+:\d+\s*[AP]$#", $depTime)) {
                $depTime .= 'M';
            }

            if (preg_match("#^\d+:\d+\s*[AP]$#", $arrTime)) {
                $arrTime .= 'M';
            }

            if (!empty($depTime) && empty($arrTime)) {
                $s->setAboard(strtotime($depTime, $dt));
            } elseif (empty($depTime) && !empty($arrTime)) {
                $s->setAshore(strtotime($arrTime, $dt));
            } else {
                $s->setAboard(strtotime($depTime, $dt));
                $s->setAshore(strtotime($arrTime, $dt));
            }
        }

        return true;
    }

    private function parseEmail_3(Email $email)
    {
        $this->logger->debug(__FUNCTION__);

        // $this->logger->alert('email 3');

        $r = $email->add()->cruise();

        $sum = $this->amount($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Fare')]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][last()]",
            null, true, "#^\s*(\d[\d\,\. ]+)\s*$#"));
        $cur = $this->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'All amounts are quoted in')]/ancestor::tr[1]",
            null, true, "#All amounts are quoted in\s*(.+?)[\s\.]*$#"));

        if (!empty($sum) && !empty($cur)) {
            $email->price()
                ->total($sum)
                ->currency($cur);

            $feeNodes = $this->http->XPath->query("//td[{$this->starts($this->t('Taxes, Fees & Port Expenses'))}]/ancestor::tr[1]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = trim($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $feeRoot), ':');
                $feeSumm = $this->http->FindSingleNode("./descendant::text()[normalize-space()][last()]", $feeRoot, true, "/^([\d\.\,\']+)$/");

                if (!empty($feeName) && $feeSumm !== null) {
                    $email->price()
                        ->fee($feeName, $feeSumm);
                }
            }
        }

        $confirmation = $this->http->FindSingleNode("(//*[contains(text(), 'BOOKING') and not(contains(text(), 'CONFIRMATION')) and not(contains(text(), 'ITINERARY'))])[1]",
            null, true, "#^\s*BOOKING\s+([\d\w]+)#");

        if (empty($confirmation)) {
            $confirmation = $this->re("/Booking\-([A-Z\d]{3,})(\s|$)/", $this->subject);
        }

        $dateRes = $this->http->FindSingleNode("//*[contains(text(), 'BOOKING') and not(contains(text(), 'CONFIRMATION')) and not(contains(text(), 'ITINERARY'))]/ancestor::td[1]/following-sibling::td[position()=last()]");

        if (empty($dateRes)) {
            $dateRes = $this->http->FindSingleNode("//text()[normalize-space()='Gifts and Services Summary']/following::text()[normalize-space()][1]", null, true, "/^(\w+\s*\d+\,\s*\d{4})$/");
        }

        if ($this->http->FindSingleNode("(//text()[{$this->contains($this->t('cancelledTextHtml'))}])[1]")) {
            $r->general()
                ->cancelled()
                ->status('Cancelled')
            ;
        }

        $r->general()
            ->confirmation($confirmation)
            ->date(strtotime($dateRes))
        ;

        $pax = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'FARE INFORMATION')]/ancestor::tr[1]/following-sibling::tr/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));

        if (count($pax) === 0) {
            $pax = array_filter($this->http->FindNodes("//text()[normalize-space()='PASSENGERS']/following::text()[normalize-space()='Name:']/ancestor::tr[1]/descendant::td[string-length()>3][not(contains(normalize-space(), 'Name:'))]"));
        }

        if (count($pax) > 0) {
            $pax = preg_replace("/^(?:Mrs|Mr|Ms|Miss)\s+/", "", $pax);
            $r->general()->travellers(array_unique($pax));
        }

        $accounts = array_filter($this->http->FindNodes("//text()[normalize-space()='Member Number:']/ancestor::tr[1]/descendant::td[string-length()>3][not(contains(normalize-space(), 'Member Number:'))]"));

        if (count($accounts) === count($pax)) {
            foreach ($accounts as $key => $account) {
                $r->addAccountNumber($account, false, $pax[$key]);
            }
        } elseif (count($accounts) > 0) {
            $r->setAccountNumbers($accounts, false);
        }

        $r->details()
            ->ship($this->http->FindSingleNode("(//*[contains(text(), 'Ship') and not(contains(., ':'))])[1]/ancestor::td[1]/following-sibling::td[1]"))
            ->deck($this->http->FindSingleNode("(//*[contains(text(), 'Deck Group')])[1]/ancestor::td[1]/following-sibling::td[1]"), false, true)
            ->room($this->http->FindSingleNode("(//*[contains(text(), 'Stateroom / Cat')])[1]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "#^(.*?)\s*/#"))
            ->roomClass($this->http->FindSingleNode("(//*[contains(text(), 'Stateroom / Cat')])[1]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "#^.*?\s*/\s*(.+)\s*#"));

        $list = preg_split('#\s+/\s*#',
            $this->http->FindSingleNode("(//*[contains(text(), 'Voyage / Dest') or contains(text(), 'Cruisetour')])[1]/ancestor::td[1]/following-sibling::td[1]"));

        if (isset($list[0])) {
            $r->details()
                ->shipCode($list[0]);
        }

        if (isset($list[1])) {
            $r->details()
                ->description($list[1]);
        }

        $s = $r->addSegment();
        $s->setName($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Embarkation:']/following-sibling::td[1]",
            null, true, "#^.*?\s*/\s*(.+)#"));
        $s->setAboard(strtotime($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Embarkation:']/following-sibling::td[1]",
            null, true, "#^(.*?)\s*/#")));

        $s = $r->addSegment();
        $s->setName($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Disembarkation:']/following-sibling::td[1]",
            null, true, "#^.*?\s*/\s*(.+)#"));
        $s->setAshore(strtotime($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Disembarkation:']/following-sibling::td[1]",
            null, true, "#^(.*?)\s*/#")));

        return true;
    }

    private function parseEmail_4(Email $email)
    {
        $xpath = "//tr[*[normalize-space() = 'Date'] and *[normalize-space() = 'Duration'] and *[normalize-space() = 'Hotel Information']]/ancestor::thead/following::tbody/*[normalize-space()]";
        $rows = $this->http->XPath->query($xpath);

        if ($rows->length == 0) {
            $this->logger->debug('other format: can\'t find segments in Html body (format 4)');

            return false;
        }
        $this->logger->debug(__FUNCTION__);

        // $this->logger->alert('email 4');

        $r = $email->add()->cruise();

        $r->general()
            ->confirmation($this->http->FindSingleNode("(//*[contains(text(), 'BOOKING') and not(contains(text(), 'CONFIRMATION')) and not(contains(text(), 'ITINERARY'))])[1]",
                null, true, "#^\s*BOOKING\s+([\d\w]+)#"))
            ->date(strtotime($this->http->FindSingleNode("//*[contains(text(), 'BOOKING') and not(contains(text(), 'CONFIRMATION')) and not(contains(text(), 'ITINERARY'))]/ancestor::td[1]/following-sibling::td[position()=last()]")))
        ;

        $pax = [];
        $pax = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following-sibling::tr/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));

        if (empty($pax)) {
            $last = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following-sibling::tr/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'Last Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));
            $first = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following-sibling::tr/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'First/Middle Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));

            if (!empty($last) && empty($first)) {
                $first = array_filter($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGERS')]/ancestor::tr[1]/following::tr[normalize-space()][position()<5]/descendant::td[not(./td)][normalize-space()][1][starts-with(normalize-space(.),'First/Middle Name:')][1]/following-sibling::td[normalize-space() and not(starts-with(normalize-space(), 'Totals'))]"));
            }

            if (count($last) == count($first)) {
                foreach ($last as $key => $value) {
                    $pax[] = $first[$key] . ' ' . preg_replace("/^(?:Mrs\s|Mr\s|Ms\s|Miss\s)/", "", $value);
                }
            }
        }
        $pax = preg_replace("/^(?:Mrs|Mr|Ms|Miss)\s+/", "", $pax);

        if (count($pax) > 0) {
            $r->general()->travellers(array_unique($pax));
        }

        $r->details()
            ->ship($this->http->FindSingleNode("(//*[contains(text(), 'Ship') and not(contains(., ':'))])[1]/ancestor::td[1]/following-sibling::td[1]"))
            ->deck($this->http->FindSingleNode("(//*[contains(text(), 'Deck Group')])[1]/ancestor::td[1]/following-sibling::td[1]"), false, true)
            ->room($this->http->FindSingleNode("(//*[contains(text(), 'Stateroom / Cat')])[1]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "#^(.*?)\s*/#"))
            ->roomClass($this->http->FindSingleNode("(//*[contains(text(), 'Stateroom / Cat')])[1]/ancestor::td[1]/following-sibling::td[1]",
                null, true, "#^.*?\s*/\s*(.+)\s*#"));

        $list = preg_split('#\s+/\s*#',
            $this->http->FindSingleNode("(//*[contains(text(), 'Voyage / Dest') or contains(text(), 'Cruisetour')])[1]/ancestor::td[1]/following-sibling::td[1]"));

        if (isset($list[0])) {
            $r->details()
                ->shipCode($list[0]);
        }

        if (isset($list[1])) {
            $r->details()
                ->description($list[1]);
        }

        $s = $r->addSegment();
        $s->setName($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Embarkation:']/following-sibling::td[1]",
            null, true, "#^.*?\s*/\s*(.+)#"));
        $s->setAboard(strtotime($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Embarkation:']/following-sibling::td[1]",
            null, true, "#^(.*?)\s*/#")));

        $s = $r->addSegment();
        $s->setName($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Disembarkation:']/following-sibling::td[1]",
            null, true, "#^.*?\s*/\s*(.+)#"));
        $s->setAshore(strtotime($this->http->FindSingleNode("//td[not(.//td)][normalize-space() = 'Disembarkation:']/following-sibling::td[1]",
            null, true, "#^(.*?)\s*/#")));

        foreach ($rows as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->noConfirmation()
                ->travellers($pax)
            ;

            // Hotel
            $node = implode("\n", $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));
            $node = preg_replace('/(^|\n)\s*Â\s*(?:\n|$)/u', "\n", $node);

            if (preg_match("/^\s*(?<name>.+)\s*\n(?<address>[\s\S]+?)(?<phone>\n[\d\W]{5,})?\s*$/", $node, $m)
                || preg_match("/^\s*(?<name>.+)\s*$/", $node, $m)
            ) {
                $h->hotel()
                    ->name($m['name'])
                    ->phone(trim($m['phone'] ?? null), true, true)
                ;

                if (!empty($m['address'])) {
                    $h->hotel()
                        ->address(preg_replace('/\s+/', ' ', $m['address']));
                } else {
                    $h->hotel()
                        ->noAddress();
                }
            }
            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode("*[1]", $root)))
            ;
            $duration = $this->http->FindSingleNode("*[2]", $root, true, "/^\W*(\d)\D*$/");

            if (!empty($duration) && !empty($h->getCheckInDate())) {
                $h->booked()
                    ->checkOut(strtotime('+ ' . $duration . ' day', $h->getCheckInDate()));
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

    private function assignProvider($headers, $text = ''): bool
    {
        if (!empty($headers)) {
            if (preg_match('/[.@]pocruises\.(com\.au|co\.nz)$/i', rtrim($headers['from'], '> ')) > 0
                || $this->http->XPath->query('//a[contains(@href,".pocruises.com.au/") or contains(@href,".pocruises.co.nz/")]')->length > 0
                || $this->http->XPath->query('//*[contains(normalize-space(),"with P&O Cruises") or contains(.,"@pocruises.com.au") or contains(.,"@pocruises.co.nz")]')->length > 0
            ) {
                $this->providerCode = 'pocruises';

                return true;
            }

            if ($this->http->XPath->query('//a[contains(@href,"//www.carnival.co")]')->length > 0
                || $this->http->XPath->query('//*[contains(normalize-space(),"your cruise with Carnival") or contains(.,"www.carnival.co") or contains(.,"@carnival.co")]')->length > 0
            ) {
                $this->providerCode = 'carnival';

                return true;
            }

            if (strpos($headers['subject'], 'Princess Cruise') !== false
                || $this->http->XPath->query('//*[contains(.,"PRINCESS.COM") or contains(.,"princess.com")]')->length > 0
                || $this->http->XPath->query('//img/@src[contains(.,"princess.com")]')->length > 0
            ) {
                $this->providerCode = 'princess';

                return true;
            }
        } else {
            if (strpos($text, 'P&O Cruises') !== false
                || stripos($text, 'at www.pocruises.com.au') !== false
                || stripos($text, '@pocruises.co') !== false
            ) {
                $this->providerCode = 'pocruises';

                return true;
            }

            if (strpos($text, 'with Carnival plc') !== false || strpos($text, '@carnival.com') !== false) {
                $this->providerCode = 'carnival';

                return true;
            }

            if (strpos($text, 'PRINCESS') !== false || strpos($text, 'Princess Cruises') !== false) {
                $this->providerCode = 'princess';

                return true;
            }
        }

        return false;
    }

    private function assignLang($body = null)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }

            if (isset($body) && is_string($body)) {
                foreach ($this->reBody as $lang => $reBody) {
                    if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function findСutSection($input, $searchStart, $searchFinish)
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
                    return false;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            'New Zealand Dollars' => 'NZD',
            'Singapore Dollar'    => 'SGD',
            'Australian Dollars'  => 'AUD',
            'Canadian Dollars'    => 'CAD',
            'U.S. Dollars'        => 'USD',
            '€'                   => 'EUR',
            '$'                   => 'USD',
            '£'                   => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nicePax($pax)
    {
        return preg_replace("/^(?:Mrs|Mr|Ms)\s/", "", $pax);
    }
}
