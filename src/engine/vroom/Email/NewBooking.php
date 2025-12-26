<?php

namespace AwardWallet\Engine\vroom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewBooking extends \TAccountChecker
{
    public $mailFiles = "vroom/it-174924139.eml";
    public $subjects = [
        'New VROOMVROOMVROOM booking',
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

            if (strpos($text, 'Rental Agreement Number') !== false
                && strpos($text, 'Driver Details:') !== false
                && strpos($text, 'Location Details') !== false
                && strpos($text, 'and RMS to charge my charge card for all charges') !== false
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
        $this->logger->debug($text);

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/([A-Z\d]+)[\s\-]+{$this->opt($this->t('Rental Agreement Number'))}/", $text));

        $travellerInfo = $this->re("/Driver Details:.*\n(Hirer.+)Location Details/s", $text);
        $travellerTable = $this->splitCols($travellerInfo);
        $this->logger->warning($travellerTable[0]);
        $r->general()
            ->traveller($this->re("/Hirer(.+)Email/s", $travellerTable[0]));

        $pickUpInfo = $this->re("/\n(\s*Rental Location.+)\nReturn Location/s", $text);
        $pickUpTable = $this->splitCols($pickUpInfo);

        $r->pickup()
            ->location(str_replace("\n", " ", $this->re("/{$this->opt($this->t('Address:'))}(.+){$this->opt($this->t('Phone:'))}/su", $pickUpTable[0])))
            ->phone($this->re("/{$this->opt($this->t('Phone:'))}\s*([\d\s]{12,})/", $pickUpTable[0]))
            ->date(strtotime(str_replace('/', '.', $this->re("/\s(\d+\/\d+\/\d{4}\s*[\d\:]+)/", $pickUpTable[1]))));

        $dropOffInfo = $this->re("/\n(\s*Return Location.+)\nVehicle Details/s", $text);
        $dropOffTable = $this->splitCols($dropOffInfo);

        $r->dropoff()
            ->location(str_replace("\n", " ", $this->re("/{$this->opt($this->t('Address:'))}(.+){$this->opt($this->t('Phone:'))}/su", $dropOffTable[0])))
            ->phone($this->re("/{$this->opt($this->t('Phone:'))}\s*([\d\s]{12,})/", $dropOffTable[0]))
            ->date(strtotime(str_replace('/', '.', $this->re("/\s(\d+\/\d+\/\d{4}\s*[\d\:]+)/", $dropOffTable[1]))));

        if (stripos($dropOffInfo, 'Opening Hours:') !== false) {
            $hoursInfo = $this->re("/Return Location.+Opening Hours:(.+)\nVehicle/su", $text);

            if (preg_match_all("/^\s*(\w+\s*[\d\:]+\s*\-\s*[\d\:]+)\s*/mu", $hoursInfo, $m)) {
                $r->dropoff()
                    ->openingHours(implode(', ', $m[1]));
            }
        }

        $r->car()
            ->model($this->re("#{$this->opt($this->t('Make/Model'))}\s+([A-Z\d\s]+)#su", $text));

        $total = $this->re("/{$this->opt($this->t('Total Charges Inc GST'))}\s*\D+([\d\.\,]+)/", $text);
        $currency = $this->re("/{$this->opt($this->t('Total Charges Inc GST'))}\s*\(([A-Z]{3})\)\s+[\d\.\,]+/", $text);

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        $total = $this->re("/{$this->opt($this->t('Estimated Charge Inc GST'))}\s*\D+([\d\.\,]+)/", $text);
        $currency = $this->re("/{$this->opt($this->t('Estimated Charge Inc GST'))}.*\n.*\(([A-Z]{3})\)/", $text);

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

            if (stripos($text, 'Rental Agreement Number') !== false) {
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
