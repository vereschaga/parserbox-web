<?php

namespace AwardWallet\Engine\ixigo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "ixigo/it-625448886.eml, ixigo/it-626290725.eml";
    public $subjects = [
        'flight booking is confirmed. Booking ID',
        'flight booking is cancelled. BookingID',
    ];

    public $lang = 'en';

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Booking ID' => ['Booking ID', 'BookingID'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ixigo.com') !== false) {
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

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'ixigo Support') !== false
                    && stripos($text, 'PNR') !== false
                    && stripos($text, 'E-Ticket no.') !== false) {
                    return true;
                }
            }
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Team ixigo')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'your flight booking') and contains(normalize-space(), 'has been cancelled')]")->length > 0) {
            return true;
        }

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Thanks for booking your flight via ixigo')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('For any queries, please visit our'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('E-TICKET NO.'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ixigo\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'your flight booking') and contains(normalize-space(), 'has been cancelled')]")->length > 0) {
            $f = $email->add()->flight();
            $f->general()
                ->confirmation($this->re("/{$this->opt($this->t('Booking ID'))}[\s\:]+\s*([A-Z\d]{6,})/", $parser->getSubject()))
                ->cancelled()
                ->status('cancelled');

            return $email;
        }

        if (preg_match("/Booking ID[\s\:]+\s*([A-Z\d]{6,})/", $parser->getSubject(), $m)) {
            $email->ota()
                ->confirmation($m[1]);
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'ixigo Support') !== false
                    && stripos($text, 'PNR') !== false
                    && stripos($text, 'E-Ticket no.') !== false) {
                    $this->ParseFlightPDF($email, $text);
                }
            }
        } else {
            $this->ParseFlightHTML($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlightHTML(Email $email)
    {
        $f = $email->add()->flight();

        $confs = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='PNR']/ancestor::tr[1]/following-sibling::tr/descendant::td[2]", null, "/^([A-Z\d]{6})$/")));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $f->general()
            ->travellers(array_filter($this->http->FindNodes("//text()[normalize-space()='PNR']/ancestor::tr[1]/following-sibling::tr/descendant::td[1]", null, "/^\s*\d+\.\s*(?:Mrs|Ms|Mr)\.\s*(.+)\s*\,/")));

        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='PNR']/ancestor::tr[1]/following-sibling::tr/descendant::td[3]", null, "/^([A-Z\d]+)$/")));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'riouw')]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\n*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\-(?<fNumber>\d{2,4})$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depName>.+)\n(?<depCode>[A-Z]{3})\n(?<depTime>[\d\:]+)\n(?<depDay>.+\d{2})\n(?<aeroport>.+)\n*(?:Terminal\s*(?<depTerminal>.+))?$/", $depInfo, $m)) {
                $m['depDay'] = str_replace("‘", "", $m['depDay']);
                $s->departure()
                    ->name($m['depName'] . ', ' . $m['aeroport'])
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDay'] . ', ' . $m['depTime']));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }
            $duration = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()]", $root, true, "/^(\d+\s*([hm]).*)/");

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[4]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrName>.+)\n(?<arrCode>[A-Z]{3})\n(?<arrTime>[\d\:]+)\n(?<arrDay>.+\d{2})\n(?<aeroport>.+)\n*(?:Terminal\s*(?<arrTerminal>.*))?$/", $arrInfo, $m)) {
                $m['arrDay'] = str_replace("‘", "", $m['arrDay']);
                $s->arrival()
                    ->name($m['arrName'] . ', ' . $m['aeroport'])
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDay'] . ', ' . $m['arrTime']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $seats = $this->http->FindNodes("//text()[normalize-space()='SEAT']/ancestor::tr[1]/following-sibling::tr/descendant::td[4]", null, "/^(\d+[A-Z])$/");

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }
        }
    }

    public function ParseFlightPDF(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $travellerText = $this->re("/^([ ]{0,3}Barcode.+PNR\s*E-Ticket no\.\n.+)\n+^[ ]{1,3}(?:Baggage Allowance|Other Add-ons)/mus", $text);
        $travellerTable = $this->splitCols($travellerText);
        $travellers = [];

        if (preg_match_all("/\s*(?:Mrs|Mr|Ms)\.\s*(?<traveller>.+)/u", $travellerTable[1], $m)) {
            $f->general()
                ->travellers($travellers = $m['traveller']);

            $confs = [];

            foreach ($m['traveller'] as $traveller) {
                if (preg_match("/$traveller\s*(?<conf>[\dA-Z]{5,})\s*(?<ticket>[\dA-Z]{5,})?\-?/", $travellerText, $m)) {
                    if (in_array($m['conf'], $confs) === false) {
                        $f->general()
                            ->confirmation($m['conf']);

                        $confs[] = $m['conf'];
                    }

                    if (isset($m['ticket']) && !empty($m['ticket'])) {
                        $f->addTicketNumber($m['ticket'], false, $traveller);
                    }
                }
            }
        }

        $total = $this->re("/Total Paid\s*(\d+)\n/", $text);

        if (!empty($total)) {
            $f->price()
                ->total($total);

            $cost = $this->re("/Base Price\s*(\d+)\n/", $text);

            if (!empty($cost)) {
                $f->price()
                    ->cost($cost);
            }

            $tax = $this->re("/Airline Taxes and Fees\s*(\d+)\n/", $text);

            if (!empty($tax)) {
                $f->price()
                    ->tax($tax);
            }

            $fee = $this->re("/Convenience Fee\s*(\d+)\n/", $text);

            if (!empty($fee)) {
                $f->price()
                    ->fee('Convenience Fee', $fee);
            }

            $discount = $this->re("/Instant off\s*(\d+)\n/", $text);

            if (!empty($discount)) {
                $f->price()
                    ->discount($discount);
            }
        }

        $segText = $this->re("/^([ ]{5,}\D+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\-\d{2,4}[\s\-]*\w+\D*\n{2,}.+)\n{3,}\s*Barcode/smu", $text);

        if (empty($segText)) {
            $segText = $this->re("/^([ ]{5,}\D+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\-\d{2,4}[\s\-]*\w+\n{2,}.+)\n{3,}\s*Travellers/smu", $text);
        }

        $segments = $this->splitText($segText, "/([ ]{5,}\D+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\-\d{2,4}[\s\-]*\w+\D*\n)/", true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match("/\s(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\-(?<fNumber>\d{2,4})[\s\-]*(?<cabin>\w+)\D*\n/", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $tableText = $this->re("/^(\s{1,3}[A-Z]{3}\s.+)/msu", $segment);

            $firstPosition = 0;
            $secondPosition = strlen($this->re("/^\n*(\s*[A-Z]{3}\s*\d+\:\d+\s+)\d+(?:h|m)\s*/mu", $segment));

            $lastPosition = strlen($this->re("/\n+^(\s*[A-Z]{3}\s*\d+\:\d+\s+\d+(?:h|m)(?:\s*\d+m)?)\b\s+[A-Z]{3}\s*\d+\:\d+/mu", $segment));

            if (empty($lastPosition)) {
                $lastPosition = strlen($this->re("/\n+^(\s*[A-Z]{3}\s*\d+\:\d+\s+\d+(?:h|m)(?:\s*\d+m)?)\b\s*.*\n\s+[A-Z]{3}\s*\d+\:\d+/mu", $segment)) - 5;
            }

            $table = $this->splitCols($tableText, [$firstPosition, $secondPosition, $lastPosition]);

            /*$this->logger->debug($segment);
            $this->logger->debug($secondPosition);
            $this->logger->debug($lastPosition);

            $this->logger->error(var_export($table, true));
            $this->logger->debug('-----------------------');*/

            if (preg_match("/^\s*(?<depCode>[A-Z]{3})\s*(?<depTime>[\d\:]+)\n*\s+(?<depDay>.+\d{2})\n*(?<aeroport>(?:.+\n){0,10})\n*\s*Terminal\s*(?<depTerminal>.*)\n/mu", $table[0], $m)
            || preg_match("/^\s*(?<depCode>[A-Z]{3})\s*(?<depTime>[\d\:]+)\n*\s+(?<depDay>.+\d{2})\n*(?<aeroport>(?:.+\n){0,10})(?:\n|$)/u", $table[0], $m)) {
                $s->departure()
                    ->name(str_replace("\n", " ", preg_replace("/\n\s+/", " ", $m['aeroport'])))
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDay'] . ', ' . $m['depTime']));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            if (preg_match("/^\s*(?<arrCode>[A-Z]{3})\s*(?<arrTime>[\d\:]+)\n*\s+(?<arrDay>.+\d{2})\n*(?<aeroport>(?:.+\n){0,10})\n*\s*Terminal\s*(?<arrTerminal>.*)\n/u", $table[2], $m)
            || preg_match("/^\s*(?<arrCode>[A-Z]{3})\s*(?<arrTime>[\d\:]+)\n*\s+(?<arrDay>.+\d{2})\n*(?<aeroport>(?:.+\n){0,10})(?:\n|$)/u", $table[2], $m)) {
                $s->arrival()
                    ->name(preg_replace("/\s+/", ' ', str_replace("\n", " ", $m['aeroport'])))
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDay'] . ', ' . $m['arrTime']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            if (preg_match_all("/{$s->getDepCode()}\-{$s->getArrCode()}\s*(?<seats>\d+[A-Z])/u", $text, $m)) {
                foreach ($m['seats'] as $seat) {
                    if (preg_match("/({$this->opt($travellers)})\s*{$s->getDepCode()}\-{$s->getArrCode()}\s*$seat/", $text, $match)) {
                        $s->extra()
                            ->seat($seat, false, false, $match[1]);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }

            $duration = $this->re("/(\d+h(?:\s*\d+m)?)\b(?:\s|\n)/", $tableText);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
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

    private function normalizeDate($str)
    {
        $str = $this->re("/\s(\d+.+\d{2})/", $str);

        $in = [
            "#(\d+)\s*(\w+)\s*[‘](\d+)\,\s*([\d\:]+)#u", //Sat, 30 Dec ‘23, 14:25
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
