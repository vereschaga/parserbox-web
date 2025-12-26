<?php

namespace AwardWallet\Engine\princess\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AirSchedule extends \TAccountChecker
{
    public $mailFiles = "princess/it-16537982.eml, princess/it-32818703.eml";
    private $langDetectors = [
        'en' => ['Airline Confirmation', 'AirlineConfirmation', 'Air Confirmation'],
    ];
    private $langDetectorsPdf = [
        'en' => ['Flight Schedule'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Depart'              => ['Depart', 'Depart Time'],
            'Arrive'              => ['Arrive', 'Arrival'],
            'Airline Confirmation'=> ['Airline Confirmation', 'AirlineConfirmation', 'Air Confirmation'],
        ],
    ];

    private $date;
    private $pdfPattern = '.+\.pdf';

    public function parseEmailPdf(Email $email, string $text)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("#BOOKING[ ]?([A-Z\d]{5,})\s*#", $text), "BOOKING");

        /*
         * FLIGHT
         */

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->res("#\n\s*Passenger:[ ]*(.+)#", $text), true);

        // Segments
        $segmentsTexts = $this->res("#\n\s*Carrier[ ]+Flight[ ]+.+\s*\n([ ]*\S[\s\S]+?)(?:\n\s*Passenger:|.*Restricted Air|\n\n[ ]{0,20}[^\d\n]+\n|\n\n\n\n)#", $text);
        $segments = [];

        foreach ($segmentsTexts as $stext) {
            $segments = array_merge($segments, $this->split("#(?:^|\n)([ ]{0,20}\S.+?[ ]{2,}\d{1,5} )#", $stext));
        }

        $this->date = $this->normalizeDate($this->re("#BOOKING[ ]?[A-Z\d]{5,}[ ]{2,}(.+)#", $text));

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $table = $this->SplitCols($stext);

            if (count($table) !== 9) {
                $this->logger->info("error in parsing table $stext");

                return $email;
            }

            // Airline
            $s->airline()
                ->name(trim($table[0]))
                ->number(trim($table[1]))
                ->operator(preg_replace("#\s*\n\s*#", ' ', trim($this->re("#Operated by:\s*([\s\S]+)#", $table[5]))), true, true)
                ->confirmation(trim($table[8]))
            ;

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->re("#^\s*(.+)#", $table[2]))
                ->terminal(trim(preg_replace("#\s*terminal\s*#i", ' ', $this->re("#\n(.*Terminal.*)#i", $table[2]))), true, true)
                ->date($this->normalizeDate(trim($table[3]) . ' ' . trim($table[4]), $this->date))
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($this->re("#^\s*(.+)#", $table[5]))
                ->terminal(trim(preg_replace("#\s*terminal\s*#i", ' ', $this->re("#\n(.*Terminal.*)#i", $table[5]))), true, true)
                ->date($this->normalizeDate(trim($table[6]) . ' ' . trim($table[7]), $this->date))
            ;

            // Extra
            $s->extra()
                ->aircraft($this->re("#Aircraft Type:[ ]*(.+)#", $table[2]), true, true);

            $count = count($f->getSegments());

            foreach ($f->getSegments() as $key => $seg) {
                if ($key == $count - 1) {
                    continue;
                }

                if ($s->getAirlineName() == $seg->getAirlineName()
                        && $s->getFlightNumber() == $seg->getFlightNumber()
                        && $s->getDepName() == $seg->getDepName()
                        && $s->getDepDate() == $seg->getDepDate()) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $f->removeSegment($s);
                }
            }
        }

        /*
         * CRUISE
         */

        $c = $email->add()->cruise();

        // General
        $c->general()
            ->noConfirmation()
            ->travellers($this->res("#\n\s*Passenger:[ ]*(.+)#", $text), true);

        // Details
        $c->details()
            ->description($this->re("#\s{2,}Voyage / Dest:[ ]*(.+)#", $text))
            ->ship($this->re("#\s{2,}Ship / Registry:[ ]*(.+?)/.+#", $text))
            ->number($this->re("#\s{2,}Voyage / Dest:[ ]*(.+?)/#", $text));

        // Segments
        $c->addSegment()
            ->setName($this->re("#\s{2,}Embarkation:[ ]*.+?/[ ]*(.+)#", $text))
            ->setAboard($this->normalizeDate($this->re("#\s{2,}Embarkation:[ ]*(.+?)/.+#", $text)));
        $c->addSegment()
            ->setName($this->re("#\s{2,}Disembarkation:[ ]*.+?/[ ]*(.+)#", $text))
            ->setAshore($this->normalizeDate($this->re("#\s{2,}Disembarkation:[ ]*(.+?)/.+#", $text)));

        return $email;
    }

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@princesscruises.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Air Schedule') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"www.princess.com") or contains(.,"@princesscruises.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.princess.com")] | //img[contains(@src,"//www.princess.com")]')->length === 0;
        $result = false;

        if ($condition1 || $condition2) {
            $result = $this->assignLang();
        }

        if ($result === true) {
            return true;
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (stripos($text, 'princess.com') === false) {
                continue;
            }

            foreach ($this->langDetectorsPdf as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('Can\'t determine a language!');
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }

                foreach ($this->langDetectorsPdf as $lang => $detectBody) {
                    foreach ($detectBody as $dBody) {
                        if (strpos($text, $dBody) !== false) {
                            $this->lang = $lang;
                            $this->parseEmailPdf($email, $text);

                            continue 3;
                        }
                    }
                }
            }
        } else {
            $this->parseEmail($email);
        }

        $email->setType('AirSchedule' . ucfirst($this->lang));

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

    private function parseEmail(Email $email)
    {
        $patterns = [
            'phone'    => '[+)(\d][-\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52
            'time'     => '\d{1,2}:\d{2}(?:\s*[AP])?', // 11:20P    |    9:15A
            'terminal' => '/^([A-Z\d\s]*TERMINAL[A-Z\d\s]*)$/i', // TERMINAL 1    |    MAIN TERMINAL
        ];

        $email->obtainTravelAgency(); // because Princess Cruise is not airline

        $bookingCodeText = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"BOOKING")]');

        if (preg_match('/^(BOOKING)\s*([A-Z\d]{6})$/', $bookingCodeText, $matches)) {
            $confirmationCodeTitle = $matches[1];
            $confirmationCode = $matches[2];
        }

//        mta closed for traxo
//        $otaPhone = $this->http->FindSingleNode('//text()[normalize-space(.)="Mta Travel"]/following::text()[normalize-space(.)][1]', null, true, '/^(' . $patterns['phone'] . ')$/');
//        if ($otaPhone) {
//            $otaProviderKeyword = $this->http->FindSingleNode('//text()[normalize-space(.)="' . $otaPhone . '"]/preceding::text()[normalize-space(.)][1]');
//            $otaProviderCode = $this->normalizeProvider($otaProviderKeyword);
//        }

        $bookingDate = 0;
        $bookingDateText = $this->http->FindSingleNode('//text()[normalize-space(.)="Booking Date:"]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]');

        if (empty($bookingDateText)) {
            $bookingDateText = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"BOOKING")]/following::text()[normalize-space(.)!=""][1]');
        }

        if ($bookingDateText) {
            $bookingDate = strtotime($bookingDateText);
        }

        $xpathFragment1 = "({$this->eq($this->t('Depart'))}) and ./following::text()[{$this->eq($this->t('Arrive'))}]";
        $xpath = "//text()[{$xpathFragment1}]/ancestor::tr[ ./preceding-sibling::tr[normalize-space(.)] ][1]";
        $flights = $this->http->XPath->query($xpath);

        foreach ($flights as $flight) {
            $f = $email->add()->flight();

            // confirmationNumbers
            if (isset($confirmationCode)) {
                $f->general()->confirmation($confirmationCode, $confirmationCodeTitle);
            }

            // reservationDate
            if ($bookingDate) {
                $f->general()->date($bookingDate);
            }

//            if (!empty($otaPhone)) {
//                // ta.providerPhones
//                $f->ota()->phone($otaPhone);
//
//                if ($otaProviderCode) {
//                    $f->ota()->code($otaProviderCode);
//                } else {
//                    // ta.providerKeyword
//                    $f->ota()->keyword($otaProviderKeyword);
//                }
//            }

            // travellers
            $passenger = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]', $flight, true, '/Passenger\s*:\s*(.+)/');
            $passenger = preg_replace("/(\s(?:Mrs|Mr|Ms))$/", "", $passenger);
            $f->addTraveller($passenger);

            $xpathFragment2 = './td[3][normalize-space(.)] and ./td[7][normalize-space(.)]';

            // segments
            $segments = $this->http->XPath->query("./descendant::tr[not({$this->contains($this->t('Airline Confirmation'))})][{$xpathFragment2}]", $flight);

            foreach ($segments as $segment) {
                $s = $f->addSegment();

                // airlineName
                $s->airline()->name($this->http->FindSingleNode('./td[1]', $segment));

                // flightNumber
                $s->airline()->number($this->http->FindSingleNode('./td[3]', $segment, true, '/^(\d+)$/'));

                // depName
                $s->departure()->name($this->http->FindSingleNode('./td[5]', $segment));

                // arrName
                $s->arrival()->name($this->http->FindSingleNode('./td[9]', $segment));

                // depCode
                // arrCode
                if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                    $s->departure()->noCode();
                    $s->arrival()->noCode();
                }

                // depTerminal
                $depTerminal = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/td[5]', $segment, true, $patterns['terminal']);

                if ($depTerminal) {
                    $s->departure()->terminal(preg_replace('/^TERMINAL\s+/i', '', preg_replace('/\s+TERMINAL$/i', '', $depTerminal)));
                }

                // arrTerminal
                $arrTerminal = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/td[9]', $segment, true, $patterns['terminal']);

                if ($arrTerminal) {
                    $s->arrival()->terminal(preg_replace('/^TERMINAL\s+/i', '', preg_replace('/\s+TERMINAL$/i', '', $arrTerminal)));
                }

                // aircraft
                $posAircraft = empty($s->getDepTerminal()) ? 1 : 2;
                $aircraft = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][{$posAircraft}][not({$xpathFragment2})]/td[5]", $segment, true, '/^Aircraft Type:\s*(.+)$/');

                if ($aircraft) {
                    $s->extra()->aircraft($aircraft);
                }

                // depDate
                $depDate = $this->http->FindSingleNode('./td[6]', $segment);
                $depTime = $this->http->FindSingleNode('./td[7]', $segment, true, '/^(' . $patterns['time'] . ')$/');

                if ($depTime && preg_match('/\d\s*[AP]$/', $depTime)) {
                    $depTime .= 'M';
                }

                if ($depDate && $depTime && $bookingDate) {
                    $s->departure()->date2($depDate . ', ' . $depTime, $bookingDate);
                }

                // arrDate
                $arrDate = $this->http->FindSingleNode('./td[10]', $segment);
                $arrTime = $this->http->FindSingleNode('./td[11]', $segment, true, '/^(' . $patterns['time'] . ')$/');

                if ($arrTime && preg_match('/\d\s*[AP]$/', $arrTime)) {
                    $arrTime .= 'M';
                }

                if ($arrDate && $arrTime && $bookingDate) {
                    $s->arrival()->date2($arrDate . ', ' . $arrTime, $bookingDate);
                }

                // confirmation
                $s->airline()->confirmation($this->http->FindSingleNode('./td[13]', $segment, true, '/^([A-Z\d]{5,})$/'));
            }
        }
    }

    /**
     * @param string $string Provider keyword
     *
     * @return string Provider code
     */
    private function normalizeProvider(string $string): string
    {
        $string = trim($string);
        $providers = [
            //            'mta' => ['Mta Travel'], // closed for traxo
        ];

        foreach ($providers as $providerCode => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $providerCode;
                }
            }
        }

        return '';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function normalizeDate($date, $relDate = false)
    {
        //		$this->http->log('$date = '.print_r( $date,true));
        $in = [
            "#^\s*([^\d\s\.\,]+)\s+(\d+),\s*(\d{4})\s*$#", //January 19, 2019
            "#^\s*(\d{1,2})\s*-\s*([^\s\d]+)\s+(\d+:\d+\s*[AP])$#", //25-May 12:39P
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 %Y%, $3M",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (strpos($date, "%Y%") !== false && !empty($relDate)) {
            $date = str_replace("%Y%", date('Y', $relDate), $date);

            return EmailDateHelper::parseDateRelative($date, $relDate);
        }

        if (strpos($date, "%Y%") !== false || !preg_match("#\b\d{4}\b#", $date)) {
            return false;
        }

        return strtotime($date);
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
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
