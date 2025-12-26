<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelPDF extends \TAccountChecker
{
    public $mailFiles = "expedia/it-804169347.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "TAAP Trip Details") !== false
                && (strpos($text, 'Property') !== false)
                && (strpos($text, 'Check-in and Check-out') !== false)
                && (strpos($text, 'Price details') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mtatravel\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParsePDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParsePDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/Itinerary number\:\s+(\d{10,})/", $text))/*
            ->traveller($this->re("/Primary traveller.+\n\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]{2}/", $text))*/;

        if (preg_match_all("/Room\s+\d+\:?\n\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+[•]/", $text, $matches)) {
            $h->general()
                ->travellers(array_unique($matches[1]));
        }

        $cancellationPolicy = $this->re("/(Free cancellation until.+)\n/", $text);

        if (!empty($cancellationPolicy)) {
            $h->general()
                ->cancellation($cancellationPolicy);
        }

        $h->hotel()
            ->name($this->re("/Stay at\s+(.+)\n/", $text));

        $posColumn = strlen($this->re("/^(.+)Price details\n/m", $text));
        $table = $this->SplitCols($text, [0, $posColumn]);

        $h->hotel()
            ->address($this->re("/Itinerary number:(?:.+\n*){1,4}[ ]+{$h->getHotelName()}\s*\n\s*(.+)\n/", $table[0]));

        if (preg_match("/[•]\s+(?<rooms>\d+)\s*rooms?\s*[•]\s*\d+\s*guests/u", $table[0], $m)) {
            $h->booked()
                ->rooms($m['rooms']);
        }

        if (preg_match_all("/Room\s+\d+\:?\n\s+[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\s+[•]\s+(\d+)\s+adult/u", $text, $matches)) {
            $h->booked()
                ->guests(array_sum($matches[1]));
        }

        if (preg_match("/Check-in\s+Check-out\s*\n\s+\w+\,\s+(?<checkIn>\d+\s*\w+\s*\d{4})\s+\w+\,\s+(?<checkOut>\d+\s*\w+\s*\d{4})/", $table[0], $m)) {
            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']));

            if (preg_match("/Check-in\s+Check-out\n\s+Check-in time starts at\s+(?<inTime>[\d\:\.]+\s*A?P?M)\s+Check-out time is\s+(?<outTime>.+)\n/", $table[0], $m)) {
                if (stripos($m['outTime'], 'noon') !== false) {
                    $m['outTime'] = '12:00';
                }

                $h->booked()
                    ->checkIn(strtotime($m['inTime'], $h->getCheckInDate()))
                    ->checkOut(strtotime($m['outTime'], $h->getCheckOutDate()));
            }
        }

        $this->detectDeadLine($h);

        if (preg_match("/Total\s+(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)\n/", $table[1], $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }

        if (preg_match_all("/(.+\n+\d+)\s*rooms?\s+x\s+\d+\s*night/", $table[1], $matches)) {
            foreach ($matches[1] as $roomInfo) {
                if (preg_match("/(?<roomType>.+)\n+(?<roomCount>\d+)/", $roomInfo, $m)) {
                    for ($i = 0; $i < intval($m['roomCount']); $i++) {
                        $h->addRoom()->setType($m['roomType']);
                    }
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function SplitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        /*if (preg_match("/We\'re unable to refund your stay if your plans change/", $cancellationText)) {
            $h->setNonRefundable(true);
        }*/

        if (preg_match("/Free cancellation until\s+(?<date>\d+\s*\w+\s*\d{4})\s+at\s+(?<time>[\d\:]+\s*a?p?m)\s+\(/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
