<?php

namespace AwardWallet\Engine\goair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass2Pdf extends \TAccountChecker
{
    public $mailFiles = "goair/it-29861536.eml, goair/it-29862125.eml, goair/it-30728696.eml";

    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = "no-reply@goair.in";
    private $detectSubject = [
        'Boarding Pass for PNR',
    ];

    private $detectPdfCompany = [
        'GoAir.in',
    ];

    private $detectPdfBody = [
        'en' => ['Boarding Pass'],
    ];

    private $lang = 'en';
    private $pdfNamePattern = ".*\.pdf";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (empty($pdfs)) {
            $this->http->Log('Pdf is not found');

            return $email;
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->parseEmail($email, $text);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $text = str_replace(chr(194) . chr(160), ' ', $text);

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->detectFrom) !== false && isset($headers["subject"])) {
            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    private function parseEmail(Email $email, string $text)
    {
        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $segments = $this->splitText("#(Boarding Pass.+)#", $text);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("#PNR[ ]*:[ ]*([A-Z\d]{5,7})\s+#", $segment, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            if (preg_match("#Flight No[ ]*:[ ]*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})(?:\s{2}|Class|\s*\n)#", $segment, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#\s+From:[ ]*(.+?)(?:\s{2,}|\n)#", $segment, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->noCode();
            }

            if (preg_match("#\s+Date[ ]*:[ ]*(.+?)(?:\s{2,}|Board Time|\n)#", $segment, $md)
                    && preg_match("#\s+Dep\. Time:[ ]*(\d+:\d+)\s+#", $segment, $mt)) {
                $s->departure()->date($this->normalizeDate($md[1] . ' ' . $mt[1]));
            }

            if (preg_match("#\s+To:[ ]*(.+?)(?:\s{2,}|\n)#", $segment, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->noCode()
                    ->noDate();
            }

            if (preg_match("#Class:[ ]*([A-Z]{1,2})(?:\s{2,}|\n)#", $segment, $m)) {
                $s->extra()->bookingCode($m[1]);
            }

            if (preg_match("#Seat:[ ]*(\d{1,3}[A-Z])(?:\s{2,}|\n)#", $segment, $m)) {
                $s->extra()->seat($m[1]);
            }

            $count = count($f->getSegments());

            foreach ($f->getSegments() as $key => $seg) {
                if ($key == $count - 1) {
                    continue;
                }

                if ($s->getAirlineName() == $seg->getAirlineName()
                        && $s->getFlightNumber() == $seg->getFlightNumber()
                        && $s->getDepName() == $seg->getDepName()
                        && $s->getDepDate() == $seg->getDepDate()) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $f->removeSegment($s);
                }
            }

            if (preg_match("#Name:[ ]*(.+?)(?:\s{2,}|\n)#", $segment, $m)) {
                if (!in_array($m[1], array_column($f->getTravellers(), 0))) {
                    $f->general()->traveller($m[1]);
                }
            }
        }

        return $email;
    }

    private function AssignLang($body)
    {
        $foundCompany = false;

        foreach ($this->detectPdfCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $foundCompany = true;
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        foreach ($this->detectPdfBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+)\s+(\w+)\s+(\d{2})\s+(\d+:\d+)\s*$#", //02 Oct 18 15:30
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //			if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }
}
