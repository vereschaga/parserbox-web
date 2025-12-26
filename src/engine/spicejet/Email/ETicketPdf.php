<?php

namespace AwardWallet\Engine\spicejet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "spicejet/it-20259376.eml, spicejet/it-20267025.eml";
    private $langDetectors = [
        'en' => ['ARR.TIME', 'ARR TIME'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'namePrefixes' => ['Mr.', 'Ms.', 'Mrs.', 'Chd.'],
            'ARR.TIME'     => ['ARR.TIME', 'ARR TIME'],
        ],
    ];
    /** @var \HttpBrowser */
    private $pdf;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@spicejet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'SpiceJet') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Booking PNR') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'travelling on SpiceJet') === false && strpos($textPdf, 'SpiceJet Ltd') === false && stripos($textPdf, 'www.spicejet.com') === false && stripos($textPdf, '@spicejet.com') === false) {
                continue;
            }

            if ($this->assignLang($textPdf) && preg_match("#PASSENGER NAME[ ]+FLIGHT#", $textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $i => $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            if (empty($htmlPdf)) {
                continue;
            }
            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);

            if ($this->assignLang($htmlPdf) && preg_match("#PASSENGER NAME\s+FLIGHT#", strip_tags($htmlPdf))) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($htmlPdf);
                \PDF::sortNodes($this->pdf, 3, true);
                $this->parsePdf($email);
            } else {
                $this->logger->debug("Pdf {$i}: Can't determine a language!");
            }
        }
        $email->setType('ETicketPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email)
    {
        $patterns = [
            'confNumber'    => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
            'travellerName' => '[A-z][-.\'A-z ]*[A-z]', // Mr. Hao-Li Huang
            'nameTerminal'  => '/^(.+?)\s*\/\s*T\s*([A-Z\d])$/i', // DUBAI/T1
        ];

        $f = $email->add()->flight();

        // travellers
        $passengerTexts = $this->pdf->FindNodes('/descendant::p[' . $this->eq($this->t('TRAVEL DATE')) . '][1]/preceding::text()[' . $this->contains($this->t('namePrefixes')) . ']', null, '/^(?:\d{1,3}\.)?\s*(' . $patterns['travellerName'] . ')\s*(?:\(.+)?$/');
        $passengerValues = array_values(array_filter(array_unique($passengerTexts)));
        $f->general()->travellers($passengerValues);

        // segments
        $segmentParagraphs = $this->pdf->XPath->query('//p[' . $this->eq($this->t('ARR.TIME')) . ']/following::p[1][' . $this->eq($this->t('AIRLINE')) . ']/following::p');

        foreach ($segmentParagraphs as $key => $p) {
            $pText = $p->nodeValue;

            if (preg_match('/' . $this->opt(array_merge((array) $this->t('Confirmation Number (PNR):'), (array) $this->t('Booking Date:'), (array) $this->t('Status:'))) . '/', $pText)) {
                break;
            }

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $pText, $matches)) {
                $s = $f->addSegment();

                $xpathFragment1 = '//p[' . $this->starts([$matches['airline'] . $matches['flightNumber'], $matches['airline'] . ' ' . $matches['flightNumber']]) . ']';

                // airlineName
                // flightNumber
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);

                $date = '';
                $dateText = $this->pdf->FindSingleNode('./preceding::p[1]', $p);

                if ($dateText) {
                    $date = $this->normalizeDate($dateText);
                }

                // depName
                // depTerminal
                $airportDep = $this->pdf->FindSingleNode('./following::p[1]', $p);

                if (preg_match($patterns['nameTerminal'], $airportDep, $matches)) {
                    $s->departure()
                        ->name($matches[1])
                        ->terminal($matches[2]);
                } else {
                    $s->departure()->name($airportDep);
                }

                // arrName
                // arrTerminal
                $airportArr = $this->pdf->FindSingleNode('./following::p[2]', $p);

                if (preg_match($patterns['nameTerminal'], $airportArr, $matches)) {
                    $s->arrival()
                        ->name($matches[1])
                        ->terminal($matches[2]);
                } else {
                    $s->arrival()->name($airportArr);
                }

                // depDate
                $timeDep = $this->pdf->FindSingleNode('./following::p[3]', $p, true, '/^\s*(' . $patterns['time'] . ')\s*$/');

                if ($date && $timeDep) {
                    $s->departure()->date(strtotime($date . ' ' . $timeDep));
                }

                // arrDate
                $timeArr = $this->pdf->FindSingleNode('./following::p[4]', $p, true, '/^\s*(' . $patterns['time'] . ')\s*$/');

                if ($date && $timeArr) {
                    $s->arrival()->date(strtotime($date . ' ' . $timeArr));
                }

                // depCode
                // arrCode
                $airportCodes = $this->pdf->FindNodes($xpathFragment1, null, '/[A-Z]{3}\s*-\s*[A-Z]{3}/');
                $airportCodeValues = array_values(array_filter(array_unique($airportCodes)));

                if (count($airportCodeValues) === 1 && !empty($airportCodeValues[0])) {
                    $airportCodeValuesParts = preg_split('/\s*-\s*/', $airportCodeValues[0]);
                    $s->departure()->code($airportCodeValuesParts[0]);
                    $s->arrival()->code($airportCodeValuesParts[1]);
                } elseif (!empty($s->getDepName()) && !empty($s->getArrName())) {
                    $s->departure()->noCode();
                    $s->arrival()->noCode();
                }

                // seats
                $seats = $this->pdf->FindNodes($xpathFragment1 . '/following::p[normalize-space()][position()<4]', null, '/^(\d{1,4}[A-Z])$/');
                $seatsValues = array_values(array_filter($seats));

                if (count($seatsValues) == 0) {
                    $seats = $this->pdf->FindNodes($xpathFragment1 . '/following::text()[normalize-space()][string-length()<4]', null, '/^(\d{1,4}[A-Z])$/');
                    $seatsValues = array_values(array_filter($seats));
                }

                if (!empty($seatsValues[0])) {
                    $s->extra()->seats($seatsValues);
                }
            }
        }

        // confirmation number
        $confirmationNumberText = $this->pdf->FindSingleNode('//p[' . $this->contains($this->t('Confirmation Number (PNR):')) . ']');

        if (preg_match('/(' . $this->opt($this->t('Confirmation Number (PNR):')) . ')\s*(' . $patterns['confNumber'] . ')\b/', $confirmationNumberText, $matches)) {
            $f->addConfirmationNumber($matches[2], preg_replace('/\s*:\s*$/', '', $matches[1]));
        }

        if (empty($f->getConfirmationNumbers())) {
            $confirmationNumberTitle = $this->pdf->FindSingleNode('//p[' . $this->eq($this->t('Confirmation Number (PNR):')) . '][1]');
            $confirmationNumber = $this->pdf->FindSingleNode('//p[' . $this->eq($this->t('Confirmation Number (PNR):')) . '][1]/following::p[normalize-space(.)][1]', null, true, '/^\s*(' . $patterns['confNumber'] . ')\s*$/');
            $f->addConfirmationNumber($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));
        }

        // reservationDate
        $bookingDate = $this->pdf->FindSingleNode('//p[' . $this->contains($this->t('Booking Date:')) . ']', null, true, '/' . $this->opt($this->t('Booking Date:')) . '\s*(.{6,})/');
        $f->general()->date2($bookingDate);

        // status
        $status = $this->pdf->FindSingleNode('//p[' . $this->contains($this->t('Status:')) . ']', null, true, '/' . $this->opt($this->t('Status:')) . '\s*(\w[\w\s]+)/u');
        $f->general()->status($status);

        // p.total
        // p.currencyCode
        $payment = $this->pdf->FindSingleNode('//p[' . $this->contains($this->t('Total Price:')) . ']', null, true, '/' . $this->opt($this->t('Total Price:')) . '\s*(\w.+)/u');
        // 38,129.00 INR
        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            $f->price()->total($this->normalizeAmount($matches['amount']));
            $f->price()->currency($matches['currency']);
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/\b(\d{1,2})\s*([A-Z ]{3,})\s*,\s*(\d{4})$/u', $string, $matches)) { // TUE 04 SEP,2018; THU 28 M AR,2019
            $day = $matches[1];
            $month = str_replace(' ', '', $matches[2]);
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
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
