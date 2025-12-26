<?php

namespace AwardWallet\Engine\iberostar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "iberostar/it-774265175.eml, iberostar/it-786526378.eml";
    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Tax' => ['Tax', 'Fee', 'Assessment'],
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

            if (stripos($parser->getHeader('from'), 'iberostar.com') === false
                && stripos($parser->getHeader('subject'), 'iberostar') === false
                && stripos($parser->getBodyStr(), 'iberostar') === false
            ) {
                return false;
            }

            if ($this->re("/({$this->opt($this->t('IHG® One Rewards | Account Management'))})/s", $text) !== null
                && $this->re("/({$this->opt($this->t('Hotel Bill'))})/s", $text) !== null
                && $this->re("/({$this->opt($this->t('Date / Description'))})/s", $text) !== null
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iberostar\.com/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseCruisePDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseCruisePDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        // collect reservation confirmation
        if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Confirmation Number'))})\:\s*(?<number>\d+)\s*$/m", $text, $m)) {
            $h->general()
                ->confirmation($m['number'], $m['desc']);
        }

        // extract text with hotel name and hotel address
        $hotelText = $this->re("/{$this->opt($this->t('Hotel Bill'))}\s*(.+?)\n{2,}/s", $text);

        $hotelName = $this->re("/^(.+?)\n/s", $hotelText);
        $hotelAddress = preg_replace("/\n[ ]*/", ', ', $this->re("/^.+?\n(.+)$/s", $hotelText));

        if (!empty($hotelName)) {
            $h->setHotelName($hotelName);
        }

        if (!empty($hotelAddress)) {
            $h->setAddress($hotelAddress);
        }

        // collect traveller
        $traveller = $this->re("/{$this->opt($this->t('Email Address'))}.+?\n\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\n/s", $text);

        if (!empty($traveller)) {
            $h->addTraveller($traveller, true);
        }

        // collect description (room number)
        $desc = $this->re("/^\s*({$this->opt($this->t('Room Number'))}\:\s*\d+)\s*$/m", $text);

        if (!empty($desc)) {
            $room = $h->addRoom();
            $room->setDescription($desc);
        }

        // collect check-in and check-out dates
        $checkIn = $this->re("/^\s*{$this->opt($this->t('Check-In Date'))}\:\s*(\d+\/\d+\/\d{4})\s*$/m", $text);

        if (!empty($checkIn)) {
            $h->setCheckInDate(strtotime($checkIn));
        }

        $checkOut = $this->re("/^\s*{$this->opt($this->t('Check-Out Date'))}\:\s*(\d+\/\d+\/\d{4})\s*$/m", $text);

        if (!empty($checkIn)) {
            $h->setCheckOutDate(strtotime($checkOut));
        }

        // collect currency
        $currency = $this->re("/^.+?{$this->opt($this->t('AMOUNT'))}\s*\(([A-Z]{3})\)\s*$/m", $text);

        if (!empty($currency)) {
            $h->price()
                ->currency($currency);
        }

        // extract text with prices
        $priceText = $this->re("/\n.+?{$this->opt($this->t('AMOUNT'))}\s*(.+?)\s*{$this->opt($this->t('Terms and Conditions'))}/s", $text);

        // extract prices as entry [date, name, amount]
        $prices = [];

        if (preg_match_all("/^\s*(?<date>\d+\/\d+\/\d{4})\s+(?<sign>\-)?\D(?<amount>[\d\.\,\']+)\s*\n\s*(?<name>.+?)\s*$/m", $priceText, $m)) {
            foreach (array_map(null, $m['date'], $m['name'], $m['amount'], $m['sign']) as [$date, $name, $amount, $sign]) {
                $price = ['date' => $date, 'name' => $name, 'amount' => (float) PriceHelper::parse($amount)];

                if (!empty($sign)) {
                    $price['amount'] = -$price['amount'];
                }

                $prices[] = $price;
            }
        }

        // calculate total
        $total = null;

        foreach ($prices as $price) {
            if ($price['amount'] < 0) {
                $total += $price['amount'];
            }
        }

        // save total
        if ($total !== null) {
            $total = -$total;
            $h->price()
                ->total($total);
        }

        // calculate cost
        $cost = null;

        foreach ($prices as $price) {
            if (preg_match("/{$this->opt($this->t('Accommodation'))}/", $price['name'])) {
                $cost += $price['amount'];
            }
        }

        // calculate fees
        $fees = [];

        foreach ($prices as $price) {
            if (preg_match("/{$this->opt($this->t('Tax'))}/", $price['name'])) {
                if (!empty($fees[$price['name']])) {
                    $fees[$price['name']] += $price['amount'];
                } else {
                    $fees[$price['name']] = $price['amount'];
                }
            }
        }

        // save cost and fees if cost + fees = total
        if ($cost + array_sum($fees) === $total) {
            $h->price()
                ->cost($cost);

            foreach ($fees as $feeName => $feeAmount) {
                $h->obtainPrice()
                    ->addFee($feeName, $feeAmount);
            }
        }

        // calculate rates
        $rates = [];

        foreach ($prices as $price) {
            if ($price['amount'] < 0) { // skip payments
                continue;
            }

            if (!empty($rates[$price['date']])) {
                $rates[$price['date']] += $price['amount'];
            } else {
                $rates[$price['date']] = $price['amount'];
            }
        }

        // save rates if sum(rates) = total
        if (array_sum($rates) === $total && count($h->getRooms()) <= 1) {
            $room = $h->getRooms()[0] ?? $h->addRoom();

            foreach ($rates as $date => $rate) {
                $room->addRate("{$rate} {$h->obtainPrice()->getCurrencyCode()} per {$date}");
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
