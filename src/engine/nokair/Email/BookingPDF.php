<?php

namespace AwardWallet\Engine\nokair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingPDF extends \TAccountChecker
{
    public $mailFiles = "nokair/it-6066570.eml, nokair/it-184430668.eml";

    public $reSubject = [
        "en" => "Nok Air Booking Confirmation",
    ];

    public $reBody2 = [
        "en" => ["YOUR TRAVEL ITINERARY", "YOUR BOOKING DETAILS"],
    ];

    public static $dictionary = [
        "en" => [
            'travelItineraryEnd' => ['If you have any questions', 'SERVICES'],
            'servicesEnd'        => ['Check-in at Airport Counter', 'Mobile Check-in'],
            'statusPhrases'      => ['Your reservation has been', 'Your reservation is'],
            'statusVariants'     => ['confirmed', 'pending'],
        ],
    ];

    public $lang = "";

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    // private $result = [];
    // private $anotherSegments = [];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (count($pdfs) === 0) {
            return $email;
        }

        $pdfTexts = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            $pdfTexts[] = $textPdf;
        }

        $textPdfFull = implode("\n", $pdfTexts);

        $this->assignLang($textPdfFull);
        $email->setType('BookingPDF' . ucfirst($this->lang));

        $this->parseEmail($email, $textPdfFull);

        // not fact that ferry or other!
        // if ( !empty($arr = $this->getAnotherSegments()) )
        //     $itineraries = array_merge($itineraries, $arr);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@nokair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (stripos($textPdf, 'Nok Air') === false && stripos($textPdf, 'NokAir') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function parseEmail(Email $email, $plainText): void
    {
        $bookingDetails = $this->re("/\n[ ]*{$this->opt($this->t('YOUR BOOKING DETAILS'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Name of Passenger(s)'))}/", $plainText);
        $nameOfPassengers = $this->re("/\n[ ]*{$this->opt($this->t('Name of Passenger(s)'))}.*\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('YOUR TRAVEL ITINERARY'))}/", $plainText);
        $travelItinerary = $this->re("/\n[ ]*{$this->opt($this->t('YOUR TRAVEL ITINERARY'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('travelItineraryEnd'))}/", $plainText);
        $services = $this->re("/\n[ ]*{$this->opt($this->t('SERVICES'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('servicesEnd'))}/", $plainText);
        $receipt = $this->re("/({$this->opt($this->t('RECEIPT CONFIRMATION'))}\n[\s\S]+?\n(?:.+\/)?[ ]*{$this->opt($this->t('Total Amount received'))}.+)/i", $plainText);

        // correcting text
        $travelItinerary = preg_replace("/(.+ Departing[ ]{2,}Arriving)\n(.+)/", "$1\n\n$2", $travelItinerary);
        $travelItinerary = preg_replace("/(.{8,} {$this->patterns['time']}.*)\n(.{8,} (?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+|\bAir conditioned\b|\bHi-speed\b) .{7,})/", "$1\n\n$2", $travelItinerary);

        $f = $email->add()->flight();

        $this->itineraryInfo($email, $f, $bookingDetails, $nameOfPassengers);
        $this->parseSegments($f, $travelItinerary, $services);

        if (preg_match("/(YOUR BOOKING NUMBER)[ ]*:\n+[ ]*(\d{6,})\n/", $plainText, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:[ ]*[!,.]|\n)/", $plainText, $m)) {
            $f->general()->status($m[1]);
        }

        $totalPrice = Booking::parsePrice($receipt);

        if ($totalPrice !== null && preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalPrice, $matches)) {
            // 6,240.80 THB
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        } elseif (preg_match('/Total Payment[ ]+(.*\d.*)\n/', $nameOfPassengers, $m)) {
            if (preg_match('/(?<Total>[\d.,]+)/', $m[1], $tot)) {
                $email->price()->total(str_replace(',', '', $tot['Total']));
            }

            if (preg_match('/[\d.,]+\s+(?<Currency>[A-Z]{3}\b)/', $m[1], $tot) || preg_match('/Total Fare\s+[\d.,]+\s+(?<Currency>[A-Z]{3}\b)/', $nameOfPassengers, $tot)) {
                $email->price()->currency($tot['Currency']);
            }
        }
    }

    private function itineraryInfo(Email $email, Flight $f, ?string $text, ?string $text2): void
    {
        $table = $this->splitCols($text, $this->colsPos($text));
        $tableText = implode("\n\n", $table);

        if (preg_match("/(?:^\s*|\n[ ]*)Booking Date:\n+[ ]*(.*\d.*)(?:\n|$)/", $tableText, $m)) {
            $f->general()->date2($this->normalizeDate($m[1]));
        }

        if (preg_match("/(?:^\s*|\n[ ]*)(Booking Number)[ ]*:\n+[ ]*([A-Z\d]{5,})(?:\n|$)/", $tableText, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/(?:^\s*|\n[ ]*)(Booking Reference(?: *\(PNR\))?)[ ]*:\n+[ ]*([A-Z\d]{5,})(?:\n|$)/", $tableText, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match_all('/^[ ]*(?:MISS|GIRL|MRS|BOY|MR|MS)[. ]+([[:alpha:]][-.\'’[:alpha:] ]*?[[:alpha:]])([ ]{2}|\n|$)/mu', $text2, $m)) {
            $f->general()->travellers($m[1], true);
        }
    }

    private function parseSegments(Flight $f, ?string $travelItinerary, ?string $services): void
    {
        $tableRows = preg_split("/\n{2,}/", $travelItinerary);

        if (count($tableRows) < 2) {
            $this->logger->debug('Wrong table segments!');

            return;
        }

        $firstRow = array_shift($tableRows);
        $tablePos = $this->colsPos($firstRow);

        foreach ($tableRows as $sText) {
            $table = $this->splitCols($sText, $tablePos);

            if (count($table) !== 4) {
                $this->logger->debug('Wrong segment!');

                return;
            }

            if (!preg_match("/\n[ ]*{$this->patterns['time']}/", $table[2]) && !preg_match("/\n[ ]*{$this->patterns['time']}/", $table[3])) {
                // garbage row
                continue;
            }

            $airlineName = $flightNumber = $cabin = null;

            if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)(?:\n+(?<cabin>.+)|\s*$)/", $table[1], $m)) {
                $airlineName = $m['name'];
                $flightNumber = $m['number'];

                if (array_key_exists('cabin', $m)) {
                    $cabin = $m['cabin'];
                }
            }

            if (empty($airlineName) && empty($flightNumber) && preg_match("/(?:\bCoach\b|\bHi-speed\b|\bCatamaran\b)/i", $table[1]) > 0) {
                // non flight segment
                continue;
            }

            $s = $f->addSegment();

            $s->airline()->name($airlineName)->number($flightNumber);
            $s->extra()->cabin($cabin, false, true);

            $dateVal = $this->re("/(.*\d.*)/", $table[0]) ?? '';
            $date = strtotime($this->normalizeDate($dateVal));
            $timeDep = $timeArr = null;

            /*
                Chiang Mai International Airport
                10:05
            */
            $pattern = "/^\s*(?<airport>[\s\S]{3,}?)\n+[ ]*(?<time>{$this->patterns['time']})/";

            if (preg_match($pattern, $table[2], $m)) {
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode();
                $timeDep = $m['time'];
            }

            if (preg_match($pattern, $table[3], $m)) {
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['airport']))->noCode();
                $timeArr = $m['time'];
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            if (!empty($airlineName) && !empty($flightNumber) && !empty($services)
                && preg_match_all("/^.{2,36}[ ]{$airlineName} ?{$flightNumber}[ ]{1,20}(\d{1,3}[A-Z])(?:[ ]*\*)?(?:[ ]{2}|$)/m", $services, $seatMatches)
            ) {
                $s->extra()->seats($seatMatches[1]);
            }
        }
    }

    /*
    private function getAnotherSegments(): array
    {
        $res = [];

        if (!empty($this->result) && !empty($this->anotherSegments)) {
            foreach ($this->anotherSegments as $type => $anotherSegment) {
                $it = ['Kind' => 'T'];

                if ($type == 'Coach') {
                    $it['TripCategory'] = TRIP_CATEGORY_BUS;
                } elseif ($type == 'Ferry') {
                    $it['TripCategory'] = TRIP_CATEGORY_FERRY;
                }
                $it['RecordLocator'] = $this->result['RecordLocator'];
                $it['Passengers'] = $this->result['Passengers'];

                if (!empty($this->result['TripNumber'])) {
                    $it['TripNumber'] = $this->result['TripNumber'];
                }
                $it['TripSegments'][] = $anotherSegment;
                $res[] = $it;
            }
        }

        return $res;
    }
    */

    private function assignLang(?string $text): bool
    {
        if (!isset($this->reBody2, $this->lang)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases[0]) || empty($phrases[1])) {
                continue;
            }

            if (stripos($text, $phrases[0]) !== false && stripos($text, $phrases[1]) !== false) {
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
        //      $year = date("Y", $this->date);
        $in = [
            "#^\w+[\s\-]+(\d+\s*\w+\s*\d{4})$#u",
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
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

    private function colsPos($table, $delta = 5): array
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $delta) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
