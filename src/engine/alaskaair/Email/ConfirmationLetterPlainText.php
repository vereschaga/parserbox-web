<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationLetterPlainText extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-11015400.eml, alaskaair/it-12021980.eml, alaskaair/it-12168100.eml, alaskaair/it-12879753.eml, alaskaair/it-1705711.eml, alaskaair/it-2270259.eml, alaskaair/it-5.eml, alaskaair/it-789429657.eml";

    private $reservationDate;

    public function parseText(Email $email, $text)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re('/Confirmation Code\s*:\s*([A-Z\d]{5,7})\b/i', $text));

        $passengersStr = $this->re('#TRAVELERS\s+((?s).*?)\s+FLIGHT\s+INFORMATION#i', $text);
        $f->general()
            ->travellers(explode("\n", $passengersStr));

        // Program
        if (preg_match_all('/^[ ]*Traveler[ ]*: *(?<traveller>.+?), *(?<name>.*#)[ ]*(?<number>[*\d ]*\d{2}[*\d ]*)\b[ ]*$/mi', $text, $ffNumberMatches)) {
            // Traveler: ... # *****8936
            $usedAccounts = [];

            foreach ($ffNumberMatches[0] as $i => $ff) {
                if (!in_array($ffNumberMatches['number'][$i], $usedAccounts)) {
                    $usedAccounts[] = $ffNumberMatches['number'][$i];
                    $f->program()
                        ->account($ffNumberMatches['number'][$i], preg_match('/\*{3,}/', $ffNumberMatches['number'][$i]) ? true : false, $ffNumberMatches['traveller'][$i], $ffNumberMatches['name'][$i]);
                }
            }
        }

        // Issued
        if (preg_match_all('/^[ ]*Traveler[ ]*: *(?<traveller>.+?)\s*(, .+)?\s*$\s*[> ]*Ticket[ ]*[:]+[ ]*(?<number>\d{3}[- ]*\d{5,}[- ]*\d{1,2})[ ]*(?:\(|$)/im', $text, $ticketNumberMatches)) {
            $usedTickets = [];

            foreach ($ticketNumberMatches[0] as $i => $ff) {
                if (!in_array($ticketNumberMatches['number'][$i], $usedTickets)) {
                    $usedTickets[] = $ticketNumberMatches['number'][$i];
                    $f->issued()
                        ->ticket($ticketNumberMatches['number'][$i], false, $ticketNumberMatches['traveller'][$i]);
                }
            }
        }

        // Price
        if (empty($this->re("/\b(New Ticket Value)\b/", $text))) {
            $totalText = $this->re("#\n\s*Total Fare\s*:\s*([^\n]+)#i", $text);

            if (!empty($totalText)) {
                $currency = $this->currency(trim(preg_replace('/^\s*(\D*?)\s*\d[\d,. ]+?\s*(\D*)\s*$/', '$1 $2',
                    $totalText)));
                $totalCharge = $this->amount($this->re("/^\s*\D*(\d[\d,. ]+?)\D*\s*$/", $totalText), $currency);
                $email->price()->total($totalCharge);
                $email->price()->currency($currency);

                if (preg_match('/^[> ]*(\d[\d,]* *miles) have been redeemed from Mileage Plan /im', $text, $m)) {
                    $f->price()
                        ->spentAwards($m[1]);
                }

                if (preg_match_all('/^[> ]*Base Fare and Surcharges\s*:\s*(.*)/im', $text, $m) && count($m[0]) === 1) {
                    $f->price()
                        ->cost($this->amount($this->re("/^\s*\D*(\d[\d,. ]+?)\D*\s*$/", $m[1][0]), $currency));
                }

                if (preg_match_all('/^[> ]*Taxes and Other Fees\s*:\s*(.*)/im', $text, $m) && count($m[0]) === 1) {
                    $f->price()
                        ->tax($this->amount($this->re("/^\s*\D*(\d[\d,. ]+?)\D*\s*$/", $m[1][0]), $currency));
                }
            }
        }

        // Segments
        $segments = $this->split("/(\n\s*Flight:\s+)/", $text);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("/\n\s*Flight\s*:\s*(.*?)\s+(\d+)/", $sText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("/\n\s*Flight Operated By (\S.+?)(?: as | dba |\.|\n)/", $sText, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            $rePart = ':\s+(?<name>.*?)\s+\((?<code>[A-Z]{3})\)\s+on\s+(?<date>\w+,\s+\w+\s+\d+\s+at\s+\d+:\d+\s*(?:am|pm))\b';
            // Departure
            if (preg_match("/Departs{$rePart}/", $sText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }

            // Arrival
            if (preg_match("/Arrives{$rePart}/", $sText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }

            // Extra
            if (preg_match("#Class\s*:\s*([A-Z])\s*\(([[:alpha:]\s]+)\)#", $sText, $m)) {
                // Class: S(Coach)
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2]);
            }

            if (preg_match("#\n\s*Equipment\s*:\s*(.+)#", $sText, $m)) {
                // Class: S(Coach)
                $s->extra()
                    ->aircraft($m[1]);
            }
            $seatsStr = $this->re('/\n\s*Seats\s*:\s*([^\n]+)/', $sText);

            if (preg_match_all('/\s*\b\d{1,3}[A-Z]\b\s*/i', $seatsStr, $m)) {
                // return array_map('trim', $m[0]);
                $s->extra()
                    ->seats(array_map('trim', $m[0]));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        $text = $parser->getPlainBody();

        if (empty($text)) {
            $text = strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", $parser->getHtmlBody()));
        }

        $text = str_replace('&gt;', '>', $text);
        $text = preg_replace('/^[> ]+/m', '', $text);

        if (!preg_match('/^[ ]*Flight:/im', $text)) { // it-12168100.eml
            $this->http->SetEmailBody($text);
            $textNodes = $this->http->FindNodes('//text()[normalize-space(.)]');
            $text = implode("\n", $textNodes);
        }

        $this->logger->debug('$text = ' . print_r($text, true));

        $text = str_replace("\r", '', $text);

        $this->reservationDate = strtotime(re('/Requested at (\d+\/\d+\/\d+)\b/', $text));

        if (!$this->reservationDate && preg_match("/utm_campaign=(\d{4})(\d{2})(\d{2})\&/", $text, $m)) {
            $this->reservationDate = strtotime($m[3] . '.' . $m[2] . '.' . $m[1]);
        }

        if (!$this->reservationDate) {
            $this->logger->debug('Relative date not found!');

            return $email;
        }

        $this->parseText($email, $text);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Alaska Airlines') !== false
            || stripos($from, '@alaskaair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Confirmation Letter.* from Alaska Airlines/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img")->length === 0) {
            if (empty($textBody = $parser->getPlainBody())) {
                $textBody = text($parser->getHTMLBody());
            }

            if (
                strpos($textBody, 'Thank you for booking with Alaska') === false
                && strpos($textBody, 'from Alaska Airlines') === false
                && strpos($textBody, 'traveling on Alaska Airlines') === false
                && strpos($textBody, 'Contact Alaska Airlines') === false
                && strpos($textBody, 'Alaska Airlines. All rights reserved') === false
                && stripos($textBody, '@alaskaair.com') === false
                && stripos($textBody, 'www.alaskaair.com') === false
            ) {
                return false;
            }

            return stripos($textBody, 'Confirmation code:') !== false;
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug("Date: {$str}");
        $year = date('Y', $this->reservationDate);
        $this->logger->debug('$year = ' . print_r($year, true));
        $in = [
            // Tue, Nov 26 at 7:36 pm
            "/^\s*([[:alpha:]]+)\,\s*([[:alpha:]]+)\s+(\d{1,2})\s+at\s+(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/iu",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug("Date: {$str}");

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m) && $year > 2000) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
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

    private function currency($s)
    {
        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        $sym = [
            // '€'  => 'EUR',
            // '£'  => 'GBP',
            // '$'  => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }

    private function amount($amount, $currency = null)
    {
        if (empty($amount)) {
            return null;
        }

        $amount = PriceHelper::parse($amount, $currency);

        if (is_numeric($amount)) {
            $amount = (float) $amount;
        } else {
            $amount = null;
        }

        return $amount;
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
