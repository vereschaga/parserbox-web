<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StayFolioPdf extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-218417839.eml, hhonors/it-218573392.eml, hhonors/it-219084098.eml, hhonors/it-219736109.eml";

    public $reFrom = "no-reply@hilton.com";
    public $reSubject = [
        // en
        "We hope you enjoyed your stay at the",
    ];
    public $reBody = ['Hilton Honors', '@Hilton.com'];
    public $langDetectorsPdf = [
        "en"=> ["Guest Folio"],
    ];
    public $emailSubject;
    public $pdfPattern = '.*\..*p.*d.*f';

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = '';

    public function parsePdf(Email $email, $text)
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("/Confirmation Number [- ]+(\d{5,})\s+/", $text))
            ->traveller(trim($this->re("/\n\s*Guest Name +(([A-Za-z\']+(?: ?[\-,\.])? ?)+?)(?: {3,}|\n)/", $text)))
        ;

        // Hotel
        $hotelText = $this->re("/^([\s\S]+)\n *Guest Folio\n/", $text);
        $hotelText = preg_replace(["/^( {0,30}\S.*?) {3,}.*$/m", "/^( ) {40,}\S.*$/m"], '$1', $hotelText);

        if (!empty($hotelText)) {
            $h->hotel()
                ->phone($this->re("/\n *(\d{5,})\n.*@/", $hotelText))
            ;
            $hotelText = preg_replace("/\n *\d{5,}\n.*@[\s\S]*/", '', $hotelText);
            $ha = explode("\n", $hotelText);
            if (count($ha) == 2) {
                $h->hotel()
                    ->name($ha[0])
                    ->address($ha[1])
                ;
            } else {
                $name = $this->re("/ your stay at the (.+) - come again soon/", $this->emailSubject);
                if (empty($name)) {
                    $name = $this->http->FindSingleNode("//text()[contains(., 'stay with us here at the')]",
                        null, true, "/stay with us here at the (.+?)\./");
                }

                $address = $this->re("/^\s*".str_replace([' ', '\-', ','], ['\s+', '\s*-\s*', '\s*,\s*'], preg_quote($name))."\n([\s\S]+)/", $hotelText);

                if (!empty($name) && !empty($address)) {
                    $h->hotel()
                        ->name($name)
                        ->address($address)
                    ;
                }
            }

        }

        // Program
        $account = $this->re("/ {3,}Hilton Honors\n.+ {3,}\S.* {3,}\w+\n.+ {3,}\S.* {3,}(\d{5,})\n/", $text);
        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        // Booked
        $date = $this->re("/\n *Check In Date +(\w+[ ,]+\w+[ ,]+\d{4})\s+/", $text);
        $time = $this->re("/\n *Check In Time +(\d{1,2}:\d{2}(?: *[apAP][mM])?)\s+/", $text);
        $h->booked()
            ->checkIn(strtotime($date . ', ' . $time))
        ;
        $date = $this->re("/\n *Check Out Date +(\w+[ ,]+\w+[ ,]+\d{4})\s+/", $text);
        $time = $this->re("/\n *Check Out Time +(\d{1,2}:\d{2}(?: *[apAP][mM])?)\s+/", $text);
        $h->booked()
            ->checkOut(strtotime($date . ', ' . $time))
        ;
        $h->booked()
            ->guests($this->re("/\n\s*Guests +(\d+)\\/\d+\s+/", $text))
            ->kids($this->re("/\n\s*Guests +\d+\\/(\d+)\s+/", $text))
        ;


        // Price
        if (preg_match("/^ *(?:\S ?)+ {4,}Charge {4,}GUEST ROOM {4,}/m", $text)) {
            if (preg_match_all("/^ *(?:\S ?)+ {4,}Payments {4,}(?:\S ?)+ {4,}\((.+)\)/m", $text, $mTotal)) {
                $total = 0.0;
                foreach ($mTotal[1] as $value) {
                    if (preg_match("/^\s*(?<currency>[^\d\s]\D{0,4}?)\s*(?<amount>\d[,.‘\'\d ]*)\s*$/u", $value, $m)
                        || preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*)\s*(?<currency>[^\d\s]\D{0,4}?)\s*$/u", $value, $m)
                    ) {
                        $total += PriceHelper::parse($m['amount'], $this->currency($m['currency']));
                        $currency = $this->currency($m['currency']);
                    } else {
                        $total = null;
                        break;
                    }
                }
                if ($total !== null) {
                    $h->price()
                        ->total($total)
                        ->currency($currency);
                }
            }
            if (preg_match_all("/^ *(?:\S ?)+ {4,}(?:Tax|Charge) {4,}(?<name>(?:\S ?)+) {4,}(?<value>.+)$/m", $text, $mTax)) {
                $this->logger->debug('$mTax = '.print_r( $mTax,true));
                foreach ($mTax[0] as $i => $value) {
                    if (preg_match("/^GUEST ROOM/iu", $mTax['name'][$i])) {
                        continue;
                    }
                    if (preg_match("/^\s*(?<currency>[^\d\s]\D{0,4}?)\s*(?<amount>\d[,.‘\'\d ]*)\s*$/u", $mTax['value'][$i], $m)
                        || preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*)\s*(?<currency>[^\d\s]\D{0,4}?)\s*$/u", $mTax['value'][$i], $m)
                    ) {
                        $h->price()
                            ->currency($this->currency($m['currency']));
                        $h->price()
                            ->fee($mTax['name'][$i], PriceHelper::parse($m['amount'], $this->currency($m['currency'])));
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->arrikey($textPdf, $this->reBody) === false) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $textPdfFull = '';
        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLangPdf($textPdf)) {
                $textPdfFull .= $textPdf;
            }
        }

        if (!$textPdfFull) {
            return false;
        }

        $this->parsePdf($email, $textPdfFull);

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

    private function assignLangPdf($text = ''): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+)$#", //08 June 2017, 09:45
        ];
        $out = [
            "$1 $2 $3, $4",
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function currency($s)
    {
        if ($code = $this->re("#\b([A-Z]{3})\b$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
