<?php

namespace AwardWallet\Engine\renfe\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Train;
// use AwardWallet\Schema\Parser\Common\TrainSegment;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "renfe/it-12821265.eml, renfe/it-16133127.eml, renfe/it-16597884.eml, renfe/it-2113984.eml, renfe/it-2169740.eml, renfe/it-40288586.eml, renfe/it-40289104.eml, renfe/it-4955412.eml, renfe/it-4955513.eml, renfe/it-59882779.eml, renfe/it-6706754.eml, renfe/it-7046636.eml, renfe/it-7046708.eml, renfe/it-7075802.eml, renfe/it-7075804.eml";

    private $pdfText = '';
    private $lang = '';
    private $region = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('Confirmation');
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $textPdfFull = '';

        if (0 < count($pdfs)) {
            foreach ($pdfs as $pdf) {
                $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (!$textPdf) {
                    continue;
                }

                if (strpos($textPdf, 'Localizador:') !== false || strpos($textPdf, 'Localizador :') !== false) {
                    $textPdfFull .= $textPdf;
                }
            }
            $textPdfFull = str_replace(' ', ' ', $textPdfFull);

            if (false !== stripos($textPdfFull, 'Origen')) {
                $this->pdfText = $textPdfFull;
            }
        }

        $anchor = false;

        if (!empty($textPdfFull)) {
            // for google, to help find correct address of stations
            if (stripos($textPdfFull, 'Avda. de Pío XII, 110. Madrid - 28036') !== false) {
                $this->region = ', Spain';
            }
            $firstWorld = $this->re("#^(.+?:)#", $textPdfFull);
            $anchor = $this->parseReservations($this->findCutSectionAll($textPdfFull, $firstWorld, ['Gastos de gestión:', 'Mantenga la integridad', 'Cierre del acceso al tren']), $email);
        }

        if (!$anchor) {
            if (!empty($email->getItineraries()[0])) {
                $email->removeItinerary($email->getItineraries()[0]);
            }
            $this->parseEmail($email);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@renfe.es') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Renfe") or contains(normalize-space(),"renfe")]')->length > 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Localizador:") or contains(normalize-space(),"Localizador :")]')->length > 0
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'Mantenga la integridad de toda la hoja, sin cortar ninguna de las zonas impresas.') !== false
                && (strpos($textPdf, 'Localizador:') !== false || strpos($textPdf, 'Localizador :') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Confirmacion de venta Renfe') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }

    public static function getEmailTypesCount()
    {
        return 3; // html + 2 pdf
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * <b>LEFT</b> <i>cut text2</i> <b>RIGHT2</b>.
     */
    protected function findCutSectionAll($input, $searchStart, $searchFinish): array
    {
        $array = [];

        while (empty($input) !== true) {
            $right = mb_strstr($input, $searchStart);

            foreach ($searchFinish as $value) {
                $left = mb_strstr($right, $value, true);

                if (!empty($left)) {
                    $input = mb_strstr($right, $value);
                    $array[] = mb_substr($left, mb_strlen($searchStart));

                    break;
                }
            }

            if (empty($left)) {
                $input = false;
            }
        }

        return $array;
    }

    private function parseEmail(Email $email): void
    {
        $this->logger->debug(__METHOD__);
        $r = $email->add()->train();

        if ($conf = $this->http->FindSingleNode('//td[contains(., "Localizador") and not(.//td)]', null, true, '/Localizador:\s*([A-Z\d]+)/')) {
            $r->general()->confirmation($conf, 'Localizador');
        }

        $ticketNum = $this->http->FindNodes("//td[(contains(., \"NÃºmero billete\") or contains(., \"Número billete\")) and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]",
            null, "#^\d{5,}$#");

        if (count($ticketNum) > 0) {
            $r->setTicketNumbers($ticketNum, false);
        }

        if ($pax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Estimado cliente')][1]", null, true, '/Estimado cliente\s+(.+)/')) {
            $r->addTraveller($pax);
        }

        $segments = [];

        if (!empty($this->pdfText)) {
            $segmentsText = $this->cutText('Localizador', 'EMBARQUE POR', $this->pdfText);

            if (preg_match('/Nº Billete\s*\:\s*(\d+)/', $segmentsText, $m)) {
                $r->addTicketNumber($m[1], false);
            }
            $segments = $this->splitter('/(Origen\s*:\s*.+?\s+\d+\/\d+\/\d+\s+\d+:\d+)/sim', $segmentsText);
        }

        $xpath = "//td[contains(., \"Origen\") and not(.//td)]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($nodes as $i => $root) {
            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root);

            $s = $r->addSegment();

            if (preg_match('/(?<d>\d{1,2}) de (?<m>\w+) de (?<y>20\d{2}) a las (?<t>\d{1,2}.\d{2})/', $date,
                    $m) > 0 && $month = MonthTranslate::translate($m['m'], 'es')
            ) {
                $s->departure()->date2(sprintf('%s %s %s %s', $m['d'], $month, $m['y'],
                    str_replace('.', ':', $m['t'])));
            }

            $s->departure()
                ->name('EUROPE, ' . $this->http->FindSingleNode('.//td[contains(., "Origen") and not(.//td)]/following-sibling::td', $root))
                ->geoTip('europe')
            ;

            $s->arrival()
                ->name('EUROPE, ' . $this->http->FindSingleNode('.//td[contains(., "Destino") and not(.//td)]/following-sibling::td', $root))
                ->geoTip('europe')
            ;

            if (0 < count($segments) && isset($segments[$i]) && preg_match('/Destino\s*:\s*.+?\s+(\d{1,2}\/\d{1,2}\/\d{2,4})\s+(\d{1,2}:\d{2})/iu', $segments[$i], $m)) {
                $s->arrival()
                    ->date(strtotime(str_replace('/', '.', $m[1]) . ', ' . $m[2]));
            } else {
                $s->arrival()
                    ->noDate();
            }

            $train = $this->http->FindSingleNode('.//td[contains(., "Tren") and not(.//td)]/following-sibling::td',
                $root);

            if (preg_match('/^(.+) (\d+)$/', $train, $m) > 0) {
                $s->extra()
                    ->number($m[2])
                    ->service(preg_replace('/ \d+$/', '', $m[1]));
            } else {
                $s->extra()
                    ->noNumber()
                    ->service($train);
            }

            if (0 < count($segments) && isset($segments[$i])) {
                $s->extra()
                    ->car($this->re('/Coche\s*\:\s+(\d{1,2})/', $segments[$i]))
                    ->seat($this->re('/Plaza\s*\:\s*([A-Z\d]{1,4})/', $segments[$i]))
                ;
            }

            $s->extra()->cabin($this->http->FindSingleNode('.//td[contains(., "Clase") and not(.//td)]/following-sibling::td',
                $root), true, true);
        }

        if ($total = $this->http->FindSingleNode('//td[contains(., "Total Compra") and not(.//td)]', null, true, '/Total Compra\s*:\s*([\d,]+)\s*€/')) {
            $r->price()
                ->total(str_replace(',', '.', $total))
                ->currency('EUR');
        }
    }

    private function parseReservations($tickets, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $t = $email->add()->train();

        $payment = $this->http->FindSingleNode('//*[contains(text(), "Total Compra")]', null, false, '/Total Compra\s*:\s*(.+)/');

        if (!empty($payment)) {
            if (preg_match('/(\d+\s+(?:Ptos|Puntos))/', $payment, $m)) {
                $t->program()
                    ->earnedAwards($m[1]);
            } elseif (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[^\d)(a-zA-Z]+)/', $payment, $matches)) { // 1.018,20 €
                $t->price()
                    ->total($this->normalizeAmount($matches['amount']))
                    ->currency($this->normalizeCurrency($matches['currency']));
            }
        } else {
            if (preg_match_all("#[ ]{2,}TOTAL[ ]*(.+?)[ ]+IVA:#", implode("\n", $tickets), $m)) {
                $total = 0;
                $totalpoints = [];
                $currency = [];

                foreach ($m[1] as $value) {
                    if (preg_match('/(\d+\s+(?:Ptos|Puntos))/', $value, $matches)) {
                        $totalpoints[] = $matches[1];
                    } elseif (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[^\d)(a-zA-Z]+)/', $value, $matches)) { // 1.018,20 €
                        $total += $this->normalizeAmount($matches['amount']);
                        $currency = $this->normalizeCurrency($matches['currency']);
                    }
                }

                if (!empty($totalpoints)) {
                    $t->program()
                        ->earnedAwards(implode(' + ', $totalpoints));
                }

                if (!empty($total)) {
                    $t->price()
                        ->total($total)
                        ->currency($currency);
                }
            }
        }

        $tns = [];
        $recLocs = [];

        foreach ($tickets as $ticketText) {
            if (strpos($ticketText, 'Num. Billete') !== false || strpos($ticketText, 'Nº Billete') !== false) {
                if (preg_match('#(?:Num. Billete|Nº Billete):\s*([\d.-]+)#', $ticketText, $matches)) {
                    $tns[] = $matches[1];
                }

                if (preg_match('#^\s*([A-Z\d]{5,6})#', $ticketText, $matches)) {
                    $recLocs[] = $matches[1];
                }
            } else {
                if (preg_match('#^\s*([\d.-]+)#', $ticketText, $matches)) {
                    $tns[] = $matches[1];
                }

                if (preg_match('#Localizador:\s*([A-Z\d]{5,6})#', $ticketText, $matches)) {
                    $recLocs[] = $matches[1];
                }
            }

            if (preg_match('/^(.+)TRAYECTO[ ]+2\s*$/m', $ticketText, $matchesPath)) { // it-4955412.eml
                $reservationPathsTable = $this->splitCols($ticketText, [0, mb_strlen($matchesPath[1])]);
            } else {
                $reservationPathsTable = [$ticketText];
            }

            foreach ($reservationPathsTable as $pathsColumn) {
                if (preg_match("/^[ ]*Salida /im", $pathsColumn)) {
                    $this->parseSegments1($pathsColumn, $t);
                } else {
                    $this->parseSegments2($pathsColumn, $t);
                }
            }
        }

        $tns = array_filter(array_unique($tns));
        $recLocs = array_filter(array_unique($recLocs));

        foreach ($tns as $tn) {
            $t->addTicketNumber($tn, false);
        }

        foreach ($recLocs as $recLoc) {
            $t->addConfirmationNumber($recLoc);
        }

        return true;
    }

    private function parseSegments1($text, Train $t): void
    {
        $this->logger->debug(__METHOD__);

        if (
            !preg_match('/^(.+)Plaza:/m', $text, $matches2)
            || !preg_match('/^(.+[ ]+)\d{1,2}[.:]\d{2}/m', $text, $matches3)
        ) {
            return;
        }

        $text = preg_replace("#.*?\n([ ]*Salida)#s", '$1', $text);
        $textTable = $this->splitCols($text, [
            0,
            mb_strlen($matches2[1]),
            mb_strlen($matches3[1]),
        ]);

        $patternColumn1 = '/'
            . '^[ ]*Salida\s+(?<nameDep>.+?)$' // Salida    SEVILLA SJ
            . '\s+^[ ]*Llegada\s+(?<nameArr>.+?)$' // Llegada    HUELVA
            . '\s+^[ ]*(?<service>.+?)\s+(?<number>\d+)(?<service2>\s+.+)?$' // AVE    03113
            . '\s+^[ ]*Coche\s+(?<car>\d+)' // Coche    5
            . '/ms';
        $patternColumn2 = '/'
            . '^[ ]*(?<dateDep>.{6,})$' // 06/03/2017
            . '\s+^[ ]*(?<dateArr>.{6,})$' // 06/03/2017
            . '(?:\s+^[ ]*(?<cabin>\D+?)$)?' // Turista
            . '\s+^[ ]*Plaza:\s*(?<seat>\d+[A-Z]?)?$' // Plaza:    13D
            . '/m';
        $patternColumn3 = '/'
            . '^[ ]*(?<timeDep>\d{1,2}[.:]\d{2}(?:[ ]*[AaPp][Mm])?)$' // 17:00
            . '\s+^[ ]*(?<timeArr>\d{1,2}[.:]\d{2}(?:[ ]*[AaPp][Mm])?)$' // 18:34
            . '(?:\s+^[ ]*(?<seat>\d+[A-Z]?)$)?' // 13D
            . '/m';

        if (
            preg_match_all($patternColumn1, $textTable[0], $columnMatches1, PREG_SET_ORDER)
            && preg_match_all($patternColumn2, $textTable[1], $columnMatches2, PREG_SET_ORDER)
            && preg_match_all($patternColumn3, $textTable[2], $columnMatches3, PREG_SET_ORDER)
            && count($columnMatches1) === count($columnMatches2) && count($columnMatches1) === count($columnMatches3)
        ) {
            foreach ($columnMatches1 as $key => $matches) {
                $s = $t->addSegment();

                $s->departure()
                    ->name('EUROPE, ' . preg_replace('/^\s*([^\n]+)/s', '$1', $matches['nameDep']))
                    ->geoTip('europe')
                    ->date(strtotime($this->normalizeDate($columnMatches2[$key]['dateDep']) . ', ' . $columnMatches3[$key]['timeDep']));

                $s->arrival()
                    ->name('EUROPE, ' . preg_replace('/\s+/', ' ', $matches['nameArr']))
                    ->geoTip('europe')
                    ->date(strtotime($this->normalizeDate($columnMatches2[$key]['dateArr']) . ', ' . $columnMatches3[$key]['timeArr']));

                $s->extra()
                    ->number($matches['number'])
                    ->service($matches['service'] . ' ' . (!empty($matches['service2']) ? trim($matches['service2']) . ' ' : ''))
                    ->car($matches['car']);

                if (isset($columnMatches2[$key]['cabin']) && !empty(trim($columnMatches2[$key]['cabin']))) {
                    $s->extra()
                        ->cabin(trim($columnMatches2[$key]['cabin']));
                }

                if (!empty($columnMatches2[$key]['seat'])) {
                    $s->extra()->seat($columnMatches2[$key]['seat']);
                } elseif (!empty($columnMatches3[$key]['seat'])) {
                    $s->extra()->seat($columnMatches3[$key]['seat']);
                }

                foreach ($t->getSegments() as $key => $seg) {
                    if ($s->getId() === $seg->getId()) {
                        continue;
                    }

                    if ($s->getDepName() == $seg->getDepName()
                            && $s->getNumber() == $seg->getNumber()
                            && $s->getArrName() == $seg->getArrName()
                            && $s->getDepDate() == $seg->getDepDate()) {
                        if (!empty($s->getSeats())) {
                            $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                        }
                        $t->removeSegment($s);
                    }
                }
            }
        }
    }

    private function parseSegments2($text, Train $t): void
    {
        $this->logger->debug(__METHOD__);
        $s = $t->addSegment();

        if (preg_match("/Origen:[ ]*(.{3,}?)[ ]{2,}([\d\/]{6,})[ ]{2,}(\d+[:.]\d+)/", $text, $m)) {
            $m[1] = preg_replace("/^\s*VAL\. J\.SOROLLA\s*$/", 'Valencia Joaquin Sorolla', $m[1]);
            $s->departure()
                ->name('EUROPE, ' . $m[1] . $this->region)
                ->geoTip('europe')
                ->date(strtotime($this->normalizeDate($m[2]) . ', ' . str_replace('.', '', $m[3])));
        }

        if (preg_match("/Destino:[ ]*(.{3,}?)[ ]{2,}([\d\/]{6,})[ ]{2,}(\d+[:.]\d+)/", $text, $m)) {
            $m[1] = preg_replace("/^\s*VAL\. J\.SOROLLA\s*$/", 'Valencia Joaquin Sorolla', $m[1]);
            $s->arrival()
                ->name('EUROPE, ' . $m[1] . $this->region)
                ->geoTip('europe')
                ->date(strtotime($this->normalizeDate($m[2]) . ', ' . str_replace('.', '', $m[3])));
        }

        if (
            preg_match("/(?:\n|Coche:[ ]*(?<car>\w+)[ ]{2,})Plaza:[ ]{0,7}(?<seat>\w+)[ ]{2,}(?<service>.+?\S) (?<number>\d{1,5}) (?<cabin>.+?)(?:[ ]{3,}|\n)/i", $text, $m)
            || preg_match("/(?:\n|Coche:[ ]*(?<car>\w+)[ ]{2,})Plaza:[ ]{0,7}(?<seat>\w+)[ ]{2,}(?<service>.+?\S) (?<number>\d{1,5})\n/i", $text, $m)
            || preg_match("/(?:\n|Coche:[ ]*(?<car>\w+)[ ]+)Plaza:[ ]{0,7}(?<seat>\w+)[ ]+(?<service>.+?\S) (?<number>\d{1,5}) (?<cabin>.+?)\n/i", $text, $m)
        ) {
            /*
                Coche: 10    Plaza: 4A    AVE 04209 TURISTA    Sentada
                or
                Plaza: 000    REG.EXP. 18021 UNICA
            */
            $s->extra()
                ->car(empty($m['car']) ? null : $m['car'], false, true)
                ->seat($m['seat'])
                ->service($m['service'])
                ->number($m['number'])
                ->cabin($m['cabin'], true, true)
            ;
        }
        // it-59882779.eml
        //      ALVIA 04111 TURISTA      Sin núm. plaza
        if (preg_match("/[ ]{10,}(?<service>[A-Z]{2,}) (?<number>\d{1,5}) (?<cabin>[A-Z]{2,})(?:[ ]{3,}|\n)/", $text, $m)) {
            $s->extra()
                ->service($m['service'])
                ->number($m['number'])
                ->cabin($m['cabin'])
            ;
        }

        foreach ($t->getSegments() as $key => $seg) {
            if ($s->getId() === $seg->getId()) {
                continue;
            }

            if ($s->getDepName() == $seg->getDepName()
                    && $s->getNumber() == $seg->getNumber()
                    && $s->getArrName() == $seg->getArrName()
                    && $s->getDepDate() == $seg->getDepDate()) {
                if (!empty($s->getSeats())) {
                    $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                }
                $t->removeSegment($s);
            }
        }
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
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

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 29/03/2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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
