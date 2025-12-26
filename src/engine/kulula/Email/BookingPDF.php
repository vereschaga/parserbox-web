<?php

namespace AwardWallet\Engine\kulula\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class BookingPDF extends \TAccountChecker
{
    public $mailFiles = "kulula/it-14879746.eml, kulula/it-34775592.eml";

    public $reFrom = "kulula.com";
    public $reFromH = "kulula";
    public $reBody = [
        'en'  => ['hotel booking reference number', 'adults'],
        'en2' => ['car rental', 'pick up location'],
        'en3' => ['airline booking reference', 'adult'],
    ];
    public $reSubject = [
        '#kulula\.com\s+booking\s*-\s*[A-Z\d]+\s*$#',
    ];
    public $lang = '';
    public $pdfNamePattern = "kulula\.com\s*booking.*pdf";
    public static $dict = [
        'en' => [
            'booking reference number'  => ['booking reference number', 'Booking reference number'],
            'flight details -'          => ['flight details -', 'flight itinerary -', 'Flight itinerary -'],
            'airline booking reference' => ['airline booking reference', 'Airline booking reference'],
            'type'                      => ['type', 'Type'],
            'passenger name'            => ['passenger name', 'Passenger name'],
            'child'                     => ['child', 'Child'],
            'adult'                     => ['adult', 'Adult'],
            'depart'                    => ['depart', 'Depart'],
            'arrive'                    => ['arrive', 'Arrive'],
            'total'                     => ['total', 'Total'],
        ],
    ];
    /** @var \HttpBrowser */
    private $pdf;
    private $text;
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';
            $this->text = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $this->text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        } else {
            return null;
        }
        $body = $this->pdf->Response['body'];
        $this->assignLang($body);

        $this->parseEmail($email);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'booking reference number') !== false) {
                return $this->assignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])
            && (
                stripos($headers['from'], $this->reFromH) !== false
                || stripos($headers['subject'], $this->reFromH) !== false)
            && isset($this->reSubject)
        ) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
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
        $types = 3; //flight, hotel, car+flight
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $email->ota()
            ->confirmation($this->pdf->FindSingleNode("//text()[{$this->starts($this->t('booking reference number'))}]",
                null, true, "#:\s+([A-Z\d]+)#"));

        if (($nodes = $this->pdf->XPath->query("//text()[{$this->starts($this->t('hotel booking reference number'))}]"))->length > 0) {
            $this->parseHotels($email, $nodes);
        }

        if (($nodes = $this->pdf->XPath->query("//text()[{$this->contains($this->t('- car rental -'))}]"))->length > 0) {
            $this->parseCars($email, $nodes);
        }

        if (($nodes = $this->pdf->XPath->query("//text()[{$this->contains($this->t('flight details -'))}]"))->length > 0) {
            $this->parseFlights($email, $nodes);
        }
    }

    private function parseHotels(Email $email, \DOMNodeList $nodes)
    {
        //TODO: need examples to check how good it was rewrite on objects
        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->pdf->FindSingleNode(".", $root, true, "#:\s+([A-Z\d]+)#"))
                ->travellers($this->pdf->FindNodes("//text()[starts-with(normalize-space(.),'Adult')]/preceding::text()[normalize-space(.)!=''][1]"));

            $i = 2;
            //TODO not sure that following
            $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][{$i}]", $root);
            $addr = "";

            while ((stripos($node, "Directions:") === false) && $i < 10) {
                $addr .= " " . $node;
                $i++;
                $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][{$i}]", $root);
            }

            $h->hotel()
                ->name($this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root))
                ->address(trim($addr));

            $r = $h->addRoom();
            $r->setType($this->pdf->FindSingleNode("./following::text()[starts-with(normalize-space(.),'room type')][1]/following::text()[normalize-space(.)!=''][1]",
                $root));

            $node = $this->pdf->FindSingleNode("./following::text()[starts-with(.,'Room') and contains(.,'adults') and contains(.,'nights')][1]",
                $root);
            //Room  1:  2  adults.    Wed, 26 Apr - Mon, 01 May - 5   nights
            if (preg_match("#Room\s+\d+:\s+(\d+)\s+adults.+?(\d+\s+\w+)\s+-\s+.+?(\d+\s+\w+)#", $node, $m)) {
                $h->booked()
                    ->guests($m[1])
                    ->checkIn($this->normalizeDate($m[2]))
                    ->checkOut($this->normalizeDate($m[3]));
            }

            //TODO not sure that it steel good
            $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'total(incl')]/following::text()[normalize-space(.)!=''][1]"));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    private function parseCars(Email $email, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->pdf->FindSingleNode(".", $root, true, "#\-\s+([A-Z\d]+)$#"));
            $r->car()
                ->model($this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root))
                ->type($this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][2]", $root));

            $i = 0;
            $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<20][starts-with(normalize-space(.),'pick up location')][1]",
                $root);
            $addr = "";

            while ((stripos($node, "return date time") === false) && $i < 10) {
                $addr .= " " . $node;
                $i++;
                $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<20][starts-with(normalize-space(.),'pick up location')][1]/following::text()[normalize-space(.)!=''][{$i}]",
                    $root);
            }
            $pickupLoc = $this->re("#pick up location:\s+(.+)#", $addr);

            $i = 0;
            $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<20][starts-with(normalize-space(.),'return location')][1]",
                $root);
            $addr = "";

            while ((stripos($node, "car terms and conditions") === false) && $i < 10) {
                $addr .= " " . $node;
                $i++;
                $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<20][starts-with(normalize-space(.),'return location')][1]/following::text()[normalize-space(.)!=''][{$i}]",
                    $root);
            }
            $dropOffLoc = $this->re("#return location:\s+(.+)#", $addr);

            $r->pickup()
                ->date($this->normalizeDate($this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<20][starts-with(normalize-space(.),'pick up date time')][1]",
                    $root, false, "#:\s+(.+)#")))
                ->location($pickupLoc);

            $r->dropoff()
                ->date($this->normalizeDate($this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<20][starts-with(normalize-space(.),'return date time')][1]",
                    $root, false, "#:\s+(.+)#")))
                ->location($dropOffLoc);

            $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'total due for car hire (incl')]/following::text()[normalize-space(.)!=''][1]"));

            if (!empty($tot['Total'])) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    private function parseFlights(Email $email, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $f = $email->add()->flight();

            $pax = [];

            $arr = explode("\n\n", $this->re("#{$this->opt($this->t('type'))}\s+{$this->opt($this->t('passenger name'))}.+?\n(.+?)\n\n\n#s", $this->text));

            foreach ($arr as $v) {
                $str = preg_replace("#\d{3}\-?\d{5,}#", 'dddddd', $v);
                $rows = explode("\n", $str);
                $pos = 0;

                foreach ($rows as $row) {
                    if (($pos = strpos($row, 'dddddd')) !== false) {
                        break;
                    }
                }

                if ($pos > 0) {
                    $s = $this->splitCols($v, [0, $pos]);

                    if (count($s) > 0) {
                        $str = trim(preg_replace("#\s+#", ' ',
                            preg_replace("/{$this->opt($this->t('child'))}/", '', preg_replace("/{$this->opt($this->t('adult'))}/", '', array_shift($s)))));

                        if (!empty($str)) {
                            $pax[] = $str;
                        }
                    }
                }
            }

            if (empty($pax)) {
                $pax = $this->pdf->FindNodes("//text()[{$this->starts($this->t('adult'))}]/following::text()[normalize-space()][1]",
                    null, "#^\D+$#");
            }

            $f->general()
                ->confirmation($this->pdf->FindSingleNode(".", $root, true, "#{$this->opt($this->t('airline booking reference'))}\s+([A-Z\d]+)$#"))
                ->travellers($pax);

            $f->issued()
                ->tickets(array_filter($this->pdf->FindNodes("//text()[{$this->starts($this->t('adult'))}]/following::text()[normalize-space()][position()<=2]",
                    null, "#\b(\d{3}[- ]*\d{5,}[- ]*\d{1,2})\b#")), false);

            if (($segs = $this->pdf->XPath->query("//text()[{$this->eq($this->t('depart'))}]"))->length > 0) {
                foreach ($segs as $seg) {
                    $s = $f->addSegment();
                    $this->parseFlightsSeg_1($s, $seg);
                }
            } else {
                $segs = $this->pdf->XPath->query("//text()[{$this->starts($this->t('depart'))} and contains(.,':')]");

                foreach ($segs as $seg) {
                    $s = $f->addSegment();
                    $this->parseFlightsSeg_2($s, $seg);
                }
            }
            $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("(//text()[{$this->eq($this->t('total'))}])[1]/following::text()[normalize-space()][1]"));

            if (!empty($tot['Total'])) {
                $f->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    private function parseFlightsSeg_1(FlightSegment $flightSegment, \DOMNode $root)
    {
        $timeDep = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root, false,
            "#(\d+:\d+.*)#");

        $dateDep = $this->normalizeDate($this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][2]",
            $root));
        $flightSegment->departure()
            ->date(strtotime($timeDep, $dateDep));
        $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][3]", $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
            $flightSegment->departure()
                ->name($m[1])
                ->code($m[2]);
        }
        $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][4]", $root);

        if (preg_match("#([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
            $flightSegment->airline()
                ->name($m[1])
                ->number($m[2]);
        } elseif (preg_match("#^(\w+)$#", $node)) {
            $flightSegment->departure()
                ->terminal($node);
            $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][5]", $root);

            if (preg_match("#([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $flightSegment->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
        }

        $timeArr = $this->pdf->FindSingleNode("following::text()[{$this->starts($this->t('arrive'))}][1]/following::text()[normalize-space()][1]",
            $root, false, "#(\d+:\d+.*)#");
        $dateArr = $this->normalizeDate($this->pdf->FindSingleNode("following::text()[{$this->starts($this->t('arrive'))}][1]/following::text()[normalize-space()][2]",
            $root));
        $flightSegment->arrival()
            ->date(strtotime($timeArr, $dateArr));
        $node = $this->pdf->FindSingleNode("following::text()[{$this->starts($this->t('arrive'))}][1]/following::text()[normalize-space()][3]",
            $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
            $flightSegment->arrival()
                ->name($m[1])
                ->code($m[2]);
        }

        $node = $this->pdf->FindSingleNode("following::text()[{$this->starts($this->t('arrive'))}][1]/following::text()[normalize-space()][4][not({$this->eq($this->t('depart'))})]",
            $root);

        if (preg_match("#^(\w+)$#", $node)) {
            $flightSegment->arrival()
                ->terminal($node);
        }
    }

    private function parseFlightsSeg_2(FlightSegment $flightSegment, \DOMNode $root)
    {
        $timeDep = $this->pdf->FindSingleNode(".", $root, false, "#(\d+:\d+.*)#");
        $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root);

        if (preg_match("#\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
            $flightSegment->airline()
                ->name($m[1])
                ->number($m[2]);
        }
        $dateDep = $this->normalizeDate($this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][2]",
            $root));
        $flightSegment->departure()
            ->date(strtotime($timeDep, $dateDep));
        $node = $this->pdf->FindSingleNode("./following::text()[normalize-space(.)!=''][3]", $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
            $flightSegment->departure()
                ->name($m[1])
                ->code($m[2]);
        }

        $timeArr = $this->pdf->FindSingleNode("following::text()[{$this->starts($this->t('arrive'))}][1]", $root,
            false, "#(\d+:\d+.*)#");
        $dateArr = $this->normalizeDate($this->pdf->FindSingleNode("following::text()[{$this->starts($this->t('arrive'))}][1]/following::text()[normalize-space()][2]",
            $root));
        $flightSegment->arrival()
            ->date(strtotime($timeArr, $dateArr));
        $node = $this->pdf->FindSingleNode("following::text()[{$this->starts($this->t('arrive'))}][1]/following::text()[normalize-space()][1]",
            $root);

        if (preg_match("#(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
            $flightSegment->arrival()
                ->name($m[1])
                ->code($m[2]);
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^.*?(\d+\s+\w+)$#u',
            '#^.*?(\d+\s+\w+)\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            '#^.*?(\d+\s+\w+)\s+(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
        ];
        $out = [
            '$1 ' . $year,
            '$1 ' . $year . ' $2',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
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
}
