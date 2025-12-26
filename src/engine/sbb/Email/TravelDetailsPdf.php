<?php

namespace AwardWallet\Engine\sbb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TravelDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "sbb/it-107280524.eml, sbb/it-23036652.eml, sbb/it-38337882.eml, sbb/it-872717229.eml";

    public $reFrom = ["@sbb.ch"];
    public $reBody = [
        'de'  => ['Reisedetails', 'Gilt nicht als Fahrausweis'],
        'en'  => ['Not valid as a travel document', 'Station/Stop'],
        'en2' => ['Not a valid travel document', 'Station/Stop'],
        'en3' => ['Travel itinerary', 'Quantity'],
    ];
    public $reSubject = [
        'Ihre Bestellung im SBB Ticket Shop',
        'Emailing: SBB',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'de' => [
            //            "Reisedetails" => "",
            //            "Bestellnummer" => "",
            //            "Verbindung/Artikel" => "",
            //            "Details pro Person für" => "",
            //            "Klasse" => "",
            //            "Total Person" => "",
            //            "Bahnhof/Haltestelle" => "",
            //            "Legende" => "",
            //            "ab" => "",
            //            "an" => "",
            //            "Fussweg" => "",
        ],
        'en' => [
            "Reisedetails"           => "Travel details",
            "Bestellnummer"          => ["Order number", "Confirmation of reservation"],
            "Verbindung/Artikel"     => "Connection/item",
            "Details pro Person für" => "Details per passenger for",
            "Klasse"                 => "Class",
            //"Total Person" => "total Passenger",
            "Bahnhof/Haltestelle" => "Station/Stop",
            "Legende"             => ["Duration:.*", "Legend"],
            "ab"                  => ["dep", "from"],
            "an"                  => ["arr", "to"],
            "Fussweg"             => "walk",
            "Total Hinfahrt"      => "journey",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        if (preg_match("/Date of travel\s*Train no\.\s*from\s*to\s*Departure\s*Arrival\s*Quantity/u", $text)) {
                            $this->parseEmail2($text, $email);
                        } else {
                            if (!$this->parseEmail($text, $email)) {
                                $this->logger->debug('other format pdf');

                                return null;
                            }
                        }
                    } else {
                        $this->logger->debug('can\'t determine a language');
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getHTMLBody();
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (((stripos($text, 'sbb.ch') !== false)
                    || (stripos($text, 'TGV') !== false)
                    || (stripos($body, 'SBB') !== false)
                    || (stripos($body, 'rhb.ch') !== false)
                ) && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

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

    private function parseEmail($textPDF, Email $email)
    {
//        $textPDF = strstr($textPDF, $this->t("Reisedetails"));

        $r = $email->add()->train();
        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('Bestellnummer'))}\:? *(\d+)#", $textPDF));

        $travellers = $this->res("#ticket for \d+\.\d+\.\d+ for ([A-Z][a-z]*(?: [A-Z][a-z]*){0,6})#", $textPDF);

        if (empty($travellers)) {
            $travellers = $this->res("#\n\s*Add-on ticket, single\/return journey\s*([A-Z][a-z]*(?: [A-Z][a-z]*){0,6})#", $textPDF);
        }

        if (!empty($travellers)) {
            $r->general()
                ->travellers($travellers);
        }
        $textInfo = $this->t('Verbindung/Artikel') . $this->findСutSection($textPDF, $this->t('Verbindung/Artikel'),
                $this->t('Details pro Person für'));

        $tableInfo = $this->splitCols($textInfo, $this->colsPos($this->re("#(.+)#", $textInfo)));

        if (count($tableInfo) === 6) {
            $cabin = $this->re("#{$this->opt($this->t('Klasse'))}\s+(.+)#", $tableInfo[2]);
            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total Hinfahrt'))}\s+(.+)#", $tableInfo[5]));

            if (!empty($tot['Total'])) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total Person'))} +\d+ +(.+)#", $textPDF));

        if (!empty($tot['Total'])) {
            $r->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }

        $textIt = $this->re("#( *{$this->opt($this->t('Bahnhof/Haltestelle'))} .+?)\n *{$this->opt($this->t('Legende'), false)}\n#s", $textPDF);

        $arr = $this->splitter("#(.+?\s*(?:(?:\w+,)? \d{2}\.\d{2}\.\d{4})?\s+{$this->opt($this->t('ab'))})#u", $textIt);
//        $this->logger->debug("Total " . count($arr) . " segments were found");
        $date = false;

        foreach ($arr as $i => $item) {
//            $this->logger->debug("[Searched segment]: \n" . $item);//for debug

            $pos = [0];

            if (preg_match("#^.+?((?:\w+, )?\d{2}\.\d{2}\.\d{4})? +({$this->opt($this->t('ab'))} \d{2}:\d{2})(?: +.*?)? {4,}([A-z]+ *[A-Z\d]+)#",
                $item, $m)) {
                if (!empty($m[1])) {
                    $posCol2 = mb_strpos($item, $m[1]) - 1;
                    $pos[] = $posCol2;
                }
                $posCol3 = mb_strpos($item, $m[2]) - 1;
                $pos[] = $posCol3;
                $posCol4 = mb_strpos($item, $m[3]) - 1;
                $pos[] = $posCol4;
            }

            if (count($pos) !== 4 && count($pos) !== 3) {
                $this->logger->debug("may be another format pdf, need to check (format segment {$i})");

                return false;
            }
            /*

            FE:
            Zürich HB                    So, 14.10.2018          ab 07:00        17     IC 760         InterCity,         BZ RZ
            Basel SBB                                            an 08:03        8


            FE skip segment:
            Basel SBB                    So, 14.10.2018          ab 08:03               Fussweg        18 Min, Y
            Basel SBB                                            an 08:21

            */
            // convert to table
            $table = $this->splitCols($item, $pos);

            if (count($table) === 4) {
                $tRoutes = $table[0];
                $tDate = $table[1];
                $tTimes = $table[2];
                $tInfo = $table[3];
            } elseif (count($table) === 3) {
                $tRoutes = $table[0];
                $tDate = '';
                $tTimes = $table[1];
                $tInfo = $table[2];
            }
            $strArr = preg_quote($this->http->FindPreg("#\n(.+?) *(?:(?:\w+, )?\d{2}\.\d{2}\.\d{4})? +{$this->opt($this->t('an'))} \d{2}:\d{2}#",
                false, $item));

            $depName = $this->http->FindPreg("#(.+)\s+{$strArr}#s", false, $tRoutes);
            $arrName = $this->http->FindPreg("#.+\s+({$strArr}.+)#s", false, $tRoutes);

            if ($depName == $arrName) {
                if (strpos($tInfo, $this->t('Fussweg')) !== false) {
                    continue;
                } else {
                    $this->logger->debug("may be another format pdf, need to check (format segment {$i}): depName == arrName");

                    return false;
                }
            }

            $type = '';

            if (preg_match("#^([A-z]+) *[A-Z\d]+#", $tInfo, $m)) {
                $type = $m[1];
            }
            $s = $r->addSegment();

            $s->extra()->cabin($cabin ?? null, true, true);

            if (!empty($tDate)) {
                $depDate = $this->http->FindPreg("#^(?:\w+, )?(\d{2}\.\d{2}.\d{4})\s+(?:\d{2}|$)#", false, $tDate);
                $arrDate = $this->http->FindPreg("#^(?:\w+, )?\d{2}\.\d{2}\.\d{4}\s+(?:\w+, )?(\d{2}\.\d{2}\.\d{4})#", false, $tDate);

                if (empty($arrDate)) {
                    $arrDate = $depDate;
                }
                $date = $arrDate;
            } elseif (!empty($date)) {
                $depDate = $arrDate = $date;
            }
//            $this->logger->debug('Dates: ' . $depDate . '-' . $arrDate);

            $depDate = strtotime($depDate);
            $arrDate = strtotime($arrDate);

            $depTime = $this->http->FindPreg("#{$this->opt($this->t('ab'))} +(\d{2}:\d{2})#", false, $tTimes);
            $arrTime = $this->http->FindPreg("#{$this->opt($this->t('an'))} +(\d{2}:\d{2})#", false, $tTimes);
//            $this->logger->debug('Times: ' . $depTime . '-' . $arrTime);

            $s->departure()
                ->name($depName)
                ->date(strtotime($depTime, $depDate));
            $s->arrival()
                ->name($arrName)
                ->date(strtotime($arrTime, $arrDate));
            $s->extra()
                ->number($this->http->FindPreg("#{$type} *([A-Z\d]+)#", false, $tInfo))
                ->type($type);

            $descr = $this->http->FindPreg("#{$type} *[A-Z\d]+[,\s]+(.+)#s", false, $tInfo);

            $seatsText = $this->re("/{$s->getNumber()}\s*{$type}.+SEATS\s*\:(.+)/", $textPDF);

            if (!empty($seatsText)) {
                $seats = array_filter(explode(" ", $seatsText));

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }
            }

            if (!empty($descr)) {
                $wg = $this->http->FindPreg("#Wg\.\s*(\d+)#", false, $descr);
                $pl = $this->http->FindPreg("#Pl\.\s*(\d+)#", false, $descr);

                if (!empty($wg) && !empty($pl)) {
                    $s->extra()
                        ->car($wg)
                        ->seat($pl);
                }
            }
        }

        return true;
    }

    private function parseEmail2($textPDF, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $r = $email->add()->train();

        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('Bestellnummer'))}\:? *([\d\-]+)#", $textPDF))
            ->travellers($this->res("#^{$this->t('Name')}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])#m", $textPDF));

        $accounts = array_filter($this->res("/Customer No\.\s+(\d+)\s*\n/", $textPDF));

        if ($accounts) {
            $r->setAccountNumbers($accounts, false);
        }

        $tickets = array_filter($this->res("/Ticket\-ID\s+(\d+)\s*\n/", $textPDF));

        if ($tickets) {
            $r->setTicketNumbers($tickets, false);
        }

        $segText = $this->re("/Travel itinerary\n+Date of travel\s*Train no\..+Quantity\n+(.+)\n+Travel documents/su", $textPDF);
        $segments = $this->splitText($segText, "/^(.+\d+\:\d+.+\d+\:\d+.+)$/m", true);

        foreach ($segments as $segment) {
            $this->logger->error($segment);
            $this->logger->error('---------------------------------');

            $s = $r->addSegment();

            if (preg_match("/Carriage no\.\s+(?<car>\d+)\,\s*(?<cabin>\d+.+Class)\,\s*Seats\s+(?<seat>[\d\-\,\s]+)[ ]{5,}/", $segment, $m)) {
                $s->setCarNumber($m['car']);
                $s->setCabin($m['cabin']);

                if (stripos($m['seat'], '-') === false) {
                    $s->extra()
                        ->seats(explode(", ", $m['seat']));
                } else {
                    $arraySeat = explode("-", $m['seat']);
                    $s->extra()
                        ->seats(range($arraySeat[0], $arraySeat[1], 1));
                }
            }

            $segment = preg_replace("/(Carriage no\..+)/s", " ", $segment);
            $segTable = $this->splitCols($segment);
            $s->setNumber($this->re("/^(\d+)$/", $segTable[1]));

            $date = $this->re("/\s(\d+\.\d+\.\d{4})/", $segTable[0]);
            $s->departure()
                ->name($segTable[2])
                ->date(strtotime($date . ', ' . $this->re("/^(\d+\:\d+)$/", $segTable[4])));

            $s->arrival()
                ->name($segTable[3])
                ->date(strtotime($date . ', ' . $this->re("/^(\d+\:\d+)$/", $segTable[5])));
        }
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

    private function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#^(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $tot = PriceHelper::cost($m['t']);
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field, $quoted = true)
    {
        $field = (array) $field;

        if ($quoted) {
            $field = array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field);
        } else {
            $field = array_map(function ($s) { return str_replace(' ', '\s+', $s); }, $field);
        }

        return '(?:' . implode("|", $field) . ')';
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
}
