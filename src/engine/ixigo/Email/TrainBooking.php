<?php

namespace AwardWallet\Engine\ixigo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainBooking extends \TAccountChecker
{
    public $mailFiles = "ixigo/it-629220042.eml, ixigo/it-634442998.eml";
    public $subjects = [
        'train booking is successful. PNR',
        'train booking has been cancelled',
    ];

    public $lang = 'en';

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Boarding From' => ['Boarding From', 'Booked From'],
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Team ixigo')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'train booking')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'been cancelled')]")->length > 0) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'Electronic Reservation Slip') !== false
                    && stripos($text, 'PNR') !== false
                    && stripos($text, 'Train No./Name') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ixigo\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'train booking') and contains(normalize-space(), 'been cancelled')]")->length > 0) {
            $t = $email->add()->train();

            $email->ota()
                ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking ID:']/following::text()[normalize-space()][1]"));

            $t->general()
                ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='PNR:']/following::text()[normalize-space()][1]"))
                ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Train Name:']/following::text()[normalize-space()][1]"))
                ->status('cancelled')
                ->cancelled();

            return $email;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'Electronic Reservation Slip') !== false
                    && stripos($text, 'PNR') !== false
                    && stripos($text, 'Train No./Name') !== false) {
                    $this->ParseTrainPDF($email, $text);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTrainPDF(Email $email, string $text)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/PNR.+\n(?:.+\n*)?[ ]{1,5}(\d{9,})/", $text));

        $paxText = $this->re("/Passenger Details:\n((?:.+\n){1,5})\n\n/", $text);

        if (preg_match_all("/^\d+\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+\d+/m", $paxText, $m)) {
            $t->general()
                ->travellers($m[1]);
        }

        $segment = $this->re("/^[ ]*{$this->opt($this->t('Boarding From'))}\s+To\n*((?:.+\n){1,3})\n\n/mu", $text);
        $segTable = $this->splitCols($segment, [0, 43, 81]);

        $depText = '';
        $arrText = '';

        if (stripos($segTable[0], 'Departure') !== false) {
            $depText = $segTable[0];

            if (preg_match("/^\s*$/su", $segTable[1])) {
                $arrText = $segTable[2];
            } else {
                $arrText = $segTable[1];
            }
        } elseif (stripos($segTable[1], 'Departure') !== false) {
            $depText = $segTable[1];
            $arrText = $segTable[2];
        }

        $s = $t->addSegment();

        if (preg_match("/\s+(?<depName>.+)\s+\((?<depCode>[A-Z\d]+)\)\n\s+Departure[*]\s*(?<depTime>[\d\:]+)\s*(?<depDay>.+\d{4})/", $depText, $m)) {
            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
                ->date(strtotime($m['depDay'] . ', ' . $m['depTime']));
        }

        if (preg_match("/\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z\d]+)\)\n\s+Arrival[*]\s*(?<arrTime>[\d\:]+)\s*(?<arrDay>.+\d{4})/", $arrText, $m)) {
            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->date(strtotime($m['arrDay'] . ', ' . $m['arrTime']));
        }

        $s->extra()
            ->miles($this->re("/Distance.*\n.+[ ]{10,}(\d+\s+KM)/", $text));

        $extraText = $this->re("#^([ ]\D+Train No\./Name.*)\n\s*Quota#usm", $text);
        $extraTable = $this->splitCols($extraText, [0, 30, 63]);

        if (preg_match("/Train No\.\/Name\n*\s*(?<tNumber>\d+)\/(?<sName>.+)/", $extraTable[1], $m)) {
            $s->setNumber($m['tNumber']);
            $s->setServiceName($m['sName']);
        }

        if (preg_match("/Class\s*\n*(?<cabin>.+)/su", $extraTable[2], $m)) {
            $s->extra()
                ->cabin(preg_replace("/\s\n*\s*/su", " ", $m['cabin']));
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
