<?php

namespace AwardWallet\Engine\iryo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelingWith extends \TAccountChecker
{
    public $mailFiles = "iryo/it-354647999.eml, iryo/it-356557270.eml";
    public $subjects = [
        'gracias por viajar con iryo!', // es
        'thank you for traveling with iryo!', // en
    ];

    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public $lastNumber;
    public $lastDepDate;

    public $detectLang = [
        "es" => [
            "Salida",
        ],
        "en" => [
            "Departure",
        ],
    ];

    public static $dictionary = [
        "es" => [
            'Enjoy your sustainable trip.' => 'Disfruta de tu viaje sostenible.',
            'More information on iryo.eu'  => 'Mas informaci6n en iryo.eu',
            'LOCATOR:'                     => 'LOCALIZADOR:',
            'Departure'                    => 'Salida',
            'Arrival'                      => 'Llegada',
            'Train'                        => 'Tren',
            'CARRIAGE'                     => 'COCHE',
            'SEAT'                         => 'ASIENTO',
            'TICKET:'                      => 'BILLETE:',
            'TOTAL PRICE:'                 => 'PRECIO TOTAL:',
        ],
        "en" => [
            'Enjoy your sustainable trip.' => ['Enjoy your sustainable trip.', 'jForma parte del Bosque lnteligente!'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@iryo.eu') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignLang($textPdf)) {
                continue;
            }

            if ($this->strposArray($textPdf, $this->t('TICKET:')) !== false
                && $this->strposArray($textPdf, $this->t('More information on iryo.eu')) !== false
                && $this->strposArray($textPdf, $this->t('Enjoy your sustainable trip.')) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iryo\.eu$/', $from) > 0;
    }

    public function parseTrain(Email $email, string $text): void
    {
        $t = $email->add()->train();
        $s = $t->addSegment();

        $tickets = [];
        $travellers = [];
        $confs = [];
        $total = [];

        $segments = $this->splitter("/({$this->opt($this->t('Enjoy your sustainable trip.'))}\n+)/", $text);

        foreach ($segments as $seg) {
            $info = $this->re("/^(.+){$this->opt($this->t('LOCATOR:'))}/s", $seg);
            $tableInfo = $this->splitCols($info, [0, 50, 100]);

            if (preg_match("/{$this->opt($this->t('Enjoy your sustainable trip.'))}\n+(?<depName>\D*(?:\n.+)?)\n{$this->opt($this->t('Departure'))}\n(?<depDate>[\d\.]+)\n(?<depTime>[\d\:]+)\n+(?<pax>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\n+/u", $tableInfo[0], $m)) {
                $depName = preg_replace('/\s+/', ' ', trim($m['depName']));
                $depDate = strtotime($m['depDate'] . ', ' . $m['depTime']);
                $travellers[] = $m['pax'];

                $number = $this->re("/{$this->opt($this->t('Train'))}\s*\n+\s*(?<number>\d{2,})/", $tableInfo[1]);

                if (preg_match("/^\n*(?<arrName>\D*(?:\n.+)?)\n*{$this->opt($this->t('Arrival'))}\n*(?<arrDate>[\d\.]+)\n(?<arrTime>[\d\:]+)\n+$/", $tableInfo[2], $m)) {
                    $arrName = preg_replace('/\s+/', ' ', trim($m['arrName']));
                    $arrDate = strtotime($m['arrDate'] . ', ' . $m['arrTime']);
                } else {
                    $arrName = '';
                    $arrDate = 0;
                }

                if (preg_match("/{$this->opt($this->t('CARRIAGE'))}\s*{$this->opt($this->t('SEAT'))}\n+\s*(?<carNumber>\d+)\s*(?<seat>\d+[A-Z])\n/u", $seg, $m)) {
                    $carNumber = $m['carNumber'];
                    $seat = $m['seat'];
                } else {
                    $carNumber = $seat = null;
                }

                $ticket = $this->re("/{$this->opt($this->t('TICKET:'))}\s+(\d{10,})/", $seg);
                $tickets[] = $ticket;

                $conf = $this->re("/{$this->opt($this->t('LOCATOR:'))}\s+([A-Z\d]{6})\s+/", $seg);
                $confs[] = $conf;

                if (preg_match("/{$this->opt($this->t('TOTAL PRICE:'))}\s*(?<currency>[A-Z]{3})\s+(?<total>[\d\.\,]+)\n/", $seg, $m)) {
                    $t->price()
                        ->currency($m['currency']);

                    $total[] = PriceHelper::parse($m['total'], $m['currency']);
                }

                if (empty($this->lastNumber) && empty($this->lastDepDate)) {
                    $this->lastDepDate = $depDate;
                    $this->lastNumber = $number;
                } else {
                    $noNew = false;

                    $saveSegs = $t->getSegments();

                    foreach ($saveSegs as $saveSeg) {
                        if ($saveSeg->getNumber() === $number) {
                            $s = $saveSeg;
                            $noNew = true;
                        }
                    }

                    if ($noNew === false) {
                        $s = $t->addSegment();
                    }
                }

                $s->setNumber($number);
                $s->extra()->car($carNumber, false, true)->seat($seat, false, true);

                $s->departure()
                    ->name($this->normalizeStation($depName))
                    ->date($depDate);

                $s->arrival()
                    ->name($this->normalizeStation($arrName))
                    ->date($arrDate);
            }
        }

        $t->setTicketNumbers(array_unique($tickets), false);

        $t->general()
            ->travellers(array_unique($travellers), true);

        if (count($total) > 0) {
            $t->price()->total(array_sum($total));
        }

        foreach (array_unique($confs) as $conf) {
            $t->general()
                ->confirmation($conf);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfFileNames = $pdfTexts = $pdfSegCount = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $i => $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignLang($textPdf)) {
                continue;
            }

            if (preg_match_all("/{$this->opt($this->t('Enjoy your sustainable trip.'))}\n/", $textPdf, $matches)) {
                $pdfFileNames[$i] = $this->getAttachmentName($parser, $pdf) ?? "attachment-{$i}.pdf";
                $pdfTexts[$i] = $textPdf;
                $pdfSegCount[$i] = count($matches[0]);
            }
        }

        $textPdfFull = '';

        if (count($pdfTexts) > 0
            && count($pdfTexts) === count($pdfSegCount) && count($pdfTexts) === count($pdfFileNames)
        ) {
            $pdfFileNames_collection1 = []; // with `PASSENGERS`
            $pdfFileNames_collection2 = []; // without `PASSENGERS`

            foreach ($pdfFileNames as $i => $fileName) {
                if (preg_match("/(?:^|_)PASSENGERS[-_]?\d{1,3}(?:_|\.pdf|$)/i", $fileName)) {
                    $pdfFileNames_collection1[$i] = $fileName;
                } else {
                    $pdfFileNames_collection2[$i] = $fileName;
                }
            }

            if (count($pdfFileNames_collection2) === 1) {
                // only one PDF without `PASSENGERS`
                $textPdfFull = implode("\n\n", array_intersect_key($pdfTexts, $pdfFileNames_collection2));
            } elseif (count($pdfFileNames_collection1) === count($pdfFileNames)) {
                // all PDF's contains `PASSENGERS` in file name
                $textPdfFull = implode("\n\n", $pdfTexts);
            } else {
                // only one PDF has a larger number of segments

                arsort($pdfSegCount); // [1, 1, 2] -> [2, 1, 1]

                foreach ($pdfSegCount as $i => $countVal) {
                    if (!isset($firstValId, $firstVal)) {
                        $firstValId = $i;
                        $firstVal = $countVal;

                        continue;
                    }

                    if ($countVal !== $firstVal) {
                        $textPdfFull = $pdfTexts[$firstValId];
                    }

                    break;
                }
            }
        }

        if ($textPdfFull) {
            $this->parseTrain($email, $textPdfFull);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('TravelingWith' . ucfirst($this->lang));

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

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf): ?string
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function assignLang($text): bool
    {
        foreach ($this->detectLang as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function splitter($regular, $text, $deleteFirst = false)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($deleteFirst === true) {
            array_shift($array);
        }

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
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

    private function normalizeStation(string $text): string
    {
        $text = str_replace('ó', 'o', $text);

        if (empty($text)) {
            return '';
        }

        $region = preg_match("/^(?:Cordoba)$/iu", $text) > 0 ? 'Spain' : 'Europe';

        return implode(', ', [$region, $text]);
    }
}
