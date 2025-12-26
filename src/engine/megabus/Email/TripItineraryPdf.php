<?php

namespace AwardWallet\Engine\megabus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "megabus/it-32405038.eml";

    private $detectsFrom = 'megabus.com';

    private $detectSubject = [
        "en" => "From megabus.com: Your reservations have been made",
    ];

    private $detectCompany = 'megabus';

    private $detectBody = [
        "en" => ["has been sent to"],
    ];

    private $lang = 'en';
    private $pdfNamePattern = '.+\.pdf';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectsFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (stripos($text, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        $this->parsePdf($email, $text);

                        continue 3;
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parsePdf(Email $email, string $text)
    {
        $b = $email->add()->bus();

        // General
        $b->general()
            ->confirmation(str_replace(' ', '', $this->re("#Your Booking ([A-Z\d ]{5,}) has been sent to#", $text)), "Booking", true);

        $tickets = $this->split("#\n([ ]*" . $this->addSpace("Ticket") . "[ ]*\d{1,2}[ ]*" . $this->addSpace("of") . "\d{1,2}[ ]+" . $this->addSpace("Trip Itinerary") . ")#", $text);

        foreach ($tickets as $ticket) {
            $ticket = preg_replace("#\n\s*" . $this->addSpace("Terminal details") . "\s+[\s\S]+#", '', $ticket);
            $columnlength = strlen($this->re("#^(.*[ ]+)" . $this->addSpace("Trip Itinerary") . "#", $ticket));
            $ticketTable = $this->SplitCols($ticket, [0, $columnlength]);

            $conf = preg_replace("#\s+#", '', $this->re("#Reservation number:\s*([A-Z\d\- ]+(?:\n[A-Z\d\- ]+)?)\n#", $ticketTable[0]));
            $b->general()
                ->confirmation($conf);

            $s = $b->addSegment();

            // Departure
            $s->departure()
                ->address(preg_replace("#\s*\n\s*#", ' ', $this->re("#" . $this->addSpace("From") . "\s*\n([\s\S]+?)\n\S.+\n\s*" . $this->addSpace("To") . "\s*\n#", $ticketTable[0])))
                ->date($this->normalizeDate($this->re("#" . $this->addSpace("From") . "\s*\n[\s\S]+?\n(\S.+)\n\s*" . $this->addSpace("To") . "\s*\n#", $ticketTable[0])))
            ;

            // Arrival
            $s->arrival()
                ->address(preg_replace("#\s*\n\s*#", ' ', $this->re("#\n\s*" . $this->addSpace("To") . "\s*\n([\s\S]+?)\n\S.+\n\s*\d+[ ]*(?:" . $this->addSpace("traveler") . "|" . $this->addSpace("traveller") . ")#", $ticketTable[0])))
                ->date($this->normalizeDate($this->re("#\n\s*" . $this->addSpace("To") . "\s*\n[\s\S]+?\n(\S.+)\n\s*\d+[ ]*(?:" . $this->addSpace("traveler") . "|" . $this->addSpace("traveller") . ")#", $ticketTable[0])))
            ;
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function addSpace($text)
    {
        return preg_replace("#([^\s])#u", "$1 ?", $text);
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
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
        $in = [
            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2}),\s*(\d{4})\s+at\s+(\d{2}:\d{2})\s*$#", // Thu, Jan 10, 2019 at 09:30
        ];
        $out = [
            "$2 $1 $3, $4",
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
