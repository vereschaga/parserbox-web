<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationPrintPdf extends \TAccountChecker
{
    public $mailFiles = "sixt/it-451495099.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'pickup'  => ['Pick-up'],
            'dropoff' => ['Return'],
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

            if (stripos($textPdf, '//partner.sixt.com/') === false
                && strpos($textPdf, 'SIXT Partner Hub') === false
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
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('ReservationPrintPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text): void
    {
        $patterns = [
            'date' => '\d{1,2}\.\d{1,2}\.\d{2,4}', // 01.08.2023
        ];

        // remove garbage
        $text = preg_replace([
            '/^.*[ ]{2}Reservation \| SIXT Partner Hub(?:[ ]{2}.+)?$/im',
            '/^.*[ ]{2}\d{1,3} ?\/ ?\d{1,3}$/m',
        ], '', $text);

        // cut good content
        $text = preg_replace("/.*^([ ]*{$this->opt($this->t('CUSTOMER'))}[ ]{2,}{$this->opt($this->t('OVERVIEW'))}\n.+)/ms", '$1', $text);

        $tablePos = [0];

        if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('OVERVIEW'))}\n/", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($text, $tablePos);

        $text = implode("\n", $table);

        $car = $email->add()->rental();

        $customer = $this->re("/^[ ]*{$this->opt($this->t('CUSTOMER'))}\n{1,3}[ ]*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/mu", $text);
        $car->general()->traveller($customer, true);

        $rentalPeriod = $this->re("/\n[ ]*{$this->opt($this->t('RENTAL PERIOD'))}\n+((?:.+\n+){3,7})[ ]*{$this->opt($this->t('RESERVATION DETAILS'))}\n/", $text);

        $tablePos = [0];

        if (preg_match("/^([ ]*{$this->opt($this->t('pickup'))}[ ]+){$this->opt($this->t('dropoff'))}\n/", $rentalPeriod, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($rentalPeriod, $tablePos);

        if (count($table) === 2) {
            if (preg_match("/^[ ]*{$this->opt($this->t('pickup'))}\n+[ ]*(?<location>[\s\S]{3,}?)\n+[ ]*(?<dateTime>{$patterns['date']}[\s\S]*)$/", $table[0], $m)) {
                $car->pickup()->location(preg_replace('/\s+/', ' ', $m['location']))->date2($m['dateTime']);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('dropoff'))}\n+[ ]*(?<location>[\s\S]{3,}?)\n+[ ]*(?<dateTime>{$patterns['date']}[\s\S]*)$/", $table[1], $m)) {
                $car->dropoff()->location(preg_replace('/\s+/', ' ', $m['location']))->date2($m['dateTime']);
            }
        }

        if (preg_match("/\n[ ]*({$this->opt($this->t('RESERVATION NUMBER'))})\n+[ ]*([-A-Z\d]{5,})\n/", $text, $m)) {
            $car->general()->confirmation($m[2], $m[1]);
        }

        /*
            MITSUBISHI ASX
            or similar | SUV | IFAR
        */

        $carOverview = $this->re("/\n[ ]*{$this->opt($this->t('OVERVIEW'))}\n+((?:.+\n+){1,4}?)[ ]*{$this->opt($this->t('PICK-UP STATION'))}.*\n/", $text);

        if (preg_match("/^[ ]*(?<model>[\s\S]{2,}?)\s+{$this->opt($this->t('or similar'))}/", $carOverview, $m)) {
            $car->car()->model(preg_replace('/\s+/', ' ', $m['model']));
        }

        if (preg_match("/{$this->opt($this->t('or similar'))}\s*\|\s*(?<type>[^\|]+?)\s*(?:\||$)/", $carOverview, $m)) {
            $car->car()->type($m['type']);
        }

        $priceText = $this->re("/\n[ ]*{$this->opt($this->t('PRICE'))}\n+(.+)/s", $text);
        $totalPrice = $this->re("/^[ ]*(.+?)\s*\|\s*{$this->opt($this->t('Total'))}\s*(?:\n|$)/", $priceText);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // A$     209    .97
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $matches['amount'] = str_replace(' ', '', $matches['amount']);
            $car->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['pickup']) || empty($phrases['dropoff'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['pickup']) !== false
                && $this->strposArray($text, $phrases['dropoff']) !== false
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

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'AUD' => ['A$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
