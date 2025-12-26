<?php

namespace AwardWallet\Engine\lycafly\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Receipt extends \TAccountChecker
{
    public $mailFiles = "lycafly/it-49505715.eml, lycafly/it-53483064.eml, lycafly/it-53901363.eml, lycafly/it-54068977.eml, lycafly/it-54350903.eml, lycafly/it-54871188.eml";
    //Format detectors
    //detectors[0]&&[1]
    private static $detectors = [
        'en' => [
            "1" => ["Your Itinerary Details are below", "Your order has been successfully placed."],
            "2" => ["@lycafly.com", "E-Ticket"],
        ],
    ];

    //Language detectors and dictionary
    private static $dictionary = [
        'en' => [
            "detectFirst" => ["Passenger Name", "Traveller Name"],
            "detectLast"  => ["Booking Reference"],
        ],
    ];
    private $from = "@lycafly.com";
    private $subject = ["Booking Order Confirmation Number:", "LycaFly Itinerary for Flight Booking Reference Number"];
    private $body = ['LycaFly'];
    private $lang;
    private $pdfNamePattern = ".*pdf";

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

            foreach ($this->body as $word) {
                if (stripos($text, $word) === false) {
                    return false;
                }
            }

            if ($this->detectBody($text)) {
                return $this->assignLang($text);
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null && $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), 2)) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug("Can't determine a language");

                        return $email;
                    } else {
                        $this->parseEmailPdf($email, $text, $html);
                    }
                }
            }
        }
        $email->setType('Receipt');

        return $email;
    }

    private function detectBody($body)
    {
        if (!empty($this->body)) {
            foreach (self::$detectors as $lang => $formats) {
                foreach ($formats as $phrases) {
                    if (strpos($body, $phrases[0]) !== false && strpos($body, $phrases[1]) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        if (!empty($this->body)) {
            foreach (self::$dictionary as $lang => $words) {
                foreach ($words["detectFirst"] as $word1) {
                    if (strpos($body, $word1) !== false) {
                        foreach ($words["detectLast"] as $word2) {
                            if (strpos($body, $word2) !== false) {
                                $this->lang = $lang;

                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, $text, $html)
    {
        $chttp = clone $this->http;
        $chttp->SetBody($html);

        $r = $email->add()->flight();

        if (preg_match("/Passenger Name\s*Order Confirmation #\s*Booking Reference #\s*Received Amount\s*Payment Reference #\s*((?:\n.*?)+)Your Itinerary Details are below/m",
            $text, $block)) {
            $rows = array_filter(array_map('trim', preg_split("/\s\n/", $block[1])));

            foreach ($rows as $row) {
                $column = array_filter(array_map('trim', preg_split("/\s{2,}/", $row)));
                $r->general()->traveller($column[0], true);
                $r->general()->confirmation($column[2], "Booking Reference");

                if (preg_match("/^(?<cur>.)\s(?<tot>\d+[\d.,]+)$/u", $column[3], $m)) {
                    $r->price()->total($m["tot"])->currency($m["cur"]);
                }
            }
        } elseif (preg_match("/Traveller Name\s*Booking Reference #\s* PASSPORT #\s*Ticket #\s*((?:\n.*?)+)Important Note/m",
            $text, $block)) {
            $rows = array_filter(array_map('trim', preg_split("/\s\n/", $block[1])));

            foreach ($rows as $row) {
                if (preg_match("/^(.+?)\s*([A-Z\d]{5,6})\s*(.+?)\s+([\d-]+)\s*$/", $row, $m)) {
                    $r->general()->traveller($m[1], true);
                    $r->general()->confirmation($m[2], "Booking Reference");
                    $r->issued()->ticket($m[4], false);
                }
            }
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sign = '##########################';

        $segments = $chttp->XPath->query("//text()[contains(translate(.,'$alphabet','$sign'),'(###)')]/ancestor::p[1][./following-sibling::p[3][contains(translate(.,'$alphabet','$sign'),'(###)')] or ./following-sibling::p[4][contains(translate(.,'$alphabet','$sign'),'(###)')]]");

        if ($segments->length === 0) {
            $this->logger->alert("Segment not defined");
        }

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $i = 0;

            $dep = $chttp->FindSingleNode(".", $segment, true, "/([A-z\s]+?[\s]?\([A-Z]{3}\))/");

            if (empty($dep)) {
                $name = $chttp->FindSingleNode("./preceding-sibling::p[1]", $segment, true, "/^([A-z\s]+)$/");
                $code = $chttp->FindSingleNode(".", $segment, true, "/^\(([A-Z]{3})\)$/");
                $s->departure()->name($name)->code($code);
            } elseif (preg_match("/([A-z\s]+)[\s]?\(([A-Z]{3})\)/", $dep, $m)) {
                $s->departure()->name($m[1])->code($m[2]);
            }

            $arr = $chttp->FindSingleNode("./following-sibling::p[3]", $segment, true,
                "/([A-z\s]+?[\s]?\([A-Z]{3}\))/");

            if (empty($arr)) {
                $name = $chttp->FindSingleNode("./following-sibling::p[3]", $segment, true, "/^([A-z\s]+)$/");
                $code = $chttp->FindSingleNode("./following-sibling::p[4]", $segment, true, "/^\(([A-Z]{3})\)$/");
                $s->arrival()->name($name)->code($code);
                $i = 1;
            } elseif (preg_match("/([A-z\s]+)[\s]?\(([A-Z]{3})\)/", $arr, $m)) {
                $s->arrival()->name($m[1])->code($m[2]);
            }

            $depDate = implode(' ',
                $chttp->FindNodes("./following-sibling::p[position() >= 1 and position() <= 2]", $segment));

            if (preg_match("/([A-z]{3}), (\d{1,2} [A-z]{3} \d{4}, \d{1,2}:\d{1,2})/", $depDate, $m)) {
                //(Thu), (26 Mar 2020, 17:15)
                $s->departure()->date(strtotime($m[2]));
            }

            $arrDate = implode(' ',
                $chttp->FindNodes("./following-sibling::p[position() >= " . (4 + $i) . " and position() <= " . (5 + $i) . "]",
                    $segment));

            if (preg_match("/([A-z]{3}), (\d{1,2} [A-z]{3} \d{4}, \d{1,2}:\d{1,2})/", $arrDate, $m)) {
                //(Thu), (26 Mar 2020, 17:15)
                $s->arrival()->date(strtotime($m[2]));
            }

            $depTerminal = $chttp->FindSingleNode("./following-sibling::p[(7 + $i)]", $segment, true,
                "/^Terminal\s(.+?)$/");

            if (!empty($depTerminal)) {
                $s->departure()->terminal($depTerminal);
            }

            $arrTerminal = $chttp->FindSingleNode("./following-sibling::p[(8+$i)]", $segment, true,
                "/^Terminal\s(.+?)$/");

            if (!empty($arrTerminal)) {
                $s->arrival()->terminal($arrTerminal);
            }

            $airNum = $chttp->FindSingleNode("./following-sibling::p[(6+$i)]", $segment, true, "/^\d+$/");

            if (!empty($airNum)) {
                $s->airline()->number($airNum);
            }

            $airName = implode(' ',
                $chttp->FindNodes("./following-sibling::p[position() >= (9+$i) and position() <= (11+$i)]", $segment));

            if (preg_match("/^([A-z\s]+?)\(Operated by/", $airName, $m)) {
                $s->airline()->name($m[1]);
            }
        }

        return $email;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
