<?php

namespace AwardWallet\Engine\amextravel\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TaxInvoicePdf extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-789676973.eml, amextravel/it-794543984.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'TAX INVOICE' => ['TAX INVOICE', 'Invoice Booking Reference'],
            // 'Booking Reference' => ['Booking Reference', 'Invoice Booking Reference'],
            'Other Ticket Taxes'   => ['Other Ticket Taxes', 'Ticket Tax Fare / Charges'],
            'Flight Details'       => 'Flight Details',
            'endTicketInformation' => ['Fee Information', 'Credit Card Information', 'Payment Details'],
            'Payment Details'      => 'Payment Details',
        ],
    ];

    private $detectFrom = "donotreply@mytrips.amexgbt.com";
    private $detectSubject = [
        // en
        // INVOICE 2069886 for GANGULY/UJJWAL WED 20NOV2024DELHI,INDIA/MUMBAI Ref 9LCSND
        'INVOICE ',
    ];
    private $detectBody = [
        //        'en' => [
        //            '',
        //        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]amexgbt\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, ['American Express Global Business Travel']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['TAX INVOICE'])
                && $this->containsText($text, $dict['TAX INVOICE']) === true
                && !empty($dict['Flight Details'])
                && $this->containsText($text, $dict['Flight Details']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            // if ($this->detectPdf($text) == true) {
            $this->parseEmailPdf($email, $text);
            // }
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

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $pos = $this->strposAll($textPdf, $this->t('Payment Details'));
        $pos2 = $this->strposAll($textPdf, $this->t('Total Charge'), $pos);

        if ($pos > 100 && $pos2 > 0) {
            // Дополнительная проверка, что нет полной резервации, как парсере ItineraryInvoice
            $part = substr($textPdf, $pos2 + 50);
            preg_match_all("/^.*\d.*$/m", $part, $m);

            if (empty($part) || count($m[0]) > 5) {
                $this->logger->info('maybe full reservation (check ItineraryInvoice)');

                return false;
            }
        }

        $email->obtainTravelAgency();

        if (preg_match("/{$this->opt($this->t('Trip ID'))}[ ]*- *([A-Z\d]{5,})(?:[ ]{2}|$)/", $textPdf, $m)) {
            $email->ota()
                ->confirmation($m[1]);
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Booking Reference'))}[ ]*([A-Z\d]{5,})(?:[ ]{2}|\n)/", $textPdf))
            ->traveller($this->re("/{$this->opt($this->t('Passenger Name'))} +(\S.+?)(?: {2,}|\n)/u", $textPdf))
        ;
        $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $textPdf)));

        if (!empty($resDate)) {
            $f->general()
                ->date($resDate);
        }

        if (preg_match("/{$this->opt($this->t('Ticket Number'))}[ ]+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2})(?: {2,}|\n)/", $textPdf, $m)) {
            $f->issued()
                ->ticket($m[1], false);
        }

        // Segments
        $flightText = $this->re("/{$this->opt($this->t('Passenger Name'))} {2,}.+(?:\n.*){0,3}\n(.* {2,}\w+ \w+ \w+ \S[\S\s]+?)\n *{$this->opt($this->t('endTicketInformation'))}/", $textPdf);
        $flightText = preg_replace("/^(?: {0,5}\S.+?)( {2,}.+|)$/m", '$1', $flightText);
        // $this->logger->debug('$flightText = '.print_r( $flightText,true));

        $segments = $this->split("/\n( *\w+ \w+ 20\d{1,2})/", "\n\n" . $flightText . "\n\n");
        // $this->logger->debug('$segments = ' . print_r($segments, true));
        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<date>\w+ \w+ \w+) +(?<an>.+)\n\s*(?<fn>\d{1,4}) (?<code>[A-Z]{1,2}) Class\s+(?<d>[^\/]+)\/(?<a>[^\/]+)(?:\n|$)/", $sText, $m)) {
                $s->airline()
                    ->name($m['an'])
                    ->number($m['fn']);

                $s->departure()
                    ->noDate()
                    ->day(strtotime($m['date']))
                    ->noCode()
                    ->name($m['d']);

                $s->arrival()
                    ->noDate()
                    ->noCode()
                    ->name($m['a']);
            }
        }

        // Price
        if (preg_match("/ {2,}Total \((?<currency>[A-Z]{3})\) Ticket Amount {2,}(?<value>\d.*)/", $textPdf, $m)) {
            $currency = $m['currency'];
            $total = PriceHelper::parse($m['value'], $m['currency']);

            if (preg_match("/\n *Payment Details\n[\S\s]+?\n {5,}Total Charge {2,}(?<value>\d.*)/", $textPdf, $m)) {
                $total = PriceHelper::parse($m['value'], $currency);
            }
            $email->price()
                ->currency($currency)
                ->total($total);

            if (preg_match("/ {2,}Ticket Base Fare {2,}(?<value>\d.*)/", $textPdf, $m)) {
                $email->price()
                    ->cost(PriceHelper::parse($m['value'], $currency));
            }

            if (preg_match("/ {2,}{$this->opt($this->t('Other Ticket Taxes'))} {2,}(?<value>\d.*)/", $textPdf, $m)) {
                $email->price()
                    ->tax(PriceHelper::parse($m['value'], $currency));
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function strposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = strpos($text, $n);

                if ($pos !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
        }

        return false;
    }
}
