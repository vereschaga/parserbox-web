<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalPDF extends \TAccountChecker
{
    public $mailFiles = "national/it-296001659.eml";
    public $subjects = [
        'NATIONAL CAR RENTAL - R/A No.:',
        'National Car Rental Rental Agreement',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tsdnotify.com') !== false) {
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

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, '@NATIONALCAR.COM.AU') !== false
                && strpos($text, 'RENTER INFORMATION:') !== false
                && strpos($text, 'VEHICLE INFORMATION:') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tsdnotify.com$/', $from) > 0;
    }

    public function ParseCar(Email $email, $text)
    {
        $r = $email->add()->rental();

        $paxText = $this->re("/(RENTER INFORMATION\:?.+)RENTAL LOCATION\:?/su", $text);
        $paxTable = $this->splitCols($paxText, [0, 22]);

        $r->general()
            ->confirmation($this->re("/Res\:\s*(\d+)/", $text))
            ->traveller($this->re("/RENTER INFORMATION\:?\n+([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])/", $paxTable[0]), true);

        $textPart = $this->re("/(RENTAL LOCATION\:?.+)/su", $text);
        $table = $this->splitCols($textPart);

        if (count($table) == 3) {
            $r->pickup()
                ->date(strtotime($this->re("/RENTAL DATE\/TIME\:?\s*(.+)/", $table[1])))
                ->location(str_replace("\n", " ", $this->re("/RENTAL LOCATION\:?(.+)Phone/su", $table[0])));

            $pickUpPhone = $this->re("/{$this->opt($this->t('Phone:'))}\s*([\d\s\-]+)/", $table[0]);

            if (!empty($pickUpPhone)) {
                $r->pickup()
                    ->phone($pickUpPhone);
            }

            $r->dropoff()
                ->date(strtotime($this->re("/RETURN DATE\/TIME\:?\s*(.+)/", $table[1])))
                ->location(str_replace("\n", " ", $this->re("/RETURN LOCATION\:?(.+)Phone/su", $table[2])));

            $dropOffPhone = $this->re("/{$this->opt($this->t('Phone:'))}\s*([\d\s\-]+)/", $table[0]);

            if (!empty($dropOffPhone)) {
                $r->dropoff()
                    ->phone($dropOffPhone);
            }

            $make = $this->re("/{$this->opt($this->t('MAKE:'))}\s*(.+)/", $table[0]);
            $model = $this->re("/{$this->opt($this->t('MODEL:'))}\s*(.+)/", $table[0]);

            if (!empty($make) && !empty($model)) {
                $r->car()
                    ->model($make . ' ' . $model);
            }
        }

        $total = $this->re("/ESTIMATED CHARGES.*\:[ ]*([\d\.]+)/", $text);

        if (!empty($total)) {
            $r->price()
                ->total($total);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseCar($email, $text);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
