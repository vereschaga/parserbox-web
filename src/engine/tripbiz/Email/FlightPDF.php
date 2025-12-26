<?php

namespace AwardWallet\Engine\tripbiz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
    public $mailFiles = "tripbiz/it-12534958.eml, tripbiz/it-12534961.eml, tripbiz/it-12553168.eml";
    public $lang = 'en';

    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Booking number' => ['Booking number', ' Booking no'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "Itinerary") !== false
                && (strpos($text, 'Booking Details') !== false)
                && (strpos($text, 'Flight Information') !== false)
                && (strpos($text, 'Departure') !== false)
            ) {
                return true;
            }

            if (strpos($text, "Itinerary") !== false
                && (strpos($text, 'Frequent Flyer Card No.') !== false)
                && (strpos($text, 'Departure') !== false)
                && (strpos($text, 'Baggage Policies') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]centrient\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $flightInformation = $this->re("/Flight Information\s*\n(.+)\n\s*Price Summary/s", $text);
        $segments = $this->splitText($flightInformation, "/^([A-Z]\D+\-\D+.*\n\n)/m", true);

        if (empty($flightInformation)) {
            $flightInformation = $this->re("/(Itinerary\s+\d\s+.+)/s", $text);
            $segments = $this->splitText($flightInformation, "/^(?:\s*Itinerary\s+\d+\s*)([A-Z]\D+\-\D+.*\n\n)/m", true);
        }

        $tickets = [];

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $aName = '';
            $fNumber = '';

            if (preg_match("/Flight\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})/", $segment, $m)) {
                $aName = $m['aName'];
                $fNumber = $m['fNumber'];
            }

            foreach ($email->getItineraries() as $key => $flight) {
                if ($flight->getType() === 'flight') {
                    $segs = $flight->getSegments();

                    foreach ($segs as $seg) {
                        if ($seg->getFlightNumber() === $fNumber && $seg->getAirlineName() === $aName) {
                            $f->removeSegment($s);
                            $s = $seg;
                        }
                    }
                }
            }

            $s->airline()
                ->name($aName)
                ->number($fNumber);

            $pnr = $this->re("/Airline PNR\s+([A-Z\d]{6})\s+/", $segment);

            if (!empty($pnr)) {
                $s->setConfirmation($pnr);
            }

            $status = $this->re("/Status\s+(\w+)/", $segment);

            if (!empty($status)) {
                $s->setStatus($status);
            }

            $duration = $this->re("/Duration\s+(.*\d(?:h|m))\b[ ]{10,}/", $segment);

            if (!empty($duration)) {
                $s->setDuration($duration);
            }

            $ticket = $this->re("/Ticket No\.\s*(\d+[\d\-]*)/", $segment);

            if (empty($ticket)) {
                $ticket = $this->re("/Passengers\s*Ticket No.+\n+\s*\D+(\d+\-[\d]+)\n/", $segment);
            }

            if (!empty($ticket)) {
                $tickets[] = $ticket;
            }

            if (preg_match("/Departure\n\s*(?<depName>.+)\/(?<depDate>\d+\:\d+\,.+\d{4})(?:\s*\/\s*T(?<terminal>.+))?/", $segment, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->noCode()
                    ->date(strtotime($m['depDate']));

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            } elseif (preg_match("/Departure\s+(?<depTime>\d+\:\d+)\s*\(Local\s*time\)\s+(?<depName>.+[A-z])(?:\s+T\s*(?<terminal>.+))?\s*\n/", $segment, $m)) {
                $date = $this->re("/Date\s+\d+\:\d+\,\s*(\w+\s*\d+\,\s*\d{4})[ ]{5,}/", $segment);
                $s->departure()
                    ->name($m['depName'])
                    ->noCode()
                    ->date(strtotime($date . ', ' . $m['depTime']));

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            if (preg_match("/Arrival\n*\s*(?<arrName>.+)\/(?<arrDate>\d+\:\d+\,.+\d{4})(?:\s*\/\s*T(?<terminal>.+))?/", $segment, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->noCode()
                    ->date(strtotime($m['arrDate']));

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            } elseif (preg_match("/Arrival\s+(?<arrTime>\d+\:\d+)\s*(?:(?<nextDay>[+]\d+))?\(Local\s*time\)\s+(?<arrName>.+[A-z])(?:\s+T\s*(?<terminal>.+))?\s*\n/", $segment, $m)) {
                $date = $this->re("/Date\s+\d+\:\d+\,\s*(\w+\s*\d+\,\s*\d{4})[ ]{5,}/", $segment);
                $s->arrival()
                    ->name($m['arrName'])
                    ->noCode()
                    ->date(strtotime($date . ', ' . $m['arrTime']));

                if (isset($m['nextDay']) && !empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime('+1 day', $s->getArrDate()));
                }

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }

            $cabin = $this->re("/Class\s+(.+class)/", $segment);

            if (!empty($cabin)) {
                $s->setCabin($cabin);
            }

            $bookingCode = $this->re("/Class\s+.+class\s+\|\s+([A-Z]{1,2})\n/", $segment);

            if (!empty($bookingCode)) {
                $s->setBookingCode($bookingCode);
            }
        }

        if (preg_match_all("/{$this->opt($this->t('Booking number'))}[\s\:\.]+([A-Z\d]+)\.?/", $text, $m)) {
            $confs = array_unique($m[1]);

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        }

        $resDate = str_replace(',', '', $this->re("#{$this->opt($this->t('Ticket Issuing Date'))}[\s\:]+(\w+\s*\d+\,\s*\d{4})#", $text));

        if (!empty($resDate)) {
            $f->general()
                ->date(strtotime($resDate));
        }

        $travellers = [];

        if (preg_match_all("/\n\s*([[:alpha:]][-&.\/'’[:alpha:] ]*[[:alpha:]]) +[A-Z\d]{2,}[*]+[A-Z\d]*\n/u", $text, $m)) {
            $travellers = array_merge($travellers, $m[1]);
        } elseif (preg_match_all("/Passengers\s*Ticket No.+\n+\s*(\D+)\b\s+\d+\-[\d]+\n/u", $text, $m)) {
            $travellers = array_merge($travellers, $m[1]);
        }

        $f->general()
            ->travellers(array_unique($travellers));

        if (preg_match_all("/Ticket Number\n\s*(\d+)\n/", $text, $m)) {
            $f->setTicketNumbers($m[1], false);
        } elseif (count(array_unique($tickets)) > 0) {
            foreach (array_unique($tickets) as $ticket) {
                $pax = $this->re("#^\s*({$this->opt($travellers)})\b[ ]{2,}$ticket#mu", $text);

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        $priceText = $this->re("/\n *Price Summary\s*\n([\s\S]+?)\n\s*Notice/u", $text);
        $pricesRows = [];
        $rows = preg_split("/\n{2,}/", $priceText);

        foreach ($rows as $row) {
            $table = $this->createTable($row, $this->rowColumnPositions($this->inOneRow($row)));
            $table = array_map('trim', preg_replace('/\s+/', ' ', $table));

            if (count($table) == 2) {
                $pricesRows[] = ['name' => $table[0], 'value' => $table[1]];
            } elseif (count($table) == 4) {
                $pricesRows[] = ['name' => $table[0], 'value' => $table[1]];
                $pricesRows[] = ['name' => $table[2], 'value' => $table[3]];
            }
        }

        if (count($pricesRows) >= 2
            && preg_match("/^\s*Ticket fare\s*$/", $pricesRows[0]['name'], $m)
            && preg_match("/^\s*Total\s*$/", $pricesRows[count($pricesRows) - 1]['name'], $m)
        ) {
            foreach ($pricesRows as $i => $pRow) {
                if (preg_match("/^\s*(?<currency>\D+)\s+(?<total>\d[\d\.\,]*?)\s*$/", $pRow['value'], $m)) {
                    $currency = $this->normalizeCurrency($m['currency']);

                    if ($i === 0) {
                        $f->price()
                            ->cost(PriceHelper::parse($m['total'], $currency))
                            ->currency($currency);
                    } elseif ($i === count($pricesRows) - 1) {
                        $f->price()
                            ->total(PriceHelper::parse($m['total'], $currency))
                            ->currency($currency);
                    } else {
                        $f->price()
                            ->fee($pRow['name'], PriceHelper::parse($m['total'], $currency));
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $text = '';

        foreach ($pdfs as $pdf) {
            $text = $text . "\n" . \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        $this->ParseFlightPDF($email, $text);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
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
        $pos = [];
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
