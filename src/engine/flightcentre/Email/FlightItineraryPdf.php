<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class FlightItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-753364534.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            // Itinerary (pdf)
            'otaConfNumber'  => ['Flight Centre booking number'],
            'Arrives'        => ['Arrives'],
            'Flight No'      => ['Flight No'],
            'segmentsStart'  => ['FLIGHT ITINERARY'],
            'segmentsEnd'    => ['Use this itinerary to check', 'International check-in', 'Domestic check-in', 'Important Information', 'To change your booking'],
            'garbagePhrases' => ['Use this reference'],

            // Invoice (pdf)
            'BOOKING NUMBER'   => ['BOOKING NUMBER'],
            'TOTAL AMOUNT DUE' => ['TOTAL AMOUNT DUE'],
            'priceStart'       => ['COST BREAKDOWN'],
        ],
    ];

    private $otaConfNumbers = [];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        'namePrefixes'  => '(?:MSTR|MISS|MRS|MR|MS|DR)',
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]flightcentre\.(?:com|ca|com\.au)$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if (stripos($textPdf, 'www.flightcentre.com') === false && stripos($textPdf, 'www.flightcentre.ca') === false
                && stripos($textPdf, "Flight Centre Travel Group Limited trading as Flight Centre\n") === false
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
        $pdfInvoiceTexts = [];

        /* Parse Itineraries */

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (empty($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $f = $email->add()->flight();
                $this->parseFlight($f, $textPdf);

                $yourBookingInfo = $this->re("/(.*{$this->opt($this->t('To change your booking'))}.*\n[\s\S]+)/", $textPdf);
                $tablePos = [0];

                if (preg_match("/^(.*){$this->opt($this->t('To change your booking'))}/", $yourBookingInfo, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }
                $table = $this->splitCols($yourBookingInfo, $tablePos);
                $yourBookingInfo = implode("\n\n", $table);

                if (preg_match("/({$this->opt($this->t('otaConfNumber'))})[ ]*[:]+\s+([-A-Z\d]{3,20})$/m", $yourBookingInfo, $m)
                    && !in_array($m[2], $this->otaConfNumbers)
                ) {
                    $email->ota()->confirmation($m[2], preg_replace('/\s+/', ' ', $m[1]));
                    $this->otaConfNumbers[] = $m[2];
                }
            } elseif ($this->assignLangInvoice($textPdf)) {
                $pdfInvoiceTexts[] = $textPdf;
            }
        }

        /* Parse Price */

        if (count($email->getItineraries()) === 1 && isset($f)) {
            $this->parsePrice($f, $pdfInvoiceTexts);
        } elseif (count($email->getItineraries()) > 1) {
            $this->parsePrice($email, $pdfInvoiceTexts);
        }

        $email->setType('FlightItineraryPdf' . ucfirst($this->lang));

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

    private function parseFlight(Flight $f, string $text): void
    {
        $segmentsText = $this->re("/^[ ]*{$this->opt($this->t('segmentsStart'))}(?: .+)?\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('segmentsEnd'))}(?: .+)?$/m", $text);

        $segments = $this->splitText($segmentsText, "/^(.+ {$this->opt($this->t('Departs'))}[ ]+{$this->opt($this->t('Arrives'))})$/m", true);
        $airlineReference = $travellers = $accounts = [];

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $flightText = $travellersText = '';

            if (preg_match("/^(?<flight>[\s\S]+?)\n+(?<travellers>[ ]*{$this->opt($this->t('Travellers'))}(?: .+|\n)[\s\S]*)$/", $sText, $m)) {
                $flightText = $m['flight'];
                $travellersText = $m['travellers'];
            }

            // remove garbage
            $flightText = preg_replace("/[ ]+{$this->opt($this->t('garbagePhrases'))}[\s\S]*/", '', $flightText);

            if (preg_match("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Airline Reference'))}[: ]+([A-Z\d]{5,10})(?:\n|$)/", $flightText, $m)) {
                $flightText = $m[1];
                $s->airline()->confirmation($m[2]);
                $airlineReference[] = $m[2];
            }

            $tablePos = [0];

            if (preg_match("/^((.+[ ]{2}){$this->opt($this->t('Departs'))}[ ]+){$this->opt($this->t('Arrives'))}\n/", $flightText, $matches)) {
                $tablePos[] = mb_strlen($matches[2]) - 2;
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($flightText, $tablePos);

            if (count($table) !== 3) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Flight No'))}[:\s]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)$/m", $table[0], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/{$this->opt($this->t('operated by'))}[:\s]+([\s\S]+?)(?:\n+[ ]*{$this->opt($this->t('Check-in'))}|$)/i", $table[0], $m)) {
                $s->airline()->operator(preg_replace('/\s+/', ' ', $m[1]));
            }

            $pattern1 = "/^\s*(?:{$this->opt($this->t('Departs'))}|{$this->opt($this->t('Arrives'))})\n+[ ]*([\s\S]{2,}?)\n+[ ]*{$this->patterns['time']}/";
            $airportDep = $this->re($pattern1, $table[1]) ?? '';
            $airportArr = $this->re($pattern1, $table[2]) ?? '';

            $pattern2 = "/^(?<name>[\s\S]{2,}?)\n+[ ]*(?<terminal>.*{$this->opt($this->t('Terminal'))}[\s\S]*?)\s*$/i";

            if (preg_match($pattern2, $airportDep, $m)) {
                $airportDep = $m['name'];
                $m['terminal'] = trim(preg_replace(["/\s*{$this->opt($this->t('Terminal'))}\s*/i", '/\s+/'], ' ', $m['terminal']));

                if ($m['terminal'] !== '') {
                    $s->departure()->terminal($m['terminal']);
                }
            }

            if (preg_match($pattern2, $airportArr, $m)) {
                $airportArr = $m['name'];
                $m['terminal'] = trim(preg_replace(["/\s*{$this->opt($this->t('Terminal'))}\s*/i", '/\s+/'], ' ', $m['terminal']));

                if ($m['terminal'] !== '') {
                    $s->arrival()->terminal($m['terminal']);
                }
            }

            if ($airportDep) {
                $s->departure()->name(preg_replace("/(?:[ ]*\n+[ ]*)+/", ', ', $airportDep))->noCode();
            }

            if ($airportArr) {
                $s->arrival()->name(preg_replace("/(?:[ ]*\n+[ ]*)+/", ', ', $airportArr))->noCode();
            }

            $pattern3 = "/^[ ]*(?<time>{$this->patterns['time']})\n+(?<date>[\s\S]+\b\d{4})$/m";

            if (preg_match($pattern3, $table[1], $m)) {
                $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
            }

            if (preg_match($pattern3, $table[2], $m)) {
                $s->arrival()->date(strtotime($m['time'], strtotime($m['date'])));
            }

            $travellersHead = $travellersBody = '';

            if (preg_match("/^\n*(.{2,})\n+([\s\S]+)$/", $travellersText, $m)) {
                $travellersHead = $m[1];
                $travellersBody = $m[2];
            }

            $tablePos = [0];

            if (preg_match("/^(((.+ ){$this->opt($this->t('Baggage'))}[ ]+){$this->opt($this->t('Extras'))}[ ]+){$this->opt($this->t('Frequent Flyer Program'))}/m", $travellersHead, $matches)) {
                $tablePos[] = mb_strlen($matches[3]);
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }

            $travellersRows = $this->splitText($travellersBody, "/^([ ]{0,10}{$this->patterns['namePrefixes']}[ ]{1,3}[[:alpha:]])/imu", true);

            foreach ($travellersRows as $tRow) {
                $table = $this->splitCols($tRow, $tablePos);

                // remove fragment from next cell
                $table[0] = preg_replace('/^(.{12,}?)[ ]{2,}\S.*$/m', '$1', $table[0]);

                $passengerName = null;

                if (preg_match("/^\s*({$this->patterns['travellerName']})\s*$/u", $table[0], $m)) {
                    $passengerName = $this->normalizeTraveller(preg_replace('/\s+/', ' ', $m[1]));
                }

                $travellers[] = $passengerName;

                if (count($table) > 2 && preg_match("/^[^)(]+[:(]+\s*(\d+[A-Z])[)\s]*$/", $table[2], $m)) {
                    // Standard (66A)
                    $s->extra()->seat($m[1], false, false, $passengerName);
                }
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        if (count($airlineReference) > 0) {
            $f->general()->noConfirmation();
        }
    }

    private function parsePrice($obj, array $invoiceTexts): void
    {
        $currencies = $totalAmounts = [];

        foreach ($invoiceTexts as $text) {
            if (!preg_match("/^[ ]*{$this->opt($this->t('BOOKING NUMBER'))}[ ]*[:]+[ ]*{$this->opt($this->otaConfNumbers)}(?:[ ]{2}.+|$)/m", $text)) {
                $this->logger->debug('[invoice-pdf]: booking number not found! Passing...');

                continue;
            }

            $priceText = $this->re("/\n[ ]*{$this->opt($this->t('priceStart'))}\n+(.+?)\s*$/s", $text);

            if (preg_match_all("/^[ ]*{$this->opt($this->t('TOTAL AMOUNT DUE'))} .*\d/m", $priceText, $totalPriceMatches) && count($totalPriceMatches[0]) > 1) {
                $this->logger->debug('[invoice-pdf]: wrong total price! Stopped...');
                $currencies = $totalAmounts = [];

                break;
            }

            $currencyCode = null;

            if (preg_match_all("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Please note all amounts are in'))}.*[\s(]+([A-Z]{3})[) ]*(?:[ ]{2}|$)/m", $text, $currencyMatches)
                && count(array_unique($currencyMatches[1])) === 1
            ) {
                // Please note all amounts are in Australian Dollars (AUD)
                $currencyCode = $currencyMatches[1][0];
            }

            if (preg_match("/^\s*{$this->opt($this->t('Exc. GST'))}[ ]+{$this->opt($this->t('GST'))}[ ]+{$this->opt($this->t('Total Inc. GST'))}\n/", $priceText)
                && preg_match("/^[ ]*{$this->opt($this->t('TOTAL AMOUNT DUE'))}(?:[ ]+\S*?\d\.\d{2}\S*){2}[ ]+(\S*?\d\.\d{2}\S*)$/m", $priceText, $m)
            ) {
                $totalPrice = $m[1];
            } else {
                $totalPrice = '';
            }

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // $1589.81

                if (!$currencyCode) {
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                }

                $currencies[] = $currencyCode ?? $matches['currency'];
                $totalAmounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        if (count(array_unique($currencies)) === 1) {
            $currency = array_shift($currencies);
            $obj->price()->currency($currency)->total(array_sum($totalAmounts));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Arrives']) || empty($phrases['Flight No'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Arrives']) !== false
                && $this->strposArray($text, $phrases['Flight No']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignLangInvoice(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['BOOKING NUMBER']) || empty($phrases['TOTAL AMOUNT DUE'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['BOOKING NUMBER']) !== false
                && $this->strposArray($text, $phrases['TOTAL AMOUNT DUE']) !== false
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$this->patterns['namePrefixes']}[.\s]*)+$/is",
            "/^(?:{$this->patterns['namePrefixes']}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
            '$1',
        ], $s);
    }
}
