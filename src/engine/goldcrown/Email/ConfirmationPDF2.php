<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPDF2 extends \TAccountChecker
{
    public $mailFiles = "";
    public static $dict = [
        'en' => [],
    ];

    private $reFrom = "bestwestern.";
    private $reBody = [
        'en' => 'CONFIRMATION NO.:',
    ];
    private $reSubject = [
    ];
    private $lang = '';
    private $pdfNamePattern = ".*\.pdf";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                $this->AssignLang($text);
                $this->parseEmail($email, $text);
            } else {
                return null;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'www.bestwestern.com') !== false || stripos($text, 'Best Western') !== false) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'])) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, $text)
    {
        /* Example
        CONFIRMATION NO.: 4980242
        Dear ladies and gentlemen,
        thank you for choosing the Best Western Hotel Mannheim City. We are pleased to confirm the following reservation:

                Guest : John P Carrico
                Arrival : 11.08.20
                Departure : 17.10.20
                Room / Guests : 1 Deluxe Room for 1 Adults
                Payment : Directly at hotel
                Rate per room/night : 65.00 EUR per night
        */
        $h = $email->add()->hotel();

        $text = preg_replace("/^[ ]*1\n/m", '', $text);

        // General
        $dateReservation = $this->re("/\s+.*,[ ]*(\d{1,2}\.\d{2}\.\d{4})\n\s*CONFIRMATION NO/", $text);

        if (!empty($dateReservation)) {
            $h->general()
                ->date(strtotime($dateReservation));
        }

        $h->general()
            ->confirmation($this->re("/\n\s*CONFIRMATION NO\.:[ ]*(\d+)/", $text), 'CONFIRMATION NO')
            ->traveller($this->re("/\n\s+Guest[ ]+:[ ]+(.+)\n/", $text));

        // Hotel
        $phone = $this->re("/Telefon[ ]*([\d\-\(\) \+]+)\s*·\s*Telefax/", $text);

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }
        $fax = $this->re("/Telefax[ ]*([\d\-\(\) \+]+)\s*·\s*/", $text);

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        if (preg_match("/thank you for choosing the (Best Western .+?)\. /", $text, $m)) {
            $h->hotel()
                ->name($m[1]);

            if (preg_match("/^\s*" . $m[1] . "[ ]*·[ ]*(.+)/", $text, $mat)) {
                $h->hotel()
                    ->address($mat[1]);
            }
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/\n\s*Arrival[ ]*:[ ]*([\d\.]+)\n/", $text)))
            ->checkOut($this->normalizeDate($this->re("/\n\s*Departure[ ]*:[ ]*([\d\.]+)\n/", $text)))
            ->guests($this->re("/\n\s*Room[ ]*\/[ ]*Guests[ ]*:[ ]*.* for (\d+) Adult/", $text))
        ;

        $time = $this->re("/check in time is (\d+(?: ?[ap]\.m\.))/", $text);

        if (!empty($h->getCheckInDate() && !empty($time))) {
            $h->booked()
                ->checkIn(strtotime($this->normalizeTime($time), $h->getCheckInDate()))
            ;
        }
        $time = $this->re("/check out time is (\d+(?: ?[ap]\.m\.))/", $text);

        if (!empty($h->getCheckOutDate() && !empty($time))) {
            $h->booked()
                ->checkOut(strtotime($this->normalizeTime($time), $h->getCheckOutDate()))
            ;
        }

        $h->addRoom()
            ->setType($this->re("/\n\s*Room[ ]*\/[ ]*Guests[ ]*:[ ]*(\S.+) for \d+ Adult/", $text))
            ->setRate(preg_replace("/\s+/", ' ', $this->re("/\n\s*Rate per room\/night[ ]*:[ ]*(.+)\n/", $text)))
        ;

        $cancellation = str_replace("\n", " ", $this->re("/(You may cancel the reservation until .+ without any charges\.)/s", $text));

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);

            $this->detectDeadLine($h, $cancellation);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_replace('/([.$*)|(\/])/', '\\\\$1', $s);
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            '/^\s*(\d{1,2})\.(\d{2})\.(\d{2})\s*$/', // 11.08.20
        ];
        $out = [
            '$1.$2.20$3',
        ];
        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    private function normalizeTime($time)
    {
        $in = [
            '/^\s*(\d+)\s*([ap])\.(m)\.\s*$/', // 6 p.m.
        ];
        $out = [
            '$1:00 $2$3',
        ];
        $time = preg_replace($in, $out, $time);

        return $time;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/You may cancel the reservation until (\d+(?: ?[ap]\.m\.)) on the arrival day without any charges./i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative("0 days", $this->normalizeTime($m[1]));
        }
    }
}
