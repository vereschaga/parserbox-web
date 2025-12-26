<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AsiaEscapePdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-16707294.eml, mta/it-18265709.eml";

    public $reFrom = ["MTA Travel", "mtatravel.com.au", "@asiaescapeholidays.com", "SAVENIO TRAVEL"];

    public $reBody = [
        'en'  => ['PREPARED ON', 'Itinerary for', 'Passenger Pricing Details'],
        'en2' => ['Payment Schedule', 'Passenger Pricing Details'],
    ];
    public $reSubject = [
        '#[A-Z\d\/]{6,} Booking itinerary for \w+\/\w+ \w+#',
    ];
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".+_(itn|ivc|adv)\.pdf";
    public static $dict = [
        'en' => [
            'Date of Issue' => ['Date of Issue', 'Issue Date'],
        ],
    ];
    private $pax;
    private $dateRes;
    private $currency;

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

            if (((stripos($text, 'Asia Escape Holidays') !== false) || (stripos($text, 'asiaescapeholidays') !== false) || (stripos($text, 'SAVENIO TRAVEL') !== false))
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
        return count(self::$dict);
    }

    private function parseEmail($textPDF, Email $email)
    {
        $confNo = $descr = null;

        $textPDF = preg_replace("#\n *Asia Escape Holidays[^\n]+\n[^\n]+?Page +\d+.+?(?:Date of Issue[^\n]+|$)#s", '', $textPDF);
        $textPDF = preg_replace("#\n.*[ ]{5,}Page \d+(?:\n|$)#", "\n", $textPDF);

        $node = $this->re("#{$this->opt($this->t('Itinerary for'))}\s+(.+?)\s+PREPARED ON#s", $textPDF);

        if (empty($node)) {
            $node = $this->re("#{$this->opt($this->t('Passenger'))}[ ]+{$this->opt($this->t('Age'))}[ ]+[^\n]*\n(.+?)\n\s*Payment Schedule#s", $textPDF);
        }

        if (preg_match("#({$this->opt($this->t('Booking Number'))}) +([A-Z\d]{2} *\/ *[A-Z\d]{5,})#", $node, $m)
                || preg_match("#({$this->opt($this->t('Booking Number'))}) +([A-Z\d]{2} *\/ *[A-Z\d]{5,})#", substr($textPDF, 0, 500), $m)) {
            $confNo = str_replace(' ', '', $m[2]);
            $descr = $m[1];
            $node = preg_replace("#{$this->opt($this->t('Booking Number'))}.+#", '', $node);
        }

        if (preg_match("#{$this->opt($this->t('Date of Issue'))} +(.+)#", $node, $m)
                || preg_match("#{$this->opt($this->t('Date of Issue'))} +(.+)#", substr($textPDF, 0, 500), $m)) {
            $this->dateRes = strtotime($m[1]);
            $node = preg_replace("#{$this->opt($this->t('Date of Issue'))}.+#", '', $node);
        }

        if (preg_match_all("#^ *(\w.+?)(?: {2,}|$)#m", $node, $m)) {
            $this->pax = $m[1];
        }

        $email->ota()
            ->confirmation($confNo, $descr);

        $total = $this->re("#\n\s*PASSENGER TOTAL[ ]*(.+?)(?:[ ]{3,}|\n)#", $textPDF);

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $this->currency = $this->currency($m['curr']);
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency)
            ;
        }

        //grouping segments & added date on it
        $arrs = $this->splitter("#^( *\w+ \d+ \w+ \d{4} *)$#m", "ControlStr\n" . $textPDF);
        $arr = [];
        $segs = [];

        foreach ($arrs as $root) {
            $dateStr = $this->re("#(.+)#", $root);
            $newRoot = preg_replace("#(.+)(\n+)(^ +(?:TRANSFER|ACCOMMODATION|Flight - Departing|CRUISE) *\n)#mi",
                '$1' . "\n{$dateStr}\n" . '$3',
                $root);
            $newRoot = preg_replace("#({$dateStr})(\n)($dateStr)#", '$1', $newRoot);

            $arr = array_merge($arr, $this->splitter("#^( *\w+ \d+ \w+ \d{4} *)$#m", "ControlStr\n" . $newRoot));
        }

        for ($i = 0; $i < count($arr); $i++) {
            if (preg_match("#^ +(?:Flight - Departing)#m", $arr[$i])
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

        //main parsing
        foreach ($segs as $i => $root) {
            $root = preg_replace("#\n[ ]+Issue Date.+\n\s*Booking Number.+?\n\s+Agent Code .+\n#", "\n", $root);

            //accommodation, flights, transfers, car hire  and tours
            if (preg_match("#.+\n *(TRANSFER|CRUISE)(?: *\n|[ ]+.*\n)#i", $root, $m)) {
                $this->logger->info("skip {$m[1]}: need examples with more info");

                continue;
            }

            if (preg_match("#.+\n *Flight - Departing#", $root)) {
                $this->parseFlight($root, $email);

                continue;
            }

            if (preg_match("#.+\n+ *ACCOMMODATION#i", $root)) {
                $this->parseHotel($root, $email);

                continue;
            }

            if (preg_match("#.+\n+ *Own arrangements#i", $root)) {
                continue;
            }
            $this->logger->debug("other format rootSegment: {$i}");
            $email->add()->flight();

            return false;
        }

        return true;
    }

    private function parseHotel($textPDF, Email $email)
    {
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $h = $email->add()->hotel();
        $h->general()
            ->date($this->dateRes);

        if (preg_match("# {5,}Confirmed\n#", $textPDF)) {
            $h->general()
                ->status('Confirmed');
        }
        $roomType = 'IfNotFoundThenFail';

        if (preg_match("#ACCOMMODATION\s+(?<roomType>[^\n]+)\s+(?<hotel>.*?)\n(?:[ ]*For (?<guests>.+)\n)?(?:Request room[^\n]*\n)? +(?<rooms>(?:In +(?:\d+)?|\d x ).+\n\s+For \d{1,2}(?:\.\d)? nights? In)#is", $textPDF,
             $m)) {
            $roomType = $m['roomType'];

            if (preg_match("#\s*(\S.+?)[ ]{5,}(\d[\d., ]*)#", $roomType, $mat)) {
                $roomType = preg_split("#\s{5,}#", trim($mat[1]))[0];
                $h->price()
                    ->total($this->amount($mat[2]))
                    ->currency($this->currency);
            }
            $roomType = preg_split("#\s{5,}#", trim($roomType))[0];

            $m['hotel'] = preg_replace("#\n\s*ARR APPROX .+?(?:[ ]{2,}|\n|$)#", "\n", $m['hotel']);

            if (empty($h->getPrice()) && preg_match("#^\s*(\S.+?)[ ]{5,}(\d[\d., ]*)#", $m['hotel'], $mat)) {
                $h->price()
                    ->total($this->amount($mat[2]))
                    ->currency($this->currency);
                $m['hotel'] = preg_replace("#^(\s*.+?)[ ]{5,}.+#", "$1", $m['hotel']);
            }

            if (preg_match("#([^\n]+)\n\s*(.+?)\s+TEL: ([\+\-\)\(\d ]{5,})#s", $m['hotel'], $v)) {
                $h->hotel()
                    ->name($v[1])
                    ->address(trim(preg_replace("#\s+#", ' ', $v[2])))
                    ->phone(trim($v[3]));
            } elseif (preg_match("#([^\n]+)\ns*(.+)#s", $m['hotel'], $v)) {
                $h->hotel()
                    ->name($v[1])
                    ->address(trim(preg_replace("#\s+#", ' ', $v[2])));
            } elseif (strpos($m['hotel'], "\n") === false) {
                $foundHotel = false;

                foreach ($email->getItineraries() as $it) {
                    if ($it->getType() === 'hotel' && $it->getHotelName() === $m['hotel']) {
                        $h->hotel()
                            ->name($m['hotel'])
                            ->address($it->getAddress());
                        $foundHotel = true;

                        break;
                    }
                }

                if ($foundHotel === false) {
                    $h->hotel()
                        ->name($m['hotel'])
                        ->noAddress();
                }
            }

            if (!empty($m['guests'])) {
                $guests = array_filter(preg_split("#\b(MR|MS|MRS|DR)\b#", $m['guests']));
                $h->general()
                    ->travellers($guests);
            } else {
                $h->general()
                    ->travellers($this->pax);
            }

            if (preg_match("#^ *In +a (\d+) Adult#m", $m['rooms'], $mat)) {
                $h->booked()
                    ->rooms(1)
                    ->guests($mat[1]);
                $h->addRoom()->setType($roomType);
            } elseif (preg_match("#^ *In +a (\d+) (.+)#m", $m['rooms'], $mat)) {
                $h->booked()
                    ->rooms(1);
                $h->addRoom()
                    ->setType($roomType)
                    ->setDescription($mat[2]);
            }

            if (preg_match_all("#^\s*(?:In\s+)?(\d{1,2}) x (\d+) Adults?#m", $m['rooms'], $mat)) {
                $rooms = 0;
                $guests = 0;

                foreach ($mat[0] as $key => $value) {
                    $rooms += $mat[1][$key];

                    for ($i = 1; $i <= $mat[1][$key]; $i++) {
                        $h->addRoom()->setType($roomType);
                    }
                    $guests += $mat[1][$key] * $mat[2][$key];
                }
                $h->booked()
                    ->rooms($rooms)
                    ->guests($guests);
            } elseif (preg_match_all("#^\s*(?:In\s+)?(\d{1,2}) x (.+)#m", $m['rooms'], $mat)) {
                $rooms = 0;

                foreach ($mat[0] as $key => $value) {
                    $rooms += $mat[1][$key];

                    for ($i = 1; $i <= $mat[1][$key]; $i++) {
                        $h->addRoom()
                            ->setType($roomType)
                            ->setDescription($mat[2][$key]);
                    }
                }
                $h->booked()
                    ->rooms($rooms);
            }
        }

        $roomTypeReg = preg_quote($roomType);
        $node = trim($this->re("#Reservation:\s+(.+?)\s+{$roomTypeReg}#is", $textPDF));

        if (!empty($node)) {
            $resNos = array_values(array_filter(array_map("trim", explode("\n", $node)), function ($s) {
                return preg_match("#^[\w\-]+$#", $s);
            }));

            if (count($resNos) === $h->getRoomsCount()) {
                foreach ($h->getRooms() as $key => $gr) {
                    $gr->setConfirmation($resNos[$key]);
                }
                $h->general()
                    ->noConfirmation();
            } else {
                $resNos = array_unique($resNos);

                foreach ($resNos as $cn) {
                    $h->general()
                        ->confirmation($cn);
                }
            }
        } else {
            $h->general()
                ->noConfirmation();
        }

        // For 8 nights In: 07 SEP Out: 15 SEP; For 1.5 nights In: 14 JUL Out: 15 JUL
        if (preg_match("#^ *For \d+(?:\.\d)? .+? In: (.+) Out: (.+)#m", $textPDF, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $date))
                ->checkOut(strtotime($m[2], $date));
            $OutTime = $this->re("#Checkout required by (\d{1,2}:\d{1,2})[ ]*hrs#", $textPDF);

            if (!empty($OutTime) && !empty($h->getCheckOutDate())) {
                $h->booked()
                    ->checkOut(strtotime($OutTime, $h->getCheckOutDate()));
            }
        }

        if (preg_match("#\n\s*Cancellation Policy\n+([\s\S]+)(?:\n\n|$)#", $textPDF, $m)) {
            $h->general()->cancellation(preg_replace("#\s*\n\s*#", ' ', trim($m[1])));
        }

        $this->detectDeadLine($h);

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return false;
        }

        if (preg_match("#If cancelled on or after (\d{1,2}\w{2,4}\d{2}) a ([\d\.]+) fee applies\s*\([^\)]+\)\.?\s*#u", $cancellationText, $m)
                && is_numeric($m[2]) && !empty($m[2])
        ) {
            $h->booked()->deadline(strtotime($this->normalizeDate($m[1])));

            return true;
        }

        return false;
    }

    private function parseFlight($textPDF, Email $email)
    {
        $date = strtotime($this->re("#(.+)#", $textPDF));

        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation()
            ->travellers($this->pax)
            ->date($this->dateRes);

        if (preg_match("# {5,}Confirmed\n#", $textPDF)) {
            $f->general()
                ->status('Confirmed');
        }

        $s = $f->addSegment();

        $depTime = $this->re("#Flight - Departing\s+(\d+:\d+(?:\s*[AaPp][Mm])?)#", $textPDF);
        $arrTime = $this->re("#Flight - Arriving\s+(\d+:\d+(?:\s*[AaPp][Mm])?)#", $textPDF);
        $node = $this->re("#Class:\s+(.+)#", $textPDF);

        if (preg_match("#^(.+?) +([A-Z]{1,2})\s*$#", $node, $m)) {
            $s->extra()
                ->cabin($m[1])
                ->bookingCode($m[2]);
        } elseif (preg_match("#^\s*([A-Z]{1,5})\s*$#", $node)) {
            $s->extra()
                ->bookingCode($node);
        } elseif (!empty($node)) {
            $s->extra()
                ->cabin($node);
        }

        if (preg_match("#{$this->opt($this->t('Depart'))} +(.+) on (.+?)\s+flight ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)#",
            $textPDF, $m)) {
            $s->airline()
                ->name($m[3])
                ->number($m[4]);
            $s->departure()
                ->name($m[1])
                ->noCode()
                ->date(strtotime($depTime, $date));
        }

        if (preg_match("#\s+Departure Terminal:[ ]*(.+)#", $textPDF, $m)) {
            $s->departure()
                ->terminal($m[1]);
        }

        if (preg_match("#{$this->opt($this->t('Arrives'))} +(.+)#", $textPDF, $m)) {
            $s->arrival()
                ->name($m[1])
                ->noCode()
                ->date(strtotime($arrTime, $date));
        }

        if (preg_match("#\s+Arrival Terminal:[ ]*(.+)#", $textPDF, $m)) {
            $s->arrival()
                ->terminal($m[1]);
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

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})([^\s\d]+)(\d{2})\s*$#", //31May19
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
