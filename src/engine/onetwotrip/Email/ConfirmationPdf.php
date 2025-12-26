<?php

namespace AwardWallet\Engine\onetwotrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "onetwotrip/it-143543961.eml, onetwotrip/it-52349830.eml, onetwotrip/it-698827205.eml";
    public $reFrom = '@onetwotrip.com';
    public $reBody = [
        'en' => ['ITINERARY RECEIPT / E-TICKET', 'PASSENGER NAME'],
    ];
    public $reSubject = [
        // en
        'OneTwoTrip Flight Confirmation. Booking No.',
        'Ваши авиабилеты на OneTwoTrip по заказу',
    ];
    public $lang;
    public $pdfNamePattern = "[\w_\-]{10,}.*pdf";
    public static $dict = [
        'en' => [
            'HOTEL:' => ['HOTEL:', 'Hotel:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        $text = substr($text, 0, 10000);
                        $this->parseEmailPdf($email, $text);
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->logger->debug($text);

                    if (stripos($text, 'One Two Trip LLP') === false
                        && stripos($text, 'White Travel LLC') === false
                        && stripos($text, 'OTT Service LTD') === false
                        && stripos($text, 'ООО "Вайт Тревел"') === false
                    ) {
                        continue;
                    }

                    if ($this->assignLang($text)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'],
                $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf(Email $email, string $text)
    {
//        $this->logger->debug($text);

        if (preg_match_all('/(?<=\n)( *DEPARTURE:.+?ARRIVAL:.+?\d+:\d+.+?\s+[A-Z]{3}\s*\n)/s', $text, $segmentMatches, PREG_PATTERN_ORDER)) {
            $first = true;

            if (preg_match('# NO.+?\s+([A-Z]\d{7,})\s+.+?DEPARTURE:#s', $text, $m)) {
                $foundIt = false;

                foreach ($email->getItineraries() as $it) {
                    if ($it->getTravelAgency() && in_array($m[1], array_column($it->getTravelAgency()->getConfirmationNumbers(), 0))) {
                        $f = $it;
                        $foundIt = true;
                        $first = false;

                        break;
                    }
                }

                if ($foundIt === false) {
                    $f = $email->add()->flight();
                    $f->ota()->confirmation($m[1]);
                    $f->general()->noConfirmation();
                }
            } else {
                $f = $email->add()->flight();
            }

            // PASSENGER: EVGENIYA MAXIMOVA
            $traveller = $this->re('/PASSENGER: *(.+?) *\n/', $text);

            if (empty($traveller)) {
                $text = $this->re('/\n( *PASSENGER NAME \/ TRAVEL[\s\S]+?)\n *DEPARTURE:/', $text);
                $table = $this->createTable($text, $this->rowColumnPositions($this->inOneRow($text . "\n" . $text)));

                if (preg_match("/PASSENGER NAME\s*\/\s*TRAVEL\s*DOCUMENT\s*NO\.\n\s*([\s\S]+?)\s+\//", $table[0] ?? '', $m)) {
                    $traveller = preg_replace("/\s*\n\s*/", ' ', $m[1]);
                }
            }
            $f->general()
                ->traveller($traveller);

            $ticket = $this->re('/e-Ticket No\. *(\d{10,})\s+/', $text);

            if (!empty($ticket)) {
                $f->issued()->ticket($ticket, false);
            }

            // Price
            $currency = '[A-Z]{3}';

            if ($first !== true && !empty($f->getPrice())) {
                $currency = $f->getPrice()->getCurrencyCode();
            }

            if (preg_match('/TOTAL\s{3,}([\d.,\s ]+)\s*(' . $currency . ')\s+ENDORSEMENTS/', $text, $m)) {
                $f->price()->total($this->normalizeAmount($m[1]) + ($first ? 0.0 : $f->getPrice()->getTotal()));
                $f->price()->currency($m[2]);
            }

            // FARE                                                       13 820.00          RUB
            // TAXES AND CARRIER-IMPOSED FEES                             788.00          RUB
            if (preg_match('/FARE\s{3,}([\d.,\s ]+)\s*' . $currency . '/', $text, $m)) {
                $f->price()->cost($this->normalizeAmount($m[1]) + ($first ? 0.0 : $f->getPrice()->getCost()));
            }

            if (preg_match('/TAXES AND CARRIER-IMPOSED FEES\s{3,}([\d.,\s]+)\s*' . $currency . '/', $text, $m)) {
                $f->price()->fee('TAXES AND CARRIER-IMPOSED FEES', $this->normalizeAmount($m[1]));
            }

            foreach ($segmentMatches[1] as $seg) {
                $s = $f->addSegment();
//                $this->logger->debug('========================================');
//                $this->logger->debug($seg);

                // DEPARTURE:          FEB 28, 2020                  DUBAI AIRPORT , TERMINAL 3
                //                     3:55 PM                       DUBAI, AE                    DXB
                if (preg_match('/DEPARTURE:\s+(?<date>.+?)\s{3,}(?<airoport>.+?)\n\s*(?<time>\d+:\d+.+?) {3,}(?<city>.+?) {3,}(?<code>[A-Z]{3})\n/', $seg, $m)
                    || preg_match('/DEPARTURE:\s+(?<date>.+?)\s{3,}(?<airoport>.+?)\n\s*(?<time>\d+:\d+.+?) {3,}(?<airoport2>(?:\S ?)+?)\n +(?<city>(?:\S ?)+?)\s{3,}(?<code>[A-Z]{3})\n/', $seg, $m)
                ) {
                    // DEPARTURE:        JAN 5, 2025         ISLAM KARIMOV TASHKENT INTERNATIONAL
                    //                   10:30               AIRPORT AIRPORT , TERMINAL 2
                    //                                       TASHKENT, UZ
                    //                                                                                 TAS
                    $s->departure()
                        ->date2("{$m['date']}, {$m['time']}")
                        ->strict()
                        ->name("{$this->re('/^(.+?)\s*(?:,\s*TERMINAL|$)/', trim($m['airoport'] . ' ' . ($m['airoport2'] ?? '')))}, {$m['city']}")
                        ->code($m['code']);

                    if ($term = $this->re('/TERMINAL (\w{1,4})/', $m['airoport'])) {
                        $s->departure()->terminal($term);
                    }
                }

                if (preg_match('/ARRIVAL:\s+(?<date>.+?)\s{3,}(?<airoport>.+?)\n\s*(?<time>\d+:\d+.+?) {3,}(?<city>.+?) {3,}(?<code>[A-Z]{3})\n/',
                    $seg, $m)) {
                    $s->arrival()
                        ->date2("{$m['date']}, {$m['time']}")
                        ->strict()
                        ->name("{$this->re('/^(.+?)\s*(?:,\s*TERMINAL|$)/', $m['airoport'])}, {$m['city']}")
                        ->code($m['code']);

                    if ($term = $this->re('/TERMINAL (\w{1,4})/', $m['airoport'])) {
                        $s->arrival()->terminal($term);
                    }
                }

                // Flight#       EK-51                         Economy (LLW9PAE1)
                if (preg_match('/Flight#\s*([A-Z\d]{2})-(\d{2,4})\s+([A-z]{4,10})\s/', $seg, $m)) {
                    $s->airline()->name($m[1]);
                    $s->airline()->number($m[2]);
                    $s->extra()->cabin($m[3]);
                }
                // PNR No.        C6BECJ, "EK"                  Baggage: 25 kg per passenger.
                if (preg_match('/PNR No.\s*([A-Z\d]{5,6}),/', $seg, $m)) {
                    $s->airline()->confirmation($m[1]);
                }

                if (preg_match('/Operated by +(.+)/', $seg, $m)) {
                    $s->airline()->operator($m[1]);
                }

                if (preg_match('/Status: *(\w+)/', $seg, $m)) {
                    $s->extra()->status($m[1]);
                }

                foreach ($f->getSegments() as $key => $seg) {
                    if ($s->getId() !== $seg->getId() && serialize($s->toArray()) == serialize($seg->toArray())) {
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
