<?php

namespace AwardWallet\Engine\vroom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ModifiedBooking extends \TAccountChecker
{
    public $mailFiles = "vroom/it-174924171.eml";
    public $subjects = [
        'Modified VROOMVROOMVROOM booking',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@thrifty.com') !== false) {
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

            if (strpos($text, 'Thank you for renting with') !== false
                && strpos($text, 'Tax Invoice Number:') !== false
                && strpos($text, 'Hirer Particulars') !== false
                && strpos($text, 'Vehicles') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]thrifty\.com.*$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Tax Invoice Number:'))}\s*([A-Z\d]+)/", $text))
            ->traveller($this->re("/{$this->opt($this->t('Drivers'))}\n{$this->opt($this->t('Hirer'))}\s*{$this->opt($this->t('Address'))}\n(\D+)[ ]{15,}/", $text));

        $rentalInfo = $this->re("/\n(\s*Rental Location.+)\nVehicles/s", $text);
        $rentalTable = $this->splitCols($rentalInfo);

        $r->pickup()
            ->location(str_replace("\n", " ", $this->re("/^Rental Location\s*(.+)\s+[\d\s]{12,}/su", $rentalTable[0])))
            ->phone($this->re("/^Rental Location\s*.+\s+([\d\s]{12,})/su", $rentalTable[0]))
            ->date(strtotime(str_replace('/', '.', $this->re("/\s+([\d\/]+\s*[\d\:]+)\n/su", $rentalTable[1]))));

        $r->dropoff()
            ->location(str_replace("\n", " ", $this->re("/^Return Location\s*(.+)\s+[\d\s]{12,}/su", $rentalTable[2])))
            ->phone($this->re("/^Return Location\s*.+\s+([\d\s]{12,})/su", $rentalTable[2]))
            ->date(strtotime(str_replace('/', '.', $this->re("/\s+([\d\/]+\s*[\d\:]+)\n/su", $rentalTable[3]))));

        $carText = $this->re("/Vehicles\n(.+)\n+Drivers/su", $text);
        $carTable = $this->splitCols($carText);

        $r->car()
            ->model(str_replace("\n", " ", $this->re("#{$this->opt($this->t('Make/Model'))}\s+(.+)#su", $carTable[1])));

        $total = $this->re("/{$this->opt($this->t('Total Charges Inc GST'))}\s*\D+([\d\.\,]+)/", $text);
        $currency = $this->re("/{$this->opt($this->t('Total Charges Inc GST'))}\s*\(([A-Z]{3})\)\s+[\d\.\,]+/", $text);

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Tax Invoice Number') !== false) {
                $this->ParseRentalPDF($email, $text);
            }
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
}
