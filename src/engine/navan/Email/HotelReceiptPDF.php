<?php

namespace AwardWallet\Engine\navan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "navan/it-636906127.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Totals' => ['Totals', 'Est. total', 'Est. total'],
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

            if (strpos($text, 'Navan, Inc.') !== false
                && strpos($text, 'Hotel') !== false
                && strpos($text, 'Receipt Date') !== false
                && strpos($text, 'Reference #') !== false
                && preg_match("/{$this->addSpacesWord('Merchant')}/", $text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]navan\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/Confirmation\:\s*([A-Z\d\-]{6,})/", $text))
            ->traveller($this->re("/Traveller\s*(.+)\n/", $text));

        $dateReservation = $this->re("/^(.+\d{4})\s+\-\s*Booking Date/mu", $text);

        if (empty($dateReservation)) {
            $dateReservation = $this->re("/Receipt Date\s*(.+\d{4})/mu", $text);
        }

        if (!empty($dateReservation)) {
            $h->general()
                ->date(strtotime($dateReservation));
        }

        $price = $this->re("/(?:A?P?M)?\s*\n+\s*Totals\s*(\D*[\d\.\,]+)\n+\s*{$this->opt($this->t('Payment methods'))}/", $text);

        if (empty($price)) {
            $price = $this->re("/(?:A?P?M)?\s*\n+\s*{$this->opt($this->t('Totals'))}\s*(\D*[\d\.\,]+)\n+\s*{$this->opt($this->t('All prices are listed in'))}/", $text);
        }

        if (empty($price)) {
            $price = $this->re("/\n+\s*{$this->opt($this->t('Totals'))}\s*(\D*[\d\.\,]+)/", $text);
        }

        if (preg_match("/(?<currency>\D*)(?<total>[\d\.\,]+)/", $price, $m)) {
            $currency = $this->re("/All prices are listed in\s*([A-Z]{3})/", $text);

            if (empty($currency)) {
                $currency = $this->normalizeCurrency($m['currency']);
            }
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $hotelText = $this->re("/^([ ]{1,10}Merchant\s*.+(?:All prices are listed in\s*[A-Z]{3}|[*]This summary of charges is an.*)\n)/msu", $text);

        $hotelTable = $this->SplitCols($hotelText);

        if (preg_match("/Merchant\n+(?<hotelName>.+\n*\D{0,10})\n(?<address>(?:.+\n){1,4})\s*Check in/", $hotelTable[0], $m)) {
            $h->hotel()
                ->name(str_replace("\n", " ", $m['hotelName']))
                ->address(str_replace("\n", " ", $m['address']));
        }

        $checkIn = $this->re("/Check in\s*(.+\d{4})/", $hotelTable[0]);
        $checkOut = $this->re("/Check out\s*(.+\d{4})/", $hotelTable[0]);

        $h->booked()
            ->checkIn(strtotime($checkIn))
            ->checkOut(strtotime($checkOut));

        if (preg_match_all("/\s(\d+)x\s/", $text, $m)) {
            $h->booked()
                ->rooms(array_sum($m[1]));
        }

        $roomTypeText = $this->re("/{$this->addSpacesWord('Description')}\n+(.+)\n+{$this->addSpacesWord('Resort fee')}/su", $hotelTable[1]);

        if (empty($roomTypeText)) {
            $roomTypeText = $this->re("/{$this->addSpacesWord('Description')}\n+(.+)\n+{$this->addSpacesWord('Totals')}/su", $hotelTable[1]);
        }

        if (preg_match_all("/\d+x\s*(.+\n{1,2}\D*)(?:\n|$)/", $roomTypeText, $match)) {
            foreach ($match[1] as $roomType) {
                $h->addRoom()->setType(str_replace("\n", "", $roomType));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $otaConf = $this->re("/Reference [#]\s*(\d{4,})/", $text);

            if (!empty($otaConf)) {
                $email->ota()
                    ->confirmation($otaConf);
            }

            $this->ParseFlightPDF($email, $text);
        }

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8') - 4;
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $str = mb_substr($row, $p, null, 'UTF-8');

                if ($trim) {
                    $str = trim($str);
                }
                $cols[$k][] = $str;
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function addSpacesWord($text)
    {
        return preg_replace("#(\w)#u", '$1 *', $text);
    }
}
