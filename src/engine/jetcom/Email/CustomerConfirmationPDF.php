<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CustomerConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-58989487.eml, jetcom/it-59455728.eml, jetcom/it-705492876.eml";
    public static $dictionary = [
        "en" => [
            "Your holiday to..."  => ["Your holiday to...", "Your City Break to..."],
            "Terminal"            => ["Terminal", "TERMINAL"],
            "TravellersTextStart" => "Passenger Details",
            "TravellersTextEnd"   => "Flights Going Out",
            "SegmentStart"        => "Jet2holidays Summary",
            "SegmentEnd"          => "Holiday Price",
        ],
    ];

    public $lang = "en";
    public $room;
    private $reFrom = "hull@hays-travel.co.uk";
    private $reSubject = [
        "en" => "Jet2holidays Limited",
    ];
    private $keywordProv = 'Jet2holidays';
    private $reBody = [
        "en" => ["Your holiday to...", "Your City Break to..."],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('*.*pdf');

        $fullText = '';

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $fullText .= "\n" . $text;

            if (strpos($text, $this->keywordProv)) {
                foreach ($this->reBody as $lang => $reBody) {
                    foreach ($reBody as $body) {
                        if (strpos($text, $body) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        if (stripos($fullText, 'Flight Number:') === false
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Flight Number:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Please contact Jet2holidays')]")) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('*.*pdf');

        $fullText = '';

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $fullText .= "\n" . $text;

            //Ota Confirmation
            $bookingReference = $this->re("/Booking Reference\s([A-Z\d\/]+)/", $text);

            if (!empty($bookingReference)) {
                $email->ota()
                    ->confirmation($bookingReference);
            }

            //Amount Price
            $totalPrice = $this->re("/(.[\d\.\,]+)\s+Important Information/u", $text);
            $tot = $this->getTotalCurrency($totalPrice);

            if (!empty($tot['Total'])) {
                $email->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            //Travellers
            $travellersText = $this->cutText("{$this->t('TravellersTextStart')}", "{$this->t('TravellersTextEnd')}", $text);
            $travellersRows = preg_split("/\n/", $travellersText);

            foreach ($travellersRows as $row) {
                $travellers[] = $this->re("/^\s?(\D+)\s\-/", $row);
            }
            $travellers = array_filter($travellers);

            $segmentText = $this->cutText("{$this->t('SegmentStart')}", "{$this->t('SegmentEnd')}", $text);
            $table = $this->splitCols($segmentText, $this->colsPos($this->re("/^.+\n/u", $segmentText)));

            //HOTELS

            if (isset($table[0]) && !empty($table[0])) {
                $hotelName = $this->re("/Jet2holidays Summary.+(?:Adults|Child)\s+\n?(\D+)/us", $table[0]);
            }

            if (!empty($hotelName)) {
                $hotel = $email->add()->hotel();
                $hotel->general()
                    ->travellers($travellers)
                    ->noConfirmation();
                $hotel->hotel()
                    ->name(str_replace("\n", " ", $hotelName))
                    ->noAddress();

                $checkIn = $this->re("/Jet2holidays Summary\s+\d+\s+nights\sfrom\s+\w+\s+(\d+\s\w+\s+\d{4})/", $table[0]);
                $countNights = $this->re("/Jet2holidays Summary\s+(\d+)\s+nights\sfrom\s+/", $table[0]);

                if (!empty($checkIn)) {
                    $hotel->booked()
                        ->checkIn(strtotime($checkIn))
                        ->checkOut(strtotime("+{$countNights} day", strtotime($checkIn)));
                }

                $guest = $this->re("/(\d+)\sAdults/", $text);
                $kids = $this->re("/(\d+)\sChild/", $text);

                if (!empty($guest)) {
                    $hotel->booked()
                        ->guests($guest);
                }

                if (!empty($kids)) {
                    $hotel->booked()
                        ->kids($kids);
                }

                if (preg_match_all("/(\d+\s[ x ]\s\D+\s\-\s\D+)\n/", $table[0], $match)) {
                    $this->room = count($match[0]);

                    foreach ($match[0] as $m) {
                        $room = $hotel->addRoom();
                        $room->setType(str_replace("\n", " ", $this->re("/^\d+\s[ x ]\s(\D+)\s\-\s\D+$/", $m)));
                        $room->setDescription(str_replace("\n", " ", $this->re("/^\d+\s[ x ]\s\D+\s\-\s(\D+)$/", $m)));
                    }
                }

                if (!empty($this->room)) {
                    $hotel->booked()
                        ->rooms($this->room);
                }
            }

            //FLIGHTS

            if (isset($table[1]) && !empty($table[1]) && preg_match_all("/(?:Flights Going Out|Flights Coming Back)\n(\D+\s+to\s+\D+\s+(?:Terminal\s\d+)?\n\s+Airline\:.+Flight\sNumber\:\s[A-Z]{2}[\d]{2,4}\s+Departs\:\s+.+\s+Arrives\:\s+.+)/", $table[1], $match)) {
                foreach ($match[1] as $m) {
                    if (preg_match("/(?<depName>\D+)\s+to\s+(?<arrName>\D+)\s+(?:Terminal\s+(?<terminal>\S+))?\n\s+Airline\:.+Flight\sNumber\:\s(?<flightName>[A-Z]{2})(?<flightNumber>[\d]{2,4})\s+Departs\:\s+(?<depDate>.+)\s+Arrives\:\s+(?<arrDate>.+)/", $m, $f)) {
                        $flight = $email->add()->flight();
                        $flight->general()
                            ->travellers($travellers)
                            ->noConfirmation();

                        $segment = $flight->addSegment();

                        $segment->departure()
                            ->name($f['depName'])
                            ->date($this->normalizeDate($f['depDate']));

                        if (!empty($code = $this->re("/\s([A-Z]{3}(?:\s|$))/", $f['depName']))) {
                            $segment->departure()
                                ->code($code);
                        } else {
                            $segment->departure()
                                ->noCode();
                        }

                        if (isset($f['terminal'])) {
                            $terminalTable = $this->splitCols($m, $this->colsPos($this->re("/^.+\n/", $segmentText)));

                            if (!empty($this->re("/(Terminal)/", $terminalTable[0]))) {
                                $segment->departure()
                                    ->terminal($f['terminal']);
                            }

                            if (!empty($this->re("/(Terminal)/", $terminalTable[1]))
                                || !empty($this->re("/(Terminal)/", $terminalTable[2]))) {
                                $segment->arrival()
                                    ->terminal($f['terminal']);
                            }
                        }

                        $segment->arrival()
                            ->name($f['arrName'])
                            ->date($this->normalizeDate($f['arrDate']));

                        if (!empty($code = $this->re("/\s([A-Z]{3}(?:\s|$))/", $f['arrName']))) {
                            $segment->arrival()
                                ->code($code);
                        } else {
                            $segment->arrival()
                                ->noCode();
                        }

                        $segment->airline()
                            ->name($f['flightName'])
                            ->number($f['flightNumber']);
                    }
                }
            }
        }

        if (stripos($fullText, 'Flight Number:') === false && $this->http->XPath->query("//text()[contains(normalize-space(), 'Flight Number:')]")->length > 0) {
            $this->parseHTML($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseHTML(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Your booking reference']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)\/.*/"))
            ->travellers($this->http->FindNodes("//text()[contains(normalize-space(), 'Lead Passenger')]/ancestor::table[1]/descendant::tr", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*(?:\(Lead Passenger\))?$/"));

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Holiday total:']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,]+)$/", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($currency);

            $discount = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Special Discount')]/following::text()[normalize-space()][1]", null, true, "/\-\D{1,3}([\d\.\,\']+)/");

            if (!empty($discount)) {
                $f->price()
                ->discount(PriceHelper::parse($discount, $m['currency']));
            }
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Flight Number:']");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $pointsNameArray = explode(' to ', $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root));

            if (!empty($pointsNameArray[1]) && !empty($pointsNameArray[0])) {
                if (preg_match("/^(?<name>.+)\s+(?<code>[A-Z]{3})$/", $pointsNameArray[0], $m)) {
                    $s->departure()
                        ->name($m['name'])
                        ->code($m['code']);
                } else {
                    $s->departure()
                        ->noCode()
                        ->name($pointsNameArray[0]);
                }

                if (preg_match("/^(?<name>.+)\s+(?<code>[A-Z]{3})$/", $pointsNameArray[1], $m)) {
                    $s->arrival()
                        ->name($m['name'])
                        ->code($m['code']);
                } else {
                    $s->arrival()
                        ->noCode()
                        ->name($pointsNameArray[1]);
                }
            }

            $depDate = $this->http->FindSingleNode("./ancestor::table[3]/following::tr[1][contains(normalize-space(), 'Departing:')]", $root, true, "/{$this->opt($this->t('Departing:'))}\s*(.+)/");
            $s->departure()
                ->date($this->normalizeDate($depDate));

            $arrDate = $this->http->FindSingleNode("./ancestor::table[3]/following::tr[1]/following::tr[1][contains(normalize-space(), 'Arriving:')]", $root, true, "/{$this->opt($this->t('Arriving:'))}\s*(.+)/");
            $s->arrival()
                ->date($this->normalizeDate($arrDate));

            $seats = explode(",", $this->http->FindSingleNode("./ancestor::table[3]/following::tr[1]/following::tr[1]/following::tr[1][contains(normalize-space(), 'Seats:')]", $root, true, "/{$this->opt($this->t('Seats:'))}\s*([\dA-Z\,\s]+)$/"));

            if (count($seats) > 0) {
                $s->extra()
                    ->seats(array_filter($seats));
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
            "#^^\w+\s(\d+)\s+(\w+)\s+(\d{4})\s+at\s+([\d\:]+)$#", //Fri 16 Oct 2020 at 18:00
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
