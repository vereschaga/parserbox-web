<?php

namespace AwardWallet\Engine\interjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassNonPdf extends \TAccountChecker
{
    public $mailFiles = "interjet/it-29288693.eml";

    public $detectFrom = '@interjet.com';
    public $detectSubject = [
        'es' => 'Pase de abordar Interjet',
    ];

    private $detectCompany = 'Interjet';

    private $detectBody = [
        'es' => ['Pase de abordar para tu vuelo'],
    ];

    private $lang = 'es';
    private static $dict = [
        'es' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        $bpPdf = false;

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (stripos($text, $this->detectCompany) !== false && stripos($text, 'Boarding Pass') !== false) {
                $this->logger->debug("Finded Pdf Boarding Pass -> go to another parsers(BoardingPassPdf or BoardingPassPdf2)");

                return $email;
            }
        }
//        $body = html_entity_decode($this->http->Response['body']);
        //		foreach ($this->detectBody as $lang => $detectBody) {
        //			foreach ($detectBody as $dBody) {
        //				if (strpos($body, $dBody) !== false) {
        //					$this->lang = $lang;
        //					break 2;
        //				}
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        subject included provider name
        //		if (self::detectEmailFromProvider($headers['from']) === false)
        //			return false;

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Nombre del Pasajero")) . "]/ancestor::tr[1]/following-sibling::tr/td[1]"), true);

        // Segments
        $s = $f->addSegment();

        // Airline
        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Gracias por viajar en Interjet")) . "])[1]"))) {
            $s->airline()->name('4O');
        } else {
            $s->airline()->noName();
        }
        $s->airline()->noNumber();

        $node = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Pase de abordar para tu vuelo")) . "])[1]");
        $time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Salida")) . "]/ancestor::tr[1]/following-sibling::tr/td[3]", null, "#^\s*\d+:\d+.*#");

        if (preg_match("#\s+([A-Z]{3})-([A-Z]{3}) (?:\w+ ){2}(\d+ \w+ \d{2})#u", $node, $m) && !empty($time)) {
            // Departure
            $s->departure()
                ->code(trim($m[1]))
                ->date($this->normalizeDate($m[3] . ' ' . $time));

            // Arrival
            $s->arrival()
                ->code($m[2])
                ->noDate()
            ;
        }

        // Extra
        $seat = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Asiento")) . "]/ancestor::tr[1]/following-sibling::tr/td[4]", null, "#^\s*(\d{1,3}[A-Z])\s*$#");

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        return $email;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)\s*([^\s\d]+)\s*(\d{4})\s*(\d+:\d+(?:\s*[AP]M)?)\s*$#", // 30 Aug 17 11:35
        ];
        $out = [
            "$2 $1 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang)) || ($en = MonthTranslate::translate($m[1], 'da')) || ($en = MonthTranslate::translate($m[1], 'no'))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
