<?php

namespace AwardWallet\Engine\navan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "navan/it-634700339.eml";
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

            if ((strpos($text, 'Navan Inc.') !== false || strpos($text, 'https://app.navan.com') !== false)
                && strpos($text, 'Hotels Summary') !== false
                && strpos($text, 'Star Hotel') !== false
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
            ->confirmation($this->re("/Hotel confirmation\:\s*([A-Z\d\-]{6,})/", $text))
            ->traveller($this->re("/Guest\:\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n/", $text));

        $account = $this->re("/Loyalty program\:\s*\D*(\d+)/", $text);

        if (!empty($account)) {
            $h->setAccountNumbers([$account], false);
        }

        if (preg_match("/Total\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\n/", $text, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->re("/Taxes\s*(?<total>[\d\.\,]+)\s*[A-Z]{3}/", $text);

            if (!empty($tax)) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $cost = $this->re("/Subtotal\s*(?<total>[\d\.\,]+)\s*[A-Z]{3}/", $text);

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $fee = $this->re("/Trip Fee\s*(?<total>[\d\.\,]+)\s*[A-Z]{3}/", $text);

            if (!empty($fee)) {
                $h->price()
                    ->fee('Trip Fee', PriceHelper::parse($fee, $m['currency']));
            }
        }

        $rooms = $this->re("/Number of rooms\s*(\d+)/", $text);

        if (!empty($rooms)) {
            $h->booked()
                ->rooms($rooms);
        }

        if (preg_match("/Check-out\:.+\n*\s*.*A?P?M\n+\s*(?<hotelName>.+)\n\s+(?<address>(?:.+\n){1,3})\s*(?<phone>[+][\d\-]+)/", $text, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $checkIn = $this->re("/Check-in:\s*(.+)\b[ ]{5,}/", $text);
        $checkOut = $this->re("/Check-out:\s*(.+\n*\s*.*A?P?M)\b/", $text);

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        if (preg_match("/Your room\n\s*(?<roomType>.+)\n\s*(?<roomDescription>.+)/", $text, $m)) {
            $room = $h->addRoom();
            $room->setType($m['roomType']);
            $room->setDescription($m['roomDescription']);

            $rate = $this->re("/Price per night\s*(.+)/", $text);

            if (!empty($rate)) {
                $room->setRate($rate . ' / night');
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

    private function addSpacesWord($text)
    {
        return preg_replace("#(\w)#u", '$1 *', $text);
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#u", //Sun, Apr 14, 2024 at 3:00PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
