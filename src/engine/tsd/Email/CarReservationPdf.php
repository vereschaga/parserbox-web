<?php

namespace AwardWallet\Engine\tsd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarReservationPdf extends \TAccountChecker
{
    public $mailFiles = "tsd/it-87165801.eml";
    public $lang = "en";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = 'mailer@tsdnotify.com';
    private $detectCompany = 'tsdnotify.com';

    private $detectOtherCompany = ['THRIFTY CAR RENTAL'];

    private $detectSubject = [
        // en
        " - Confirmation No.:",
    ];

    private $detectBody = [
        "en" => ["This Reservation is valid until"],
    ];

    private $rentalProviders = [
        'thrifty' => [
            'THRIFTY CAR RENTAL',
        ],
        'dollar' => [
            'DOLLAR RENT A CAR',
        ],
        'foxrewards' => [
            'FOX RENT A CAR',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".+\.pdf");

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (preg_match("/\n\s*LESSOR: {3,}RENTER:([ ]{3,}|\n)/", $text)) {
                    $this->parseEmailPdf($text, $email);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".+\.pdf");

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (preg_match("/\n\s*LESSOR: {3,}RENTER:([ ]{3,}|\n)/", $text)
                && preg_match("/\n\s*CONFIRM\. NO\.:.+ {3,}BOOKED DATE: .+\n/", $text)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmailPdf(string $text, Email $email)
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        $email->obtainTravelAgency();

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->re("/\n *CONFIRM\. NO\.: *([A-Z\d\-]+) {3,}/", $text))
            ->date(strtotime($this->re("/ *BOOKED DATE: *(.+)\n/", $text)))
        ;

        $table = $this->splitCols($this->re("/\n( *LESSOR: {3,}RENTER:(?: {3,}.*|\n)[\s\S]+?)\n *CONFIRM\. NO\.:/", $text));

        if (preg_match("/RENTER:\s+([^,\n]+),([^,\n]+)/", $table[1] ?? '', $m)) {
            $r->general()
                ->traveller(trim($m[2]) . ' ' . trim($m[1]), true);
        }

        // Provider
        $rentalCompany = $this->re("/^\s*(.+?)[\- ]+Reservation Summary\s*$/", $text);

        if (!empty($rentalCompany)) {
            $r->extra()->company($rentalCompany);

            foreach ($this->rentalProviders as $code => $detects) {
                foreach ($detects as $detect) {
                    if ($rentalCompany === $detect) {
                        $r->setProviderCode($code);

                        break 2;
                    }
                }
            }
        }

        if (preg_match("/LESSOR:\s*(?<location>[\s\S]+?)\n(?<phone>[\d \-\+\(\)]{6,})\s*$/", $table[0] ?? '', $m)) {
            $r->pickup()
                ->location(preg_replace("/\s+/", ' ', trim($m['location'])))
                ->phone($m['phone'])
            ;
            $r->dropoff()->same();
        } elseif (preg_match("/LESSOR:\s*(?<location>[\s\S]+)\s*$/", $table[0] ?? '', $m)) {
            $r->pickup()
                ->location(preg_replace("/\s+/", ' ', trim($m['location'])))
            ;
            $r->dropoff()->same();
        }

        // Pick Up
        $r->pickup()
            ->date(strtotime($this->re("/\n\s*Pick-up date *(.+?\d{1,2}:\d{2} [ap]m) /i", $text)))
        ;

        // Drop Off
        $r->dropoff()
            ->date(strtotime($this->re("/\n\s*Return date *(.+?\d{1,2}:\d{2} [ap]m) /i", $text)))
        ;

        // Car
        $r->car()
            ->type($this->re("/ Unit type\/description +(.+)/", $text))
        ;

        if (preg_match('/\n *Total Charges *(\d[\d, ]*(\.\d{2})?)\s*(?:$|\n)/', $text, $m)) {
            $r->price()
                ->total(PriceHelper::cost($m[1]))
                ->currency('USD');
        }

        $taxes = array_filter(explode("\n", $this->re("/\n\s*Description +Total\n([\s\S]+)\n *Total Charges {3,}/", $text)));

        foreach ($taxes as $row) {
            if (preg_match("/ *(.*\b(?:Tax|Fee)\b.*|.*\d%\)) {3,}(\d[\d, ]*(\.\d{2})?) *$/i", $row, $m)) {
                $r->price()
                    ->fee($m[1], PriceHelper::cost($m[2]))
                ;
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function getNode(string $s, int $n = 1, bool $contains = false): ?string
    {
        if (!$contains) {
            return $this->http->FindSingleNode("//node()[normalize-space(.)='{$s}']/following-sibling::node()[normalize-space(.)!=''][{$n}]");
        } else {
            return $this->http->FindSingleNode("(//node()[contains(normalize-space(.), '{$s}')]/following-sibling::node()[normalize-space(.)!=''][{$n}])[1]");
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
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
}
