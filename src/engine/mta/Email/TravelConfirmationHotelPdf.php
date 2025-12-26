<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelConfirmationHotelPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-150880844.eml";

    public $detectBodyProvider = [
        // en
        'MTA Travel - ',
        '@mtatravel.com.au',
        '@whiteluxtravel.com.au',
    ];
    public $detectBody = [
        'en'  => ['confirmed the following hotel'],
    ];
    public $lang = 'en';

    public $pdfNamePattern = ".*\.pdf";
    public static $dict = [
        'en' => [
            'Your Reference No:' => ['Your Reference No:', 'Agoda Booking ID:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $detectedProvider = false;

            foreach ($this->detectBodyProvider as $dbProv) {
                if (stripos($text, $dbProv) !== false) {
                    $detectedProvider = true;

                    break;
                }
            }

            if ($detectedProvider == false) {
                continue;
            }

            foreach ($this->detectBody as $lang => $ldBody) {
                foreach ($ldBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;
                        $this->parseEmailPdf($text, $email);

                        continue 2;
                    }
                }
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

            $detectedProvider = false;

            foreach ($this->detectBodyProvider as $dbProv) {
                if (stripos($text, $dbProv) !== false) {
                    $detectedProvider = true;

                    break;
                }
            }

            if ($detectedProvider == false) {
                continue;
            }

            foreach ($this->detectBody as $ldBody) {
                foreach ($ldBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "mtatravel.com.au") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf($text, Email $email)
    {
        $this->logger->debug('$text = ' . print_r($text, true));

        $h = $email->add()->hotel();

        $travellers = [];

        if (preg_match_all("/Room\s+\d+\n+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/", $text, $m)) {
            $travellers = $m[1];
        }

        if (count($travellers) == 0) {
            $travellers = preg_split("/\s*\n\s*/", trim($this->re("/\n *Guest Details: *(\S.+(?:\n {20,}\S.+)*)\s*\n/", $text)));
        }

        if (preg_match_all("/Room\s+\d+\n+\s*[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\s*\-\s*(\d+)/", $text, $m)) {
            $h->setAccountNumbers(array_filter($m[1]), false);
        }

        // General
        $h->general()
            ->confirmation($this->re("/\n *{$this->opt($this->t('Your Reference No:'))} *([\d\-]{5,})\s*(?:\n|\()/", $text))
            ->travellers(preg_replace("/(?: \d+ ?years? old\s*|[\s\-\d]+\d+)$/", '', $travellers, true))
        ;

        // Hotel
        $hotelInfo = preg_replace("/\s*\n\s*/", ' ',
            $this->re("/Hotel Details: *(\S.+(?:\n {20,}\S.+){0,2})\s*\n/", $text));

        if (preg_match("/(.+?) +\| +(.+)/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address($m[2]);
        }

        // Booked
        $bookedInfo = $this->re("/Stay Details: *(\S.+(?:\n {20,}\S.+){0,10})\s*\n/su", $text);
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/Check-in (.+?) \|/", $bookedInfo)))
            ->checkOut($this->normalizeDate($this->re("/Check-out (.+)/", $bookedInfo)))
            ->rooms($this->re("/\n +\W? *(\d{1,2}) Rooms?\s*\n/u", $bookedInfo))
            ->kids($this->re("/You have booked a .* for .* (\d+) child/", $bookedInfo), false, true)
        ;

        $guests = $this->re("/You have booked a .* for (\d+) adults?/", $bookedInfo);

        if (empty($guests)) {
            $guests = count($travellers);
        }
        $h->booked()
            ->guests($guests);

        $roomType = $this->re("/You have booked a (.+) for /", $bookedInfo);

        if (empty($roomType)) {
            $roomType = preg_replace("/(?:\n|\s+)/", " ", $this->re("/You have booked a (.+\n\D*) for/", $bookedInfo));
        }

        $h->addRoom()
            ->setType($roomType);

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("date in: " . $date);
        $in = [
            //18 jul, 2019
            '#^(\d+)\-(\w+)\-(\d{4})$#u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug("date out: " . $date);

        return strtotime($date);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }
}
