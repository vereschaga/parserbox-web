<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class GTAVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-15819869.eml, mta/it-16155778.eml, mta/it-16308607.eml";

    public $reBody = [
        'en' => ['PLEASE PRESENT THIS VOUCHER', 'Voucher Information'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'headerFields' => [
                [
                    //order matters
                    'Country',
                    'Contact',
                    'Int. Call',
                    'Domestic Call',
                    'Local Call',
                    'Office Hours',
                    'Emergency no.',
                    'Language',
                ],
                [
                    //order matters
                    'Country',
                    'Contact',
                    'Int. Call',
                    'Domestic Call',
                    'Local Call',
                    'Office Hours',
                    'Non Office hours',
                    'Language',
                ],
            ],
            'Emergency no.' => ['Emergency no.', 'Non Office hours'],
            'segmentsReg'   => '[^\n]*Voucher[^\n]*\s+PLEASE PRESENT THIS VOUCHER',
            'nights'        => ['nights', 'night'],
        ],
    ];
    private $date;

    private $code;
    private static $bodies = [
        'mta' => [
            '//a[contains(@href,"mtatravel.com.au")]',
            'MTA Travel',
        ],
        'tbound' => [
            '//a[contains(@href,"booktravelbound.com")]',
            'Travel Bound',
        ],
    ];
    private $headers = [
        'mta' => [
            'from' => ["mtatravel.com.au", "travelcube.com.au"],
            'subj' => [
                'Travel Voucher for Booking',
            ],
        ],
        'tbound' => [
            'from' => ["gta-travel.com"],
            'subj' => [
                'Travel Voucher for Booking',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->code = $this->getProvider($parser);

        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
            $this->logger->debug("[PROVIDER]: {$this->code}");
        }

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

            if ((stripos($text, 'GTA') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$bodies);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; //Tour | Transfer | Hotel;

        return $types * count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach (self::$bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail($textPDF, Email $email)
    {
        if (!empty($str = strstr($textPDF, $this->t('Enjoy...')))) {
            $textPDF = $str;
        } elseif (!empty($str = strstr($textPDF, $this->t('Getting in touch when traveling')))) {
            $textPDF = $str;
        } else {
            $this->logger->debug('other format');

            return false;
        }
        $email->ota()
            ->confirmation($this->re("#{$this->t('Booking ID')}[: ]+([\w\/\-]{5,})#", $textPDF),
                $this->t('Booking ID'));

        $rows = [];
        $currentHeader = [];

        foreach ($this->t('headerFields') as $headerFields) {
            $header = implode('\s+', $headerFields);
            $node = $this->re("#{$header}\s+(.+?)\n{$this->t('segmentsReg')}#s", $textPDF);

            if (!empty($node)) {
                $rows = array_filter(array_map("trim", explode("\n", $node)));
                $currentHeader = $headerFields;

                break;
            }
        }

        $addedPhones = [];

        foreach ($rows as $row) {
            $cells = array_map("trim", explode("|", preg_replace("#\s{4,}#", "|", $row)));

            if (count($cells) === count($currentHeader)) {
                $keyNames = (array) $this->t('Emergency no.');
                $currentName = '';
                $num = null;

                foreach ($keyNames as $keyName) {
                    $key = array_search($keyName, $currentHeader);

                    if ($key !== false) {
                        $num = $cells[$key];
                        $currentName = $keyName;

                        break;
                    }
                }
                $key = array_search($this->t('Contact'), $currentHeader);
                $descr = $cells[$key];
                $key = array_search($this->t('Language'), $currentHeader);
                $descr .= ' ' . $currentName . ' (' . $this->t('Language') . ': ' . $cells[$key] . ')';

                if (isset($num) && !in_array($num, $addedPhones)) {
                    $email->ota()->phone($num, $descr);
                    $addedPhones[] = $num;
                }

                $key = array_search($this->t('Int. Call'), $currentHeader);
                $num = $cells[$key];
                $key = array_search($this->t('Contact'), $currentHeader);
                $descr = $cells[$key];
                $key = array_search($this->t('Office Hours'), $currentHeader);
                $descr .= ' (' . $cells[$key] . ')';

                if (!in_array($num, $addedPhones)) {
                    $email->ota()->phone($num, $descr);
                    $addedPhones[] = $num;
                }
            }
        }

        $arr = $this->splitter("#({$this->t('segmentsReg')})#", $textPDF);

        foreach ($arr as $root) {
            if (strpos($root, 'Tour Name:') !== false) {
                $this->parseTour($root, $email);
            } elseif (preg_match("#^ *Service Voucher#", $root)) {
                $this->parseTransfer($root, $email);
            } elseif (preg_match("#^ *Hotel Voucher#", $root)) {
                $this->parseHotel($root, $email);
            } else {
                $this->logger->info("unknown type or reservation");

                return false;
            }
        }

        return true;
    }

    private function parseTour($textPDF, Email $email)
    {
        $tourName = $this->re("#{$this->t('Tour Name')}: +(.+)#", $textPDF);

        if (stripos($tourName, 'VISA SUPPORT LETTER') !== false) {
            return $email;
        }

        $t = $email->add()->event();
        $t->setEventType(EVENT_EVENT);
        $t->place()
            ->name($tourName);

        $address = $this->re("#\s+at\s+(.+)#", $tourName);

        if (!empty($address)) {
            $t->place()
                ->address($address);
        } else {
            $address = $this->nice($this->re("#{$this->opt($this->t('Departure point'))}:\s+(.+)\s+{$this->opt($this->t('No. of Adults'))}#s",
                $textPDF));

            if (!empty($address)) {
                $t->place()
                    ->address($address);
            }
        }

        $date = strtotime($this->re("#^Date: +(.+?\d{4})\b#m", $textPDF));
        $time = $this->normalizeTime($this->re("#^\s*Start Time: +(.+?)(?:[ ]{2,}|$)#m", $textPDF));

        if (!empty($date) && !empty($time)) {
            $date = strtotime($time, $date);
        }
        $t->booked()
            ->start($date);
        $duration = $this->re("#^\s*Duration: +(.+?)(?:[ ]{2,}|$)#m", $textPDF);

        if (!empty($duration)) {
            if (!empty($date)) {
                $t->booked()->end(strtotime('+' . $duration, $date));
            }
        } else {
            $t->booked()->noEnd();
        }

        $t->general()
            ->confirmation($this->re("#GTA Tour No[: ]+([\w\-]{5,})#", $textPDF))
            ->traveller($this->re("#Lead Name[: ]+(.+)#", $textPDF))
            ->date(strtotime($this->re("#{$this->t('Booking ID')}[: ]+[\w\/\-]{5,}[^\n]+{$this->t('Issued')}[: ]+(.+)#",
                $textPDF)));

        $t->program()
            ->phone($this->re("#{$this->t('Telephone')}: +([\d\+\- \(\)]{5,})#", $textPDF),
                $this->re("#{$this->t('Tour Supplied by')}:\s+(.+)#", $textPDF));

        $adults = $this->re("#{$this->opt($this->t('No. of Adults'))}: +(\d+)#", $textPDF);
        $kids = $this->re("#{$this->opt($this->t('No. of Children'))}: +(\d+)#", $textPDF);
        $adults = !empty($adults) ? (int) $adults : 0;
        $kids = !empty($kids) ? (int) $kids : 0;
        $guests = $adults + $kids;

        if (!empty($guests)) {
            $t->booked()->guests($guests);
        }

        return true;
    }

    private function parseTransfer($textPDF, Email $email)
    {
        $t = $email->add()->transfer();
        $t->general()
            ->confirmation($this->re("#GTA Tour No[: ]+([\w\-]{5,})#", $textPDF))
            ->traveller($this->re("#Lead Name[: ]+(.+)#", $textPDF))
            ->date(strtotime($this->re("#{$this->t('Booking ID')}[: ]+[\w\/\-]{5,}[^\n]+{$this->t('Issued')}[: ]+(.+)#",
                $textPDF)));

        if (!empty($status = $this->re("#this booking is already (confirmed)#", $textPDF))) {
            $t->general()
                ->status($status);
        }

        if (!empty($phone = $this->re("#{$this->t('emergency on spot, please contact the end supplier')}:\s+.+?\s+at\s+([\d\+\- \(\)]{5,})#",
            $textPDF))
        ) {
            $t->program()
                ->phone($phone,
                    $this->re("#({$this->t('emergency on spot, please contact the end supplier')}:\s+.+?)\s+at#",
                        $textPDF));
        }

        if (!empty($phone = $this->re("#{$this->t('Customer support team')}\s+on\s+([\d\+\- \(\)]{5,})#", $textPDF))) {
            $t->program()
                ->phone($phone, $this->t('Customer support team'));
        }

        $s = $t->addSegment();
        $date = strtotime($this->re("#^Date: +(.+?\d{4})\b#m", $textPDF));

        if (!empty($type = $this->re("#{$this->t('You have booked a')}\s+(.+)#", $textPDF))) {
            $s->extra()
                ->type($type);
        }

        $s->departure()
            ->date(strtotime($this->normalizeTime($this->re("#{$this->opt($this->t('Pick Up Time'))}[: ]+(.+)#",
                $textPDF)), $date))
            ->name($this->nice($this->re("#{$this->opt($this->t('Pick Up From'))}[: ]+(.+?)\s+{$this->opt($this->t('Drop Off'))}#s",
                $textPDF)));

        //$time = $this->re("#{$this->opt($this->t('Drop Off'))}[: ]+.+?Flight arrival time and.+?departing\s+(\d+:\d+)#s", $textPDF);
        //just guessing
        $time = $this->re("#{$this->opt($this->t('Drop Off Time'))}[: ]+(.+)#", $textPDF);

        if (empty($time)) {
            $s->arrival()->noDate();
        } else {
            $s->arrival()
                ->date(strtotime($this->normalizeTime($time), $date));
        }
        $s->arrival()
            ->name($this->nice($this->re("#{$this->opt($this->t('Drop Off'))}[: ]+(.+?)\s+(?:{$this->t('Flight arrival time and')}|{$this->t('You have booked a')})#s",
                $textPDF)));

        return true;
    }

    private function parseHotel($textPDF, Email $email)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->re("#GTA Internal Reference[: ]+([\w\-]{5,})#", $textPDF), 'GTA Internal Reference')
            ->date(strtotime($this->re("#{$this->t('Booking ID')}[: ]+[\w\/\-]{5,}[^\n]+{$this->t('Issued')}[: ]+(.+)#",
                $textPDF)));

        if (preg_match("#\n\s*Reservation No:[ ]*([\d\-]{5,})\s+#", $textPDF, $m)) {
            $h->general()->confirmation($m[1], 'Reservation No');
        }

        $hotelInfoBlock = $this->re("#{$this->opt($this->t('PLEASE PRESENT THIS VOUCHER'))}[^\n]*\n(.+)#s",
            strstr($textPDF, 'GTA Booking Id', true));
        $hotelInfoBlock = $this->splitCols($hotelInfoBlock, $this->colsPos($hotelInfoBlock, 10));

        if (count($hotelInfoBlock) < 2) {
            $this->logger->debug("other format hotelInfoBlock");

            return false;
        }

        $hInfo = explode("\n", trim($hotelInfoBlock[0]));

        if (count($hInfo) >= 2) {
            $h->hotel()
                ->name(array_shift($hInfo))
                ->address(trim(implode(', ', $hInfo)));
        }

        $hInfoTF = array_values(array_filter(array_map("trim", explode("\n", trim($hotelInfoBlock[1])))));

        if (count($hInfoTF) === 2 || (count($hInfoTF) === 3 && strpos($hInfoTF[2], '@') !== false)) {
            $h->hotel()
                ->phone($hInfoTF[0])
                ->fax($hInfoTF[1]);
        }

        $node = $this->re("#^ *{$this->opt($this->t('Rooms'))}:\s+(.+)#m", $textPDF);

        if (preg_match("#(\d+)\s+x\s+(.+?)\s+\-\s+(.+)#", $node, $m)) {
            $h->booked()
                ->rooms($m[1]);
            $r = $h->addRoom();
            $r->setType($m[2]);

            $h->general()
                ->travellers(array_map("trim", explode(",", $m[3])));
        }
        $node = $this->re("#^ *Arrival Date:\s+(.+)#m", $textPDF);

        if (preg_match("#(.+)\s+\-\s+(\d+)\s+{$this->opt($this->t('nights'))}#", $node, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime("+ " . $m[2] . " days", strtotime($m[1])));
        }

        return true;
    }

    private function normalizeTime($strTime)
    {
        $in = [
            //20.15hours
            '#^(\d+)\.(\d+)\s*hours$#u',
        ];
        $out = [
            '$1:$2',
        ];

        $str = preg_replace($in, $out, $strTime);

        return $str;
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
                    $this->lang = $lang;

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

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
