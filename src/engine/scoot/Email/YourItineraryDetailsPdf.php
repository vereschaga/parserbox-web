<?php

namespace AwardWallet\Engine\scoot\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class YourItineraryDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "scoot/it-12968457.eml, scoot/it-19772067.eml, scoot/it-27288263.eml, scoot/it-28631497.eml";

    // name="Itinerary – OFHUPE.pdf"    |    name="=?UTF-8?B?SXRpbmVyYXJ5IOKAkyBPRkhVUEUucGRm?="
    private $pdfPattern = '(?:.*pdf|[=?]{2,3}[-_?A-z\d]+[?=]{2,3})';

    private $langDetectors = [
        'id' => ['Waktu Check in :'],
        'en' => ['Check-in time:', 'Check-in time :'],
        'th' => ['เช็คอินเวลา'],
    ];

    private $lang = '';

    private static $dict = [
        'id' => [
            'Fare Rules'              => 'Aturan Tarif',
            'Booking Date:'           => 'Tanggal Pemesanan:',
            'Scoot Booking Reference' => 'Ref Pemesanan Scoot',
            'Booking Status:'         => 'Status Pemesanan:',
            'passengersHeader'        => "Detail Penumpang\n",
            'Depart'                  => 'Berangkat',
            'Arrive'                  => 'Tiba',
            'Your payment details'    => 'Detail pembayaran anda',
        ],
        'th' => [
            'Fare Rules'                    => 'ระเบียบค่าโดยสาร',
            'Booking Date:'                 => 'วันที สํารองที นั ง:',
            'Scoot Booking Reference'       => 'รห ัสอ ้างอิงการสํารองทีนัง Scoot',
            'Booking Status:'               => 'สถานะการสํารองที น ั ง:',
            'passengersHeader'              => "รายละเอยดของผูโ้ ดยสาร\n",
            'Depart'                        => 'ขาออก',
            'Arrive'                        => ['เดินทางถึง', 'เดินทาง'],
            'Terminal'                      => 'อาคารผู ้โดยสาร',
            'Layover time'                  => 'เวลาหยุดพัก',
            'Your payment details'          => 'รายละเอียดการชําระเงินของคุณ',
            'Total'                         => 'รวม',
        ],
        'en' => [
            'passengersHeader' => ["Passenger on this flight\n", "Passenger on this ýight\n", "Passenger on this ight\n"],
        ],
    ];

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'Scoot Booking') === false && strpos($textPdf, 'board Scoot flights') === false
                && stripos($textPdf, 'Scoot-in-Style') === false && stripos($textPdf, 'checkin.flyscoot.com') === false
                && stripos($textPdf, 'checkin. yscoot.com') === false) {
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
        $textPdfFull = '';
        $textPdfPayment = '';

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= $textPdf;
            }

            if (preg_match("/{$this->opt($this->t('Your payment details'))}/", $textPdf)) {
                $textPdfPayment .= $textPdf;
            }
        }

        if (!$textPdfFull) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourItineraryDetailsPdf' . ucfirst($this->lang));

        if (!$this->parsePdf($email, $textPdfFull)) {
            return $email;
        }

        // p.currencyCode
        // p.total
        if (preg_match("/^[ ]*{$this->opt($this->t('Total'))}\s+(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*?)$/mi", $textPdfPayment, $matches)
            || preg_match("/^[ ]*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*?)\s+{$this->opt($this->t('Total'))}$/mi", $textPdfPayment, $matches)
        ) {
            // Total SGD 523.00    |    SGD 523.00 Total
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parsePdf(Email $email, $text): bool
    {
        $text = preg_replace("/\n +( ๊| ื|  ี| ั| ิ) /u", '', $text);
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
            'travellerName' => '[A-z][-.\'A-z ]*?[A-z]', // Mr. Hao-Li Huang
        ];

        $endPos = strripos($text, $this->t('Fare Rules') . "\n");

        if ($endPos !== false) {
            $text = substr($text, 0, $endPos);
        }

        $f = $email->add()->flight();

        // reservationDate
        if (preg_match('/' . $this->opt($this->t('Booking Date:')) . '[ ]*(.{6,}?)(?:[ ]{2}|$)/m', $text, $matches)) {
            $f->general()->date2($this->normalizeDate($matches[1]));
        }

        // confirmation number
        if (preg_match('/^[ ]*(' . $this->opt($this->t('Scoot Booking Reference')) . ')\s*([A-Z\d]{5,})$/m', $text, $matches)) {
            $f->general()->confirmation($matches[2], $matches[1]);
        }

        // status
        $bookingStatus = preg_match('/' . $this->opt($this->t('Booking Status:')) . '[ ]*(.+?)(?:[ ]{2}|$)/um', $text, $matches) ? $matches[1] : '';
        $f->general()->status($bookingStatus);

        $endPos = $this->striposArray($text, $this->t('passengersHeader'), true);

        if ($endPos !== false) {
            $segmentsText = substr($text, 0, $endPos);
            $passengersText = substr($text, $endPos);
        } else {
            $this->logger->debug('Segments not found!');

            return false;
        }

        $passengers = [];
        $seatsBySection = [];

        // SIN - SGN (TR304)    PlusPerks    Seat    Baggage
        $passengerSections = $this->splitText($passengersText, '/^([ ]{0,15}[A-Z]{3}[ ]*-[ ]*[A-Z]{3}[ ]*\((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+\)[ ]{2,}.+)/m', true);

        foreach ($passengerSections as $key => $sectionText) {
            // Mr WEI SIANG TANG    -    16B    -
            if (preg_match_all('/^[ ]{0,15}([\p{Thai}]{1,6} )?(?<passenger>' . $patterns['travellerName'] . ')[ ]{2,}-*[ ]{2,}(?<seat>\d{1,5}[A-Z]\b)?(.*?\n[ ]{0,15}(?<passenger2>[A-z][-.\'A-z ]*?[A-z])\s*\n)?/mu', $sectionText, $passengerMatches)) {
                foreach ($passengerMatches[0] as $i => $value) {
                    $passengers[] = trim($passengerMatches['passenger'][$i] . ' ' . ($passengerMatches['passenger2'][$i] ?? ''));
                }
                $seatsBySection[$key] = array_filter($passengerMatches['seat']);
            }
        }
        $passengers = array_unique(array_filter($passengers));
        $seatsBySectionCount = count($seatsBySection);

        // travellers
        if (count($passengers)) {
            $f->general()->travellers($passengers);
        }

        $segments = $this->splitText($segmentsText, '/(.+[ ]{2}' . $this->opt($this->t('Depart')) . '[ ]+.+)/', true);
        $segmentsCount = count($segments);
        $this->logger->debug("Found {$segmentsCount} segment(s).");

        foreach ($segments as $key => $segment) {
            $segment = preg_replace("/^(\s+{$this->opt($this->t('Layover time'))}[\s\:]+[\d\:]+)/m", "", $segment);
            $s = $f->addSegment();

            $departPos = preg_match('/^(.+?[ ]{2})' . $this->opt($this->t('Depart')) . '[ ]+/mu', $segment, $matches) ? mb_strlen($matches[1]) : null;
            $arrivePos = preg_match('/^(.+?[ ]{2})' . $this->opt($this->t('Arrive')) . '[ ]+/mu', $segment, $matches) ? mb_strlen($matches[1]) : null;

            if ($departPos === null || $arrivePos === null) {
                $this->logger->debug("Segment-$key corrupted! (1)");

                return false;
            }
            $segCol2Pos = $departPos < $arrivePos ? $departPos : $arrivePos;

            if (preg_match_all("/^(.+ ){$patterns['time']}$/m", $segment, $timeMatches) && count($timeMatches[0]) === 2) {
                array_walk($timeMatches[1], function (&$item) {
                    $item = mb_strlen($item);
                });
                sort($timeMatches[1]);
                $segCol3Pos = $timeMatches[1][0];
            } else {
                $this->logger->debug("Segment-$key corrupted! (2)");

                return false;
            }

            $segTable = $this->splitCols($segment, [0, $segCol2Pos, $segCol3Pos]);

            // airlineName
            // flightNumber
            // aircraft
            // duration

            // TR 898 (Scoot B787-9) - 4h 35min
            $patterns['segmentInfo1'] = '/'
                . '^[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?[ ]*(?<flightNumber>\d+)[ ]*\((?<aircraft>.+?)\)[ ]*-[ ]*(?<duration>\d.+)'
                . '/u';

            if (preg_match($patterns['segmentInfo1'], $segTable[0], $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
                $s->extra()
                    ->aircraft($matches['aircraft'])
                    ->duration($matches['duration']);
            }

            if ($bc = $this->re('/Fare Class\:[ ]+([A-Z]|[A-Z]\d)[ ]*(?:\n|$)/', $segTable[0])) {
                $s->extra()
                    ->bookingCode($bc);
            }

            // depDate
            // arrDate
            $addedSymbols = '';

            if ($this->lang == 'th') {
                $addedSymbols = '\p{Thai}\.';
            }

            if (preg_match_all("/(?<time>{$patterns['time']})\s+(?<date>\d{1,2}\s*[[:alpha:]{$addedSymbols}]+\s*\d{2,4})/u", $segTable[2], $dateMatches) && count($dateMatches[0]) === 2) {
                $flightDate['dTime'] = $dateMatches['time'][0];
                $flightDate['dDate'] = strtotime($this->normalizeDate($dateMatches['date'][0]));
                $flightDate['aTime'] = $dateMatches['time'][1];
                $flightDate['aDate'] = strtotime($this->normalizeDate($dateMatches['date'][1]));

                if (!empty($flightDate['dTime']) && !empty($flightDate['dDate'])) {
                    $s->departure()->date(strtotime($flightDate['dTime'], $flightDate['dDate']));
                }

                if (!empty($flightDate['aTime']) && !empty($flightDate['aDate'])) {
                    $s->arrival()->date(strtotime($flightDate['aTime'], $flightDate['aDate']));
                }
            }

            /*
                Depart      Singapore (SIN)
                            Singapore - Changi Airport Terminal 2
                Arrive      Taipei (TPE)
                            Taipei - Taoyuan Intl Airport Terminal 1
            */
            $patterns['segmentInfo2'] = '/'
                . $this->opt($this->t('Depart')) . '[ ]+.+?\((?<depCode>[A-Z]{3})\)[ ]*$'
                . '\s+(?<depName>.+?)(?:\s+' . $this->opt($this->t('Terminal')) . '\s+(?<depTerminal1>[A-z\d]+)|(?<depTerminal2>International)\s+' . $this->opt($this->t('Terminal')) . ')?[ ]*$'
                . '\s+' . $this->opt($this->t('Arrive')) . '[ ]+.+?\((?<arrCode>[A-Z]{3})\)[ ]*$'
                . '\s+(?<arrName>.+?)(?:[ ]+' . $this->opt($this->t('Terminal')) . '[ ]+(?<arrTerminal1>[A-z\d]+)|(?<arrTerminal2>International)\s+' . $this->opt($this->t('Terminal')) . ')?[ ]*$'
                . '/msu';

            // fix if part of the word "arrival" is on the other line
            $segTable[1] = preg_replace('/^(ง|ึง|ถึง) {3,}/m', '', $segTable[1]);

            if (!empty($s->getDepDate()) && !empty($s->getArrDate())
                && preg_match($patterns['segmentInfo2'], $segTable[1], $matches)
            ) {
                $matches = preg_replace("/\s*\n\s*/", ' ', $matches);
                // depCode
                // depName
                // depTerminal
                $s->departure()
                    ->code($matches['depCode'])
                    ->name($matches['depName']);

                if (!empty($matches['depTerminal1']) || !empty($matches['depTerminal2'])) {
                    $s->departure()->terminal(($matches['depTerminal1'] ?? '') . ($matches['depTerminal2'] ?? ''));
                }

                // arrCode
                // arrName
                // arrTerminal
                $s->arrival()
                    ->code($matches['arrCode'])
                    ->name($matches['arrName']);

                if (!empty($matches['arrTerminal1']) || !empty($matches['arrTerminal2'])) {
                    $s->arrival()->terminal(($matches['arrTerminal1'] ?? '') . ($matches['arrTerminal2'] ?? ''));
                }

                // seats
                if ($segmentsCount === $seatsBySectionCount && !empty($seatsBySection[$key])) {
                    $s->extra()->seats($seatsBySection[$key]);
                }
            }
        }

        $uniqueSegments = [];
        /** @var FlightSegment $segment */
        foreach ($f->getSegments() as $key => $segment) {
            $s = [
                $segment->getDepCode(),
                $segment->getFlightNumber(),
                $segment->getAirlineName(),
                $segment->getArrCode(),
                $segment->getArrDate(),
                $segment->getDepDate(),
                $segment->getArrName(),
                $segment->getDepName(),
            ];
            $serSeg = serialize($s);

            if (!in_array($serSeg, $uniqueSegments)) {
                $uniqueSegments[$key] = $serSeg;
            } else {
                $f->removeSegment($segment);
            }
        }

        return true;
    }

    private function normalizeDate(?string $date): string
    {
        $addedSymbols = '';

        if ($this->lang == 'th') {
            $addedSymbols = '\p{Thai}\.';
        }
        $in = [
            // 03 Januari 2020
            '/^\s*(\d{1,2})\s+([[:alpha:]' . $addedSymbols . ']+)\s+(\d{4})\s*$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        if ($this->lang == 'th' && preg_match('/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(2[56]\d{2})\s*$/u', $str, $m)) {
            $year = (int) $m[3] - 543;
            $str = $m[1] . ' ' . $m[2] . ' ' . $year;
        }

        return $str;
    }

    private function dateStringToEnglish(string $date): string
    {
        $addedSymbols = '';

        if ($this->lang == 'th') {
            $addedSymbols = '\p{Thai}\.';
        }

        if (preg_match('#[[:alpha:]' . $addedSymbols . ']+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#iu", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function striposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strripos($text, $phrase) : stripos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
