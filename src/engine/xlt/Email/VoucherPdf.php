<?php

namespace AwardWallet\Engine\xlt\Email;

use AwardWallet\Schema\Parser\Email\Email;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "xlt/it-55012900.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'CLIENT'     => ['CLIENT'],
            'ARRIVE'     => ['ARRIVE'],
            'RES NUMBER' => ['RES NUMBER'],
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

            if (strpos($textPdf, 'TRAVELROX trading as') === false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parseHotel($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('VoucherPdf' . ucfirst($this->lang));

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

        $pattern = "/"
            . "(?:^|.*@.*\n|\n\n)"
            . "(?<hotel>(?:.{3,}\n){2,4})"
            . "\n*"
            . "[ ]*{$this->opt($this->t('Tel'))}[ ]*[:]+[ ]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])\n"
            . "\n*"
            . "[ ]*{$this->opt($this->t('CLIENT'))}[ ]*[:]+"
            . "/";

        if (preg_match($pattern, $text, $m)) {
            $hotelRows = preg_split('/[ ]*\n+[ ]*/', $m['hotel']);
            $h->hotel()
                ->name(array_shift($hotelRows))
                ->address(implode(' ', $hotelRows))
                ->phone($m['phone']);
        }

        $client = preg_match("/^[ ]*{$this->opt($this->t('CLIENT'))}[ ]*[:]+[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/m", $text, $m) ? $m[1] : null;
        $h->general()->traveller($client);

        $arrive = preg_match("/^[ ]*{$this->opt($this->t('ARRIVE'))}[ ]*[:]+[ ]*(.{6,})$/m", $text, $m) ? $m[1] : null;
        $h->booked()->checkIn2($arrive);

        $depart = preg_match("/^[ ]*{$this->opt($this->t('DEPART'))}[ ]*[:]+[ ]*(.{6,})$/m", $text, $m) ? $m[1] : null;
        $h->booked()->checkOut2($depart);

        if (preg_match("/^[ ]*({$this->opt($this->t('RES NUMBER'))})[ ]*[:]+[ ]*([-\dA-Z]{5,})$/m", $text, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $room = $h->addRoom();

        $rate = preg_match("/^[ ]*{$this->opt($this->t('RATE'))}[ ]*[:]+[ ]*(.*\d.*)$/m", $text, $m) ? $m[1] : null;
        $room->setRate($rate);
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['CLIENT']) || empty($phrases['ARRIVE']) || empty($phrases['RES NUMBER'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['CLIENT']) !== false
                && $this->strposArray($text, $phrases['ARRIVE']) !== false
                && $this->strposArray($text, $phrases['RES NUMBER']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

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
}
