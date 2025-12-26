<?php

namespace AwardWallet\Engine\yatra\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightETicketPDF extends \TAccountChecker
{
    public $mailFiles = "yatra/it-52933371.eml, yatra/it-52987358.eml, yatra/it-696127934.eml, yatra/it-697137608.eml";
    private static $detectors = [
        'en' => ["GUEST TICKET BOOKLET"],
    ];
    private static $dictionary = [
        'en' => [
            "Booking Ref. No.:" => "Booking Ref. No.:",
            "Date of Issuance:" => ["Date of Issuance:"],
            "Airline Name"      => ["Airline Name"],
            "Airline Code"      => ["Airline Code"],
        ],
    ];
    private $from = "Yatra.com";
    private $subject = ["Your Booking Confirmation for Cart Id"];
    private $body = 'Flight E-Ticket';
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

            if (stripos($text, $this->body) !== false) {
                return true;
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
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf),
                        2)) !== null && ($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->parseEmailPdf($email, $html, $text);
                }
            }
        } else {
            $this->ParseEmailHTML($email);
        }
        $email->setType('FlightETicketPDF');

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

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'www.yatra.com')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'Airline Name')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'Dep Terminal')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'PNR Number')]")->length > 0) {
            return true;
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
                if ($http1->XPath->query("//*[{$this->contains($words["Booking Ref. No.:"])}]")->length > 0
                    && $http1->XPath->query("//*[{$this->contains($words["Date of Issuance:"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        } else {
            foreach (self::$dictionary as $lang => $words) {
                if ($this->http->XPath->query("//*[{$this->contains($words["Airline Name"])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words["Airline Code"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function ParseEmailHTML(Email $email)
    {
        $f = $email->add()->flight();

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Airline Name')]/preceding::text()[starts-with(normalize-space(), 'Dear')][1]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(.+)\,/");
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'PNR Number')]", null, true, "/\:\s*([A-Z\d]{6})$/"))
            ->traveller($traveller);

        $ticket = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Ticket Number ')]", null, true, "/\:\s*([A-Z\d]{5,})$/");

        if (!empty($ticket)) {
            if (!empty($traveller)) {
                $f->addTicketNumber($ticket, false, $traveller);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Airline Code')]", null, true, "/\:\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))/"))
            ->number($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Flight Number')]", null, true, "/\:\s*(\d+)/"));

        $depInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure')]");

        if (preg_match("/Departure\s*\:\s*(?<depCode>[A-Z]{3})\s+(?<depDate>\d+\s+\w+\s*\d{4}\s*\d+\:\d+A?P?M)/", $depInfo, $m)) {
            $depTerminal = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dep Terminal')]", null, true, "/\:\s*(.+)/");

            $s->departure()
                ->date(strtotime($m['depDate']))
                ->code($m['depCode'])
                ->terminal($depTerminal, true, true);
        }

        $arrInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival')]");

        if (preg_match("/Arrival\s*\:\s*(?<arrCode>[A-Z]{3})\s+(?<arrDate>\d+\s+\w+\s*\d{4}\s*\d+\:\d+A?P?M)/", $arrInfo, $m)) {
            $arrTerminal = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arr Terminal')]", null, true, "/\:\s*(.+)/");

            $s->arrival()
                ->date(strtotime($m['arrDate']))
                ->code($m['arrCode'])
                ->terminal($arrTerminal, true, true);
        }
    }

    private function parseEmailPdf(Email $email, $html, $textFull)
    {
        $httpc = clone $this->http;
        $httpc->SetBody($html);

        $r = $email->add()->flight();

        $confNo = $httpc->FindSingleNode("//p[" . $this->starts($this->t('Booking Ref. No.:')) . "]", null, true,
            '/^' . $this->opt($this->t('Booking Ref. No.:')) . '[\s]?(.+?)$/');

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, $this->t('Booking Ref. No.:'));
        }

        $pax = $httpc->FindSingleNode("//p[" . $this->starts($this->t('Name - ')) . "]", null, true,
            '/^' . $this->opt($this->t('Name - ')) . '[\s]?(.+?)$/');

        if (!empty($pax)) {
            $r->general()->traveller($pax, true);
        }

        $ticket = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Ticket Number ')]", null, true, "/\:\s*(\d{5,})$/");

        if (!empty($ticket)) {
            $pax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(.+)\,/");

            if (!empty($pax)) {
                $r->addTicketNumber($ticket, false, $pax);
            } else {
                $r->addTicketNumber($ticket, false);
            }
        }

        $rDate = $httpc->FindSingleNode("//p[" . $this->starts($this->t('Date of Issuance:')) . "]", null, true,
            '/^' . $this->opt($this->t('Date of Issuance:')) . '[\s]?(\d{2,4}-\d{1,2}-\d{1,2})$/');

        if (!empty($rDate)) {
            $r->general()->date(strtotime($rDate));
        }

        //Price

        if (preg_match("/No\.\s*Passenger Type\s*Basic Fare\s*Tax BreakUp\s*Total Fare((?:\n.*?)+)Reporting Details/",
            $textFull, $m)) {
            $match = array_values(array_filter(array_map('trim', preg_split("/\s{2}/", $m[1]))));

            if (!empty($match[4])) {
                if (preg_match("/([A-Z]{3})[\s]?(\d+[\d,.]+)/", $match[4], $m)) {
                    $r->price()->total(str_replace(".00", "", $m[2]))->currency($m[1]);
                }
            }

            if (!empty($match[2])) {
                if (preg_match("/((\d+[\d,.]+))/", $match[2], $m)) {
                    $r->price()->cost(str_replace(".00", "", $m[2]));
                }
            }

            if (!empty($match[3])) {
                if (preg_match("/((\d+[\d,.]+))[\(]?/", $match[3], $m)) {
                    $r->price()->tax(str_replace(".00", "", $m[2]));
                }
            }
        }

        $segments = $this->splitter("/((?:^\s^[A-z\s]+\s-\s[A-z\s]+[\s]+[A-Z]{3}\s-\s[A-Z]{3}\s*Airline|\n^[ ]{3,}\D+\s+\-\s+\D+\n+\s*Airline.+Class))/m", $textFull);

        foreach ($segments as $seg) {
            $s = $r->addSegment();

            if (count($segments) > 1) {
                $text = $seg;
            } else {
                $text = $textFull;
            }

            $this->logger->error($seg);
            $this->logger->error("==========================================");

            if (preg_match("/^\s(^[A-z\s]+)\s-\s([A-z\s]+)[\s]+([A-Z]{3})\s-\s([A-Z]{3})\s*Airline/m", $text, $depArr)) {
                $s->departure()
                    ->name($depArr[1])
                    ->code($depArr[3]);

                $s->arrival()
                    ->name($depArr[2])
                    ->code($depArr[4]);
            } elseif (preg_match("/No\.\s*Name\s*Ticket Number\s*Fare Type\s*FF Number\s*Itinerary\s*Baggage((?:\n.*?)+)([A-z\s]+)\s-\s([A-z\s]+?)\s*Airline/",
                $text, $m)) {
                $s->departure()
                    ->name($m[2]);
                $s->arrival()
                    ->name($m[3]);

                $match = array_filter(array_map('trim', preg_split("/\s{2,}/", $m[1])));

                if (!empty($match[6])) {
                    if (preg_match("/([A-Z]{3})[\s]?-[\s]?([A-Z]{3})/", $match[5], $ad)) {
                        $s->departure()
                            ->code($ad[1]);
                        $s->arrival()
                            ->code($ad[2]);
                    }
                }
            } elseif (preg_match("/^\s+(?<depName>[A-z\s]+)\s*\-\s*(?<arrName>[A-z\s]+)\s*\n+[ ]{5,}Airline[ ]{5,}/m", $text, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->noCode();

                $s->arrival()
                    ->name($m['arrName'])
                    ->noCode();
            }

            if (preg_match("/Airline\s*Departure\s*Arrival\s*FareBasisCode\s* Airline PNR\s*Class\s*Meal((?:\n.*?)+)Fare Details/",
                $text, $m)) {
                $match = array_filter(array_map('trim', preg_split("/\n\s/", $m[1])));

                if (!empty($match[1])) {
                    if (preg_match("/([A-Z\d]{5,6})\s*(.+?)\s+(.+?)$/", $match[1], $m)) {
                        $r->issued()->ticket($m[1], false);
                        $s->extra()
                            ->cabin($m[2])
                            ->meal($m[3]);
                    }
                }

                if (!empty($match[3])) {
                    if (preg_match("/\d{1,2}\s[A-z]{3}\s\d{2,4}\s\d{1,2}:\d{1,2}/", $match[3], $depDate)) {
                        $depD = strtotime($depDate[0]);
                    }
                }

                if (preg_match("/\d{1,2}\s[A-z]{3}\s\d{2,4}\s\d{1,2}:\d{1,2}/", $match[4], $arrDate)) {
                    $arrD = strtotime($arrDate[0]);
                } elseif (preg_match("/\d{1,2}\s[A-z]{3}\s\d{2,4}\s\d{1,2}:\d{1,2}\s*(\d{1,2}\s[A-z]{3}\s\d{2,4}\s\d{1,2}:\d{1,2})/",
                    $match[3], $arrDate)) {
                    $arrD = strtotime($arrDate[1]);
                }

                if (!empty($match[5])) {
                    if (preg_match("/T-([A-z\d]+)/", $match[5], $depTerminal)) {
                        $depT = $depTerminal[1];
                    }
                }

                if (!empty($match[6])) {
                    if (preg_match("/T-([A-z\d]+)/", $match[6], $arrTerminal)) {
                        $arrT = $arrTerminal[1];
                    }
                }

                if (!empty($arrD) && !empty($depD)) {
                    if ($arrD > $depD) {
                        $s->departure()->date($depD);
                        $s->arrival()->date($arrD);

                        if (!empty($depT)) {
                            $s->departure()
                                ->terminal($depT);
                        }

                        if (!empty($arrT)) {
                            $s->arrival()
                                ->terminal($arrT);
                        }
                    } else {
                        $s->departure()->date($arrD);
                        $s->arrival()->date($depD);

                        if (!empty($arrT)) {
                            $s->departure()
                                ->terminal($arrT);
                        }

                        if (!empty($depT)) {
                            $s->arrival()
                                ->terminal($depT);
                        }
                    }
                }

                if (preg_match("/([A-Z\d][A-Z]|[A-Z][A-Z\d])[-]?(\d{3,6})/", $match[4], $airline)) {
                    $s->airline()
                        ->name($airline[1])
                        ->number($airline[2]);
                }
            } elseif (preg_match("/^\s+Airline\s+Departure\s+Arrival\s+.+\n+((?:.+\n){3,}\s+[A-Z\d]{6})/mu", $text, $m)) {
                //it-696127934.eml
                $table = $this->splitCols($m[1], [0, 24, 44, 70]);

                if (preg_match("/[ ]{2,}(?<conf>[A-Z\d]{6})[ ]{2,}\s*(?<cabin>\w+)/", $m[1], $match)) {
                    $s->extra()
                        ->cabin($match['cabin']);

                    $s->setConfirmation($match['conf']);
                }

                if (preg_match("/(?<airlineName>(?:[A-Z\d][A-Z]|[A-Z][A-Z\d]))[-]?(?<flightNumber>\d{1,4})/", $table[0], $match)) {
                    $s->airline()
                        ->name($match['airlineName'])
                        ->number($match['flightNumber']);
                }

                if (preg_match("/\s*(?<depDate>\d+\s*\w+\s*\d{4}\s*\d+\:\d+)\D*\n*\s+Hrs\n*\s*(?:T\-(?<depTerminal>.+))?/", $table[1], $match)) {
                    $s->departure()
                        ->date(strtotime($match['depDate']));

                    if (isset($match['depTerminal']) && !empty($match['depTerminal'])) {
                        $s->departure()
                            ->terminal($match['depTerminal']);
                    }
                }

                if (preg_match("/\s*(?<arrDate>\d+\s*\w+\s*\d{4}\s*\d+\:\d+)\n*\s+Hrs\n*\s*(?:T\-(?<arrTerminal>.+))?/", $table[2], $match)) {
                    $s->arrival()
                        ->date(strtotime($match['arrDate']));

                    if (isset($match['arrTerminal']) && !empty($match['arrTerminal'])) {
                        $s->arrival()
                            ->terminal($match['arrTerminal']);
                    }
                }
            }
        }

        return $email;
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function re($re, $str, $c = 1)
    {
        if (preg_match($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }
}
