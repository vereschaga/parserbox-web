<?php

namespace AwardWallet\Engine\goair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WebBoardingPass extends \TAccountChecker
{
    public $mailFiles = "goair/it-111916960.eml";
    public $subjects = [
        '/^Boarding Pass for PNR [A-Z\d]+$/',
    ];

    public $lang = 'en';
    public $date;
    public $file;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@goair.in') !== false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $body = $parser->getAttachmentBody($pdf);

            if (($html = \PDF::convertToText($body)) !== null) {
                if (stripos($html, 'www.GoAir.In') !== false
                    && stripos($html, 'Web Boarding Pass') !== false
                    && stripos($html, 'Flight No.') !== false
                    && stripos($html, 'Sequence') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]goindigo\.in$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $html = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));

            $temp = $parser->getAttachment($pdf);
            $this->file[] = $this->re('/name[=]\"(.+pdf)\"$/', $temp['headers']['content-type']);

            if ($html === null) {
                continue;
            }
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($html);

            $this->ParseEmailPDF($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEmailPDF(Email $email)
    {
        $text = $this->pdf->Response['body'];

        $segments = array_filter($this->splitText("#(Web Boarding Pass)#i", $text));

        foreach ($segments as $key => $segment) {
            $confirmation = $this->re("/{$this->opt($this->t('PNR'))}\s*\:?\s*([A-Z\d]{6})\s+/s", $segment);

            if (empty($confirmation)) {
                continue;
            }
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($confirmation, 'PNR')
                ->traveller(str_replace("\n", ' ', $this->re("/{$this->opt($this->t('Name:'))}\s*(?:Mrs|Mr|Ms)\s*(\D+)\s*{$this->opt($this->t('Date:'))}/s", $segment)), true);

            $s = $f->addSegment();

            $depDate = $this->re("/{$this->opt($this->t('Date:'))}\s+(\d+\s+\w+\s+\d+)\s+{$this->opt($this->t('From'))}/s", $segment);
            $depTime = $this->re("/{$this->opt($this->t('Dep. Time:'))}\s+([\d\:]+)/s", $segment);

            if (preg_match("/{$this->opt($this->t('From:'))}\s*([A-Z]{3})\s*\/*\s*{$this->opt($this->t('To:'))}\s*([A-Z]{3})\s*/s", $segment, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->date($this->normalizeDate($depDate . ', ' . $depTime));
                $s->arrival()
                    ->code($m[2])
                    ->noDate();
            }

            if (preg_match("/{$this->opt($this->t('Flight No:'))}\s*([A-Z\d]{2})\s*([\d]{2,4})\s*{$this->opt($this->t('Class'))}/s", $segment, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->extra()
                ->seats(array_filter(explode("\n", $this->re("/{$this->opt($this->t('Seat:'))}\s+(.+)\s+{$this->opt($this->t('Gate'))}/s", $segment))));

            if (preg_match("/{$this->opt($this->t('Class'))}\s*\:?\s*([A-Z])\s+\((\D+)\)/", $text, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2]);
            }

            if (count($this->file) > 0) {
                if (count($f->getTravellers()) > 0) {
                    foreach ($f->getTravellers() as $pax) {
                        $bp = $email->add()->bpass();
                        $bp
                            ->setDepCode($s->getDepCode())
                            ->setFlightNumber($s->getFlightNumber())
                            ->setDepDate($s->getDepDate())
                            ->setRecordLocator($f->getConfirmationNumbers()[0][0])
                            ->setTraveller($pax[0])
                            ->setAttachmentName($this->file[0]);
                    }
                }
            }
        }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s*(\w+)\s*(\d{4})\,\s*(\d{1,2})(\d{2})$#u", //5 Sep 2021, 1350
            "#^(\d+)\s+(\w+)\s+(\d{2})\,\s+(\d+\:\d+)$#", // 12 Jan 21, 21:55
        ];
        $out = [
            "$1 $2 $3, $4:$5",
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
