<?php

namespace AwardWallet\Engine\indigo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPdf2 extends \TAccountChecker
{
    public $mailFiles = "indigo/it-112624728.eml, indigo/it-76014795.eml";
    public $subjects = [
        '/^Boarding Pass for PNR [A-Z\d]+$/',
    ];

    public $lang = 'en';
    public $date;

    public $lastPNR;
    public $lastDateDep;
    public $lastPax;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@goindigo.in') !== false) {
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
                if ((stripos($html, 'www.goindiGo.in') !== false || stripos($html, 'indiGo') !== false || $this->http->XPath->query("//text()[contains(normalize-space(), 'IndiGo')]")->length > 0)
                    && stripos($html, 'Boarding Pass (Web Check­in)') !== false
                    && stripos($html, 'BOARDING TIME') !== false
                    && (stripos($html, 'SEQUENCE #') !== false || stripos($html, 'Seat') !== false)) {
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

    public function ParseEmailPDF(Email $email)
    {
        $text = $this->pdf->Response['body'];

        $segments = array_filter($this->splitText("#(Boarding Pass[ ]*\(|Boarding Confirmation)#i", $text));

        foreach ($segments as $key => $segment) {
            $confirmation = $this->re("/{$this->opt($this->t('PNR'))}\s*{$this->opt($this->t('FLIGHT NO.'))}\s+([A-Z\d]+)/s", $segment);

            if (empty($confirmation)) {
                continue;
            }
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($confirmation, 'PNR')
                ->traveller(str_replace("\n", ' ', $this->re("/{$this->opt($this->t('PASSENGER'))}\s+(\D+)\s+{$this->opt($this->t('PNR'))}/s", $segment)), true);

            $s = $f->addSegment();

            $depDate = $this->re("/{$this->opt($this->t('DATE'))}\s+(\d+\s+\w+\s+\d+)\s+{$this->opt($this->t('SEQUENCE #'))}/s", $segment);
            $depTime = $this->re("/{$this->opt($this->t('DEPARTURE TIME'))}\s+([\d\:]+)/s", $segment);

            if (preg_match("/www\.goindiGo\.in\s+([A-Z]{3})\s*([A-Z]{3})\s+/s", $segment, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->date($this->normalizeDate($depDate . ', ' . $depTime));
                $s->arrival()
                    ->code($m[2])
                    ->noDate();
            }

            if (preg_match("/{$this->opt($this->t('FLIGHT NO.'))}\s*([A-Z\d]{2})\s*([\d]{2,4})\s*{$this->opt($this->t('SEAT #'))}/s", $segment, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->extra()
                ->seats(array_filter(explode("\n", $this->re("/{$this->opt($this->t('SEAT #'))}\s+(.+)\s+{$this->opt($this->t('BOARDING TIME'))}.+{$this->opt($this->t('GATE'))}/s", $segment))));

            $bpURL = $this->http->FindSingleNode("//a[normalize-space()='View Boarding Pass']/@href");

            if (!empty($bpURL)) {
                if (count($f->getTravellers()) > 0) {
                    foreach ($f->getTravellers() as $pax) {
                        $bp = $email->add()->bpass();
                        $bp
                            ->setDepCode($s->getDepCode())
                            ->setFlightNumber($s->getFlightNumber())
                            ->setDepDate($s->getDepDate())
                            ->setRecordLocator($f->getConfirmationNumbers()[0][0])
                            ->setTraveller($pax[0])
                            ->setUrl($bpURL);
                    }
                }
            }
        }
    }

    public function ParseEmailPDF2(Email $email)
    {
        $this->logger->debug(__METHOD__);

        $text = $this->pdf->Response['body'];

        $segments = array_unique(array_filter($this->splitText("#(Boarding Pass[ ]*\(|Boarding Confirmation)#i", $text)));

        foreach ($segments as $key => $segment) {
            $confirmation = $this->re("/{$this->opt($this->t('PNR'))}\s+([A-Z\d]+)/s", $segment);

            if (empty($confirmation)) {
                continue;
            }
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($confirmation, 'PNR');

            $traveller = str_replace("\n", " ", $this->re("/\n+\s*([A-Z\s\/]+)\s+(?:MRS|MR|MS)\s*\n+\s*\D+.*\s+To/su", $segment));

            if (!empty($traveller)) {
                $f->general()
                    ->traveller($traveller, true);
            }

            $s = $f->addSegment();

            $depDate = $this->re("/{$this->opt($this->t('Date'))}\s+(\d+\s+\w+\s+\d+)\s+{$this->opt($this->t('Seq'))}/s", $segment);
            $depTime = $this->re("/{$this->opt($this->t('Departure'))}\s+([\d\:]+)/s", $segment);

            if (preg_match("/(?:MRS|MR|MS)\n+\s(?<depName>\D+)\s+To\s+(?<arrName>.+)\((?<arrTerminal>.+)\)/", $segment, $m) // GUWAHATI  To  HYDERABAD(T2)
                || preg_match("/(?:MRS|MR|MS)\n+\s(?<depName>\D+)\s*\((?<depTerminal>.+)\)\s+To\s+(?<arrName>.+)\((?<arrTerminal>.+)\)/", $segment, $m) // GUWAHATI(T2)  To  HYDERABAD(T2)
                || preg_match("/(?:MRS|MR|MS)\n+\s+To\s+(?<arrName>.+)\((?<arrTerminal>.+)\)/", $segment, $m) //  To  HYDERABAD
                || preg_match("/(?:MRS|MR|MS)\n+\s(?<depName>\D+)\s+To\s+(?<arrName>\D+)\s*Flight/", $segment, $m) // GUWAHATI(T2)  To  HYDERABAD(T2)
                || preg_match("/(?:MRS|MR|MS)\n+\s(?<depName>\D+)\((?<depTerminal>.+)\)\s+To\s+(?<arrName>\D+)\s*Flight/", $segment, $m) // GUWAHATI  To  HYDERABAD(T2)
            ) {
                if (isset($m['depName'])) {
                    $s->departure()
                        ->name($m['depName']);
                }

                $s->departure()
                    ->code($this->http->FindSingleNode("//text()[normalize-space()='{$confirmation}']/preceding::text()[normalize-space()='From']/ancestor::tr[1]/following::tr[1]/descendant::td[1]"))
                    ->date($this->normalizeDate($depDate . ', ' . $depTime));

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($this->http->FindSingleNode("//text()[normalize-space()='{$confirmation}']/preceding::text()[normalize-space()='From']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]"))
                    ->noDate();

                if (isset($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }

                if (isset($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            if (preg_match("/{$this->opt($this->t('Flight'))}\s*([A-Z\d]{2})\s*([\d]{2,4})\s*{$this->opt($this->t('Date'))}/s", $segment, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->extra()
                ->seats(array_filter(explode("\n", $this->re("/{$this->opt($this->t('Seat'))}\s+(\d+[A-Z])\s+{$this->opt($this->t('Seq'))}/s", $segment))));

            if (empty($traveller) && empty($s->getDepDate()) && !empty($confirmation)) {
                $email->removeItinerary($f);
            } elseif (!empty($traveller) && !empty($confirmation) && !empty($s->getDepDate())) {
                if ($this->lastPax == $traveller && $this->lastPNR == $confirmation && $this->lastDateDep == $s->getDepDate()) {
                    $email->removeItinerary($f);
                } else {
                    $this->lastPax = $traveller;
                    $this->lastPNR = $confirmation;
                    $this->lastDateDep = $s->getDepDate();

                    $bpURL = $this->http->FindSingleNode("//a[normalize-space()='View Boarding Pass']/@href");

                    if (!empty($bpURL)) {
                        if (count($f->getTravellers()) > 0) {
                            foreach ($f->getTravellers() as $pax) {
                                $bp = $email->add()->bpass();
                                $bp
                                    ->setDepCode($s->getDepCode())
                                    ->setFlightNumber($s->getFlightNumber())
                                    ->setDepDate($s->getDepDate())
                                    ->setRecordLocator($f->getConfirmationNumbers()[0][0])
                                    ->setTraveller($pax[0])
                                    ->setUrl($bpURL);
                            }
                        }
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $html = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));

            if ($html === null) {
                continue;
            }
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($html);

            if (stripos($html, 'FLIGHT NO.') !== false) {
                $this->ParseEmailPDF($email);
            } else {
                $this->ParseEmailPDF2($email);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
        //$this->logger->debug('IN-'.$str);
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

        //$this->logger->debug('OUT-'.$str);
        return strtotime($str);
    }
}
