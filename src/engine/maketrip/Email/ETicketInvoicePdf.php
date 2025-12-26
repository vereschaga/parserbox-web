<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketInvoicePdf extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-5589163.eml, maketrip/it-59481346.eml, maketrip/it-59754538.eml, maketrip/it-67289904.eml, maketrip/it-67514426.eml, maketrip/it-8431186.eml, maketrip/it-8431242.eml";

    public static $detectProvider = [
        'maketrip' => [
            'from' => '@makemytrip.com',
            'text' => 'MakeMyTrip',
        ],
        'goibibo' => [
            'from' => ['noreply@goibibo.com'],
            'text' => 'Ibibo Group',
        ],
    ];

    public static $dictionary = [
        "en" => [],
    ];
    public $text;
    public $lang = "en";
    //	var $reFrom = "@makemytrip.com";
    private $detectSubject = [
        // en
        "Flight Ticket Reimbursement",
        "Tax Invoice for your Flight Booking id",
    ];
    private $providersCode;
    private $reBody = 'MakeMyTrip';
    private $reBody2 = [
        "en"=> "Invoice",
    ];
    private $detectBody = [
        "en" => "Invoice",
    ];
    private $pdfPattern = ".*\.pdf";

    public function parsePdf(Email $email)
    {
        $text = str_replace(chr(194) . chr(160), " ", $this->text);
        // https://support.makemytrip.com/PrintInvoice.aspx                                                                                            1/2
        // 3/3/2016                                                                Invoice Mailer
        $text = preg_replace("#\n\n[^\n]*https://support.makemytrip.com/PrintInvoice.aspx\s+\d+/\d+\n" .
                            "\d+/\d+/\d{4}\s+Invoice Mailer#", "", $text);

        preg_match_all("#Flight Details\n(.*?)Fare Details#ms", $text, $parts);

        if (count(array_filter($parts)) === 0) {
            preg_match_all("#Flight Details\n(.*?)PAYMENT BREAKUP#ms", $text, $parts);
        }
        $segments = [];
        $passengers = [];
        $pnrs = [];

        foreach ($parts[1] as $stext) {
            $tableText = $this->re("#(.*?)Passengers:#ms", $stext);
            $details = array_filter(preg_split("/Flight\s+Details/u", $tableText));

            foreach ($details as $detail) {
                $segmentsText = $this->split("/(?:^|\n)((?:.{0,40}\n)*.*[A-Z]{3}\s+[A-Z]{3}\s*\n.+)/u", $detail);

                foreach ($segmentsText as $segText) {
                    $pos = $this->TableHeadPos($this->re("#(.*[A-Z]{3}\s+[A-Z]{3})#", $segText));
                    $pos[] = 0;
                    sort($pos);
                    $pos = array_merge([], $pos);
                    $seg = $this->splitCols($segText, $pos);
                    $seg = array_values(array_filter($seg, function ($v) {
                        return !empty(trim($v));
                    }));

                    if (preg_match("/^(\s+)$/s", $seg[0])) {
                        unset($seg[0]);
                        $seg = array_values($seg);
                    }

                    if (count($seg) != 3) {
                        $this->logger->debug("incorrect table parse");

                        return;
                    }
                    $segments[] = $seg;
                }
            }

            $passengersText = $this->re("#Passengers:(.+)#ms", $stext);

            if (preg_match_all("#\d+\s*\.\s*(.*?)(?:\s{2,}|\s\(|\n)#", $passengersText, $m)) {
                $passengers = array_merge($passengers, $m[1]);
            }

            if (preg_match_all("#\d+\s*\.\s*.*?PNR: *([A-Z\d]{5,7})\s*\)#", $passengersText, $m)) {
                $pnrs = array_unique(array_merge($pnrs, $m[1]));
            }
        }

        $flight = $email->add()->flight();

        //General
        $confirmationNumber = $this->re("#Booking Date\s+.*?\s{2,}(.*?)\s{2,}#ms", $text);
        $dateReservation = ($this->re("#Booking Date\s+.*?\s{2,}.*?\s{2,}(.*?)\n#ms", $text));

        if (empty($confirmationNumber) && empty($dateReservation)) {
            $bookedText = $this->cutText('Booked by', 'Flight Details', $text);
            $tableBooked = $this->SplitCols($bookedText, $this->ColsPos($this->re("/^.+\n/", $bookedText)));

            if (isset($tableBooked[1]) && isset($tableBooked[2])) {
                $confirmationNumber = $this->re("/Booked\sID\s+([A-Z\d]+)/s", $tableBooked[1]);
                $dateReservation = $this->re("/Booked\sDate\s+(\d+\-\d+\-\d+\sT\s[\d\:]+\sA?P?M)/s", $tableBooked[2]);

                if (empty($dateReservation)) {
                    $dateReservation = $this->re("/Booked\sDate\s+(\w+\s+\w+\s+\d{2}\s+[\d\:]+\s\w+\s\d{4})/s", $tableBooked[2]);
                }
            }
        }

        $flight->ota()
            ->confirmation($confirmationNumber);

        if (!empty($pnrs)) {
            foreach ($pnrs as $pnr) {
                $flight->general()
                    ->confirmation($pnr);
            }
        } else {
            $flight->general()
                ->noConfirmation();
        }
        $flight->general()
//			->confirmation($confirmationNumber)
            ->date(strtotime($this->normalizeDate($dateReservation)))
            ->travellers(array_unique($passengers));

        //Price
        $total = $this->re("#Grand Total\:?\s+(?:[A-Z]+\s+)?([\d\.]+)#us", $text);

        if (empty($total) && empty($this->re("#Grand Total\:? +(.+)#us", $text))) {
            $total = $this->re("#Total Booking Amount\:?\s+(?:[A-Z]+\s+)?([\d\.]+)#us", $text);
        }

        if (!empty($total)) {
            $flight->price()
                ->total($total);
        }

        $cost = $this->re("#Total Fare \(All Passenger\)\s*:\s+(?:[A-Z]+\s+)?([\d\.]+)#us", $text);

        if (!empty($cost)) {
            $flight->price()
                ->cost($cost);
        }

        $currency = $this->re("#(?:Grand Total|Total Booking Amount)\:?\s+(?:([A-Z]+)\s+)?[\d\.]+#us", $text);

        if (!empty($currency)) {
            $flight->price()
                ->currency($currency);
        }

        $feeText = $this->cutText('Total Fare (All Passenger)', 'Grand Total', $text);

        if (empty($feeText)) {
            $feeText = $this->cutText('Total Booking Amount', 'Grand Total', $text);
        }
        $feeText = $this->re("/^.+\n\s*([\n\s\S]+)$/", $feeText);

        if (preg_match_all("/(.+\:?\n?\s*(?:[A-Z]{3}\s*)?[\d\.\,]+)/", $feeText, $fees)) {
            foreach ($fees[1] as $fee) {
                $feeName = $this->re("/(.+)\:?\n?\s{4,}(?:[A-Z]{3}\s*)?\s[\d\.\,]+/", $fee);
                $feeSum = $this->re("/.+\:?\n?\s{4,}(?:[A-Z]{3}\s*)?\s([\d\.\,]+)/", $fee);

                if (!empty($feeName) && !empty($feeSum) && $feeSum > 0) {
                    $flight->price()
                        ->fee($feeName, $feeSum);
                }
            }
        }

        $discount = $this->re("/\D+\:\s+\-\s+(?:[A-Z]+\s*)?([\d\.]+)/", $feeText);

        if (!empty($discount)) {
            $flight->price()
                ->discount($discount);
        }

        foreach ($segments as $table) {
            $seg = $flight->addSegment();

            //Airline
            $flightNumber = $this->re("#\n\w{2}\s*­\s*(\d+)\n#", $table[0]);

            if (empty($flightNumber)) {
                $flightNumber = $this->re("#\w{2}\s?\-\s?(\d+)#u", $table[0]);
            }

            // AirlineName
            $airlineName = $this->re("#\n(\w{2})\s*­\s*\d+\n#", $table[0]);

            if (empty($airlineName)) {
                $airlineName = $this->re("#(\w{2})\s?\-\s?\d+#u", $table[0]);
            }

            $seg->airline()
                ->name($airlineName)
                ->number($flightNumber);

            $operator = trim($this->re("#^([A-Za-z\d\s]+)\n\w{2}\s*­\s*\d+\n#", $table[0]));

            if (empty($operator)) {
                $operator = trim($this->re("#^([A-Za-z\d\s]+)\s+\w{2}\s?\-\s?\d+#", $table[0]));
            }

            if (!empty($operator)) {
                $seg->airline()
                    ->operator($operator);
            }

            //Departure
            $seg->departure()
                ->code($this->re("#(?:^|\n)([A-Z]{3})\n#", $table[1]));

            if ($name = $this->re("#(?:^|\n)[A-Z]{3}\n([^\d\n]{3,})#", $table[1])) {
                $seg->departure()->name(str_replace("\n", ' ', $name));
            }

            $depDate = $this->re("/([^\s\d]+,\s+\d+\s+[^\s\d]+\s+\d{2},\s+\d+:\d+\s+hrs)/", $table[1]);

            if (empty($depDate)) {
                $depDate = $this->re("/(\d+\-\d+\-\d+\sT\s[\d\:]+\sA?P?M)/", $table[1]);
            }

            if (empty($depDate)) {
                $depDate = $this->re("/(\w+\s+\w+\s+\d{2}\s+[\d\:]+\s\w+\s\d{4})/", $table[1]);
            }

            if (!empty($depDate)) {
                $seg->departure()
                    ->date(strtotime($this->normalizeDate($depDate)));
            } else {
                $seg->departure()
                ->noDate();
            }

            //Arrival
            $seg->arrival()
                ->code($this->re("#(?:^|\n)([A-Z]{3})\n#", $table[2]));

            if ($name = $this->re("#(?:^|\n)[A-Z]{3}\n([^\d\n]{3,})#", $table[2])) {
                $seg->arrival()->name(str_replace("\n", ' ', $name));
            }

            $arrDate = $this->re("/([^\s\d]+,\s+\d+\s+[^\s\d]+\s+\d{2},\s+\d+:\d+\s+hrs)/", $table[2]);

            if (empty($arrDate)) {
                $arrDate = $this->re("/(\d+\-\d+\-\d+\sT\s[\d\:]+\sA?P?M)/", $table[2]);
            }

            if (empty($arrDate)) {
                $arrDate = $this->re("/(\w+\s+\w+\s+\d{2}\s+[\d\:]+\s\w+\s\d{4})/", $table[2]);
            }

            if (!empty($arrDate)) {
                $seg->arrival()
                    ->date(strtotime($this->normalizeDate($arrDate)));
            } else {
                $seg->arrival()
                ->noDate();
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $providerParams) {
            if ($this->striposAll($headers["from"], $providerParams['from']) === false) {
                $this->providersCode = $code;

                continue;
            }

            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }
            // Ignore
            if (strpos($this->text, 'TICKET - Confirmed') !== false) {
                return false;
            }

            $foundProvider = false;

            foreach (self::$detectProvider as $code => $providerParams) {
                if ($this->striposAll($text, $providerParams['text']) !== false) {
                    $foundProvider = true;

                    break;
                }
            }

            if ($foundProvider === false) {
                continue;
            }

            foreach ($this->detectBody as $dBody) {
                if (strpos($text, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            if (strpos($this->text, 'TICKET - Confirmed') !== false) {
                // go to parse ticket(not invoise) in maketrip/FlightInvoice
                foreach ($email->getItineraries() as $itinerary) {
                    $email->removeItinerary($itinerary);
                }

                break;
            }

            $foundProvider = false;

            foreach (self::$detectProvider as $code => $providerParams) {
                if ($this->striposAll($this->text, $providerParams['text']) !== false) {
                    $this->providersCode = $code;
                    $foundProvider = true;

                    break;
                }
            }

            if ($foundProvider === false) {
                continue;
            }

            foreach ($this->detectBody as $lang => $re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;
                    $this->parsePdf($email);

                    break;
                }
            }
        }

        if (empty($this->providersCode)) {
            foreach (self::$detectProvider as $code => $providerParams) {
                if ($this->striposAll($parser->getCleanFrom(), $providerParams['from']) === false) {
                    $this->providersCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providersCode)) {
            $email->setProviderCode($this->providersCode);
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "#^[^\s\d]+,\s+(\d+)\s+([^\s\d]+)\s+(\d{2}),\s+(\d+:\d+)\s+hrs$#", //Wed, 25 May 16, 07:00 hrs
            "#^(\d{2})\-(\d{2})\-(\d{4})\sT\s([\d\:]+)\sA?P?M$#u", // 22-05-2020 T 12:10:59 PM
            "#^\w+\s+(\w+)\s+(\d{2})\s+([\d\:]+)\s\w+\s(\d{4})$#u", // Fri May 24 09:37:00 IST 2019
            "#^\w+\,\s(\d+)\s(\w+)\s+(\d+)\,\s([\d\:]+\sA?P?M)$#u", // Sat, 25 January 14, 12:00 PM
        ];
        $out = [
            "$1 $2 20$3, $4",
            "$1.$2.$3, $4",
            "$2 $1 $4, $3",
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->TableHeadPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
