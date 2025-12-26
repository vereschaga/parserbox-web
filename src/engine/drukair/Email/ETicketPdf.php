<?php

namespace AwardWallet\Engine\drukair\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "drukair/it-710106105.eml, drukair/it-710279661.eml";

    public $pdf;
    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'PASSENGER AND TICKET INFORMATION' => 'PASSENGER AND TICKET INFORMATION',
            'TRAVEL INFORMATION'               => 'TRAVEL INFORMATION',
            'FARE AND ADDITIONAL INFORMATION'  => 'FARE AND ADDITIONAL INFORMATION',
        ],
    ];

    private $detectFrom = "drukairticketing@drukair.com.bt";
    private $detectSubject = [
        // en
        'Druk Air eTicket',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]drukair\.com\.bt$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Druk Air') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

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
        if ($this->containsText($text, ['drukair.com.bt', 'Drukair offices']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['PASSENGER AND TICKET INFORMATION'])
                && $this->containsText($text, $dict['PASSENGER AND TICKET INFORMATION']) === true
                && !empty($dict['TRAVEL INFORMATION'])
                && $this->containsText($text, $dict['TRAVEL INFORMATION']) === true
                && !empty($dict['FARE AND ADDITIONAL INFORMATION'])
                && $this->containsText($text, $dict['FARE AND ADDITIONAL INFORMATION']) === true
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
            $text = \PDF::convertToHtml($parser->getAttachmentBody($pdf));
            $text = $this->htmlToText($text);

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("/\n{$this->opt($this->t('BOOKING REFERENCE'))}\s+([A-Z\d]{5,7})\n/", $textPdf))
            ->traveller(preg_replace(["/ (MR|MS|MRS)$/i", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"], ['', '$2 $1'],
                $this->re("/\n{$this->opt($this->t('PASSENGER NAME'))}\s+(.+)\n/", $textPdf)))
        ;

        // Issued
        $f->issued()
            ->ticket($this->re("/\n{$this->opt($this->t('E-TICKET NUMBER'))}\s+(\d{8,})\n/", $textPdf), false);

        // Segments
        $segments = $this->split("/\n( *(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,4}\s+.+\s+\d{1,2}:\d{2})/", $textPdf);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $re = "/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,4})\s+(?<dDate>.+\s+\d{1,2}:\d{2})\n\s*(?<dName>.+?)\s*\((?<dCode>[A-Z]{3})\)(?<dTerminal>\/.+)?\n\s*(?<duration>\d+H\d+M)(?<stops>\s*\([^\)]*\))?\s+[A-Z]+ (?<cabin>.+)\n[\S\s]+?\s+(?<aDate>\w+ \d+ \w+ \d+\s+\d{1,2}:\d{2})\n\s*(?<aName>.+?)\s*\((?<aCode>[A-Z]{3})\)(?<aTerminal>\/.+)?/";

            if (preg_match($re, $sText, $m)) {
                // Arrival
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                // Departure
                $s->departure()
                    ->name($m['dName'])
                    ->code($m['dCode'])
                    ->date($this->normalizeDate($m['dDate']))
                    ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m['dTerminal'] ?? '', '/'))), true)
                ;

                // Arrival
                $s->arrival()
                    ->name($m['aName'])
                    ->code($m['aCode'])
                    ->date($this->normalizeDate($m['aDate']))
                    ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m['aTerminal'] ?? '', '/'))), true)
                ;

                // Extra
                $s->extra()
                    ->duration($m['duration'])
                    ->cabin($m['cabin'])
                ;

                if (preg_match("/^\s*\D*(\d+)\D*\s*/", $m['stops'], $mat)) {
                    $s->extra()
                        ->stops($mat[1]);
                }
            }
        }

        // Price
        $cost = null;

        if (preg_match("/\n{$this->opt($this->t('BASE FARE'))}\s+([A-Z]{3}) *(\d[\d., ]*)\n/", $textPdf, $m)) {
            $cost = PriceHelper::parse($m[2], $m[1]);
            $f->price()
                ->currency($m[1]);
        }
        $f->price()
            ->cost($cost);
        $tax = null;

        if (preg_match("/\n{$this->opt($this->t('TAXES'))}\s+([A-Z]{3}) *(\d[\d., ]*)\n/", $textPdf, $m)) {
            $tax = PriceHelper::parse($m[2], $m[1]);
            $f->price()
                ->currency($m[1]);
        }
        $f->price()
            ->tax($tax);
        $total = null;

        if (preg_match("/\n{$this->opt($this->t('TOTAL'))}\s+([A-Z]{3}) *(\d[\d., ]*)\n/", $textPdf, $m)) {
            $total = PriceHelper::parse($m[2], $m[1]);
            $f->price()
                ->currency($m[1]);
        }
        $f->price()
            ->total($total);

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
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            //  Tue 01 Oct 24 15:20
            '/^\s*[\w\-]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
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
}
