<?php

namespace AwardWallet\Engine\navan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "navan/it-434914785.eml, navan/it-435021412.eml";
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

            if (strpos($text, 'Navan, Inc.') !== false
                && (strpos($text, 'Tax Invoice') !== false
                    || strpos($text, 'Invoice #') !== false
                    || strpos($text, 'Reference #') !== false)
                && preg_match("/{$this->addSpacesWord('Ticket #')}/", $text)
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
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Booking ID\s*([A-Z\d]{6})/", $text))
            ->traveller($this->re("/Traveller\s*(.+)\n/", $text));

        $dateReservation = $this->re("/^(.+\d{4})\s+\-\s*Booking Date/mu", $text);

        if (!empty($dateReservation)) {
            $f->general()
                ->date(strtotime($dateReservation));
        }

        $price = $this->re("/(?:A?P?M)?\s*\n+\s*Totals\s*(\D*[\d\.\,]+)\n+\s*{$this->opt($this->t('Payment methods'))}/", $text);

        if (empty($price)) {
            $price = $this->re("/(?:A?P?M)?\s*\n+\s*Totals\s*(\D*[\d\.\,]+)\n+\s*{$this->opt($this->t('All prices are listed in'))}/", $text);
        }

        if (empty($price)) {
            $price = $this->re("/\n+\s*Totals\s*(\D*[\d\.\,]+)/", $text);
        }

        if (preg_match("/(?<currency>\D*)(?<total>[\d\.\,]+)/", $price, $m)) {
            $currency = $this->re("/All prices are listed in\s*([A-Z]{3})/", $text);

            if (empty($currency)) {
                $currency = $this->normalizeCurrency($m['currency']);
            }
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        if (preg_match_all("/(.*\n.+\n+.+\n*\s+{$this->addSpacesWord('Ticket')}\s*[#]\d{10,}\n(?:.*\s+\w+\s*\d+\s*[•].+\n\s+[\d\:]+\s*A?P?M.+\n*){1,})/u", $text, $m)) {
            foreach ($m[1] as $segPart) {
                if (stripos($segPart, 'Seat assignment') !== false) {
                    continue;
                }

                $segPart = preg_replace("/^\n+/", "", $segPart);
                $flightInfo = $this->re("/^(.+)\n/", $segPart);
                $flightTable = $this->SplitCols($flightInfo);
                $airlineName = '';
                $cabin = '';

                if (count($flightTable) == 5) {
                    $airlineName = $flightTable[0];
                }

                if (preg_match_all("/{$this->addSpacesWord($this->opt($this->t('Ticket #')))}\s*(\d{10,})/", $segPart, $m)) {
                    $f->setTicketNumbers(array_unique($m[1]), false);
                }
                $segDate = $this->normalizeDate($this->re("/(\w+\s+\d+\,\s+\d{4})(?:\n|\s*\-)/", $segPart));

                //O ct 23 •
                $segPart = preg_replace("/\s([A-Z])\s([a-z]{2})\s+/", "$1$2", $segPart);

                if (preg_match_all("/\n*(\s+\w+\s*\d+\s*[•].+\n\s+[\d\:]+\s*[AP]M.+)\n/", $segPart, $match)) {
                    foreach ($match[1] as $seg) {
                        /*    Jul 10    •     Jul 10     SFO › RDU • UA1822
                              9:56 AM         6:11 PM*/

                        /*
                              Nov 5     •    Nov 5       SFO › O RD • 2627
                              2:14 PM        8:25 PM
                         * */
                        $reg = "/\s*(?<depDate>\w+\s*\d+)\s*[•]\s+(?<arrDate>\w+\s*\d+)\s*"
                              . "(?<depCode>[A-Z\s]{3,5})\s*[›]\s*(?<arrCode>[A-Z\s]{3,5})\s*[•]\s*"
                              . "(?<airlineName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))?\s*(?<flightNumber>\d{2,4})\n+\s*"
                              . "(?<depTime>[\d\:]+\s*A?P?M)\s*(?<arrTime>[\d\:]+\s*A?P?M)/u";

                        if (preg_match($reg, $seg, $m)) {
                            $s = $f->addSegment();

                            if (isset($m['airlineName']) && !empty($m['airlineName'])) {
                                $s->airline()
                                    ->name($m['airlineName']);
                            } elseif (!empty($airlineName)) {
                                $s->airline()
                                    ->name($airlineName);
                            } elseif (!empty($airlineName = $this->re("/^(.+)[ ]{30,}\S\d+.*\n\s*GST/", $segPart))) {
                                $s->airline()
                                    ->name($airlineName);
                            } elseif (!empty($airlineName = $this->re("/^(.+)[ ]{5,}Flight exchange/", $segPart))) {
                                $s->airline()
                                    ->name($airlineName);
                            } elseif (!empty($airlineName = $this->re("/^(.+)[ ]{10,}\d+\s*\D+Class\s+flight/", $segPart))) {
                                $s->airline()
                                    ->name($airlineName);
                            } elseif (!empty($airlineName = $this->re("/^(.+)[ ]{10,}\s*\D+flight/iu", $segPart))) {
                                $s->airline()
                                    ->name($airlineName);
                            } elseif (!empty($airlineName = $this->re("/^\s*(\D+)[ ]{50,}[$][\d\.\,]+.+\n.+\-\s*Booking\s*Date\s*{$this->addSpacesWord('Ticket')}/iu", $segPart))) {
                                $s->airline()
                                    ->name($airlineName);
                            }

                            $s->airline()
                                ->number($m['flightNumber']);

                            $s->departure()
                                ->code(str_replace(" ", "", $m['depCode']))
                                ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime'], $segDate));

                            $s->arrival()
                                ->code(str_replace(" ", "", $m['arrCode']))
                                ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime'], $segDate));

                            $cabin = $this->re("/[ ]{10,}\d+\s*(.+)\s+class flight/iu", $segPart);

                            if (!empty($cabin)) {
                                $s->setCabin($cabin);
                            }
                        }
                    }
                }
            }
        }

        $flightText = $this->re("/((?:Outbound Flight|Inbound Flight).+(?:Outbound Flight|Inbound Flight).+)\nHotel Details/s", $text);

        if (!empty($flightText)) {
            $flightParts = array_filter(preg_split("/(?:Outbound Flight|Inbound Flight).+Passengers/", $flightText));

            foreach ($flightParts as $flightPart) {
                if (preg_match("/(?<aName>[A-Z\d]{2})(?<aNumber>\d{2,4})\s*(?<cabin>\D+)[\d\-]+\n\s*\D+\((?<depCode>[A-Z]{3})\)\s*\D+\((?<arrCode>[A-Z]{3})\)\n(\D+(?<depDate>[\w\-]+))\D+(?<arrDate>[\w\-]+)\n\s*Departure\s*(?<depTime>[\d\:]+)\s*Arrival\s*(?<arrTime>[\d\:]+)\n*\s*(?:Operated\s*by\s*(?<operator>.+)|$)/su", $flightPart, $m)) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['aNumber']);

                    if (isset($m['operator'])) {
                        $s->airline()
                            ->operator($m['operator']);
                    }

                    $s->departure()
                        ->code($m['depCode'])
                        ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));

                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));

                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

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

    private function normalizeDate(?string $string, $relativeDate = null)
    {
        if (empty($string)) {
            return null;
        }
        // $this->logger->debug('$string 1 = '.print_r( $string,true));
        $year = date("Y", $relativeDate);
        $in = [
            // Dec 11, 2023
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*[,\s]\s*(\d{4})\s*$/ui',
            // Jan 9
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{1,2}:\d{2}( *[AP]M)?)\s*$/ui',
        ];

        // $year - for date without year and with week
        // %year% - for date without year and without week

        $out = [
            '$2 $1 $3',
            '$2 $1 %year%, $3',
        ];

        $string = preg_replace($in, $out, trim($string));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $string, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $string = str_replace($m[1], $en, $string);
            }
        }

        if (!empty($relativeDate) && $relativeDate > strtotime('01.01.2000') && strpos($string, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $string, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $string = EmailDateHelper::parseDateRelative($m['date'], $relativeDate);

            if (!empty($string) && !empty($m['time'])) {
                return strtotime($m['time'], $string);
            }

            return $string;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $string, $m)) {
            // $this->logger->debug('$date (week no year) = '.print_r( $string,true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $string)) {
            // $this->logger->debug('$date (year) = '.print_r( $string,true));
            return strtotime($string);
        } else {
            return null;
        }

        return null;
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
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
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
