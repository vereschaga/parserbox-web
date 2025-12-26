<?php

namespace AwardWallet\Engine\breakers\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "breakers/it-772711577.eml, breakers/it-781536681.eml, breakers/it-790035670.eml";
    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';
    public $subject = null;

    public static $dictionary = [
        'en' => [
            'Arrival'   => ['Arrival', 'Arrival Date'],
            'Departure' => ['Departure', 'Departure Date'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'THE BREAKERS PALM BEACH') !== false
                && stripos($text, 'Credit Card Authorization Receipt') !== false
                && stripos($text, 'Authorization Amount') !== false
                && stripos($text, 'Approval Code') !== false
            ) {
                return true;
            }

            if ($this->re("/({$this->opt($this->t('Arrival'))}[ ]+\d+\/\d+\/\d{4})[ ]*\n.+?({$this->opt($this->t('Departure'))}[ ]+\d+\/\d+\/\d{4})/s", $text) !== null
                && $this->re("/({$this->opt($this->t('Date'))}[ ]+{$this->opt($this->t('Description'))}[ ]+{$this->opt($this->t('Debit'))}[ ]+{$this->opt($this->t('Credit'))})/s", $text) !== null
                && $this->re("/({$this->opt($this->t('Balance Due'))}[ ]+\(?[\d\.\,\']+\)?)/s", $text) !== null
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]thebreakers\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $this->subject = $parser->getHeader('subject');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->parseHotelPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseHotelPDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $number = $this->re("/{$this->opt($this->t('stay'))}\s+([\d\-]+)\s+{$this->opt($this->t('on'))}/", $this->subject);

        // collect reservation confirmation from pdf
        if (preg_match("/(?<desc>{$this->opt($this->t('Confirmation Number'))})\s+(?<number>[\d\-]+)\s+/", $text, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc']);
        }
        // collect reservation confirmation from email subject
        elseif (!empty($number)) {
            $h->general()
                ->confirmation($number);
        }
        // no confirmation
        else {
            $h->general()
                ->noConfirmation();
        }

        // collect traveller
        $traveller = $this->re("/\s*([[:alpha:]][-.,\/\'’[:alpha:] ]*[[:alpha:]])[ ]+{$this->opt($this->t('Arrival'))}/", $text);

        if (!empty($traveller)) {
            $h->addTraveller($traveller, true);
        }

        // collect check-in
        $checkIn = $this->re("/{$this->opt($this->t('Arrival'))}[ ]+(\d+\/\d+\/\d{4})/", $text);

        if (!empty($checkIn)) {
            $h->setCheckInDate(strtotime($checkIn));
        }

        // collect check-out
        $checkOut = $this->re("/{$this->opt($this->t('Departure'))}[ ]+(\d+\/\d+\/\d{4})/", $text);

        if (!empty($checkOut)) {
            $h->setCheckOutDate(strtotime($checkOut));
        }

        // collect total
        $priceText = $this->re("/{$this->opt($this->t('Credit'))}(.+?{$this->opt($this->t('Balance Due'))}[ ]+\(?[\d\.\,\']+\)?(?:\s*|$))/s", $text);

        if (!empty($priceText)) {
            $startPos = strlen($this->re("/\n([ ]*{$this->opt($this->t('Date'))}.+?){$this->opt($this->t('Credit'))}/s", $text)) - 3;

            $creditCol = null;

            if ($startPos !== 0) {
                $creditCol = $this->splitCols($priceText, [$startPos])[0];
            }

            $credits = [];

            if (preg_match_all("/(?:^|\s|\()([\d\.\,\']+)(?:\s|$|\))/", $creditCol, $m)) {
                foreach ($m[1] as $credit) {
                    $credits[] = PriceHelper::parse($credit);
                }
            }

            if (!empty(array_filter($credits))) {
                $h->price()
                    ->total(PriceHelper::parse(array_sum($credits)));
            }
        }

        // set hardcoded items
        $h->setHotelName('The Breakers Palm Beach');
        $h->setAddress('One South County Road, Palm Beach, FL 33480');
        $h->setPhone('(561) 655-6611');
        $h->setFax('(561) 659-8403');
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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
