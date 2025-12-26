<?php

namespace AwardWallet\Engine\ufly\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "ufly/it-13358992.eml, ufly/it-13410247.eml, ufly/it-4428352.eml, ufly/it-4449281.eml";

    private $reBody = [
        'en' => ['Departing ', 'Information'],
    ];
    private $pdf;
    private $pdfNamePattern = '.*\.pdf';

    private $lang = '';
    private static $dict = [
        'en' => [
            //			'Reservation Code:' => '',
            //			'Rewards #' => '',
            //			'Flight Information' => '',
            //			'Flight Record Locator:' => '',
            //			'Origin Airport:' => '',
            //			'Destination Airport:' => '',
            //			'Date of Departure:' => '',
            //			'Date of Arrival:' => '',
            //			'Flight Number:' => '',
            //			'Trip Duration:' => '',
            //			'Number of stops:' => '',
            //			'Cabin Class of Service:' => '',
            //			'Seat assignment:' => '',
            //			'Customer Receipt' => '',
            //			'Date Booked:' => '',
            //			'Total Price' => '',
            //			'Price' => '',
            //			'Tax details:' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                } else {
                    continue;
                }

                if (isset($this->reBody)) {
                    foreach ($this->reBody as $lang => $reBody) {
                        if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                            $this->lang = $lang;

                            break;
                        }
                    }
                }

                if (stripos($text, $this->t('Flight Information')) !== false) {
                    $this->flight($text, $email);
                }

                if (stripos($text, $this->t('Customer Receipt')) !== false) {
                    $this->parsePrice($text, $email);
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $html = '';

        foreach ($pdfs as $pdf) {
            if (($html .= \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
            } else {
                continue;
            }
        }

        return stripos($html, "Sun Country Airlines");
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Travel documents') !== false
        || isset($headers['from']) && stripos($headers['from'], 'suncountry.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "suncountry.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function flight($text, Email $email)
    {
        $f = $email->add()->flight();

        if (preg_match("#\s+" . $this->t('Date Booked:') . "\s*(.+?)\s*\|#", $text, $m)) {
            $f->general()->date(strtotime($m[1] . " 00:00"));
        }

        if (preg_match("#" . preg_quote($this->t('Rewards #'), '#') . "\s+((?:\s*\d+\s+.+\n)+)#", $text, $m)
                && preg_match_all("#^\s*\d+\s+(.+)[ ]{2,}(?:([\d -]{5,})|.*)$#m", $m[1], $mat)) {
            $f->general()->travellers($mat[1], true);
            $f->program()->accounts(array_filter($mat[2]), false);
        }

        if (preg_match("#\s+" . $this->t('Reservation Code:') . "[ ]+([A-Z\d]{5,})\s+#", $text, $m)) {
            $f->general()->confirmation($m[1], trim($this->t('Reservation Code:'), ' :'));
        }

        if (preg_match("#\s+" . $this->t('Flight Record Locator:') . "[ ]+([A-Z\d]{5,})\s+#", $text, $m)) {
            $f->general()->confirmation($m[1], trim($this->t('Flight Record Locator:'), ' :'));
        }

        $segments = [];
        $segmentsBig = $this->split("#\n\s*\w*\s*(" . $this->t('Flight Information') . ")#u", $text);

        foreach ($segmentsBig as $value) {
            $count = substr_count($value, $this->t('Flight Number:'));

            if ($count == 1) {
                $segments[] = $value;
            } elseif ($count > 1) {
                $segments = array_merge($segments, $this->split("#\n\s*(" . $this->t('Flight Number:') . ")#", $text));
            }
        }

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("#" . $this->t('Flight Number:') . "\s*([A-Z\d]{2})\s*(\d+)#", $stext, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            // Departure
            if (preg_match("#" . $this->t('Origin Airport:') . "\s*(.+)(?:,\s*Terminal\s*(.+)\s*)?\(([A-Z]{3})\)#U", $stext, $m)) {
                $s->departure()
                    ->code($m[3])
                    ->name(trim($m[1]))
                    ->terminal(trim($m[2]), true, true);
            }

            if (preg_match("#" . $this->t('Date of Departure:') . "[ ]*(.+)#", $stext, $m)) {
                $s->departure()
                    ->date(strtotime($this->normalizeDate($m[1])));
            }

            // Arrival
            if (preg_match("#" . $this->t('Destination Airport:') . "\s*(.+)(?:,\s*Terminal\s*(.+)\s*)?\(([A-Z]{3})\)#U", $stext, $m)) {
                $s->arrival()
                    ->code($m[3])
                    ->name(trim($m[1]))
                    ->terminal(trim($m[2]), true, true);
            }

            if (preg_match("#" . $this->t('Date of Arrival:') . "[ ]*(.+)#", $stext, $m)) {
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($m[1])));
            }

            // Extra
            if (preg_match("#" . $this->t('Trip Duration:') . "[ ]*(.+)#", $stext, $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            if (preg_match("#" . $this->t('Number of stops:') . "[ ]*(\d+)#", $stext, $m)) {
                $s->extra()
                    ->stops($m[1]);
            }

            if (preg_match("#" . $this->t('Cabin Class of Service:') . "[ ]*(.+)#", $stext, $m)) {
                $s->extra()
                    ->cabin(trim(str_ireplace('class', '', $m[1])));
            }

            if (preg_match("#" . preg_quote($this->t('Seat assignment:')) . "\s+((?:\s*\w+\s*\d+:\s*\d{1,3}[A-Z]+\s*\n)+)#", $stext, $m)
                && preg_match_all("#^.*?:\s*(\d{1,3}[A-Z]+)\s*$#m", $m[1], $mat)) {
                $s->extra()->seats($mat[1], true);
            }
        }

        return $email;
    }

    private function parsePrice($text, Email $email)
    {
        if (preg_match("#\s+" . $this->t('Price') . ":?[ ]+(.+)#", $text, $m)) {
            $email->price()->cost($this->amount($m[1]));
            $email->price()->currency($this->currency($m[1]));
        }

        if (preg_match("#\s+" . $this->t('Total Price') . ":?[ ]*(.+)#", $text, $m)) {
            $email->price()->total($this->amount($m[1]));
            $email->price()->currency($this->currency($m[1]));
        }

        if (preg_match("#" . $this->t('Tax details:') . "\s+([\s\S]+?)" . $this->t('Total Price') . "#", $text, $m)
                && preg_match_all("#^\s*(\d[\d .]+)[ ]+([A-Z]{3})[ ]+(.+)$#m", $m[1], $mat)) {
            $email->price()->currency($mat[2][0]);

            foreach ($mat[0] as $key => $value) {
                $email->price()->fee(trim($mat[3][$key]), $this->amount(trim($mat[1][$key])));
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*[^\d\s]+[,\s]+([^\d\s\.\,]+)\s++(\d+)[,\s]+(\d{4})\s+(\d+:\d+(\s*[APM]{2})?)$#", //Friday, Jul 06, 2018 01:37PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
