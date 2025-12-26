<?php

namespace AwardWallet\Engine\rentcars\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationVoucherPDF extends \TAccountChecker
{
    public $mailFiles = "rentcars/it-117227630.eml, rentcars/it-118044981.eml";
    private $subjects = [
        'O voucher da sua reserva chegou',
    ];

    private $lang = '';
    private $detectCompany = 'Rentcars';

    private $detectLang = [
        'en' => ['PICKUP'],
        'pt' => ['Voucher da Reserva'],
    ];

    private $pdfPattern = '.+\.pdf';
    private $detectBody = [
        'en' => ['This document must be printed and delivered to', 'Rentcars.com Request Code'],
        'pt' => ['Código da Solicitação Rentcars', 'Instruções de retirada'],
    ];

    private static $dictionary = [
        "en" => [
            'mainTableStart' => 'PICKUP',
            'mainTableEnd'   => ['ATTENTION:', 'Pickup instructions'],
            'TextStart'      => 'Excess amount limit',
        ],
        "pt" => [
            'mainTableStart'      => 'RETIRADA',
            'mainTableEnd'        => ['ATENÇÃO:', 'Instruções de retirada'],
            'RENTER'              => 'LOCATÁRIO',
            'PICKUP'              => 'RETIRADA',
            'RETURN'              => 'DEVOLUÇÃO',
            'ATTENTION:'          => 'ATENÇÃO:',
            'RENTAL COMPANY:'     => 'LOCADORA:',
            'Your reservation:'   => 'Sua reserva:',
            'Vehicle'             => 'Veículo',
            '(or similar)'        => '(ou similar)',
            'Total amount of the' => 'Valor total da reserva',
            'TextStart'           => 'o valor',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rentcars.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (stripos($textPdf, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $words) {
                foreach ($words as $detectBody) {
                    if (stripos($textPdf, $detectBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]projectexpedition\.com$/', $from) > 0;
    }

    public function ParseRental(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('RENTER'))}\s*([A-Z\d]{7})\n/", $text))
            ->traveller($this->re("/{$this->opt($this->t('RENTER'))}.*\n[ ]*({$patterns['travellerName']})\n/u", $text), true);

        $mainTableText = $this->re("/(.+ {$this->opt($this->t('mainTableStart'))}\n+[\s\S]+?)\n+[ ]*{$this->opt($this->t('mainTableEnd'))}/m", $text);
        $tablePos = [0];

        if (preg_match("/(.+ ){$this->opt($this->t('PICKUP'))}\n+/", $mainTableText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($mainTableText, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('Wrong main table!');

            return;
        }

        /*
            PICKUP
            26 DEC, 2021 - SUN, 2:00 PM
            Lisbon Airport (LIS), Portugal
        */
        $patterns['dateLocation'] = "(?<date>\d{1,2}\s*[[:alpha:]]+,\s*\d{4}\s*-\s*\w+,\s*{$patterns['time']})\n+"
        . "(?<location>[\s\S]{3,}?)";

        if (preg_match("/{$this->opt($this->t('PICKUP'))}\n+[ ]*{$patterns['dateLocation']}\n+[ ]*{$this->opt($this->t('RETURN'))}/u", $table[1], $m)) {
            $r->pickup()->date($this->normalizeDate($m['date']))->location(preg_replace('/\s+/', ' ', $m['location']));
        }

        if (preg_match("/{$this->opt($this->t('RETURN'))}\n+[ ]*{$patterns['dateLocation']}\n+[ ]*{$this->opt($this->t('RENTAL COMPANY:'))}/u", $table[1], $m)) {
            $r->dropoff()->date($this->normalizeDate($m['date']))->location(preg_replace('/\s+/', ' ', $m['location']));
        }

        if (preg_match("/{$this->opt($this->t('Your reservation:'))}.+\n\s*{$this->opt($this->t('Vehicle'))}\s*(?<model>.+{$this->opt($this->t('(or similar)'))})(?:\s+- (?<type>(?:\w+ )+)-)?/su", $text, $m)) {
            $r->car()->model($m['model']);

            if (!empty($m['type'])) {
                $r->car()->type($m['type']);
            }
        }

        $totalPrice = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Total amount of the'))}[ ]+(.*?)(?:[ ]{2}|\n|$)/", $table[0]);

        if (preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $totalPrice, $matches)) {
            // €1,510.69
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $company = $this->re("/{$this->opt($this->t('RENTAL COMPANY:'))}\n+(.{2,})(?:\n|$)/", $table[1]);
        $r->setCompany($company);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $this->ParseRental($email, $textPdf);
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            // 6 AGO, 2022 - SÁB, 17:00
            "/^(\d{1,2})\s+([[:alpha:]]+)[,\s]+(\d{4})[-\s]+[-[:alpha:]]+[,\s]+(\d{1,2}:\d{2}\s*A?P?M?)$/u",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function assignLang($text): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'BRL' => ['R$'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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
