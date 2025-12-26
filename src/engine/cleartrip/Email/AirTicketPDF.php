<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

// TODO: rewrite on objects + remove split flights; fixed tickets and price fields (examples: it-679362516-tataneu.eml)

class AirTicketPDF extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-1794452.eml, cleartrip/it-335356584.eml, cleartrip/it-4322714.eml, cleartrip/it-4370886.eml, cleartrip/it-5475180.eml, cleartrip/it-5475185.eml, cleartrip/it-6322282.eml, cleartrip/it-6395144.eml, cleartrip/it-8541350.eml, cleartrip/it-679362516-tataneu.eml";
    protected $lang = '';

    protected $langDetectors = [
        'en' => ['AIRLINE PNR', 'Airline PNR'],
    ];

    protected static $dictionary = [
        'en' => [
            'otaConfNumber' => ['Trip ID', 'Trip Id'],
            'travellers' => ['TRAVELLERS', 'Travellers'],
            'cabinValues' => ['Economy'],
            'fareBreakup' => ['FARE BREAK UP', 'Fare break up', 'FARE BREAKUP'],
            'totalPrice' => ['Total Fare:', 'Total Amount Paid:'],
            'discountNames' => ['Discounts & Cashbacks:', 'Coupon Discount:', 'Neu Coins Used'],
            'feeNames' => ['Fees and Taxes'],
        ],
    ];

    protected $tripNumber = '';
    protected $travellers = [];

    protected $patterns = [
        'code' => '[A-Z]{3}',
        'time' => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?',
        'date' => '[^,.\d ]{2,}, \d{1,2} [^,.\d ]{3,} \d{4}',
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
    ];

    private $providerCode = '';

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Cleartrip Bookings') !== false
            || stripos($from, '@cleartrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], 'Cleartrip Bookings') === false && stripos($headers['from'], 'reply@cleartrip.com') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Ticket for') !== false && stripos($headers['subject'], 'Trip ID') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$this->assignProvider($textPdf, $parser->getCleanFrom(), $parser->getSubject())) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf) === false) {
                continue;
            }
            $this->assignProvider($textPdf, $parser->getCleanFrom(), $parser->getSubject());

            return $this->parsePdf($textPdf);
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['cleartrip', 'amextravel', 'tataneu'];
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function parsePdf($textPdf): array
    {
        $result['providerCode'] = $this->providerCode;
        $result['emailType'] = 'AirTicketPdf' . ucfirst($this->lang);

        $its = [];

        $textPdfParts = [];
        $textPdfPartsAll = preg_split("/({$this->opt($this->t('totalPrice'))}[ ]+\D[^\d]* \d[,\d]*)$/im", $textPdf, -1, PREG_SPLIT_DELIM_CAPTURE);
        array_pop($textPdfPartsAll);

        for ($i = 0; $i < count($textPdfPartsAll) - 1; $i += 2) {
            $textPdfParts[] = $textPdfPartsAll[$i] . $textPdfPartsAll[$i + 1];
        }

        $segCount = 0;

        foreach ($textPdfParts as $textPdfPart) {
            $pdfPartData = $this->parsePdfPart($textPdfPart);

            if ($pdfPartData === null) {
                continue;
            }

            foreach ($pdfPartData['Itineraries'] as $itFlight) {
                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);

                    if (!empty($itFlight['TicketNumbers'][0])) {
                        if (!empty($its[$key]['TicketNumbers'][0])) {
                            $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                            $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                        } else {
                            $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                        }
                    }
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                } else {
                    $its[] = $itFlight;
                }
            }

            if (!empty($pdfPartData['TotalCharge']['Currency'])) {
                $its[$segCount]['Currency'] = $pdfPartData['TotalCharge']['Currency'];
                $its[$segCount]['TotalCharge'] = $pdfPartData['TotalCharge']['Amount'];
            }
            $its[$segCount]['BaseFare'] = $pdfPartData['TotalCharge']['BaseFare'];

            if (isset($pdfPartData['TotalCharge']['Fees']) && count($pdfPartData['TotalCharge']['Fees']) > 0) {
                $its[$segCount]['Fees'] = $pdfPartData['TotalCharge']['Fees'];
            }

            /* parsers on arrays supported only Car Rental
            if (isset($pdfPartData['TotalCharge']['Discount'])) {
                $its[$segCount]['Discount'] = $pdfPartData['TotalCharge']['Discount'];
            }
            */

            $segCount++;

            /*if ( !empty($pdfPartData['TotalCharge']['Currency']) ) {
                if ( empty($currency) || $currency === $pdfPartData['TotalCharge']['Currency'] ) {
                    $currency = $pdfPartData['TotalCharge']['Currency'];
                    $amount += (float)$pdfPartData['TotalCharge']['Amount'];

                }
            }*/
        }

        /*if ( !empty($currency) ) {
            $result['parsedData']['TotalCharge']['Currency'] = $currency;
            $result['parsedData']['TotalCharge']['Amount'] = $amount;
            if (count($its)===1){
                $its[0]['Currency'] = $currency;
                $its[0]['TotalCharge'] = $amount;
            }
        }*/

        $result['parsedData']['Itineraries'] = $its;

        return $result;
    }

    private function parsePdfPart($textPdfPart): ?array
    {
        $result = [
            'Itineraries' => [],
            'TotalCharge' => [],
        ];

        $its = [];

        $text = implode("\n", $this->splitText($textPdfPart, "/^(.*{$this->opt($this->t('otaConfNumber'))}[ ]*[:]+[ ]*[A-Z\d]{5,}.*)$/m", true));

        // TripNumber
        if (preg_match("/{$this->opt($this->t('otaConfNumber'))}[ ]*[:]+[ ]*([-A-Z\d]{4,40})(?:[ ]{2}|\n|$)/", $text, $matches)) {
            $this->tripNumber = $matches[1];
        }

        // Passengers
        // RecordLocator
        // TicketNumbers
        $textTravellers = $this->re("/^([ ]*{$this->opt($this->t('travellers'))}\s[\s\S]+)/m", $text);

        if (!$textTravellers) {
            $this->logger->debug('Travellers text not found!');

            return null;
        }

        $prefixes = ['Mstr', 'Miss', 'Mrs', 'Ms', 'Mr'];
        preg_match_all("/^[ ]{0,15}({$this->opt($prefixes)}(?: ?\. ?| ){$this->patterns['travellerName']}\s+[A-Z\d]{5}.*)$/mu", $textTravellers, $travellerMatches);

        $this->travellers = [];

        foreach ($travellerMatches[1] as $travellerRow) {
            $travellerParts = preg_split('/\s{2,}/', $travellerRow);

            if (count($travellerParts) > 1 && preg_match('/^[,A-Z\d ]{5,}$/', $travellerParts[1])) {
                $travellerPNRs = explode(',', $travellerParts[1]);
                $travellerPNRs = array_map(function ($s) { return trim($s, ', '); }, $travellerPNRs);
                $travellerPNRs = array_values(array_filter($travellerPNRs));
                $this->travellers[$travellerParts[0]]['PNRs'] = $travellerPNRs;
            }

            $tickets = [];
            $ticketVal = $travellerParts[count($travellerParts) - 1];
            $ticketValues = preg_split('/(?:\s*,\s*)+/', $ticketVal); // it-6322282.eml

            foreach ($ticketValues as $tkt) {
                if (preg_match("/^{$this->patterns['eTicket']}$/", $tkt)) {
                    $tickets[] = $tkt;
                }
            }

            if (count($tickets) > 0) {
                $this->travellers[$travellerParts[0]]['TicketNumbers'] = $tickets;
            }
        }

        if (count($this->travellers) === 0) {
            $this->logger->debug('Travellers not found!');

            return null;
        }

        // TripSegments
        $textSegments = $this->re("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('travellers'))}\s/", $text);

        if (!$textSegments) {
            $this->logger->debug('Segments text not found!');

            return null;
        }

        // example: it-5475185.eml
        $travelSegments = [];
        $flights = $this->splitText($textSegments, '/^[ ]*\w[\w ]+\w to \w[\w ]+\w[ ]{2,}[[:alpha:]]{2,}[, ]+\d{1,2} [[:alpha:]]{3,30} \d{2,4}\b/mu');

        foreach ($flights as $flight) {
            $travelSegments = array_merge($travelSegments, preg_split('/^[ ]*Layover[ ]*:[ ]*[\d hmin]{2,}$/mi', $flight));
        }

        foreach ($travelSegments as $i => $travelSegment) {
            $itFlights = $this->parseFlight($travelSegment, $i);

            foreach ($itFlights as $itFlight) {
                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);

                    if (!empty($itFlight['TicketNumbers'][0])) {
                        if (!empty($its[$key]['TicketNumbers'][0])) {
                            $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                            $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                        } else {
                            $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                        }
                    }
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                } else {
                    $its[] = $itFlight;
                }
            }
        }

        if (empty($its[0]['RecordLocator'])) {
            $this->logger->debug('RecordLocator not found!');

            return null;
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        $result['Itineraries'] = $its;

        // Currency
        // Amount
        $textFare = $this->re("/(.*{$this->opt($this->t('fareBreakup'))}\s[\s\S]+)/", $text);

        if (preg_match("/^(.*?\S.*?[ ]{2}|[ ]{40,}){$this->opt($this->t('fareBreakup'))}/", $textFare, $matches)) {
            $tablePos = [0, mb_strlen($matches[1])];
            $table = $this->splitCols($textFare, $tablePos);

            if (count($table) === 2) {
                $textFare = $table[1];
            }
        }

        if (preg_match("/{$this->opt($this->t('totalPrice'))}[ ]+(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/im", $textFare, $matches)) {
            // Total Fare:              Rs.       18,852
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $result['TotalCharge']['Currency'] = $currency;
            $result['TotalCharge']['Amount'] = PriceHelper::parse($matches['amount'], $currencyCode);

            if (preg_match('/Base fare:[ ]+(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/im', $textFare, $m)) {
                $result['TotalCharge']['BaseFare'] = PriceHelper::parse($m['amount'], $currencyCode);
            }

            $discountAmounts = [];

            if (preg_match_all("/{$this->opt($this->t('discountNames'))}[: ]+(.*\d.*)/", $textFare, $discountMatches)) {
                foreach ($discountMatches[1] as $discountValue) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $discountValue, $m)) {
                        $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                    }
                }
            }

            if (count($discountAmounts) > 0) {
                $result['TotalCharge']['Discount'] = array_sum($discountAmounts);
            }

            $fees = [];

            $feesText = $this->re("/^[ ]*Base fare:.*\n+([\s\S]{2,}?)\n+[ ]*(?i){$this->opt($this->t('totalPrice'))}/m", $textFare) ?? '';
            $feesText = preg_replace("/^[ ]*{$this->opt($this->t('discountNames'))}(?:[: ].*|$)/m", '', $feesText);

            if (preg_match_all("/^(?<name>{$this->opt($this->t('feeNames'))}|.{2,}?:)[: ]+(?<value>.*\d.*)$/m", $feesText, $feeMatches, PREG_SET_ORDER)) {
                foreach ($feeMatches as $m) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m['value'], $m2)) {
                        $fees[] = [
                            'Name' => rtrim($m['name'], ': '),
                            'Charge' => PriceHelper::parse($m2['amount'], $currencyCode),
                        ];
                    }
                }
            }

            if (count($fees) > 0) {
                $result['TotalCharge']['Fees'] = $fees;
            }
        }

        return $result;
    }

    private function parseFlight($text, $i): array
    {
        $its = [];

        $it = [];
        $it['Kind'] = 'T';
        $it['TripNumber'] = $this->tripNumber;
        $it['TripSegments'] = [];

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        if (preg_match($pattern = "/[ ]{2}({$this->opt($this->t('cabinValues'))})[ ]{2}/i", $text, $m)) {
            $seg['Cabin'] = $m[1];
            $text = preg_replace($pattern, str_repeat(' ', 2 + mb_strlen($m[1]) + 2), $text);
        }

        // AirlineName
        // FlightNumber
        if (preg_match('/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\W ?(\d+)(?:$|[ ]{2,})/m', $text, $matches)
            || preg_match('/^.+ — ([A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\W ?(\d+)$/m', $text, $matches)
        ) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        $dateDep = $dateArr = $timeDep = $timeArr = null;

        // DepCode
        // ArrCode
        if (preg_match("/\b(?<codeDep>{$this->patterns['code']}) (?<timeDep>{$this->patterns['time']})[ ]{2,}(?<timeArr>{$this->patterns['time']}) (?<codeArr>{$this->patterns['code']})\b/", $text, $matches)
            || preg_match("/(?<codeDep>{$this->patterns['code']}) (?<timeDep>{$this->patterns['time']})[ ]{2,}(?<codeArr>{$this->patterns['code']}) (?<timeArr>{$this->patterns['time']})/", $text, $matches)
        ) {
            $seg['DepCode'] = $matches['codeDep'];
            $timeDep = $matches['timeDep'];
            $timeArr = $matches['timeArr'];
            $seg['ArrCode'] = $matches['codeArr'];
        }

        $tablePos = [0];

        // Duration
        if (preg_match("/^((.*?(?<dateDep>{$this->patterns['date']})[ ]+)(?<duration>\d[\d hmin]+?)?[ ]+)(?<dateArr>{$this->patterns['date']})/m", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[2]);
            $tablePos[] = mb_strlen($matches[1]);

            $dateDep = $matches['dateDep'];
            $dateArr = $matches['dateArr'];

            if (!empty($matches['duration'])) {
                $seg['Duration'] = $matches['duration'];
            }
        }

        $table = $this->splitCols($text, $tablePos);

        if (count($table) === 3) {
            $depText = $table[0];
            $arrText = $table[2];
        } else {
            $depText = $arrText = '';
        }

        if (preg_match('/Terminal ([A-Z\d]{1,3})[ ]+(?:Airport[ ]+)?Terminal ([A-Z\d]{1,3})\b/', $text, $m)) {
            $seg['DepartureTerminal'] = $m[1];
            $seg['ArrivalTerminal'] = $m[2];
        }

        if (empty($seg['DepartureTerminal']) && preg_match('/Terminal\s+([A-Z\d]{1,3})$/m', $depText, $m)) {
            $seg['DepartureTerminal'] = $m[1];
        }

        if (empty($seg['ArrivalTerminal']) && preg_match('/Terminal\s+([A-Z\d]{1,3})$/m', $arrText, $m)) {
            $seg['ArrivalTerminal'] = $m[1];
        }

        // DepDate
        if ($timeDep && $dateDep) {
            $seg['DepDate'] = strtotime($dateDep . ', ' . $timeDep);
        }

        // ArrDate
        if ($timeArr && $dateArr) {
            $seg['ArrDate'] = strtotime($dateArr . ', ' . $timeArr);
        }

        $it['TripSegments'][] = $seg;

        foreach ($this->travellers as $travellerName => $travellerData) {
            $it['Passengers'] = [$this->normalizeTraveller($travellerName)];

            if (empty($travellerData['PNRs'][$i])) {
                $it['RecordLocator'] = $travellerData['PNRs'][0];
            } else {
                $it['RecordLocator'] = $travellerData['PNRs'][$i];
            }

            if (!empty($travellerData['TicketNumbers'][0])) {
                $it['TicketNumbers'] = $travellerData['TicketNumbers'];
            }
            $its[] = $it;
        }

        return $its;
    }

    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];
        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);
            for ($i=0; $i < count($textFragments)-1; $i+=2)
                $result[] = $textFragments[$i] . $textFragments[$i+1];
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }
        return $result;
    }

    protected function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;
        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }
        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];
        if ($text === null)
            return $cols;
        $rows = explode("\n", $text);
        if ($pos === null || count($pos) === 0) $pos = $this->rowColsPos($rows[0]);
        arsort($pos);
        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);
        foreach ($cols as &$col) $col = implode("\n", $col);
        return $cols;
    }

    /**
     * @param string $string Unformatted string with currency
     * @return string
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'INR' => ['Rs.'],
        ];
        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency)
                    return $currencyCode;
            }
        }
        return $string;
    }

    protected function assignLang($text): bool
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

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re(string $re, ?string $str, $c = 1): ?string
    {
        if (preg_match($re, $str ?? '', $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
            '$1',
        ], $s);
    }

    private function assignProvider($textPdf, $from, $subject): bool
    {
        if (self::detectEmailFromProvider($from) === true
            || stripos($textPdf, 'cleartrip.com/support') !== false || stripos($textPdf, 'with Cleartrip about') !== false || stripos($textPdf, 'Cleartrip website') !== false || stripos($textPdf, 'Cleartrip Pvt Ltd') !== false
        ) {
            $this->providerCode = 'cleartrip';

            return true;
        }

        if (stripos($from, '@amexindiatravel.com') !== false
            || stripos($textPdf, 'your tickets with Amex Customer Care') !== false
        ) {
            $this->providerCode = 'amextravel';

            return true;
        }

        if (preg_match('/[.@]tataneu\.com$/i', $from) > 0
            || stripos($textPdf, 'your tickets with Tataneu') !== false
            || stripos($textPdf, 'with Tataneu about this booking') !== false
        ) {
            $this->providerCode = 'tataneu';

            return true;
        }

        return false;
    }
}
