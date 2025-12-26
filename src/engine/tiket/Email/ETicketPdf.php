<?php

namespace AwardWallet\Engine\tiket\Email;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "tiket/it-12651076.eml, tiket/it-12716704.eml, tiket/it-12738743.eml, tiket/it-12995915.eml";

    protected $langDetectors = [
        'id' => ['Waktu Kedatangan :'],
        'en' => ['Arrival Date :'],
    ];
    protected $lang = '';
    protected static $dict = [
        'id' => [
            // HTML
            'Airlines'       => 'Maskapai',
            'Route From'     => 'Rute Dari',
            'Route To'       => 'Rute Ke',
            'Booking Code'   => 'Kode Booking',
            'Departure Date' => 'Waktu Keberangkatan',
            'Arrival Date'   => 'Waktu Kedatangan',
            // PDF
            'Duration'         => 'Durasi',
            'Passenger Detail' => 'Detil Penumpang',
            'Total'            => ['Total', 'Total*', 'Total *'],
        ],
        'en' => [
            // PDF
            'Total' => ['Total', 'Total*', 'Total *'],
        ],
    ];

    protected $pdf;

    protected $pdf1Detectors = [
        'id' => ['Kode Booking Anda'],
        'en' => ['Your Booking Code'],
    ];
    protected $pdf2Detectors = [
        //        'id' => [''],
        'en' => ['Booking Code:'],
    ];
    protected $typePdf = '';

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Tiket.com') !== false
            || stripos($from, '@tiket.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/eTicket[-\s]+Tiket\.com[-\s]+Order\s+ID/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $rule = $this->contains([
            'Terima kasih telah memilih tiket.com', 'Hormat kami, tiket.com', // id
            'Thank you for choosing Tiket.com', 'Best Regards, Tiket.com', // en
        ]);

        $condition1 = $this->http->XPath->query('//node()[contains(.,"@tiket.com") or ' . $rule . ']')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.tiket.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $this->typePdf = '';

        $htmlPdfFull = '';
        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            // TODO: better using only \PDF::convertToHtml()
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $htmlPdf = str_replace([' ', '&#160;', '&nbsp;', '  '], ' ', $htmlPdf);
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->pdf1Detectors as $phrases) { // it-12716704.eml
                foreach ($phrases as $phrase) {
                    if (stripos($htmlPdf, $phrase) !== false) {
                        $this->typePdf = '1';
                        $htmlPdfFull .= $htmlPdf;
                        $textPdfFull .= $textPdf;

                        break 3;
                    }
                }
            }

            foreach ($this->pdf2Detectors as $phrases) { // it-12738743.eml
                foreach ($phrases as $phrase) {
                    if (stripos($htmlPdf, $phrase) !== false) {
                        $this->typePdf = '2';
                        $htmlPdfFull .= $htmlPdf;
                        $textPdfFull .= $textPdf;

                        break 3;
                    }
                }
            }
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($htmlPdfFull);

        $it = $this->parseEmail($textPdfFull);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ETicketPdf' . $this->typePdf . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseEmail($textPdf)
    {
        $patterns = [
            'airport'       => '/^(?<code>[A-Z]{3})\s*\((?<name>.+?)\)$/', // CGK (Jakarta - Cengkareng)
            'ticketNumber'  => '/\d[\d ]{6,}\d/', // 99717577
            'passengerName' => '[A-z][-.\'A-z\s\/]*[.A-z]', // Mr Alexey Mikhalevich
            'terminal'      => 'Terminal[ ]+([A-Z\d][A-Z\d ]*?)', // Terminal 1C
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('//div[' . $this->eq($this->t('Booking Code')) . ']/following-sibling::div[normalize-space(.)][last()]', null, true, '/^([A-Z\d]{5,})$/');

        // Status
        $status = $this->pdf->FindSingleNode('//text()[' . $this->eq($this->t('Status')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^(Confirmed)$/i');

        if ($status) {
            $it['Status'] = $status;
        }

        // Passengers
        // TicketNumbers
        $passengers = [];
        $ticketNumbers = [];

        if ($this->typePdf === '1') {
            $passengerTexts = $this->pdf->XPath->query('//p[' . $this->eq($this->t('Passenger Detail')) . ']/following-sibling::p/descendant::text()');

            foreach ($passengerTexts as $passengerText) {
                $firstCell = $this->pdf->FindSingleNode('.', $passengerText, true, '/^\d{1,3}$/');

                if ($firstCell) {
                    $ticketNumber = $this->pdf->FindSingleNode('./following::text()[normalize-space(.)][1]', $passengerText, true, $patterns['ticketNumber']);

                    if ($ticketNumber) {
                        $ticketNumbers[] = $ticketNumber;
                    }
                    $passenger = $this->pdf->FindSingleNode('./following::text()[normalize-space(.)][2]', $passengerText, true, '/^(' . $patterns['passengerName'] . ')$/');

                    if ($passenger) {
                        $passengers[] = $passenger;
                    }
                }
            }
        } elseif ($this->typePdf === '2') {
            $passengerTexts = $this->pdf->XPath->query('//p[' . $this->eq($this->t('PASSENGER DETAIL')) . ']/following-sibling::p/descendant::text()');

            foreach ($passengerTexts as $passengerText) {
                $firstCell = $this->pdf->FindSingleNode('.', $passengerText, true, '/^\d{1,3}\.\s*[A-z][-.\'A-z\s\/]*$/');

                if ($firstCell) {
                    $passenger = $this->pdf->FindSingleNode('./following::text()[normalize-space(.)][1]', $passengerText, true, '/^(' . $patterns['passengerName'] . ')$/');

                    if ($passenger) {
                        $passengers[] = $passenger;
                    }
                    $ticketNumber = $this->pdf->FindSingleNode('./following::text()[normalize-space(.)][4]', $passengerText, true, $patterns['ticketNumber']);

                    if ($ticketNumber) {
                        $ticketNumbers[] = $ticketNumber;
                    }
                }
            }
        }

        if (empty($passengers)) {
            // Hi Alexey Mikhalevich,
            $passenger = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Hi ')) . ' and contains(.,",")]', null, true, '/^Hi\s+(' . $patterns['passengerName'] . ')\s*,/');

            if ($passenger) {
                $passengers[] = $passenger;
            }
        }

        if (count($passengers)) {
            $it['Passengers'] = array_unique($passengers);
        }

        if (count($ticketNumbers)) {
            $it['TicketNumbers'] = array_unique($ticketNumbers);
        }

        // TripSegments
        $it['TripSegments'] = [];
        $seg = [];

        // AirlineName
        // FlightNumber
        // BookingClass
        $flight = $this->http->FindSingleNode('//div[' . $this->eq($this->t('Airlines')) . ']/following-sibling::div[normalize-space(.)][last()]');

        if (preg_match('/^(?<airlineFull>.+?)\s+\((?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<flightNumber>\d+)[^)(]*\)$/', $flight, $matches)) { // Batik (ID-6413 - Depart)
            $seg['AirlineName'] = $matches['airlineFull'];
            $seg['FlightNumber'] = $matches['flightNumber'];
            $classTexts = $this->pdf->FindNodes('//text()[' . $this->eq([
                $matches['airline'] . ' ' . $matches['flightNumber'],
                $matches['airline'] . $matches['flightNumber'],
            ]) . ']/following::text()[normalize-space(.)][1]', null, '/^Class\s+([A-Z]{1,2})$/i');
            $classValues = array_values(array_filter($classTexts));

            if (!empty($classValues[0])) {
                $seg['BookingClass'] = $classValues[0];
            }
        }

        // DepCode
        // DepName
        $from = $this->http->FindSingleNode('//div[' . $this->eq($this->t('Route From')) . ']/following-sibling::div[normalize-space(.)][last()]');

        if (preg_match($patterns['airport'], $from, $matches)) {
            $seg['DepCode'] = $matches['code'];
            $seg['DepName'] = $matches['name'];
        }

        // ArrCode
        // ArrName
        $to = $this->http->FindSingleNode('//div[' . $this->eq($this->t('Route To')) . ']/following-sibling::div[normalize-space(.)][last()]');

        if (preg_match($patterns['airport'], $to, $matches)) {
            $seg['ArrCode'] = $matches['code'];
            $seg['ArrName'] = $matches['name'];
        }

        // DepDate
        $dateDep = $this->http->FindSingleNode('//div[' . $this->eq($this->t('Departure Date')) . ']/following-sibling::div[normalize-space(.)][last()]');

        if ($dateDep) {
            $seg['DepDate'] = strtotime($dateDep);
        }

        // ArrDate
        $dateArr = $this->http->FindSingleNode('//div[' . $this->eq($this->t('Arrival Date')) . ']/following-sibling::div[normalize-space(.)][last()]');

        if ($dateArr) {
            $seg['ArrDate'] = strtotime($dateArr);
        }

        // DepartureTerminal
        // ArrivalTerminal
        if ($this->typePdf === '1') {
            $flightText = preg_replace('/^(.+?)(?:' . $this->opt($this->t('Passenger Detail')) . ').+/ms', '$1', $textPdf);

            if (preg_match('/^(.+[ ]{2})(?:' . $this->opt($this->t('Duration')) . ')(?:[ ]{2}|$)/m', $flightText, $matches)) {
                $durationMarginLeft = mb_strlen($matches[1]);
                preg_match_all('/^.*' . $patterns['terminal'] . '.*$/m', $flightText, $terminalMatches);

                if (count($terminalMatches) < 3) {
                    foreach ($terminalMatches[0] as $terminalRow) {
                        $terminalDepText = substr($terminalRow, 0, $durationMarginLeft);

                        if (preg_match('/' . $patterns['terminal'] . '(?:[ ]*\)|[ ]{2}|$)/m', $terminalDepText, $m)) {
                            $seg['DepartureTerminal'] = $m[1];
                        }
                        $terminalArrText = substr($terminalRow, $durationMarginLeft);

                        if (preg_match('/' . $patterns['terminal'] . '(?:[ ]*\)|[ ]{2}|$)/m', $terminalArrText, $m)) {
                            $seg['ArrivalTerminal'] = $m[1];
                        }
                    }
                }
            }
        }

        $it['TripSegments'][] = $seg;

        // TotalCharge
        // Currency
        $payment = $this->pdf->FindSingleNode('//text()[' . $this->eq($this->t('Total')) . ']/following::text()[string-length(normalize-space(.))>1][1]');

        if (preg_match('/^(\d[,.\d\s]*?)\s*([A-z]{3,})$/', $payment, $matches)) { // 7.964.000 IDR
            $it['TotalCharge'] = $this->normalizePrice($matches[1]);
            $it['Currency'] = $matches[2];
        }

        return $it;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);           // 11 507.00    ->    11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string); // 2,790        ->    2790    |    4.100,00    ->    4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);  // 18800,00     ->    18800.00

        return $string;
    }

    protected function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang(): bool
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
}
