<?php

namespace AwardWallet\Engine\qbt\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class TravelPdf extends \TAccountChecker
{
    public $mailFiles = "qbt/it-49034739.eml, qbt/it-55951425.eml, qbt/it-94836138.eml";

    public $lang = '';

    public static $dict = [
        'en' => [],
    ];
    private $from = [
        '@qbt.travel',
    ];
    private $subject = [
        'QBT travel itinerary for ',
    ];
    private $pdfBody = [
        'en' => [
            'QBT Booking Reference:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->from)}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains('QBT Team')}]")->length > 0) {
            return true;
        }
        $pdfs = $parser->searchAttachmentByName('qb[tp]_.+?\.pdf');

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $text = substr($text, 0, 10000);

            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('qb[tp]_.+?\.pdf');

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
            $text = substr($text, 0, 10000);

            if ($this->assignLang($text)) {
                $this->parseCar($email, $text);
                $this->parseFlight($email, $text);
                $this->parseHotel($email, $text);
            }
        }
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

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

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseHotel(Email $email, string $text)
    {
        //$this->logger->debug($text);
        $segments = $this->splitter('/(Product\s+(?:Flight Details|Hotel Details|Name of Hotel|Car Details)\s+.+?Other Info\s*.+)/', $text);

        foreach ($segments as $node) {
            //$this->logger->debug($node);
            if (!preg_match('/^Product\s+(?:Hotel Details|Name of Hotel)/', $node)) {
                continue;
            }

            $h = $email->add()->hotel();
            // QBT Booking Reference: SI2F5H
            if (preg_match('/QBT Booking Reference:\s+([A-Z\d]{6})/', $text, $m)) {
                $h->ota()->confirmation($m[1]);
            }
            // Tel: 1300 368 401
            if (preg_match('/\bTel:\s+([+\-\d()\s]+)\n/', $text, $m)) {
                $h->ota()->phone($m[1]);
            }
            // Created Date: 18 Nov 2019
            if (preg_match('/Created Date:\s+(.+?)\n/', $text, $m)) {
                $h->general()->date2($m[1]);
            }
            // Name of Passenger \n Mr Xiangpeng Gao
            if (preg_match('/Names? of Passengers?\s+(.+?)\s+Product\s+/s', $text, $m)) {
                $filter = array_filter(explode("\n", $m[1]), function ($v) {
                    return preg_match('/^\s*M[ri]/', $v);
                });

                if ($filter) {
                    $h->general()->travellers(array_unique($filter), true);
                }
            }

//            $this->logger->debug($node);
//            $this->logger->debug('==================');
            if (preg_match('/(Product\s+.+)/', $node, $m)) {
                $pos = $this->rowColsPos($m[1]);
                $table = $this->splitCols($node, $pos);

                if (count($table) == 0 || count($table) > 5) {
                    $this->logger->debug("Table for hotel bug");

                    return;
                }
            }

            //$this->logger->warning($node);
            //$this->logger->warning('----------------------------');

            // Quest Palmerston North       17/11/2019           22/11/2019           Confirmed
            if (preg_match('#\s+(?<name>.+?)\s+(?<inDate>[\d/]{10})\s+(?<outDate>[\d/]{10})\s{2,}(?<status>\w{5,13})#', $node, $m)) {
                $h->general()->status($m['status']);
                $h->hotel()->name($m['name']);
                $h->booked()->checkIn($this->normalizeDate($m['inDate']));
                $h->booked()->checkOut($this->normalizeDate($m['outDate']));
            }
            // Palmerston North             Sun                  Fri                  76539SB005027        Phone: 646-357-7676
            if (preg_match('/\s{2,}\w{3}\s{2,}\w{3}\s{2,}(?<number>\w{10,16})\s+/', $node, $m)) {
                if (isset($table[4]) && preg_match('/Other Info\s*(.+?)\s+Phone:/s', $table[4], $addr)) {
                    $h->hotel()->address(str_replace("\n", " ", $addr[1]));
                }
                $h->general()->confirmation($m['number']);
            }

            // Phone: 646-357-7676
            if (preg_match('/Phone:\s+(.+?)\n/', $node, $m)) {
                $h->hotel()->phone($m[1]);
            }
            // Room Type: Standard King Room
            if (preg_match('/Room Type:\s+(.+?)\n/', $node, $m)) {
                $r = $h->addRoom();
                $r->setType($m[1]);
                // Rate Type: DAILY
                if (preg_match('/Rate Type:\s+(.+?)\n/', $node, $m)) {
                    $r->setRateType($m[1]);
                }
            }

            // Hotel cancellation policy: ...
            if (preg_match('/Hotel cancellation policy:\s+(.+?)\s+(?:\.\s+|Inclusions|$)/s', $node, $m)) {
                $h->setCancellation(preg_replace(['/\n/', '/\s{2,}/'], [" ", " "], $m[1]));
                $this->detectDeadLine($h);
            }

            // Pricing Description                                                       Curr         Price            Tax           GST                   Total
            // Hotel: Premier Hotel & Apartments (15/12/2019 Check-In) for Mr Ben             AUD          342.82      0.00          0.00                342.82
            if (preg_match('/Curr\s+Price\s+Tax\s+GST\s+Total/', $text)) {
                if (preg_match("/Hotel:\s*{$h->getHotelName()}.+?([A-Z]{3})\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+/s", $text, $m)) {
                    $h->price()->currency($m[1]);
                    $h->price()->cost($this->normalizeAmount($m[2]));
                    $h->price()->tax($this->normalizeAmount($m[3]));
                    $h->price()->total($this->normalizeAmount($m[5]));
                }
            }
        }
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
        // CANCEL ON 15DEC2019 BY 14:00 LT TO AVOID A CHARGE
        if (preg_match('/CANCEL ON (\d+[A-Z]{3}\d{4}) BY (\d+:\d+) LT TO AVOID A CHARGE/', $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline2("{$m[1]}, {$m[2]}");
        }
    }

    private function parseCar(Email $email, string $text)
    {
        if (!preg_match("/Car Details/", $text)) {
            return false;
        }
        /*if (stripos('Car Details', $text) == false)
            return false;*/

        $r = $email->add()->rental();

        // QBT Booking Reference: SI2F5H
        if (preg_match('/QBT Booking Reference:\s+([A-Z\d]{6})/', $text, $m)) {
            $r->ota()->confirmation($m[1]);
        }

        // Tel: 1300 368 401
        if (preg_match('/\bTel:\s+([+\-\d()\s]+)\n/', $text, $m)) {
            $r->ota()->phone($m[1]);
        }

        // Created Date: 18 Nov 2019
        if (preg_match('/Created Date:\s+(.+?)\n/', $text, $m)) {
            $r->general()->date2($m[1]);
        }

        // Name of Passenger \n Mr Xiangpeng Gao
        if (preg_match('/Names? of Passengers?\s+(.+?)\s+Product\s+/s', $text, $m)) {
            $guests = array_filter(explode("\n", $m[1]));

            foreach ($guests as $guest) {
                if ($this->stripos($guest, ':') !== false) {
                    continue;
                }

                $travellers[] = str_replace(['Mrs', 'Mr', 'Ms'], '', $guest);
            }

            if ($travellers) {
                $r->general()->travellers(array_unique($travellers), true);
            }
        }

        // Pricing Description                                                       Curr         Price            Tax           GST                   Total
        // Air Fare (PER/SIN/WUH/SIN/PER) for Mr Xiangpeng Gao                       AUD        1081.00         157.23           0.00               1238.23
        //$this->logger->error($text);
        if (preg_match('/Curr\s+Price\s+Tax\s+GST\s+Total/', $text)) {
            if (preg_match('/Car.+?([A-Z]{3})\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+/s', $text, $m)) {
                $r->price()->currency($m[1]);
                $r->price()->cost($this->normalizeAmount($m[2]));
                $r->price()->tax($this->normalizeAmount($m[3]));
                $r->price()->total($this->normalizeAmount($m[5]));
            }
        }

        $segments = $this->splitter('/(Product\s+(?:Flight Details|Hotel Details|Name of Hotel|Car Details)\s+.+?Other Info\s*.+)/', $text);

        foreach ($segments as $node) {
            if (preg_match('/^Product\s+Car Details/u', $node)) {
                $node = preg_replace("/\s+Your Itinerary.+/su", "", $node);
                $carTable = $this->splitCols($node);

                $r->setCompany($this->re("/Car Details\s*(\D+)\s*/", $carTable[1]));

                $r->general()
                    ->status($this->re("/Status\s*(\w+)\s*Confirmation:/us", $carTable[4]))
                    ->confirmation($this->re("/Confirmation:\s*([\dA-Z]+)/us", $carTable[4]));

                if (preg_match("/Pickup\s*([\d\:]+\s*[\d\/]+)\s*\w+\s*(.+)/su", $carTable[2], $m)) {
                    $r->pickup()
                        ->date($this->normalizeDate($m[1]))
                        ->location(str_replace("\n", " ", $m[2]));
                }

                if (preg_match("/Drop\-off\s*([\d\:]+\s*[\d\/]+)\s*\w+\s*(.+)/su", $carTable[3], $m)) {
                    $r->dropoff()
                        ->date($this->normalizeDate($m[1]))
                        ->location(str_replace("\n", " ", $m[2]));
                }

                $r->car()
                    ->type(str_replace("\n", " ", $this->re("/^Other Info\s*(.+)Pickup information:/s", $carTable[5])));
            }
        }

        return true;
    }

    private function parseFlight(Email $email, string $text)
    {
        if (!preg_match("/Product\s+Flight Details/u", $text)) {
            return false;
        }

        $f = $email->add()->flight();
        $f->general()->noConfirmation();
        // QBT Booking Reference: SI2F5H
        if (preg_match('/QBT Booking Reference:\s+([A-Z\d]{6})/', $text, $m)) {
            $f->ota()->confirmation($m[1]);
        }
        // Tel: 1300 368 401
        if (preg_match('/\bTel:\s+([+\-\d()\s]+)\n/', $text, $m)) {
            $f->ota()->phone($m[1]);
        }
        // Created Date: 18 Nov 2019
        if (preg_match('/Created Date:\s+(.+?)\n/', $text, $m)) {
            $f->general()->date2($m[1]);
        }
        // Name of Passenger \n Mr Xiangpeng Gao
        if (preg_match('/Names? of Passengers?\s+(.+?)\s+Product\s+/s', $text, $m)) {
            $guests = array_filter(explode("\n", $m[1]));

            foreach ($guests as $guest) {
                if (stripos($guest, ':') !== false) {
                    continue;
                }

                $travellers[] = str_replace(['Mrs', 'Mr', 'Ms'], '', $guest);
            }

            if ($travellers) {
                $f->general()->travellers(array_unique($travellers), true);
            }
        }
        // Pricing Description                                                       Curr         Price            Tax           GST                   Total
        // Air Fare (PER/SIN/WUH/SIN/PER) for Mr Xiangpeng Gao                       AUD        1081.00         157.23           0.00               1238.23
        if (preg_match('/Curr\s+Price\s+Tax\s+GST\s+Total/', $text)) {
            if (preg_match('/Air Fare.+?([A-Z]{3})\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+([\d.,]+)\s+/s', $text, $m)) {
                $f->price()->currency($m[1]);
                $f->price()->cost($this->normalizeAmount($m[2]));
                $f->price()->tax($this->normalizeAmount($m[3]));
                $f->price()->total($this->normalizeAmount($m[5]));
            }
        }

        // Product Flight Details              Departure            Arrival       Status                   Other Info
        $segments = $this->splitter('/(Product\s+(?:Flight Details|Hotel Details|Name of Hotel)\s+.+?Other Info\s*.+)/', $text);

        foreach ($segments as $node) {
            if (preg_match('/^Product\s+Flight Details/', $node)) {
//                $this->logger->debug($node);
//                $this->logger->debug('=====================');
                $this->parseFlightSegments($f, $node);
            }
        }
    }

    private function parseFlightSegments(Flight $f, string $node)
    {
        $depTime = $arrTime = 0;
        // Singapore Airlines           06:40                 11:55                  ECONOMY (E)
        if (preg_match('/\s+(?<depTime>\d+:\d+)\s+(?<arrTime>\d+:\d+)\s+(?<cabin>[A-z]{4,10})\s*\((?<bCode>[A-Z])\)/', $node, $m)
            || preg_match('/\s+(?<depTime>\d+:\d+)\s+(?<arrTime>\d+:\d+)\s+(?<cabin>\D+)\s*Aircraft/', $node, $m)) {
            $s = $f->addSegment();
            $s->extra()->cabin($m['cabin']);

            if (isset($m['bCode'])) {
                $s->extra()->bookingCode($m['bCode']);
            }
            $depTime = $m['depTime'];
            $arrTime = $m['arrTime'];
        } else {
            $f->addSegment();

            return;
        }
        // SQ224                        14/12/2019            14/12/2019             Confirmed
        // SQ 402             02/03/2020                  02/03/2020    Confirmed                Flight Duration: 06:00
        if (preg_match('#\s+(?<arName>[A-Z]{2})\s*(?<arNum>\d{1,4})\s{2,}(?<depDate>[\d/]{10})\s+(?<arrDate>[\d/]{10})\s{2,}#', $node, $m)) {
            //$this->logger->debug(var_export($m, true));
            $s->airline()->name($m['arName']);
            $s->airline()->number($m['arNum']);
            $s->departure()->date($this->normalizeDate("{$m['depDate']}, {$depTime}"));
            $s->arrival()->date($this->normalizeDate("{$m['arrDate']}, {$arrTime}"));
            $s->departure()->noCode();
            $s->arrival()->noCode();
        }
        // Airline Reference: SI2F5H    Terminal 1            Terminal 0
        if (preg_match('/Airline Reference:\s+([A-Z\d]{5,6})\s+/', $node, $m)) {
            $s->airline()->confirmation($m[1]);
        }

        if (preg_match('/\bTerminal (\d+)\s+Terminal (\d+)\b/', $node, $m)) {
            $s->departure()->terminal($m[1]);
            $s->arrival()->terminal($m[2]);
        }
        // Aircraft type: 787 ALL SERIES
        if (preg_match('/Aircraft type:\s+(.{3,20})\n/', $node, $m)) {
            $s->extra()->aircraft($m[1]);
        }
        // Flight Duration: 5:15
        if (preg_match('/Flight Duration:\s+(.+?)\n/', $node, $m)) {
            $s->extra()->duration($m[1]);
        }
        // Airline Meal: (M) Meal
        if (preg_match('/Airline Meal:\s+(.+?)\n/', $node, $m)) {
            $s->extra()->meal($m[1]);
        }
        // Number of stops: 0
        if (preg_match('/Number of stops:\s+(\d+)\n/', $node, $m)) {
            $s->extra()->stops($m[1]);
        }
        // Flight Operated By: SCOOT(TR120)
        if (preg_match('/Flight Operated By:\s+.+?\(([A-Z]{2})\s*(\d{1,4})\)\n/', $node, $m)) {
            $s->airline()->carrierName($m[1]);
            $s->airline()->carrierNumber($m[2]);
        }
    }

    private function assignLang($text): bool
    {
        foreach ($this->pdfBody as $lang => $phrase) {
            if ($this->stripos($text, $phrase)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //$this->logger->error('IN '.$str);

        $in = [
            // 20:00 15/07/2021
            '#^(\d+:\d+)\s*(\d+)/(\d+)/(\d{4})$#su',
            // 14/12/2019, 00:40
            '#^(\d+)/(\d+)/(\d{4}), (\d+:\d+)$#',
            // 14/12/2019
            '#^(\d+)/(\d+)/(\d{4})$#',
        ];
        $out = [
            "$2.$3.$4, $1",
            "$2/$1/$3, $4",
            "$2/$1/$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->error('OUT-'.$str);
        return strtotime($str, false);
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
