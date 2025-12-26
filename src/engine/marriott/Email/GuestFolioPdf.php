<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Schema\Parser\Email\Email;

class GuestFolioPdf extends \TAccountChecker
{
    public $mailFiles = "marriott/it-59867047.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'GUEST FOLIO' => ['GUEST FOLIO'],
            'ROOM'        => ['ROOM'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@marriott.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return preg_match('/Your .+ Stay at .+/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = self::detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, 'of Marriott Hotels') === false
                && stripos($textPdf, 'Your Marriott Bonvoy points') === false
                && stripos($textPdf, 'See members.marriott.com') === false
                && stripos($textPdf, 'Visit ShopMarriott.com') === false
                && stripos($textPdf, 'on Marriott.com') === false
            ) {
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

        $email->setType('GuestFolioPdf' . ucfirst($this->lang));

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
        $h->general()->noConfirmation();

        if (preg_match("/^[ ]*(?<hotelName>.{3,}?)[ ]+{$this->opt($this->t('GUEST FOLIO'))}$/m", $text, $matches)
            && preg_match("/\n\n[ ]*(?<name>{$this->opt($matches['hotelName'])})\n(?<address>(?:.+\n){1,3})\n/", $text, $m)
        ) {
            $h->hotel()->name($m['name']);

            if (preg_match("/^(?<address>[\s\S]{3,})\n[ ]*(?<phone>[+(\d][-. \d)(]{5,}[\d)]).*$/", $m['address'], $m2)) {
                $h->hotel()
                    ->address(preg_replace('/[ ]*\n+[ ]*/', ', ', $m2['address']))
                    ->phone($m2['phone']);
            } else {
                $h->hotel()->address(preg_replace('/[ ]*\n+[ ]*/', ', ', trim($m['address'])));
            }
        }

        $hotelTableText = preg_match("/{$this->opt($this->t('GUEST FOLIO'))}\n([\s\S]+?{$this->opt($this->t('ADDRESS'))}[ ]+{$this->opt($this->t('PAYMENT'))}.*\n.*)/", $text, $m) ? $m[1] : null;

        $tablePos = [0];

        if (!preg_match('/^(((((([ ]+)' . implode('[ ]{2,})', ['ROOM', 'NAME', 'RATE', 'DEPART', 'TIME', 'ACCT#']) . '/m', $hotelTableText, $matches)) {
            $this->logger->debug('Table headers not found!');

            return;
        }
        unset($matches[0]);

        foreach (array_reverse($matches) as $textHeaders) {
            if (preg_match('/^.*\S.*$/', $textHeaders)) {
                $tablePos[] = mb_strlen($textHeaders) - 5;
            }
        }
        $table = $this->splitCols($hotelTableText, $tablePos);

        if (count($table) !== 6) {
            $this->logger->debug('Hotel table is wrong!');

            return;
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('ROOM'))}\n[ ]*([\s\S]*?)\n[ ]*{$this->opt($this->t('TYPE'))}/", $table[0], $m)) {
            $room = $h->addRoom();
            $room->setType(preg_replace('/\s+/', ' ', $m[1]));
        }

        $guestName = preg_match("/^\s*([[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]])\n[ ]*{$this->opt($this->t('NAME'))}/u", $table[1], $m) ? $m[1] : null;
        $h->general()->traveller($guestName);

        if (preg_match("/^\s*(.{6,})\n[ ]*{$this->opt($this->t('DEPART'))}\n[ ]*(.{6,})\n[ ]*{$this->opt($this->t('ARRIVE'))}/", $table[3], $m1)
            && preg_match("/^\s*(\d.+)\n[ ]*{$this->opt($this->t('TIME'))}\n[ ]*(\d.+)\n[ ]*{$this->opt($this->t('TIME'))}/", $table[4], $m2)
        ) {
            $h->booked()
                ->checkIn2($m1[2] . ' ' . $m2[2])
                ->checkOut2($m1[1] . ' ' . $m2[1]);
        }

        if (preg_match("/^[ ]*MBV[ ]*#[: ]+([A-Z\d]{5,})$/m", $table[5], $m)) {
            // MBV#: XXXXX2916R
            $h->program()->account($m[1], preg_match("/^[X]{4}/i", $m[1]) > 0);
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['GUEST FOLIO']) || empty($phrases['ROOM'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['GUEST FOLIO']) !== false
                && $this->strposArray($text, $phrases['ROOM']) !== false
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
