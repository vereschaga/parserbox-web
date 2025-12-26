<?php

namespace AwardWallet\Engine\japanair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ElectronicTicketPDF extends \TAccountChecker
{
    public $mailFiles = "japanair/it-327464694.eml, japanair/it-645908310.eml";
    public $subjects = [
        '【JAL】 eチケットお客さま控 - Electronic Ticket Itinerary/Receipt',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jal.com') !== false) {
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

            if (strpos($text, 'JAL') !== false && strpos($text, 'ELECTRONIC TICKET ITINERARY/RECEIPT') !== false && strpos($text, 'This e-ticket is valid only for the passenger above and is not transferable') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jal\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        // $this->logger->debug($text);
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/[ ]{10,}([A-Z\d]{6})\n\s*RESERVATION NUMBER/", $text))
            ->date(strtotime(str_replace('.', '/', $this->re("/TICKETING DATE\/PLACE\n(\d{4}\.\d+\.\d+)/", $text))));

        $traveller = $this->re("/^\s+\D{1,3}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+\D{1,2}\n\s+NAME/mu", $text);

        if (empty($traveller)) {
            $traveller = $this->re("/^\s+\D{1,3}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\n\s+NAME/mu", $text);
        }
        $f->general()
            ->traveller($traveller, true);

        $ticketsText = $this->re("/PAID AMOUNT(.+)/su", $text);

        if (preg_match_all("/\s(\d{13})\s/u", $ticketsText, $m)) {
            $f->setTicketNumbers($m[1], false);
        }

        if (preg_match("/\/\s+(?<account>\d{4,})\n.*\sFFR/", $ticketsText, $m)) {
            $f->program()
                ->account($m['account'], false);
        }

        $flightText = $this->re("/RESERVATION NUMBER\n+(.+)\n{4,}/s", $text);

        if (!empty($flightText)) {
            $flightParts = $this->splitText($flightText, "/^(\s*\d{2}\S\d{2}\S\s+)/msu", true);

            foreach ($flightParts as $flightPart) {
                $s = $f->addSegment();

                if (preg_match("/^\s+\d+\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})(?<flightNumber>\d{2,4})\s*SEAT\s*(?<seat>\d{1,2}[A-Z])?/msu", $flightPart, $m)) {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['flightNumber']);

                    if (isset($m['seat']) && !empty($m['seat'])) {
                        $s->extra()
                            ->seat($m['seat']);
                    }
                }

                if (preg_match("/\s+(?<day>\d+)(?<month>[A-Z]+)\s+(?<depName>.+)[ ]{15,}(?<arrName>.+)\n\S+\s+(?<depTime>\d+\:\d+).*\s+(?<arrTime>\d+\:\d+)/u", $flightPart, $m)) {
                    $year = $this->re("/TICKETING DATE\/PLACE\n(\d{4})\.\d+\.\d+/", $text);
                    $s->departure()
                        ->date(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $year . ', ' . $m['depTime']))
                        ->name($m['depName'])
                        ->noCode();

                    $s->arrival()
                        ->date(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $year . ', ' . $m['arrTime']))
                        ->name($m['arrName'])
                        ->noCode();
                }

                if (preg_match("/SEAT.+[ ]{5,}(?:\([^)]{1,5}側\) *)?(?<cabin>[^\d\n]+)\n+/u", $flightPart, $m)
                || preg_match("/SEAT\n[ ]{5,}(?:\([^)]{1,5}側\) *)?(?<cabin>[^\d\n]+)\n+/u", $flightPart, $m)
                ) {
                    if (preg_match("/^\s*クラス\s*([A-Z]{1,2})\s*$/u", $m['cabin'], $mat)) {
                        $s->extra()
                            ->bookingCode($mat[1]);
                    } else {
                        $s->extra()
                            ->cabin($m['cabin']);
                    }
                }
            }
        }

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $f->price()
                ->total($m[1])
                ->currency($m[2]);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
