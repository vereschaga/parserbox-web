<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelBookingPdf extends \TAccountChecker
{
    public $mailFiles = "hotels/it-139913755.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'CHECK-OUT'          => ['CHECK-OUT'],
            'NUMBER OF NIGHTS'   => ['NUMBER OF NIGHTS'],
            'mainTableEnd'       => ['Billing Address', 'Booking Details', 'Payment details'],
            'bookingDetailsEnd'  => ['Payment details'],
            'paymentDetailsEnd'  => ['Your Receipt'],
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'Hotels.com') === false && stripos($textPdf, 'www.hotels.com') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('HotelBookingPdf' . ucfirst($this->lang));

        $hotels = $this->splitText($textPdfFull, "/(.*[ ]{3}{$this->opt($this->t('CHECK-IN'))}(?:\n.*){2,5}[ ]{3}{$this->opt($this->t('CHECK-OUT'))})/", true);

        foreach ($hotels as $hText) {
            $this->parseHotel($email, $hText);
        }

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

    private function parseHotel(Email $email, $text): void
    {
        $h = $email->add()->hotel();

        if (preg_match("/^(.+)?\n+[ ]*{$this->opt($this->t('You were charged for this booking.'))}\n/s", $text, $m)) {
            $text = $m[1];
        }

        $mainTableText = $this->re("/(.+[ ]{3}{$this->opt($this->t('CHECK-IN'))}\n[\s\S]+?)\n+[ ]*{$this->opt($this->t('mainTableEnd'))}/", $text);

        $tablePos = [0];

        if (preg_match("/(.+[ ]{3}){$this->opt($this->t('CHECK-IN'))}\n/", $mainTableText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($mainTableText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('Wrong main table!');

            return;
        }

        if (preg_match("/(?:^\s*|\n[ ]*)(?<name>.{2,})\n[ ]*(?<address>.{3,})\n[ ]*(?<phone>[+(\d][-+. \d)(]{5,}[\d)])/", $table[0], $m)) {
            $h->hotel()->name($m['name'])->address($m['address'])->phone($m['phone']);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Hotels.com Confirmation Number'))})[ ]*:[ ]*([-A-Z\d]{5,})$/m", $table[0], $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
            $h->general()->noConfirmation();
        }

        $rooms = $this->re("/^[ ]*{$this->opt($this->t('Number of rooms'))}[ ]*:[ ]*(\d{1,3})$/m", $table[0]);
        $h->booked()->rooms($rooms);

        $checkIn = $this->re("/^[ ]*{$this->opt($this->t('CHECK-IN'))}\n+[ ]*(.{3,})/m", $table[1]);
        $h->booked()->checkIn2($checkIn);

        $checkOut = $this->re("/^[ ]*{$this->opt($this->t('CHECK-OUT'))}\n+[ ]*(.{3,})/m", $table[1]);
        $h->booked()->checkOut2($checkOut);

        /* Booking Details */

        $bookingDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('Booking Details'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('bookingDetailsEnd'))}/", $text);
        $tablePos = [0];

        if (preg_match("/\n([ ]*{$this->opt($this->t('Cancellation Policy'))}[ ]{2,})\S/", $bookingDetailsText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $roomInfoRow = $cancellationRow = null;

        if (preg_match("/^(?<room>[\s\S]+)\n(?<cancellation>[ ]*{$this->opt($this->t('Cancellation Policy'))}[ ]{2,}\S[\s\S]+)$/", $bookingDetailsText, $m)) {
            $roomInfoRow = $m['room'];
            $cancellationRow = $m['cancellation'];
        }

        $tableRoom = $this->splitCols($roomInfoRow, $tablePos);

        if (count($tableRoom) === 2) {
            $roomDesc = trim($tableRoom[0]);

            if (strpos($roomDesc, "\n") === false) {
                $room = $h->addRoom();
                $room->setDescription($roomDesc);
            }
            $traveller = trim($tableRoom[1]);

            if (preg_match("/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u", $traveller)) {
                $h->general()->traveller($traveller, true);
            }
        }

        $tableCancellation = $this->splitCols($cancellationRow, $tablePos);

        if (count($tableCancellation) === 2) {
            if (preg_match("/^\s*{$this->opt($this->t('Cancellation Policy'))}\s*$/", $tableCancellation[0])) {
                $h->general()->cancellation(preg_replace('/\s+/', ' ', $tableCancellation[1]));
            }
        }

        $patters['time'] = '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?';
        $patters['date'] = '\d{1,2}\/\d{1,2}\/\d{2,4}';

        if (!empty($h->getCancellation())) {
            if (preg_match("/^Free cancell?ation until (?<time>{$patters['time']})\s*,\s*(?<date>{$patters['date']})(?:\s*\(|$)/i", $h->getCancellation(), $m)
            ) {
                $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
            }
        }

        /* Payment details */

        $paymentDetailsText = $this->re("/\n[ ]*{$this->opt($this->t('Payment details'))}\n+([\s\S]+?)(?:\n+[ ]*{$this->opt($this->t('paymentDetailsEnd'))}|$)/", $text);
        $totalPrice = $this->re("/\n[ ]*{$this->opt($this->t('Total'))}[ ]{2,}(.*\d.*)(?:\n|$)/", $paymentDetailsText);

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $1,919.98
            $currencyCode = $this->re("/^\s*{$this->opt($this->t('Charges'))}[ ]{2,}([A-Z]{3})(?:[ ]+–|$)/", $paymentDetailsText);
            $h->price()->currency($currencyCode ?? $matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['CHECK-OUT']) || empty($phrases['NUMBER OF NIGHTS'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['CHECK-OUT']) !== false
                && $this->strposArray($text, $phrases['NUMBER OF NIGHTS']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
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
}
