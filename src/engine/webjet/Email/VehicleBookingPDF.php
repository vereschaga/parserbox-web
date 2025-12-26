<?php

namespace AwardWallet\Engine\webjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VehicleBookingPDF extends \TAccountChecker
{
    public $mailFiles = "webjet/it-155198287.eml, webjet/it-88014765.eml";
    public $subjects = [
        '/CONFIRMED\: Your vehicle booking [A-Z\d]+ with/',
    ];

    public $lang = 'en';

    public $pdfPattern = ".*\.pdf";

    public $detects = [
        'en' => ['Pick up instructions', 'Contact Details for', 'Reference number'],
        'en2' => ['Pick up instructions', 'Contact details for', 'Reference number'],
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airportrentals.com') !== false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detects as $lang => $words) {
                if (strpos($text, $words[0]) !== false && strpos($text, $words[1]) !== false && strpos($text, $words[2]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airportrentals\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseCar($email, $text);
        }
    }

    public function ParseCar(Email $email, $text)
    {
        $email->ota()
            ->confirmation($this->re("/\n {0,20}Reference number(?: {10,}.*\n)? +([A-Z\d]{5,})(?: {10,}|\n)/", $text));

        $r = $email->add()->rental();
        $r->general()
            ->status($this->re("/\n *Status +(\w+)\s+/", $text))
            ->traveller(trim($this->re("/Driver\s(.+)\sfrom/", $text)));

        if (preg_match("/\n {0,20}Booking number\s+(?: {10,}.*\n)? *(?<confNumber>[\d\|A-Z]+)\s*\((?<company>[^\)]+)\)(?: {3,}|\n)/su", $text, $m)) {
            $confirmations = array_filter(explode('|', $m['confNumber']));

            foreach ($confirmations as $conf) {
                $r->general()
                    ->confirmation($conf);
            }

            $r->setCompany(
                preg_replace(["/^(.{40,}) {10,}.+$/m", '/\s+/'], ['$1', ' '], $m['company']));
        }
        if (preg_match("/Booking number .*(?:\n.*){0,3} {10,}(?<model>\S.+ +or +similar)\s*(?:\n.*){1,2}.* {10,}(?<type>.+) with \d Seats/u", $text, $m)) {
            $r->car()
                ->model(preg_replace("/\s+/", ' ', $m['model']))
                ->type($m['type']);
        }

        $pickUpBlock = $this->re("/(Pick Up Location\:\s*.+)\n\s*Drop off Location:/su", $text);
        $pickUpDate = preg_replace("/^(.{40,}) {10,}.+$/m", '$1', $this->re("/\n( *Pick up\s+.+?)\n\s*Return/su", $text));

        $r->pickup()
            ->location(preg_replace("/\s*\n\s*/", " ", $this->re("/Pick Up Location\:(.+)\n[ ].+Pick Up Type:/us", $pickUpBlock)))
            ->openingHours(preg_replace("/\s*\n\s*/",  " ", $this->re("/Opening Hours:(.+)/s", $pickUpBlock)), true, true)
            ->date(strtotime($this->re("/\b(\w+\s*\d+\s*\w+\s*\d{4}\,\s*[\d\:]+\s*A?P?M)/s", $pickUpDate)));

        $pickPhone = $this->re("/\n\s*([\d\s\,]+)\s\(Customer Services?/", $pickUpBlock);

        if (!empty($pickPhone)) {
            $r->pickup()
                ->phone($pickPhone);
        }

        $dropOffBlock = $this->re("/(Drop off Location:\s*.+)Pick up instructions:/su", $text);
        $dropOffDate = preg_replace("/^(.{40,}) {10,}.+$/m", '$1', $this->re("/\n( *Return\s+.+?)\n\s*Contact [Dd]etails/su", $text));

        $r->dropoff()
            ->location(preg_replace("/\s*\n\s*/",  " ", $this->re("/Drop off Location:(.+)\n[ ].+Drop off Type:/us", $dropOffBlock)))
            ->openingHours(preg_replace("/\s*\n\s*/",  " ", $this->re("/Opening Hours:(.+)/s", $dropOffBlock)), true, true)
            ->date(strtotime($this->re("/\b(\w+\s*\d+\s*\w+\s*\d{4}\,\s*[\d\:]+\s*A?P?M)/s", $dropOffDate)));

        $dropPhone = $this->re("/\n\s*([\d\s\,]+)\s\(Customer Services?/", $dropOffBlock);

        if (!empty($dropPhone)) {
            $r->dropoff()
                ->phone($dropPhone);
        }

        $cost = $this->re("/\n *{$this->opt($this->t('Total Rental Price'))} +(\D{3}[\d\.]+)/", $text);
        if (!empty($cost)) {
            $policy = $this->re("/\n *{$this->opt($this->t('Total policy price'))} +(\D{3}[\d\.]+)/", $text);
            $r->price()
                ->total($this->re("/([\d\.]+)/", $cost) + $this->re("/([\d\.]+)/", $policy))
                ->currency($this->normalizeCurrency($this->re("/^(\D{3})/", $cost)));
        }
    }

    public function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US Dollar'],
            'AUD' => ['AU$'],
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
}
