<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourExperience extends \TAccountChecker
{
    public $mailFiles = "skywards/it-234590656.eml, skywards/it-502601913.eml, skywards/it-596561590.eml, skywards/it-611011763.eml";
    public $subjects = [
        '/Your\D*Experience is/',
    ];

    public $lang = 'en';
    public $pdfNamePattern = "(?:Itinerary|Emirates).*pdf";

    public $hotelNames = [];

    public $flightArray = [];
    public $lastSegment;

    public static $dictionary = [
        "en" => [
            'SERVICE' => ['SERVICE', 'Bed and breakfast'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mtatravel.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
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

            if (strpos($text, 'Emirates – Dubai Experience') !== false
                && strpos($text, 'United Arab Emirates') !== false
                && strpos($text, 'RESERVATION STATUS:') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
        // return preg_match('/[@.]mtatravel\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/\bPNR\:\s*([\dA-Z]{5,7})\n/", $text), 'PNR');

        $travellers = [];
        $paxCount = $this->re("/Passenger information\:\n*Lead passenger\:.*\n*Passengers\s\((\d+)\)/", $text);

        if (!empty($paxCount)) {
            $travellers = array_merge($travellers, explode("\n", $this->re("/Passengers\s\($paxCount\)\n((?:.+\n){" . $paxCount . "})/", $text)));
        }
        $paxCount2 = $this->re("/\n\s*Additional guests *\((\d+)\)/", $text);

        if (!empty($paxCount)) {
            $travellers = array_merge($travellers, explode("\n", $this->re("/Additional guests *\($paxCount2\)\n((?:.+\n){" . $paxCount2 . "})/", $text)));
        }
        $travellers = preg_replace("/\s*\(\s*\d+\s*\)\s*$/", '', $travellers);
        $f->general()
            ->travellers(array_filter(str_replace(['Ms.', 'Mr.', 'Mrs.'], '', $travellers)), true);

        $flightText = $this->re("/Flights:(.*)Passenger information/s", $text);

        $flightParts = array_filter(preg_split("/\n\n/", trim($flightText)));

        foreach ($flightParts as $flightPart) {
            $s = $f->addSegment();

            $flightTable = $this->splitCols($flightPart, $this->rowColsPos($this->re("/.+\n(.+)/", $flightPart)));

            if (preg_match("/^\s*(?<date>\d+\s*\w+)\s*(?<year>\d{2})\n(?<time>[\d\:]+)\n(?<depCode>[A-Z]{3})/", $flightTable[0], $m)) {
                $s->departure()
                    ->date(strtotime($m['date'] . ' 20' . $m['year'] . ', ' . $m['time']))
                    ->code($m['depCode']);
            }

            if (preg_match("/^\s*(?<date>\d+\s*\w+)\s*(?<year>\d{2})\n(?<time>[\d\:]+)\n(?<arrCode>[A-Z]{3})/", $flightTable[1], $m)) {
                $s->arrival()
                    ->date(strtotime($m['date'] . ' 20' . $m['year'] . ', ' . $m['time']))
                    ->code($m['arrCode']);
            } else {
                if (preg_match("/^\s*(?<date>\d+\s*\w+)\s*(?<year>\d{2})\n(?<time>[\d\:]+)\n/", $flightTable[1], $m)) {
                    $s->arrival()
                        ->date(strtotime($m['date'] . ' 20' . $m['year'] . ', ' . $m['time']));

                    if (preg_match("/^[A-Z]{3}\s+(?<arrCode>[A-Z]{3})/m", $flightPart, $m)) {
                        $s->arrival()
                            ->code($m['arrCode']);
                    }
                }
            }

            if (preg_match("/Flight\s*number\n(?<airlineName>[A-Z\d]{2})\s*(?<airlineNumber>\d{1,4})/", $flightTable[2], $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['airlineNumber']);
            }

            if (preg_match("/Class\n(?<cabin>\D+)\n*\((?<bookingCode>[A-Z])\)/", $flightTable[3], $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }

            if (empty($this->flightArray) || !in_array($s->getAirlineName() . $s->getFlightNumber() . $s->getDepCode() . $s->getArrCode(), $this->flightArray)) {
                $this->flightArray[] = $s->getAirlineName() . $s->getFlightNumber() . $s->getDepCode() . $s->getArrCode();
                $this->lastSegment = $s;
            } else {
                $f->removeSegment($s);
            }
        }
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $hotelsSeg = $this->split("/(.+\n {0,5}{$this->opt($this->t('Check in:'))})/", $text);
        // $this->logger->debug('$hotelsSeg = '.print_r( $hotelsSeg,true));

        foreach ($hotelsSeg as $hText) {
            $h = $email->add()->hotel();

            if (preg_match("/\,\s*Reference number:\s*(?<confNumber>\d{5})(,|\n|$)/", $hText, $m)) {
                $h->general()
                    ->confirmation($m[1]);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            $re = "/^(?<hotelName>.+?)\s+\,\s*(?<roomType>.+?)\n+\s*Check in:\s*(?<inTime>[\d\:]+)\,\s*Check out:\s*(?<outTime>[\d\:]+)\n+(?<inDate>[\d\/]+)[ \-]+(?<outDate>[\d\/]+).+\,\s+(?<guests>\d+)\s*passengers?\s*\((?<travellers>.+)\)/s";

            if (preg_match($re, $hText, $m)) {
                $h->general()
                    ->travellers(preg_split('/\s*,\s*/', preg_replace('/\s+/', ' ', trim($m['travellers']))), true);

                $h->hotel()
                    ->name($m['hotelName'])
                    ->noAddress();

                $h->booked()
                    ->guests($m['guests'])
                    ->checkIn($this->normalizeDate($m['inDate'] . ', ' . $m['inTime']))
                    ->checkOut($this->normalizeDate($m['outDate'] . ', ' . $m['outTime']));

                $h->addRoom()->setType($m['roomType']);

                if (preg_match("/^\s*.+?\s*\((.+)\)\s*$/", $m['hotelName'], $mat)) {
                    $this->hotelNames[trim($mat[1])][] = $m['hotelName'];
                }
            }
        }
    }

    public function ParseTransferPDF(Email $email, $text)
    {
        $transferFullText = $this->re("/Transfers\s*(.+SERVICE\nTransfer\s*\(\D+\)\n*.+or similar\.?)\n*/s", $text);
        $transferTextArray = preg_split("/\n\n\n\n/", $transferFullText);

        foreach ($transferTextArray as $transferText) {
            if (stripos($transferText, 'Transfer (two way)') !== false) {
                $t = $email->add()->transfer();

                $travellers = $this->re("/passengers\s*\((.+)\)/s", $transferText);

                if (stripos($travellers, ')')) {
                    $travellers = preg_replace("/(\).+)$/su", "", $travellers);
                }

                $t->general()
                    ->noConfirmation()
                    ->travellers(explode(',', $travellers), true);

                $s = $t->addSegment();

                if (preg_match("/(?<inDate>\d+\/\d+\/\d+)\s*Pick-up:\s*(?<inTime>[\d\:]+)[\s\-]+(?<outDate>\d+\/\d+\/\d{4})\s*Drop-off:\s*(?<outTime>[\d\:]+)\s*\|/", $transferText, $match)) {
                    $s->departure()
                        ->date($this->normalizeDate($match['inDate'] . ', ' . $match['inTime']));

                    $s->arrival()
                        ->date($this->normalizeDate($match['outDate'] . ', ' . $match['outTime']));
                }

                $s->departure()
                    ->name($this->findHotelName($this->re("/\s+-\s+Return\b.*\((.+)\,/", $transferText)));

                $s->arrival()
                    ->name($this->findHotelName($this->re("/\s+-\s+Return\b.*\s*\(.+\,\s*(.+)\)/", $transferText)));

                if (stripos($s->getDepName(), 'Dubai') !== false
                    || stripos($s->getArrName(), 'Dubai') !== false) {
                    $s->departure()
                        ->name($s->getDepName() . ', United Arab Emirates');
                    $s->arrival()
                        ->name($s->getArrName() . ', United Arab Emirates');
                }

                $s->setCarModel($this->re("/(?:Transfer will be|Transfer \(two way\)\n*)\s*(.+or similar)/", $transferText));

                //==================================Return=================================

                $s = $t->addSegment();

                if (preg_match("/\|\s*(?<inDate>\d+\/\d+\/\d+)\s*Pick-up:\s*(?<inTime>[\d\:]+)[\s\-]+(?<outDate>\d+\/\d+\/\d{4})\s*Drop-off:\s*(?<outTime>[\d\:]+)\s*/", $transferText, $match)) {
                    $s->departure()
                        ->date($this->normalizeDate($match['inDate'] . ', ' . $match['inTime']));

                    $s->arrival()
                        ->date($this->normalizeDate($match['outDate'] . ', ' . $match['outTime']));
                }

                $s->departure()
                    ->name($this->findHotelName($this->re("/\s+-\s+Return\b.*\s*\(.+\,\s*(.+)\)/", $transferText)));

                $s->arrival()
                    ->name($this->findHotelName($this->re("/\s+-\s+Return\b.*\s*\((.+)\,/", $transferText)));

                if (stripos($s->getDepName(), 'Dubai') !== false
                    || stripos($s->getArrName(), 'Dubai') !== false) {
                    $s->departure()
                        ->name($s->getDepName() . ', United Arab Emirates');
                    $s->arrival()
                        ->name($s->getArrName() . ', United Arab Emirates');
                }

                $s->setCarModel($this->re("/(?:Transfer will be|Transfer \(two way\)\n*)\s*(.+or similar)/", $transferText));
            } elseif (stripos($transferText, 'Transfer (one way)') !== false) {
                $t = $email->add()->transfer();

                $travellers = $this->re("/passengers?\s*\((.+)\)/", $transferText);

                if (empty($travellers)) {
                    $travellers = $this->re("/passengers?\s*\((.+\n*.*)\)\n*SERVICE/", $transferText);
                }

                $t->general()
                    ->noConfirmation()
                    ->travellers(explode(',', $travellers), true);

                $s = $t->addSegment();

                if (preg_match("/(?<inDate>\d+\/\d+\/\d+)\s*Pick-up:\s*(?<inTime>[\d\:]+)[\s\-]+(?<outDate>\d+\/\d+\/\d{4})\s*Drop-off:\s*(?<outTime>[\d\:]+)\s*\|?/", $transferText, $match)) {
                    $s->departure()
                        ->date($this->normalizeDate($match['inDate'] . ', ' . $match['inTime']));

                    $s->arrival()
                        ->date($this->normalizeDate($match['outDate'] . ', ' . $match['outTime']));
                }

                $s->departure()
                    ->name($this->findHotelName($this->re("/Standard Private Car \- One Way\s*\((.+)\,/", $transferText)));
                $s->arrival()
                    ->name($this->findHotelName($this->re("/Standard Private Car \- One Way\s*\(.+\,(.+)\)/", $transferText)));

                $s->setCarModel(($this->re("/(?:Transfer will be|Transfer \(two way\)\n*)\s*(.+or similar)/", $transferText)));
            }
        }
    }

    public function findHotelName($name)
    {
        $name = trim($name);

        if (isset($this->hotelNames[$name])
            && count(array_unique($this->hotelNames[$name])) === 1
        ) {
            return $this->hotelNames[$name][0];
        }

        return $name;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $parts = $this->split("/\n( {0,10}(?:PNR:(?:.*\n){1,3} *{$this->opt($this->t('Flights:'))}|{$this->opt($this->t('Hotels'))}|{$this->opt($this->t('Transfers'))}|{$this->opt($this->t('Activities'))})\n)/", $text);
            // $this->logger->debug('$parts = ' . print_r($parts, true));
            foreach ($parts as $part) {
                if (strpos($part, 'PNR:') === 0) {
                    $this->ParseFlightPDF($email, $part);
                }

                if (strpos($part, 'Hotels') === 0) {
                    $this->ParseHotelPDF($email, $part);
                }

                if (strpos($part, 'Transfer') === 0) {
                    $this->ParseTransferPDF($email, $part);
                }
            }
        }

        $email->ota()
            ->confirmation($this->re("/Reservation number\:\s*([\dA-Z]+)/", $text), 'Reservation number');

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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})\,\s*([\d\:]+)\s*$#u", //30/03/2023, 01:05
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
}
