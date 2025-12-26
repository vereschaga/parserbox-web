<?php

namespace AwardWallet\Engine\primera\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class AirReservationConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "primera/it-6622556.eml, primera/it-6636375.eml, primera/it-6655064.eml, primera/it-6676334.eml, primera/it-7692801.eml, primera/it-7721457.eml, primera/it-7734526.eml, primera/it-8745445.eml, primera/it-8774672.eml, primera/it-12131727.eml, primera/it-14302282.eml";

    public $reSubject = [
        'en' => ['Primera Air Reservation Confirmation:', 'Primera Air Reservation update:'],
    ];

    public $langDetectorsHtml = [
        'en' => ['Confirmation number:', 'Flight number:'],
    ];
    public $langDetectorsPdf = [
        'en' => ['Departing Flight'],
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Flight Number' => ['Flight Number', 'Flight No.'],
        ],
    ];

    public $pdfKey = 0;
    public $textPdf = '';
    public $pdf;
    public $dateRelative = 0;

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'code' => '[A-Z]{3}',
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 6:45 PM
        ];

        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[normalize-space(.)="Confirmation number:"]/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');
        $it['Status'] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Status:")]/ancestor::*[1]', null, true, '/^[^:]+:\s*([^:]+)$/');

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[ ./td[1][./descendant::text()[string-length(normalize-space(.))=3]] and ./td[2][./descendant::img] and ./td[3][./descendant::text()[string-length(normalize-space(.))=3]] ]');

        foreach ($segments as $segment) {
            $seg = [];

            $xpathFragment1 = './ancestor::*[ (self::table or self::div) and ./preceding-sibling::*[normalize-space(.)] and ./following-sibling::*[normalize-space(.)] ][1]';

            $date = 0;
            $dateText = $this->http->FindSingleNode($xpathFragment1 . '/preceding-sibling::*[normalize-space(.)][2]', $segment);

            if ($dateText && $this->dateRelative && !preg_match('/\D\d{4}^/', $dateText)) {
                $date = EmailDateHelper::parseDateRelative($dateText, $this->dateRelative);
            } else {
                $date = strtotime($dateText);
            }

            // AirlineName
            // FlightNumber
            // DepartureTerminal
            $flight = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::*[normalize-space(.)][position()<3][contains(normalize-space(.),"Flight number:")]', $segment, true, '/^[^:]+:\s*(.+)$/');

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)(?:\s*Terminal[:\s]+(?<terminal>[A-Z\d\s]+))?/', $flight, $matches)) {
                if (empty($matches['airline'])) {
                    $seg['AirlineName'] = AIRLINE_UNKNOWN;
                } else {
                    $seg['AirlineName'] = $matches['airline'];
                }
                $seg['FlightNumber'] = $matches['flightNumber'];

                if (!empty($matches['terminal'])) {
                    $seg['DepartureTerminal'] = $matches['terminal'];
                }
            }

            // Aircraft
            $plane = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::*[normalize-space(.)][position()<4][contains(normalize-space(.),"Plane:")]', $segment, true, '/^[^:]+:\s*([^:]+)$/');

            if ($plane) {
                $seg['Aircraft'] = $plane;
            }

            // DepCode
            $seg['DepCode'] = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)][1]', $segment, true, '/^(' . $patterns['code'] . ')$/');

            // DepDate
            $timeDep = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)][2]', $segment, true, '/^(' . $patterns['time'] . ')/');

            if ($date && $timeDep) {
                $seg['DepDate'] = strtotime($timeDep, $date);
            }

            // ArrCode
            $seg['ArrCode'] = $this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)][1]', $segment, true, '/^(' . $patterns['code'] . ')$/');

            // ArrDate
            $timeArr = $this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)][2]', $segment, true, '/^(' . $patterns['time'] . ')/');

            if ($date && $timeArr) {
                $seg['ArrDate'] = strtotime($timeArr, $date);
            }

            $it['TripSegments'][] = $seg;
        }

        // Passengers
        $passengerTexts = $this->http->FindNodes('//text()[normalize-space(.)="Passengers:"]/following::table[1]/descendant::td[not(.//td)]', null, '/^([A-z][-.\'A-z\s]*[A-z])/');
        $passengerValues = array_values(array_filter($passengerTexts));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = array_unique($passengerValues);
        }

        $itineraries[] = $it;
    }

    public function sortSgmentByTime($a, $b)
    {
        if ($a['DepDate'] == $b['DepDate']) {
            return 0;
        }

        return $a['DepDate'] > $b['DepDate'] ? 1 : -1;
    }

    public function parsePdf1(&$itineraries)
    {
        $text = $this->textPdf;
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Confirmation number: \s+([A-Z\d]+)\s#ms", $text);

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->re("#Reservation / Invoice number:\s+([A-Z\d]+)\s#ms", $text);
        }

        // Passengers
        $passcols = $this->SplitCols(preg_replace("#^\s*\n#", "", substr($text, $s = strpos($text, "Passengers\n") + strlen("Passengers\n"), strpos($text, "Reservation Totals:") - $s)));
        $it['Passengers'] = [];

        foreach ($passcols as $ptext) {
            preg_match_all("#(.*?)\nFlight#", $ptext, $m);
            $it['Passengers'] = array_merge($it['Passengers'], array_map('trim', $m[1]));
        }
        $it['Passengers'] = array_unique($it['Passengers']);

        // TotalCharge
        $it['TotalCharge'] = $this->re("#Reservation Totals?:\s+([\d\,\.]+)\s+[A-Z]{3}#", $text);

        // Currency
        $it['Currency'] = $this->re("#Reservation Totals?:\s+[\d\,\.]+\s+([A-Z]{3})#", $text);

        // TripSegments
        $it['TripSegments'] = [];

        $flightsStart = mb_strpos($text, 'Receipt and Itinerary');

        if ($flightsStart === false) {
            $flightsStart = mb_strpos($text, 'Agent Invoice and Itinerary');
        }

        $flightsEnd = mb_strpos($text, 'Passengers');

        if ($flightsStart === false && $flightsEnd === false) {
            $this->logger->info('Segments not found!');

            return;
        }

        $flights = mb_substr($text, $flightsStart, $flightsEnd - $flightsStart);

        $flights = explode("\n", $flights);
        unset($flights[0]);
        $flights = implode("\n", $flights);
        $flights = preg_replace("#^\s*\n#", "", $flights);
        $segments = $this->SplitCols($flights);

        foreach ($segments as $stext) {
            if (strpos($stext, 'Arrival Flight') !== false && strpos($stext, 'Date:') === false) {
                continue;
            } // empty arraval
            $date = strtotime($this->normalizeDate($this->re("#Date:\s+(.+)#", $stext)));

            $itsegment = [];

            // AirlineName
            // FlightNumber
            if (preg_match('/' . $this->opt($this->t('Flight Number')) . '\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)/', $stext, $matches)) {
                if (empty($matches['airline'])) {
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                } else {
                    $itsegment['AirlineName'] = $matches['airline'];
                }
                $itsegment['FlightNumber'] = $matches['flightNumber'];
            }

            // DepCode
            $itsegment['DepCode'] = $this->re("#From:\s+.*?([A-Z]{3})\n#ms", $stext);

            // DepName
            $itsegment['DepName'] = $this->re("#From:\s+(.*?)\s+[A-Z]{3}\n#ms", $stext);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#Departure\s+(\d+:\d+)#", $stext), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#To:\s+.*?([A-Z]{3})\n#ms", $stext);

            // ArrName
            $itsegment['ArrName'] = $this->re("#To:\s+(.*?)\s+[A-Z]{3}\n#ms", $stext);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->re("#Arrival\s+(\d+:\d+)#", $stext), $date);

            // Cabin
            $fare = $this->re("#Fare:\s+(.+)#", $stext);

            if ($fare) {
                $itsegment['Cabin'] = $fare;
            }

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function parsePdf2(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "T";

        $nodes = $this->pdf->XPath->query('/descendant::p');
        $rule = $this->contains(['Confirmation number:', 'Confirmationnumber:']);
        $col2pos = $this->pdf->FindSingleNode('//p[' . $rule . ']/@style', null, true, '/\bleft:\s*(\d+)px\b/');

        if (empty($col2pos)) {
            $col2pos = $this->pdf->FindSingleNode("//p[contains(normalize-space(.),'Reservation / Invoice number:')]/@style", null, true, '/\bleft:\s*(\d+)px\b/');
        }
        $cols = ['left' => [], 'right' => []];

        foreach ($nodes as $node) {
            $rowText = implode("\n", $this->pdf->FindNodes("./descendant::text()", $node));
            $left = (int) $this->pdf->FindSingleNode("./@style", $node, true, '/\bleft:\s*(\d+)px\b/');

            if ($left < ($col2pos - 10)) {
                $cols['left'][] = $rowText;
            } else {
                $cols['right'][] = $rowText;
            }
        }
        $cols['left'] = implode("\n", $cols['left']);
        $cols['right'] = implode("\n", $cols['right']);

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Confirmation\s+number:\s+([A-Z\d]{5,})\s#", $cols['right']);

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->re("#Reservation / Invoice number:\s+([A-Z\d]+)(\s|$)#ms", $cols['right']);
        }

        // Passengers
        $it['Passengers'] = [];

        // TotalCharge
        // Currency
        if (preg_match('/Reservation Total:\s+(?<amount>\d[,.\d]*)\s+(?<currency>[A-Z]{3})\b/', $cols['right'], $matches)) {
            $it['TotalCharge'] = (float) $matches['amount'];
            $it['Currency'] = $matches['currency'];
        }

        // BaseFare
        $it['BaseFare'] = 0.0;

        // Tax
        $it['Tax'] = 0.0;

        // Fees
        if ($s = $this->re("#Transaction Fee:\s+([\d\.]+)\s+[A-Z]{3}#", $cols['right'])) {
            $it['Fees'][] = ["Name" => "Transaction Fee", "Charge" => (float) $s];
        }

        // Status
        $status = $this->http->FindSingleNode("//*[contains(text(),'Status:')]", null, true, "#Status:\s*(.+)#");

        if (!empty($status)) {
            $it['Status'] = $status;
        }

        foreach ($cols as $segment) {
            if (stripos($segment, "Flight No") === false) {
                continue;
            }
            $date = strtotime($this->normalizeDate($this->re("#Date:\s+(.+)#", $segment)));

            $itsegment = [];

            // AirlineName
            // FlightNumber
            if (preg_match('/Flight No\.:\s+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)/', $segment, $matches)) {
                if (empty($matches['airline'])) {
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                } else {
                    $itsegment['AirlineName'] = $matches['airline'];
                }
                $itsegment['FlightNumber'] = $matches['flightNumber'];
            }

            // DepCode
            $itsegment['DepCode'] = $this->re("#From:\s+[\s\S]+([A-Z]{3})\n#U", $segment);

            // DepName
            $itsegment['DepName'] = $this->re("#From:\s+([\s\S]+)\s+[A-Z]{3}\n#U", $segment);

            // DepartureTerminal
            if ($terminalDep = $this->re("#Terminal:\s+(.*)\s+Passengers#", $segment)) {
                $itsegment['DepartureTerminal'] = $terminalDep;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#Departure:\s+(\d+:\d+)#", $segment), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#To:\s+[\s\S]+([A-Z]{3})\n#U", $segment);

            // ArrName
            $itsegment['ArrName'] = $this->re("#To:\s+([\s\S]+)\s+[A-Z]{3}\n#U", $segment);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->re("#Arrival:\s+(\d+:\d+)#", $segment), $date);

            // Cabin
            $itsegment['Cabin'] = $this->re("#Compartment:\s+(.+)#", $segment);

            // Passengers
            $passengersText = '';
            $passengersStart = mb_strpos($segment, "\nPassengers\n");
            $passengersEnd = mb_strpos($segment, "\nTotal:");

            if ($passengersEnd === false) {
                $passengersEnd = mb_strrpos($segment, "\nPrimera Air ehf, Hlidarsmari 12, 201 Kopavogur, Iceland");
            }

            if ($passengersEnd === false) {
                $passengersEnd = mb_strrpos($segment, "\nGroup Receipt / Itinerary");
            }

            if ($passengersStart !== false && $passengersEnd !== false) {
                $passengersText = mb_substr($segment, $passengersStart, $passengersEnd - $passengersStart);

                if (preg_match_all('/\n([ ]*[A-Z][-A-Z ]*[A-Z])[ ]*\n/', $passengersText, $passengerMatches)) {
                    $it['Passengers'] = array_unique(array_merge($it['Passengers'], $passengerMatches[1]));
                }
            }

            // Seats
            if (preg_match_all('/Reserved [Ss]eat\s*\((\d{1,3}[A-Z])\)/', $segment, $seatMatches)) {
                $itsegment['Seats'] = $seatMatches[1];
            }

            // BaseFare
            // Tax
            // Fees
            if (preg_match_all("#\n([^.\n]+)\.{3,}\s*([\d.]+)\s+[A-Z]{3}#", $passengersText, $m)) {
                foreach ($m[0] as $key => $value) {
                    if ((float) $m[2][$key] !== 0.0) {
                        if (stripos($m[1][$key], 'Fare Type') !== false) {
                            $it['BaseFare'] += (float) $m[2][$key];

                            continue;
                        }

                        if (stripos($m[1][$key], 'Tax') !== false) {
                            $it['Tax'] += (float) $m[2][$key];

                            continue;
                        }

                        if (isset($it['Fees']) && !in_array($m[1][$key], array_map(function ($el) { return $el['Name']; }, $it['Fees']))) {
                            $it['Fees'][] = ["Name" => $m[1][$key], "Charge" => (float) $m[2][$key]];
                        }
                    }
                }
            }

            $it['TripSegments'][] = $itsegment;
        }

        usort($it['TripSegments'], [$this, 'sortSgmentByTime']);
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@primeraair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = false;
        $detectLanguage = false;

        // PDF

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $this->pdfKey => $pdf) {
            $this->textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                !$detectProvider
                && strpos($this->textPdf, 'Primera Air ehf') !== false
            ) {
                $detectProvider = true;
            }

            $detectLanguage = $this->assignLangPdf($this->textPdf);

            if ($detectProvider && $detectLanguage) {
                return true;
            }
        }

        // HTML

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for making a reservation with Primera Air")]')->length === 0;

        if ($condition1) {
            return false;
        }

        return $this->assignLangHtml();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailByBody($parser) !== true) {
            return null;
        }

        $emailType = '';
        $itineraries = [];

        if ($this->assignLangPdf($this->textPdf)) { // PDF
            if (preg_match("#Flight .*\| Departure .* \| Arrival .*#", $this->textPdf)) {
                $emailType = 'Pdf1';
                $this->parsePdf1($itineraries);
            } else {
                $pdfs = $parser->searchAttachmentByName('.*pdf');
                $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdfs[$this->pdfKey]), \PDF::MODE_COMPLEX);

                if ($htmlPdf === null) {
                    return null;
                }
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($htmlPdf);
                $emailType = 'Pdf2';
                $this->parsePdf2($itineraries);
            }
        } else { // HTML
            $emailType = 'Html';

            if ($this->assignLangHtml() === false) {
                return null;
            }
            $this->dateRelative = EmailDateHelper::calculateOriginalDate($this, $parser);
            $this->parseHtml($itineraries);
        }

        if (empty($itineraries[0])) {
            return null;
        }

        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . $emailType . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLangHtml(): bool
    {
        foreach ($this->langDetectorsHtml as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangPdf($text): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+)$#", // 08 June 2017, 09:45
            "#^(\d+)\.([^\d\s]+)\s*(\d{4})$#", // 07.Jul 2017
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function TableHeadPos($row)
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
