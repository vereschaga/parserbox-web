<?php

namespace AwardWallet\Engine\aeroplan\Email;

// use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightInfoPDF extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-259802157.eml, aeroplan/it-274694868.eml, aeroplan/it-757992502.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Booking date' => ['Booking date', 'Travel booked/ticket issued on:'],
            'direction'    => ['Depart', 'Return', 'Flight'],
            'segmentsEnd'  => ['Purchase summary', 'Baggage allowance', 'Canada, U.S.:'],

            // 'per passenger' => '',
            'adult'            => ['adult', 'adults'],
            'airFareHeader'    => ['Air Transportation Charges', 'Air transportation charges'],
            // 'Carrier surcharges' => '',
            'feesHeader'       => ['Taxes, fees and charges'],
            'feesEnd'          => ['Subtotal', 'Seat selection', 'Grand total'],
            'seatFeesHeader'   => ['Seat selection'],
            'seatFeesEnd'      => ['Subtotal'],
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "Air Canada") !== false
                && strpos($text, 'Below are your flight details and other useful information for your trip.') !== false
                && (strpos($text, 'Depart') !== false || strpos($text, 'Return') !== false || strpos($text, 'Flight 1') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]twiltravel\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'airlineLogo'   => '[^\w\s]', //     |    
        ];

        $f = $email->add()->flight();

        if (preg_match("/^[ ]*({$this->opt($this->t('Booking reference'))}).*(?:\n+.{2,})?\n+[ ]*([A-Z\d]{6})(?:[ ]{2}|\n)/m", $text, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $bookingDate = strtotime(str_replace(',', '', $this->re("/{$this->opt($this->t('Booking date'))}[\s:]+(\d{1,2}[,.\s]*[[:alpha:]]+[,.\s]+\d{4})/u", $text)));

        if ($bookingDate) {
            $f->general()->date($bookingDate);
        }

        if (preg_match_all("/^\s+({$patterns['travellerName']})\s\s\s+Seats/mu", $text, $m)) {
            $f->general()->travellers($m[1], true);
        } elseif (preg_match("/\n[ ]*{$this->opt($this->t('Passengers'))}\n+[ ]*({$patterns['travellerName']})\n+[ ]*{$this->opt($this->t('direction'))}/", $text, $m)) {
            $f->general()->traveller($m[1], true);
        }

        if (preg_match_all("/\s*Ticket[#]\:\s*(\d+)/", $text, $m)) {
            $f->setTicketNumbers($m[1], false);
        }

        if (preg_match_all("/\-\s*Aeroplan[#:\s]*([\d\s]*)/", $text, $m)) {
            $f->setAccountNumbers(preg_replace('/\s/', '', $m[1]), false);
        }

        /*$priceText = $this->re("/(GRAND TOTAL.+)/u", $text);

        if (preg_match("/GRAND TOTAL[\s\-]+(?<currency>\D+)\s+\D(?<total>[\d\.\,]+)/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }*/

        $flightText = $this->re("/\n([ ]*{$this->opt($this->t('direction'))}[ \d][\s\S]+?)\n+[ ]*{$this->opt($this->t('segmentsEnd'))}/", $text);

        if (!$flightText) {
            $this->logger->debug('Flight segments not found!');

            return;
        }

        $segments = [];
        $flightParts = $this->splitText($flightText, "/^[ ]*{$this->opt($this->t('direction'))}[ \d]+(.{6,})$/m", true);

        foreach ($flightParts as $fPart) {
            $firstRow = $this->re("/^(.{6,})\n/", $fPart);
            $partSegments = $this->splitText($fPart, "/^(.{8,}\S[ ]{3,}(?:{$patterns['airlineLogo']}[ ]+)?(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+)$/mu", true);

            foreach ($partSegments as $pSegment) {
                $segments[] = $firstRow . "\n\n" . $pSegment;
            }
        }

        foreach ($segments as $key => $sText) {
            $date = $this->re("/^(?<date>[[:alpha:]].+,[ ]*\d{4})(?:[ ]{2}|\n)/u", $sText);

            $s = $f->addSegment();

            $tablePos = [0];
            $tablePos[] = strlen($this->re("/^(.*[ ]{10,})\d+\:\d+/mu", $sText));
            $tablePos[] = strlen($this->re("/^(.+\s\s)\d+\s*(?:h|m)/miu", $sText));

            /*if (preg_match("/^((.+\S [A-Z]{3}[ ]{3,})\S.+? [A-Z]{3}[ ]{3,})(?:{$patterns['airlineLogo']}[ ]+)?(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+$/mu", $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }*/

            $tableText = preg_replace("/^.*\d{4}.*\n/", '', $sText);
            $table = $this->splitCols($tableText, $tablePos);

            if (count($table) !== 3) {
                $this->logger->debug("Wrong segment-{$key}!");

                continue;
            }

            if (preg_match("/ (?<depCode>[A-Z]{3})\n+[ ]*(?<depTime>{$patterns['time']})/u", $table[0], $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($date . ', ' . $m["depTime"]));
            }

            if (preg_match("/ (?<arrCode>[A-Z]{3})\n+[ ]*(?<arrTime>{$patterns['time']})\s*(?:[+](?<nextDay>\d)\s*day)?/u", $table[1], $m)) {
                if (isset($m['nextDay']) && !empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime("{$m['nextDay']}" . ' day', $this->normalizeDate($date . ', ' . $m["arrTime"])));
                } else {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $m["arrTime"]));
                }

                $s->arrival()
                    ->code($m['arrCode']);
            }

            if (preg_match("/\W*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<flightNumber>\d+)\n+[ ]*(?<duration>\d.+)\n+[ ]*(?<cabin>.{2,}?)\s+\((?<bookingCode>[A-Z]{1,2})\)\n+[ ]*{$this->opt($this->t('Operated by'))}/", $table[2], $m)) {
                $s->airline()->name($m['airlineName'])->number($m['flightNumber']);

                $s->extra()
                    ->duration($m['duration'])
                    ->cabin(preg_replace("/^\s*{$this->opt($this->t('Cabin'))}\s*:\s*/", '', $m['cabin']))
                    ->bookingCode($m['bookingCode'])
                ;
            }

            if (preg_match("/\n[ ]*{$this->opt($this->t('Operated by'))}\s*(?<operator>.{2,})\n[ ]*(?<aircraft>.{2,})\n+[ ]*(?:Food|Meal|Breakfast)/", $table[2], $m)) {
                $s->extra()->aircraft($m['aircraft']);
            }

            if ($s->getDepCode() && $s->getArrCode()
                && preg_match_all("/\b{$s->getDepCode()}-{$s->getArrCode()}\s*(\d+[A-Z])\b/", $text, $m)
            ) {
                $s->setSeats($m[1]);
            }
        }

        // Price
        $purchaseText = $this->re("/\n[ ]*{$this->opt($this->t('Purchase summary'))}(?:[ ]{2,}|\n+)(.{2,})/s", $text);
        $purchaseText = preg_replace("/^(.{2,}?)\n+[ ]*{$this->opt($this->t('Baggage allowance'))}(?:[ ]{2}|\n).*$/s", '$1', $purchaseText);
        // $this->logger->debug('$purchaseText = '.print_r( $purchaseText,true));

        if (!preg_match("/.*[ ]{4}{$this->opt($this->t('Flights'))}\n/m", $purchaseText, $matches)) {
            return;
        }

        $tablePos = [0];

        if (preg_match("/^(.*[ ]{4}){$this->opt($this->t('Flights'))}/m", $purchaseText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($purchaseText, $tablePos);

        if (count($table) === 2) {
            $purchaseText = $table[1];
        }

        $currencyCode = null;
        $currencyRow = $this->re("/\n(.*[ ]+{$this->opt($this->t('Grand total'))}.+(?:\n *\S.*){0,1})(?:\n|$)/", $purchaseText);
        $currencyTable = implode("\n", preg_replace('/\s+/', ' ', $this->splitCols($currencyRow)));
        $currencyCodes = [
            'CAD' => 'Canadian dollars',
            'CNY' => 'China - yuan',
            'EUR' => 'Euro',
            'USD' => 'US dollars',
        ];

        foreach ($currencyCodes as $key => $value) {
            if (preg_match("/\(\s*{$this->opt($value)}\s*\)/i", $currencyRow) > 0
                || preg_match("/{$this->opt($this->t('Grand total'))}\s*\W\s*{$this->opt($value)}\s*(?:\n|$)/i", $currencyTable) > 0
            ) {
                $currencyCode = $key;

                break;
            }
        }

        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Grand total'))}.*?[ ]{2,}(.*\d.*)$/m", $purchaseText)
            ?? (preg_match("/^[ ]{30,}(.*\d)\n+[ ]*{$this->opt($this->t('Grand total'))}[^\d\n]*$/m", $purchaseText, $m) && !preg_match("/[ ]{2}/", $m[1]) ? $m[1] : null)
        ;

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<integer>\d[,.‘\'\d]*)[. ]*(?<decimals>\d{2})$/u', $totalPrice, $matches)
            || preg_match('/^\s*(?<points>\d[\d,]* pts)\s*$/u', $totalPrice, $matches)
        ) {
            if (isset($matches['points'])) {
                $f->price()
                    ->spentAwards($matches['points']);
            } else {
                // $30723    |    $307.23    |    $307 23
                if (!$currencyCode) {
                    $currencyCode = $this->normalizeCurrency($matches['currency']);
                }

                $matches['integer'] = preg_replace('/\D*$/', '', $matches['integer']);
                $matches['amount'] = $matches['integer'] . '.' . $matches['decimals'];

                $f->price()
                    ->currency($currencyCode ?? $matches['currency'])
                    ->total(PriceHelper::parse($matches['amount'], $currencyCode));

                if (preg_match("/\n\n {20,}(?<points>\d[\d,]* pts)\n {0,11}{$this->opt($this->t('Grand total'))}.*?[ ]{2,}.*\d.*(?:\n|$)/", $purchaseText, $m)) {
                    $f->price()
                        ->spentAwards($m['points']);
                }
            }

            // baseFare
            $baseFare = [];
            $baseFareAward = [];
            $baseFareText = $this->re("/^[ ]*{$this->opt($this->t('airFareHeader'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('feesHeader'))}$/im", $purchaseText);
            preg_match_all("/(.+)[ ]{3,}(.*\d.*)$/m", $baseFareText, $bfMatches);

            foreach ($bfMatches[2] as $i => $bfCharge) {
                if (preg_match("/\({$this->opt($this->t('in points'))}\)/", $bfMatches[0][$i])) {
                    $baseFareAward[] = (int) preg_replace('/\D*/', '', $bfCharge);
                } else {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d]*)$/u', $bfCharge, $m)) {
                        $bfAmount = PriceHelper::parse($m['amount'], $currencyCode);

                        if (preg_match("/^{$this->opt($this->t('Carrier surcharges'))}/", $bfMatches[1][$i])) {
                            $f->price()->fee($bfMatches[1][$i], $bfAmount);
                        } else {
                            $baseFare[] = $bfAmount !== null ? $bfAmount : null;
                        }
                    }
                }
            }

            if (count($baseFare) > 0 && !in_array(null, $baseFare, true)) {
                $f->price()->cost(array_sum($baseFare));
            } elseif (count($baseFareAward) > 0 && !in_array(null, $baseFareAward, true)) {
                $f->price()->spentAwards(array_sum($baseFareAward));
            }

            // fees
            $feesText = $this->re("/^[ ]*{$this->opt($this->t('feesHeader'))}\n+([\s\S]+?)\n+[ ]*(?:{$this->opt($this->t('feesEnd'))}(?:[ ]{2}|$)|{$this->opt($this->t('Grand total'))})/im", $purchaseText);
            preg_match_all("/^[ ]{0,10}(?<name>\S.{0,80}?\S)\n?[ ]{5,}(?<charge>.*\d.*)$/m", $feesText, $feesMatches, PREG_SET_ORDER);

            foreach ($feesMatches as $feeRow) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d]*)$/u', $feeRow['charge'], $m)) {
                    $feeAmount = PriceHelper::parse($m['amount'], $currencyCode);
                    $f->price()->fee(rtrim($feeRow['name'], ': '), $feeAmount !== null ? $feeAmount : null);
                }
            }

            $feeSeatText = $this->re("/^[ ]*{$this->opt($this->t('seatFeesHeader'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('seatFeesEnd'))}(?:[ ]{2}|$)/im", $purchaseText);

            preg_match_all("/^(?<name>.+ \- .+)[ ]{10,}\D{0,3}(?<fee>\d[\d\.\,]*)(?<name2>\n.+)?/mu", $feeSeatText, $m1);
            preg_match_all("/^(?<name>.+[^\s\-])[ ]{10,}(?<fee>[\d\.\,]+)\n/mu", $feeSeatText, $m2);

            foreach ($m1[1] ?? [] as $key => $rows) {
                $f->price()->fee($m1['name2'][$key] !== null ? $m1['name'][$key] . ' ' . $m1['name2'][$key] : $m1['name'][$key], $m1['fee'][$key]);
            }

            foreach ($m2[1] ?? [] as $key => $rows) {
                $f->price()->fee(preg_replace('/\s+/', ' ', $m2['name'][$key]), $m2['fee'][$key]);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\d+)\s*(\w+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Thu 9 Mar, 2023, 16:40
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($string): ?string
    {
        $string = trim($string, '+-');
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€', 'Euro', 'EU €'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
            'CAD' => ['CA $', 'Canadian dollars', 'CA $', 'Dollars canadiens'],
            'JPY' => ['円(日本)', 'JP ¥'],
            'USD' => ['US $', 'US dollars'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        if (preg_match("/\(([A-Z]{3})\)/", $string, $m)) {
            return $m[1];
        }

        if ($string === '(Canadian dollars)') {
            return 'CAD';
        }

        return null;
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

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
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
}
