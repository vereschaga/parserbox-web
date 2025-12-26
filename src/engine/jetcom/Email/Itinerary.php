<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-706227980.eml";
    public static $dictionary = [
        "en" => [
        ],
    ];

    public $lang = "en";
    public $room;
    private $subjects = [
        '/your On Business statement is ready to view$/',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jet2holidays\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jet2holidays.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('*.*pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Jet2holidays') === false) {
                return false;
            }

            if (stripos($text, 'Your Holiday Summary') !== false
                && stripos($text, 'Customers') !== false
                && stripos($text, 'Room') !== false) {
                return true;
            }

            if (stripos($text, 'Flight Summary Voucher') !== false
                && stripos($text, 'Departure Airport:') !== false
                && stripos($text, 'Flight Number:') !== false) {
                return true;
            }

            if (stripos($text, 'Transfer Summary Voucher') !== false
                && stripos($text, 'Transfer to') !== false
                && stripos($text, 'Transfer Type:') !== false) {
                return true;
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Your Holiday Summary') !== false) {
                $this->hotelPDF($email, $text);
            }

            if (stripos($text, 'Flight Summary Voucher') !== false) {
                $this->flightPDF($email, $text);
            }

            if (stripos($text, 'Transfer Summary Voucher') !== false) {
                //$this->transfersPDF($email, $text);
                continue;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function flightPDF(Email $email, $textPDF)
    {
        $f = $email->add()->flight();

        $segmentText = $this->cutText('Going Out', 'Check-in Requirements', $textPDF);
        $segments = $this->splitCols($segmentText);

        $travellers = [];

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match("/Flight Number:\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})/", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depPoint = $this->re("/{$this->opt($this->t('Departure Airport:'))}\s*(.+)\b/", $segment);

            if (preg_match("/^(?<name>.+)\s+(?<code>[A-Z]{3})$/", $depPoint, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code']);
            } else {
                $s->departure()
                    ->noCode()
                    ->name($depPoint);
            }

            if (preg_match("/Departure Airport:.+\n\s+Terminal\s*(?<terminal>.+)\b/", $segment, $m)) {
                $s->departure()
                    ->terminal($m['terminal']);
            }

            $arrPoint = $this->re("/{$this->opt($this->t('Arrival Airport:'))}\s*(.+)\b/", $segment);

            if (preg_match("/^(?<name>.+)\s+(?<code>[A-Z]{3})$/", $arrPoint, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code']);
            } else {
                $s->arrival()
                    ->noCode()
                    ->name($arrPoint);
            }

            if (preg_match("/Arrival Airport:.+\n\s+Terminal\s*(?<terminal>.+)\b/", $segment, $m)) {
                $s->arrival()
                    ->terminal($m['terminal']);
            }

            if (preg_match("/Seats\:\s+(?<seats>[\dA-Z\,\s]+)\b(?:[ ]{2,}|\n)/", $segment, $m)) {
                $s->setSeats(explode(", ", $m['seats']));
            }

            $date = $this->re("/{$this->opt($this->t('Date:'))}\s*(.+\d{4})/", $segment);
            $depTime = $this->re("/{$this->opt($this->t('Departure Time:'))}\s*(\d+\:\d+)\s*\n/", $segment);
            $arrTime = $this->re("/{$this->opt($this->t('Arrival Time:'))}\s*(\d+\:\d+)\s*\n/", $segment);

            $s->departure()
                ->date(strtotime($date . ', ' . $depTime));

            $s->arrival()
                ->date(strtotime($date . ', ' . $arrTime));

            $travellersText = $this->re("/Passenger\(s\)\:\s*((?:.+\n){1,5})\s*Hand Baggage:/", $segment);

            if (preg_match_all("/([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/", $travellersText, $match)) {
                $travellers = array_unique(array_filter(array_merge($travellers, $match[1])));
            }
        }

        $f->general()
            ->travellers(preg_replace("/^(?:Miss|Mrs|Mr|Ms)/", "", $travellers))
            ->noConfirmation();
    }

    private function hotelPDF(Email $email, $textPDF)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/Booking Reference:\s*([A-Z\d]{4,})/", $textPDF));

        if (preg_match("/(?<hotelName>.+)\n+Our rating\n+(?<hotelAddress>.+)\s*\.?\n+(?<phone>[\d\s+]+)\b\n+/", $textPDF, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['hotelAddress'])
                ->phone($m['phone']);
        }

        if (preg_match("/Customers:\s+(?<adult>\d+) Adults[\s\,]+(?<kids>\d+)\s*Child\n/", $textPDF, $m)
            || preg_match("/Customers:\s+(?<adult>\d+) Adults\s*\n/", $textPDF, $m)) {
            $h->booked()
                ->guests($m['adult']);

            if (isset($m['kids']) && ($m['kids'] !== null)) {
                $h->booked()
                    ->kids($m['kids']);
            }
        }

        if (preg_match_all("/(?:Apartment)?\n+\s*Customers\:\s*((?:.+\n){1,5})\s*Duration:/", $textPDF, $m)) {
            foreach ($m[1] as $travellersText) {
                if (preg_match_all("/([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/", $travellersText, $match)) {
                    $h->general()
                        ->travellers(preg_replace("/^(?:Mstr|Miss|Mrs|Mr|Ms)/", "", array_unique(array_filter($match[1]))));
                }
            }
        }

        if (preg_match("/Arrival date\:\s+(?<inDate>.+\d{4})\n\s+Departure date\:\s+(?<outDate>.+\d{4})/", $textPDF, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['inDate']))
                ->checkOut(strtotime($m['outDate']));
        }

        if (preg_match_all("/Room\s*\d+\s+(.+)\n/", $textPDF, $m)) {
            foreach ($m[1] as $descriptions) {
                $room = $h->addRoom();

                $room->setDescription($descriptions);
            }
        }
    }

    private function transfersPDF(Email $email, $textPDF)
    {
        $t = $email->add()->transfer();

        $t->general()
            ->traveller(preg_replace("/^(?:Miss|Mrs|Mr|)/", "", $this->re("/Lead Passenger:\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/", $textPDF)))
            ->confirmation($this->re("/Booking Reference:\s*([A-Z\d]{6})/", $textPDF));

        $segmentText = $this->cutText('Transfer to your accommodation', 'Arrival Transfer', $textPDF);
        $segments = $this->splitCols($segmentText);

        foreach ($segments as $segment) {
            $s = $t->addSegment();

            $s->extra()
                ->adults($this->re("/Number of Passengers:\s*(\d+)/", $textPDF));

            if (stripos($segment, 'Transfer to') !== false) {
                $depDate = $this->re("/Arrival Date:\s*(.+)\b/", $segment);
                $depTime = $this->re("/Flight Time:\s*(.+)\b/", $segment);

                $depNameText = $this->re("/Arrival Airport:\s*(.+)/", $segment);

                if (preg_match("/^(?<name>.+)\s+(?<code>[A-Z]{3})\s*$/", $depNameText, $m)) {
                    $s->departure()
                        ->name($m['name'])
                        ->code($m['code']);
                } else {
                    $s->departure()
                        ->name($depNameText);
                }

                $s->departure()
                    ->date(strtotime($depDate . ', ' . $depTime));

                $s->arrival()
                    ->noDate()
                    ->name($this->re("/Accommodation:\s*(.+)\b/", $textPDF));
            } else {
                $arrDate = $this->re("/Arrival Date:\s*(.+)\b/", $segment);
                $arrTime = $this->re("/Flight Time:\s*(.+)\b/", $segment);

                $arrNameText = $this->re("/Arrival Airport:\s*(.+)/", $segment);
                $s->departure()
                    ->name($this->re("/Accommodation:\s*(.+)\b/", $textPDF))
                    ->noDate();

                if (preg_match("/^(?<name>.+)\s+(?<code>[A-Z]{3})\s*$/", $arrNameText, $m)) {
                    $s->arrival()
                        ->name($m['name'])
                        ->code($m['code']);
                } else {
                    $s->arrival()
                        ->name($arrNameText);
                }

                $s->arrival()
                    ->date(strtotime($arrDate . ', ' . $arrTime));
            }
        }
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return null;
        }

        return strstr(strstr($text, $start), $end, true);
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s(\d+)\s+(\w+)\s+(\d{4})\s+at\s+([\d\:]+)$#", //Fri 16 Oct 2020 at 18:00
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
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

        return '(?:' . implode("|", $field) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
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

    private function colsPos($table, $correct = 5)
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
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'HKD' => ['HK$'],
            'INR' => ['₹'],
            'BRL' => ['R$'],
            'SGD' => ['S$'],
            'AUD' => ['AU$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
