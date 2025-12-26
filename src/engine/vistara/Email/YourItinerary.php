<?php

namespace AwardWallet\Engine\vistara\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "vistara/it-225771105.eml";
    public $subjects = [
        'Your Email Confirmation',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airvistara.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Team Vistara'))}]")->length > 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (strpos($text, 'Boarding Pass Exchange Coupon') !== false && strpos($text, 'CLASS OF TRAVEL') !== false && strpos($text, 'BOOKING REFERENCE') !== false
                ) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airvistara\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text, $fileName)
    {
        $flightParts = array_filter(preg_split("/Boarding Pass Exchange Coupon/", $text));

        foreach ($flightParts as $flightPart) {
            $f = $email->add()->flight();

            $travelInfo = $this->re("/travel information\n*(.+)\n\s*name:/siu", $flightPart);

            $travelTable = $this->SplitCols($travelInfo);

            $account = $this->re("/{$this->opt($this->t('FREQUENT FLYER'))}\:\n*([A-Z\d\/]+)/", $travelTable[0]);

            if (!empty($account) && strlen(trim($account)) > 5) {
                $f->program()
                    ->account($account, false);
            }

            $f->general()
                ->confirmation($this->re("/{$this->opt($this->t('BOOKING REFERENCE:'))}\s*\n*([A-Z\d]{5,})\n/", $travelTable[0]))
                ->traveller(preg_replace("/(?:\sMrs|\sMs|\sMr)$/u", '', $this->re("/{$this->opt($this->t('Name:'))}\n*\s*(\D+)\n\s*{$this->opt($this->t('Flight:'))}/", $text)));

            $ticket = $this->re("/{$this->opt($this->t('ETKT'))}\s*\n*(\d{7,})\n/", $travelTable[0]);

            if (!empty($ticket)) {
                $f->setTicketNumbers([$ticket], false);
            }

            $s = $f->addSegment();

            $airlineInfo = $this->re("/\n*(\s*FLIGHT\s*.+GATE\n*.+)\n/ui", $flightPart);
            $airlineTable = $this->SplitCols($airlineInfo);

            if (preg_match("/FLIGHT\n+(?<airlineName>[A-Z\d]{2})(?<flightNumber>\d{2,4})\n*/su", $airlineTable[0], $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);
            }

            if (preg_match("/\s*FROM\s*TO\n+\s*(?<depCode>[A-Z]{3})\s*(?<arrCode>[A-Z]{3})\n*\s*DEPARTURE\s*ARRIVAL\n+\s*(?<depTime>\d{4})\s*(?<arrTime>\d{4})\s*(?<depDate>\w+)\s*(?<arrDate>\w+)\n/", $flightPart, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }

            $terminalsText = $this->re("/\n*(\s*FROM.+TO.*\n)\s*FLIGHT/su", $flightPart);
            $terminalsTable = $this->SplitCols($terminalsText, [0, 50]);

            $depTerminal = $this->re("/{$this->opt($this->t('Terminal'))}\s*(\S*)/", $terminalsTable[0]);

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->re("/{$this->opt($this->t('Terminal'))}\s*(\S*)/", $terminalsTable[1]);

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $cabin = $this->re("/{$this->opt($this->t('CLASS OF TRAVEL:'))}\s*\n*(\w+)\n/u", $travelTable[0]);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $seat = $this->re("/{$this->opt($this->t('SEAT'))}\s*(\d+[A-Z])/su", $airlineTable[1]);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            $bp = $email->add()->bpass();
            $bp->setTraveller($f->getTravellers()[0][0]);
            $bp->setDepDate($s->getDepDate());
            $bp->setDepCode($s->getDepCode());
            $bp->setRecordLocator($f->getConfirmationNumbers()[0][0]);
            $bp->setFlightNumber($s->getAirlineName() . $s->getFlightNumber());
            $bp->setAttachmentName($fileName);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $pdfFileName = $this->getAttachmentName($parser, $pdf);
            $this->ParseFlightPDF($email, $text, $pdfFileName);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function TableHeadPos($row)
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\d+)(\D+)(\d{4})\,\s*(\d{1,2}\d{2})$#u", //20Nov2022, 2345
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }
}
