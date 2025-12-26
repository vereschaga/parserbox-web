<?php

namespace AwardWallet\Engine\maxim\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelPdf extends \TAccountChecker
{
    public $mailFiles = "maxim/it-47395126.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation number'],
            'checkIn'    => ['CHECK-IN'],
            'roomType'   => ['ROOM TYPE'],
        ],
    ];

    private $subjects = [
        'en' => ['Hotel Confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@maxims-travel.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
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

            if ($detectProvider === false && strpos($textPdf, "Maxim's Travel 24") === false) {
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
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('HotelPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $traveller = preg_match("/^[ ]*{$this->opt($this->t('Traveller'))}[: ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/mu", $text, $m)
            ? $m[1] : null;
        $h->general()->traveller($traveller);

        $hotelName = preg_match("/^[ ]*{$this->opt($this->t('Hotel Name'))}[: ]*([\s\S]{3,}?)\n\n/m", $text, $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;

        if (preg_match("/^(?<name>.{3,})[ ]+-[ ]+(?<address>.{3,})$/", $hotelName, $m)) {
            // Crown Metropol Melbourne - 8 Whiteman St, Southbank VIC 3006
            $h->hotel()
                ->name($m['name'])
                ->address($m['address']);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Confirmation number'))})[: ]*([-A-Z\d]{5,})$/m", $text, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $tableText = preg_match("/^([ ]*{$this->opt($this->t('checkIn'))}.+{$this->opt($this->t('roomType'))}[\s\S]+?)\n\n/m", $text, $m)
            ? $m[1] : null;
        $table = $this->splitCols($tableText);

        if (count($table) !== 5) {
            $this->logger->debug('Incorrect main table!');

            return;
        }

        $checkIn = preg_match("/\s*{$this->opt($this->t('checkIn'))}[:\n]+([\s\S]{6,})/", $table[0], $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;
        $h->booked()->checkIn2($checkIn);

        $checkOut = preg_match("/\s*.+\n+([\s\S]{6,})/", $table[1], $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;
        $h->booked()->checkOut2($checkOut);

        if (preg_match("/\s*{$this->opt($this->t('roomType'))}[:\n]+([\s\S]{2,})/", $table[3], $m)) {
            $room = $h->addRoom();
            $room->setType(preg_replace('/\s+/', ' ', $m[1]));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn']) || empty($phrases['roomType'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['checkIn']) !== false
                && $this->strposArray($text, $phrases['roomType']) !== false
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

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
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
