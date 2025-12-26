<?php

namespace AwardWallet\Engine\rosewood\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationLetter extends \TAccountChecker
{
    public $mailFiles = "rosewood/it-611237908.eml";
    public $subjects = [
        'Confirmation letter ',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rosewoodhotels.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "Rosewood Hotel") !== false
                && (strpos($text, 'RESERVATION DETAILS') !== false)
                && (strpos($text, 'ROOM TYPE:') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rosewoodhotels\.com$/', $from) > 0;
    }

    public function ParseHotelPDF(Email $email, string $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/{$this->addSpacesWord('CONFIRMATION NUMBER:')}\s*([A-Z\d]{10,})/", $text))
            ->traveller(preg_replace("/^(?:Mr\.|Mrs\.|Ms\.)/", "", $this->re("/{$this->opt($this->t('NAME: '))}\s*(.+)/", $text)));

        $guestsInfo = trim($this->re("/{$this->opt($this->t('GUESTS:'))}\s*(.+)/", $text));

        if (preg_match("/^(?<guests>\d+)\s*Adults?$/", $guestsInfo, $m)
            || preg_match("/^(?<guests>\d+)\s*Adults?[\s+]*(?<kids>\d+)\s*Children$/", $guestsInfo, $m)) {
            $h->booked()
                ->guests($m['guests']);

            if (isset($m['kids']) && !empty($m['kids'])) {
                $h->booked()
                    ->kids($m['kids']);
            }
        }

        $hotelInfo = $this->re("/A SENSE OF DISCOVERY\n+(.+)\n\s*.*@/s", $text);
        $hotelInfo = str_replace("CONNECT WITH US", "", $hotelInfo);
        $hotelInfo = preg_replace("/^(\s+)/m", "", $hotelInfo);

        if (preg_match("/^(?<hotelName>(?:[A-Z\d\s]+\n){1,4})(?<address>(?:.+\n){1,2})View Map\n(?<phone>[+\d\s]*)/", $hotelInfo, $m)) {
            $h->hotel()
                ->name(preg_replace("/(\s+)/s", " ", $m['hotelName']))
                ->address(preg_replace("/(\s+)/s", " ", $m['address']))
                ->phone($m['phone']);
        }

        $inDate = $this->re("/{$this->opt($this->t('ARRIVAL:'))}\s*(.+)/", $text);
        $outDate = $this->re("/{$this->opt($this->t('DEPARTURE:'))}\s*(.+)/", $text);

        $inTime = $this->re("#{$this->opt($this->t('CHECK IN / OUT TIME:'))}\s*(\d+a?p?\.m\.)\s*\/#u", $text);
        $outTime = $this->re("#{$this->opt($this->t('CHECK IN / OUT TIME:'))}\s*\d+a?p?\.m\.\s*\/\s*(\d+\s*a?p?\.m?\.)\s+#u", $text);

        $h->booked()
            ->checkIn($this->normalizeDate($inDate . ', ' . $inTime))
            ->checkOut($this->normalizeDate($outDate . ', ' . $outTime));

        $roomType = $this->re("/{$this->opt($this->t('ROOM TYPE:'))}\s*(.+)/", $text);

        if (!empty(trim($roomType))) {
            $h->addRoom()->setType($roomType);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseHotelPDF($email, $text);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s*(?:t\s*h|n\s*d)\s*(\w+)\s*(\d{4})\,\s*(\d+)\s*(a?p?)\.(m)\.$#u", //30th Nov 2023, 4p.m.
        ];
        $out = [
            "$1 $2 $3, $4$5$6",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function addSpacesWord($text)
    {
        return preg_replace("#(\w)#u", '$1 *', $text);
    }
}
