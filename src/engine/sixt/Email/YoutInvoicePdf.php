<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YoutInvoicePdf extends \TAccountChecker
{
    public $mailFiles = "sixt/it-109471233.eml, sixt/it-139245535.eml, sixt/it-78946393.eml";
    public static $dictionary = [
        'en' => [
            'isRefundBySubject' => 'Refund for .+ rental booking',
            //            'Company/Mr/Ms' => '',
            'Time out' => ['Time out'],
            //            'Time in' => '',
            'amountBeforePrepaid' => 'Final amount',
            //            'Subtotal' => '',
            //            'Total' => '',
            'INVOICE' => 'INVOICE',
            //            'Registration No:' => '',
            //            'Res No.' => '',
            //            'Car:' => '',
            //            'Name:' => '',
        ],
        'it' => [
            // 'isRefundBySubject' => '',
            'Company/Mr/Ms'    => 'Spett.le/Sig/Sig.ra',
            'Time out'         => ['Uscita', 'Ritiro come da prenotazione'],
            'Time in'          => ['Rientro', 'Restituzione come da prenotazione'],
            // 'amountBeforePrepaid' => '',
            'Subtotal'         => ['Imponibile iva', 'Importo totale netto'],
            'Total'            => ['Totale fattura', 'Importo totale lordo'],
            'INVOICE'          => ['FATTURA', 'Fattura di acconto'],
            'Registration No:' => 'Targa:',
            'Res No.'          => 'Prenotazione:',
            //            'Car:' => '',
            'Name:' => 'Conducente:',
        ],
        'de' => [
            // 'isRefundBySubject' => '',
            'Company/Mr/Ms'    => 'Firma/Herrn/Frau',
            'Time out'         => ['Uebergabe', 'Übergabe'],
            'Time in'          => ['Rueckgabe', 'Rückgabe'],
            // 'amountBeforePrepaid' => '',
            'Subtotal'         => ['Summe Netto', 'Zwischensumme'],
            'Total'            => ['Summe Brutto', 'Endbetrag'],
            'INVOICE'          => ['RECHNUNG', 'Rechnung'],
            'Registration No:' => 'Kennzeichen:',
            'Res No.'          => ['Res-Nr.', 'Journey Nr.:'],
            'Car:'             => 'Fahrzeug:',
            'Name:'            => 'Fahrername:',
        ],
        'nl' => [
            // 'isRefundBySubject' => '',
            //'Company/Mr/Ms'    => '',
            'Time out' => ['Start van de rit'],
            'Time in'  => 'Einde van de rit',
            // 'amountBeforePrepaid' => '',
            'Subtotal' => 'Sub-totaal',
            'Total'    => 'Totaal',
            'INVOICE'  => 'Factuur',
            //'Registration No:' => '',
            'Res No.' => 'Ritnummer:',
            'Car:'    => 'Voertuig:',
            'Name:'   => 'Naam',
        ],
    ];

    private $pdfPattern = '.*\.pdf';

    private $lang = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sixt.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // PDF
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'sixt.') !== false && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'sixt.') !== false && $this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf, $parser->getSubject());
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parsePdf(Email $email, string $text, string $subject): void
    {
        $r = $email->add()->rental();

        // General
        if (preg_match("/{$this->t('isRefundBySubject')}/i", $subject, $m)) {
            $r->general()->status('refunded');
        }

        $traveller = preg_replace("/\s*\n\s*/", ' ', $this->re("#\n\s*" . $this->opt($this->t("Company/Mr/Ms")) . "\n(.+(?:\n[[:alpha:] \-]+)?)\n#", $text));

        if (empty($traveller)) {
            $traveller = $this->re("/{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-.&'[:alpha:] ]*[[:alpha:]])/u", $text);
        }

        if (empty($traveller)) {
            $traveller = $this->re("/{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-.&'[:alpha:] ]*[[:alpha:]]\n)/u", $text);
        }

        $r->general()
            ->confirmation($this->re("/[ ]{3,}" . $this->opt($this->t("Res No.")) . " *(\d{9,})\n/u", $text))
            ->traveller($traveller, true);

        // Pick Up
        if (preg_match("/\n *" . $this->opt($this->t("Time out")) . " ?\:?(?: {3,}.*)?\n(?: {40,}.*\n)? {0,10}\D{0,10}(?<date>\d.+\d{1,2}:\d{2})[ \-]+.{3,6}\:?\s(?<city>.+?)( {3,}|\n)/", $text, $m)) {
            $r->pickup()
                ->location($m['city'])
                ->date($this->normalizeDate($m['date']))
            ;
        } elseif (preg_match("/{$this->opt($this->t("Time out"))}.+Factuur.+\s(?<date>\d+\S\d+\S+\d{4}\s*\/\s*\d+\:\d+)(?<city>.+)\s+\w+\,\s*\s\d+\.\d+\.\d{4}\n/s", $text, $m)) {
            $r->pickup()
                ->location($m['city'])
                ->date($this->normalizeDate($m['date']));
        }
        // Drop Off
        if (preg_match("/\n *" . $this->opt($this->t("Time in")) . " ?\:?(?: {3,}.*)?\n(?: {40,}.*\n)? {0,10}\D{0,10}(?<date>\d.+\d{1,2}:\d{2})[ \-]+.{3,6}\:?\s(?<city>.+?)( {3,}|\n)/", $text, $m)) {
            $r->dropoff()
                ->location($m['city'])
                ->date($this->normalizeDate($m['date']))
            ;
        } elseif (preg_match("/{$this->opt($this->t("Time in"))}\s*.+\s+(?<date>\d+\S\d+\S+\d{4}\s*\/\s*\d+\:\d+)\s*(?<city>.+)Debiteurnr.:/", $text, $m)) {
            $r->dropoff()
                ->location($m['city'])
                ->date($this->normalizeDate($m['date']));
        }

        // Car
        $model = $this->re("/{$this->opt($this->t('Car:'))}[ ]*(.{2,})\n/", $text)
            ?? $this->re("/ {3}{$this->opt($this->t('Registration No:'))}.*\n.{40,} {3}(\w+ \w+) /", $text) // it-78946393.eml
        ;
        $r->car()->model($model, false, true);

        // Price (refunded only)
        $amountBeforePrepaid = $this->re("/\n[ ]*{$this->opt($this->t('amountBeforePrepaid'))}[ ]{3,}(.+?)(?:[ ]{3}|\n|$)/", $text);

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[- ]*(?<currency>[^\-\d)(]+)$/u', $amountBeforePrepaid, $matches)) {
            // 302.81- USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            return;
        }

        // Price
        $totalSegment = $this->re("/\n( *{$this->opt($this->t('Subtotal'))} {3,}(?:.*\n){1,7} *{$this->opt($this->t('Total'))} {3,}.+)/", $text);
        $totalSegment = preg_replace("/^ *(\S.+? {3,}\d[\d., ]* *[A-Z]{3}) {3,}.+/m", '$1', $totalSegment);
        $totalSegment = preg_replace("/^ {40,}.+\n/m", '', $totalSegment);

        if (!empty($totalSegment)) {
            $totals = explode("\n", $totalSegment);

            $cost = array_shift($totals);

            if (preg_match("/ {3,}(?<amount>\d[\d,. ]*) *(?<currency>[A-Z]{3})\b/", $cost, $m)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode))->currency($m['currency']);
            } elseif (preg_match("/[ ]{3,}(?<amount>\d[\d\,\.]*)\s+(?<currency>\S{1,3})/", $cost, $m)) {
                $currencyCode = $this->normalizeCurrency($m['currency']);
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode))
                    ->currency($currencyCode);
            }

            $total = array_pop($totals);

            if (preg_match("/ {3,}(?<amount>\d[\d,. ]*) *(?<currency>[A-Z]{3})\b/", $total, $m)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                $r->price()->total(PriceHelper::parse($m['amount'], $currencyCode))->currency($m['currency']);
            } elseif (preg_match("/[ ]{3,}(?<amount>\d[\d\,\.]*)\s+(?<currency>\S{1,3})/", $total, $m)) {
                $currencyCode = $this->normalizeCurrency($m['currency']);
                $r->price()->total(PriceHelper::parse($m['amount'], $currencyCode))
                    ->currency($currencyCode);
            }

            foreach ($totals as $tax) {
                if ((preg_match("/^(?<name>.{2,}?)[ ]{3,}(?<amount>\d[\d,. ]*)[ ]*(?<currency>[A-Z]{3})\b/", $tax, $m)
                    || preg_match("/^(?<name>.{2,}?)[ ]{3,}(?<amount>\d[\d,. ]*)\s+(?<currency>\S{1,3})/", $tax, $m))
                    && !preg_match("/^(?:{$this->opt($this->t('Subtotal'))}|{$this->opt($this->t('Total'))})$/i", $m['name'])
                ) {
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                    $r->price()->fee($m['name'], PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }
    }

    private function assignLang($text): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (preg_match("/{$this->opt($dict['Time out'])}/", $text) && preg_match("/{$this->opt($dict['INVOICE'])}/", $text)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('date: ' . $str);
        $in = [
            //01/13/2021 / 08:15; 21.12.2019 / 09:24
            "/^\s*(\d+[\/.]\d+[\/.]\d{4}) *\/ *(\d+:\d+)\s*$/",
        ];
        $out = [
            "$1, $2",
        ];

        $str = preg_replace($in, $out, $str);

        return strtotime($this->dateStringToEnglish($str));
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
