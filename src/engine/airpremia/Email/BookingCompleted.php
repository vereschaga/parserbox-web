<?php

namespace AwardWallet\Engine\airpremia\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingCompleted extends \TAccountChecker
{
    public $mailFiles = "airpremia/it-500202845.eml, airpremia/it-512515663.eml, airpremia/it-515675385.eml, airpremia/it-522068424.eml, airpremia/it-889187581.eml";

    public $lang;
    public static $dictionary = [
        'ko' => [
            'Passenger ltinerary' => '여정 안내서',
            'Booking Reference'   => '예약번호',
            'Status'              => '예약상태',
            'Passenger'           => '탑승객',

            'Date/Local Time'      => '날짜/시간',
            'Flight Time '         => 'Flight Time',
            'Terminal '            => ['Terminal', '여객터미널'],
            'Class'                => '예약등급',
            'Aircraft Type/Flight' => '기종 / 편명',
            'Baggage / Seat No'    => ['위탁수하물 / 좌석번호', '좌석번호 / 위탁수하물'], // type1
            // 'Passenger Name' => [''], // type2
            // 'Seat' => [''], // type2

            'Ticket/Fare Paymention Information' => '운임 결제 정보',
            'Fare'                               => '항공운임',
            'Discount'                           => '할인내역',
            'Total Amount'                       => '총 금액',
        ],
        'en' => [
            'Passenger ltinerary' => ['Passenger ltinerary', 'Passenger Itinerary'],
            'Booking Reference'   => 'Booking Reference',
            'Status'              => ['Status', 'Booking Status'],
            // 'Passenger' => '',

            'Date/Local Time' => 'Date/Local Time',
            // 'Flight Time ' => '',
            // 'Terminal ' => '',
            // 'Class' => '',
            'Aircraft Type/Flight' => ['Aircraft Type/Flight', 'Aircraft Type/Flight Number'],
            // 'Baggage / Seat No' => [''], // type1
            // 'Passenger Name' => [''], // type2
            // 'Seat' => [''], // type2

            'Ticket/Fare Paymention Information' => ['Ticket/Fare Paymention Information', 'Payment Information'],
            // 'Fare' => '',
            // 'Discount' => '',
            // 'Total Amount' => '',
        ],
    ];

    private $detectFrom = "noreply@airpremia.com";
    private $detectSubject = [
        // en
        '[AIRPREMIA] Your booking has been completed.',
        '[Air Premia] Passenger Itinerary Email',
        'Air Premia - Booking Change',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]airpremia\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && $this->containsText($headers["subject"], ['[AIRPREMIA]', '[에어프레미아]', 'Air Premia']) === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.airpremia.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Air Premia Inc.'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Passenger ltinerary']) && !empty($dict['Ticket/Fare Paymention Information'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Passenger ltinerary'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Ticket/Fare Paymention Information'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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
        return 2 * count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Booking Reference"], $dict["Date/Local Time"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking Reference'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Date/Local Time'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Booking Reference'))}]/following-sibling::td[1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->status($this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Status'))}]/following-sibling::td[1]"))
        ;

        $xpath = "//text()[{$this->eq($this->t('Date/Local Time'))}]/ancestor::*[.//text()[{$this->eq($this->t('Aircraft Type/Flight'))}]][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0
            && $this->http->XPath->query("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[2]//img", $nodes->item(0))->length > 0
        ) {
            /* with subject "Your booking has been completed"
           <tr>
               <td>LAX</td>
               <td>NRT</td>
           </tr>
           <tr>
               <td>Los Angeles (Terminal B)</td>
               <td>Tokyo/Narita(Terminal 2)</td>
           </tr>
           <tr>
               <td>Los Angeles International Airport</td>
               <td>Narita International Airport, Japan</td>
           </tr>
           */
            $this->parseSegmentsType2($f, $nodes);
        } else {
            /* with subject "Passenger Itinerary Email"
            <tr>
                <td>LAX   Los Angeles (Terminal B)    Los Angeles International Airport</td>
                <td>ico_flightlane 12h 55m</td>
                <td>NRT   Tokyo/Narita(Terminal 2)   Narita International Airport, Japan</td>
            </tr>
            */
            $this->parseSegmentsType1($f, $nodes);
        }

        // Price
        $total = $this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Total Amount'))}]/following-sibling::td[1]");

        if (preg_match("#^\s*(?<currency>[^\s\d]{1,3})\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\s\d]{1,3})\s*$#u", $total, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency);

            $feeXpath = "//tr[not(.//tr)][preceding::text()[{$this->eq($this->t('Ticket/Fare Paymention Information'))}] and following::text()[{$this->eq($this->t('Discount'))}]][count(*[normalize-space()]) = 4]";
            $feeNodes = $this->http->XPath->query($feeXpath);
            $fees = [];

            foreach ($feeNodes as $root) {
                $name1 = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
                $name2 = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][1]",
                    $root);
                $value = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/^\D*(\d[\d., ]*?)\D*$/u");

                if ($value !== '0') {
                    $fees[] = ['name' => $name1 . ($name2 ? ' (' . $name2 . ')' : ''), 'value' => $value];
                }

                $name1 = $this->http->FindSingleNode("*[normalize-space()][3]", $root);
                $name2 = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][2]",
                    $root);
                $value = $this->http->FindSingleNode("*[normalize-space()][4]", $root, true, "/^\D*(\d[\d., ]*?)\D*$/u");

                if ($value !== '0') {
                    $fees[] = ['name' => $name1 . ($name2 ? ' (' . $name2 . ')' : ''), 'value' => $value];
                }
            }

            foreach ($fees as $fee) {
                if (preg_match("/^\s*{$this->opt($this->t('Fare'))}\s*(?:\(|$)/u", $fee['name'])) {
                    $f->price()
                        ->cost(PriceHelper::parse($fee['value'], $currency));
                } else {
                    $f->price()
                        ->fee($fee['name'], PriceHelper::parse($fee['value'], $currency));
                }
            }

            $discount = $this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Discount'))}]/following-sibling::td[1]");

            if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#u", $discount, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#u", $discount, $m)
            ) {
                $f->price()
                    ->discount(PriceHelper::parse($m['amount'], $currency));
            }
        }

        return true;
    }

    private function parseSegmentsType1(Flight $f, \DOMNodeList $nodes)
    {
        $this->logger->info(__METHOD__);

        $f->general()
            ->travellers(preg_split('/\s*,\s*/', $this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Passenger'))}]/following-sibling::td[1]",
                null, true, "/^\s*([A-Z\W]+)\s*$/")));

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Aircraft Type/Flight'))}]/following-sibling::*[self::th or self::td][1]",
                $root, true, "/\\/\s*(.+)/");

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][2][count(*[normalize-space()]) = 2]/*[normalize-space()][1]", $root))
            ;

            if (preg_match("/^.+\((?:.*[, ]+)?(\w+ ?{$this->opt($this->t('Terminal'))}|{$this->opt($this->t('Terminal'))} ?\w+)\)\s*$/iu", $s->getDepName(), $m)) {
                $s->departure()
                    ->terminal(preg_replace("/\s*{$this->opt($this->t('Terminal'))}\s*/", '', $m[1]));
            }
            $dateStr = $this->http->FindSingleNode("descendant::*[self::th or self::td][{$this->eq($this->t('Date/Local Time'))}]/following-sibling::td[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<date>.+?)\s*\b(?<dTime>\d{1,2}:\d{2}.*?)\s*-\s*(?<aTime>\d{1,2}:\d{2}.*?)\s*(?:\(?(?<overnignt>[-+]\d)\)?)?\s*$/", $dateStr, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['dTime']));
                $date = $this->normalizeDate($m['date'] . ', ' . $m['aTime']);

                if (!empty($m['overnignt']) && !empty($date)) {
                    $date = strtotime($m['overnignt'] . ' days', $date);
                }
                $s->arrival()
                    ->date($date);
            }
            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][2]",
                    $root, true, "/^\s*([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][2][count(*[normalize-space()]) = 2]/*[normalize-space()][2]", $root))
            ;

            if (preg_match("/^.+\((?:.*[, ]+)?(\w+ ?{$this->opt($this->t('Terminal'))}|{$this->opt($this->t('Terminal'))} ?\w+)\)\s*$/iu", $s->getArrName(), $m)) {
                $s->arrival()
                    ->terminal(preg_replace("/\s*{$this->opt($this->t('Terminal'))}\s*/", '', $m[1]));
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Class'))}]/following-sibling::*[self::th or self::td][1]",
                    $root, true, "/^\s*(.+?)\s*\(/"))
                ->bookingCode($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Class'))}]/following-sibling::*[self::th or self::td][1]",
                    $root, true, "/^\s*.+?\s*\(([A-Z]{1,2})\)\s*$/"))
                ->aircraft($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Aircraft Type/Flight'))}]/following-sibling::*[self::th or self::td][1]",
                    $root, true, "/^\s*(.+?)\s*\\/.*$/"))
                ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Flight Time'))}]/ancestor::*[self::th or self::td][1]",
                    $root, true, "/^\s*{$this->opt($this->t('Flight Time'))}\s*(.+?)\s*$/"))
            ;
            $seats = implode("\n", $this->http->FindNodes(".//*[self::th or self::td][{$this->starts($this->t('Baggage / Seat No'))}]/following-sibling::*[self::th or self::td][1]/descendant::text()[normalize-space()]", $root));
            $seatsRe = '\d{1,3}[A-Z](\s*,\s*\d{1,3}[A-Z])*';

            if (preg_match_all("/(^\s*{$seatsRe}\s*\\/|\\/\s*{$seatsRe}\s*$)/m", $seats, $m)) {
                $m[1] = preg_replace("/(^\s*\\/\s*|\s*\\/\s*$)/m", '', $m[1]);
                $s->extra()
                    ->seats(preg_split('/\s*,\s*/', implode(',', $m[1])));
            }
        }
    }

    private function parseSegmentsType2(Flight $f, \DOMNodeList $nodes)
    {
        $this->logger->info(__METHOD__);

        $f->general()
            ->travellers($this->http->FindNodes("(//tr[*[1][{$this->eq($this->t('Passenger Name'))}]][*[2][{$this->eq($this->t('Seat'))}]])[1]/ancestor::table[1]//tr[count(*) > 3]/*[1][not({$this->eq($this->t('Passenger Name'))})]"));

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Aircraft Type/Flight'))}]/following-sibling::*[self::th or self::td][1]",
                $root, true, "/\\/\s*(.+)/");

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[normalize-space()][1]/descendant::text()[normalize-space()][3]", $root))
                ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/u", ' ',
                        $this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[normalize-space()][1]/descendant::text()[normalize-space()][{$this->contains($this->t('Terminal'))}]",
                            $root, true, "/.+[\(,](.*?{$this->opt($this->t('Terminal'))}.*?)[\),]/"))), true, true)
            ;
            $dateStr = $this->http->FindSingleNode("descendant::*[self::th or self::td][{$this->eq($this->t('Date/Local Time'))}]/following-sibling::td[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<date>.+?)\s*\b(?<dTime>\d{1,2}:\d{2}.*?)\s*-\s*(?<aTime>\d{1,2}:\d{2}.*?)\s*(?:\(\s*(?<overnignt>[-+]\d)\s*\))?\s*$/", $dateStr, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['dTime']));
                $date = $this->normalizeDate($m['date'] . ', ' . $m['aTime']);

                if (!empty($m['overnignt']) && !empty($date)) {
                    $date = strtotime($m['overnignt'] . ' days', $date);
                }
                $s->arrival()
                    ->date($date);
            }
            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[normalize-space()][3]/descendant::text()[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[normalize-space()][3]/descendant::text()[normalize-space()][3]", $root))
                ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/u", ' ',
                    $this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[normalize-space()][3]/descendant::text()[normalize-space()][{$this->contains($this->t('Terminal'))}]",
                        $root, true, "/.+[\(,](.*?{$this->opt($this->t('Terminal'))}.*?)[\),]/"))), true, true)
            ;

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Class'))}]/following-sibling::*[self::th or self::td][1]/descendant::text()[normalize-space()][1]",
                    $root, true, "/^\s*(.+?)\s*\(/"))
                ->bookingCode($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Class'))}]/following-sibling::*[self::th or self::td][1]/descendant::text()[normalize-space()][1]",
                    $root, true, "/^\s*.+?\s*\(([A-Z]{1,2})\)\s*$/"))
                ->aircraft($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Aircraft Type/Flight'))}]/following-sibling::*[self::th or self::td][1]",
                    $root, true, "/^\s*(.+?)\s*\\/.*$/"))
                ->duration($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 3]/*[normalize-space()][2]",
                    $root))
            ;
            $seatsNodes = $this->http->XPath->query(".//tr[*[1][{$this->eq($this->t('Passenger Name'))}]][*[2][{$this->eq($this->t('Seat'))}]]/ancestor::table[1]/tbody/tr", $root);

            if ($seatsNodes->length == 0) {
                $seatsNodes = $this->http->XPath->query(".//tr[*[1][{$this->eq($this->t('Passenger Name'))}]][*[2][{$this->eq($this->t('Seat'))}]]/following-sibling::tr", $root);
            }

            foreach ($seatsNodes as $sRoot) {
                $seat = $this->http->FindSingleNode("*[2]", $sRoot, true, "/^\s*(\d{1,3}[A-Z])\s*$/");

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat, true, true, $this->http->FindSingleNode("*[1]", $sRoot));
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            //            // 2023.12.11 (Mon), 00:01
            '/^\s*(\d{4})\.(\d{1,2})\.(\d{1,2})\s*\([[:alpha:]]+\)[,\s]+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1-$2-$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));
//         if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
//             $monthNameOriginal = $m[0];
//             if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
//                 return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
//             }
//         }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);

        if (preg_match("/^\s*([A-Z]{3})\s*$/", $string)) {
            return $string;
        }

        $currencies = [
            'KRW' => ['원'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
