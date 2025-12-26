<?php

namespace AwardWallet\Engine\submviag\Email;

use AwardWallet\Schema\Parser\Email\Email;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "submviag/it-49183044.eml";

    private $from = "no-reply@submarinoviagens.com.br";

    private $subject = [
        "Seu voucher Submarino foi liberado!",
    ];

    private $body = 'submarinoviagens.com.br';

    private $lang;

    private $pdfNamePattern = ".*pdf";

    private static $detectors = [
        'pt' => ["Apresentação no Aeroporto", "Detalhes do trecho aéreo"],
    ];
    private static $dictionary = [
        'pt' => [
            "Contact number"   => "Número do contrato",
            "Locator:"         => "Localizador",
            "Passengers"       => ["Passageiros"],
            "Departure"        => ["Partida"],
            "Arrival"          => ["Chegada"],
            "Ticket/e-Ticket:" => ["Bilhete/e-Ticket:"],
            "e-Ticket:"        => ["e-Ticket"],
            "| Flight"         => ["| Vôo"],
            "Travel Code"      => 'Código da viagem',
        ],
    ];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $sub) {
            if (stripos($headers["subject"], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, $this->body) === false) {
                return false;
            }
        }

        if ($this->detectBody($parser)) {
            return $this->assignLang($parser);
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang($parser)) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), 2)) !== null) {
                    $this->parseEmailPdf($email, $html);
                }
            }
        }
        $email->setType('VoucherPdf');

        return $email;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach (self::$detectors as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (!empty(stripos($text, $phrase)) && !empty(stripos($text,
                            $phrase))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf[0]), 2);
            $http1 = clone $this->http;
            $http1->SetBody($html);

            foreach (self::$dictionary as $lang => $words) {
                if ($http1->XPath->query("//*[{$this->starts($words["Contact number"])}]")->length > 0
                    && $http1->XPath->query("//*[{$this->starts($words["Locator:"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, $html)
    {
        $httpComplex = clone $this->http;
        $httpComplex->SetBody($html);

        $r = $email->add()->flight();

        $locator = $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('Locator:')) . "]", null, true,
            "/" . $this->opt($this->t('Locator:')) . ":[\s]([A-Z\d]{5,6})/");

        if (!empty($locator)) {
            $r->general()
                ->confirmation($locator, $this->t('Locator:'));
        }

        $travelCode = array_unique($httpComplex->FindNodes("//*[" . $this->starts($this->t('Travel Code')) . "]/following-sibling::p[1]"));

        if (!empty($travelCode)) {
            $r->ota()->confirmation($travelCode[0], $this->t('Travel Code'));
        }

        $paxs = $httpComplex->FindNodes("//text()[" . $this->contains($this->t('Ticket/e-Ticket:')) . "]/ancestor::p/preceding-sibling::p[2]");

        if (!empty($paxs)) {
            $r->general()->travellers($paxs, true);
        }

        $tickets = $httpComplex->FindNodes("//text()[" . $this->contains($this->t('Ticket/e-Ticket:')) . "]/ancestor::p[1]");

        if (!empty($tickets)) {
            foreach ($tickets as $ticket) {
                if (preg_match("/" . $this->opt($this->t('e-Ticket:')) . ":[\s]([\d]+)/", $ticket, $m)) {
                    $tick[] = $m[1];
                }
            }

            if (!empty($tick)) {
                $r->issued()->tickets($tick, false);
            }
        }

        $this->parseSegment($email, $r, $httpComplex);

        return $email;
    }

    private function parseSegment(Email $email, $r, \HttpBrowser $httpComplex)
    {
        $flights = $httpComplex->FindNodes("//text()[" . $this->contains($this->t('| Flight')) . "]");

        $departures = $this->depArrNormalization($httpComplex->FindNodes("//*[" . $this->contains($this->t('Partida')) . "]/following-sibling::p[position() <= 3 and not(./text()[" . $this->contains(':') . "])]"));

        $depTimes = $httpComplex->FindNodes("//*[" . $this->contains($this->t('Partida')) . "]/following-sibling::p[1]");

        $arrivals = $this->depArrNormalization($httpComplex->FindNodes("//*[" . $this->contains($this->t('Chegada')) . "]/following-sibling::p[position() <= 3 and not(./text()[" . $this->contains(':') . "])]"));
        $arrivals = array_reverse($arrivals);

        $arrTimes = $httpComplex->FindNodes("//*[" . $this->contains($this->t('Chegada')) . "]/following-sibling::p[1]");
        $arrTimes = array_reverse($arrTimes);

        $dates_airlines = $httpComplex->FindNodes("//*[" . $this->contains($this->t('| Flight')) . "]/following-sibling::p[3]");

        $durations = $httpComplex->FindNodes("//*[" . $this->contains($this->t('Partida')) . "]/following-sibling::p[position() <= 3 and contains(normalize-space(.),'h:')]");

        foreach ($flights as $key => $v) {
            $s = $r->addSegment();

            if (!empty($flights)) {
                if (preg_match("/" . $this->opt($this->t('| Flight')) . "\s([\d]+)/", $flights[$key], $m)) {
                    $s->airline()->number($m[1]);
                }
            }

            if (!empty($departures)) {
                if (preg_match('/(.+)\s\(([A-Z]{3})\)/', $departures[$key], $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2]);
                }
            }

            if (!empty($arrivals)) {
                if (preg_match('/(.+)\s\(([A-Z]{3})\)/', $arrivals[$key], $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->code($m[2]);
                }
            }

            if (preg_match("/(.+)\s-\s(\d{2}\/\d{2}\/\d{4})\s/", $dates_airlines[$key], $m)) {
                $s->airline()
                    ->name($m[1]);

                if (!empty($m[2]) && !empty($depTimes)) {
                    $s->departure()->date(strtotime(str_replace('/', '-', $m[2]) . " " . $depTimes[$key]));
                }

                if (!empty($m[2]) && !empty($arrTimes)) {
                    $s->arrival()->date(strtotime(str_replace('/', '-', $m[2]) . " " . $arrTimes[$key]));
                }
            }

            if (!empty($durations)) {
                $s->extra()->duration($durations[$key]);
            }
        }

        return $email;
    }

    private function depArrNormalization($array)
    {
        $improper = preg_grep("/\(([A-Z]{3})\)/", $array, PREG_GREP_INVERT);

        if ($improper && preg_grep("/\(([A-Z]{3})\)/", $array)) {
            foreach ($improper as $key => $v) {
                $array[$key] = trim($array[$key]) . " " . trim($array[$key + 1]);
                unset($array[$key + 1]);
            }
            $this->depArrNormalization($array);
        }
        array_splice($array, count($array));

        return $array;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
