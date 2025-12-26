<?php

namespace AwardWallet\Engine\skyscanner\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "skyscanner/it-12425677.eml, skyscanner/it-28144034.eml, skyscanner/it-28234484.eml, skyscanner/it-28332996.eml, skyscanner/it-34141876.eml, skyscanner/it-34682323.eml, skyscanner/it-34695878.eml, skyscanner/it-34908827.eml, skyscanner/it-34909125.eml";
    public static $dict = [
        'it' => [
            'Your booking reference with' => 'Il tuo codice prenotazione con',
            'is'                          => 'è',
            'Passengers'                  => 'Passeggeri',
            'Payment summary'             => 'Riepilogo pagamento',
            'Fares '                      => 'Prezzo biglietto ',
            'Total price'                 => 'Prezzo totale',
        ],
        'en' => [
            'Your booking reference with' => ['Your booking reference with', 'Your reservation reference number with'],
            //            'is' => '',
            //            'Passengers' => '',
            //            'Payment summary' => '',
            'Fares ' => ['Fares ', 'Rates '],
            //            'Total price' => '',
        ],
        'es' => [
            'Your booking reference with' => 'Tu referencia de reserva con',
            'is'                          => 'es',
            'Passengers'                  => 'Pasajeros',
            'Payment summary'             => 'Resumen del pago',
            'Fares '                      => 'Tarifas ',
            'Total price'                 => 'Precio total',
        ],
        'no' => [
            'Your booking reference with' => 'Bestillingsreferansen din med',
            'is'                          => 'er',
            'Passengers'                  => 'Passasjerer',
            'Payment summary'             => 'Betalingssammendrag',
            'Fares '                      => 'Priser ',
            'Total price'                 => 'Totalpris',
        ],
        'nl' => [
            'Your booking reference with' => 'Je boekingsreferentie bij',
            'is'                          => 'is',
            'Passengers'                  => 'Passagiers',
            'Payment summary'             => 'Betalingsoverzicht',
            'Fares '                      => 'Tarieven ',
            'Total price'                 => 'Totaalprijs',
        ],
        'zh' => [
            'Your booking reference with' => '您在',
            'is'                          => '的預訂號碼為',
            'Passengers'                  => '乘客',
            'Payment summary'             => '付款摘要',
            'Fares '                      => '票價 ',
            'Total price'                 => '價格總計',
        ],
    ];

    private $detectFrom = "skyscanner";
    private $detectSubject = [
        "it" => "Grazie di aver prenotato con Skyscanner",
        "en" => "Thank you for booking on Skyscanner",
        "es" => "Gracias por hacer la reserva con Skyscanner",
        "no" => "Takk for at du bestiller på Skyscanner",
        "nl" => "Bedankt voor het boeken op Skyscanner",
        "zh" => "感謝您透過 Skyscanner 預訂",
    ];

    private $detectCompany = ['Skyscanner Ltd', 'clicks.skyscanner.net'];

    private $detectBody = [
        'it' => ['Riepilogo prenotazione'],
        'es' => ['Resumen de la reserva'],
        'no' => ['Bestillingsoversikt'],
        'nl' => ['Boekingsoverzicht'],
        'zh' => ['預訂摘要'],
        'en' => ['Booking summary'], // last
    ];
    private $lang = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[" . $this->contains($this->detectCompany) . " or " . $this->contains($this->detectCompany, '@href') . "]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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
        $f = $email->add()->flight();

        // Travel Agency / General
        $text = $this->http->FindSingleNode("//tr[{$this->contains($this->t('Your booking reference with'))} and {$this->contains($this->t('is'))} and not(.//tr)]");

        if (preg_match("#{$this->opt($this->t('Your booking reference with'))} (.+) {$this->opt($this->t('is'))} ([\-A-Z\d]{5,})#su", $text, $m)) {
            $notSupportedProviders = ['Travel Trolley', 'HK Express'];

            foreach ($notSupportedProviders as $keyword) {
                if (strcasecmp($m[1], $keyword) === 0) {
                    $m[1] = null;
                }
            }

            if (preg_match('/^[A-Z\d]{5,7}$/', $m[2]) && preg_match('/[A-Z]/', $m[2])) {
                if ($m[1] !== null) {
                    $f->setProviderKeyword($m[1]);
                }
                $f->general()->confirmation($m[2]);
            } else {
                if ($m[1] !== null) {
                    $email->ota()->keyword($m[1]);
                }
                $email->ota()->confirmation($m[2]);
                $f->general()->noConfirmation();
            }
        } else {
            $email->obtainTravelAgency();
            $f->general()->noConfirmation();
        }

        $f->general()
            ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]/td[normalize-space(.)][1]", null, '#^\s*([[:alpha:]\-\.\' ]+)\s*$#u'))), true);

        $date = 0;
        $firstAirport = '';
        // Segments
        $segments = $this->http->XPath->query('//table[normalize-space(.) and ./descendant::tr[1][not(.//tr)]/td[1][./descendant::img] and ./following::table[normalize-space(.)][1][string-length(normalize-space(.))>15 and contains(.,">")]]');

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            $dateText = $this->http->FindSingleNode('./preceding::table[normalize-space(.)][1]/descendant::tr[normalize-space(.)][1]', $root);

            if (preg_match("#\b\d{4}\b#", $dateText)) {
                $date = $this->normalizeDate($dateText);
            }

            // Airline
            $airline = $this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]/td[position()>1][normalize-space(.)][1]', $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $airline, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            } elseif (preg_match('/^(?:None)?\s*(?<flightNumber>\d+)/i', $airline, $m)) {
                $s->airline()
                    ->number($m['flightNumber']);
            }

            if (empty($s->getAirlineName())) {
                $airlineCodeImg = $this->http->FindSingleNode('./descendant::tr[1][not(.//tr)]/td[1]/descendant::img/@src', $root, true, '/\/([~_A-Z\d]{2})\.\w+$/');

                if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])$/', $airlineCodeImg)) {
                    $s->airline()
                        ->name($airlineCodeImg);
                } else {
                    $airlinesCodes = [
                        /*
                        * IATA-code: VV
                        *
                        * Aerosvit Airlines (closed in February 2013)
                        * Viva Air Peru (open from May 9, 2017)
                        */
                        "B~" => "Viva Air Peru",
                        "07" => "VY",
                        "49" => "Indigo",
                        "I_" => "LEVEL",
                    ];

                    foreach ($airlinesCodes as $code => $al) {
                        if ($airlineCodeImg == $code) {
                            $s->airline()->name($al);

                            break;
                        }
                    }
                }
            }

            // Departure / Arrival
            $routeText = $this->http->FindSingleNode('./following::table[normalize-space(.)][1]/descendant::tr[normalize-space(.)][1]', $root);
            // 11:25 WAW Intl. Apt. > 14:00 KBP Borispol Apt.
            $pattern = '#(?<timeDep>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*(?<codeDep>[A-Z]{3})\s*(?<nameDep>[^>]{2,}?)?\s*>\s*'
                       . '(?<timeArr>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*(?<codeArr>[A-Z]{3})\s*(?<nameArr>[^>]{2,}?)\s*$#u';

            if (preg_match($pattern, $routeText, $m)) {
                if (!empty($date)) {
                    $s->departure()->date(strtotime($m['timeDep'], $date));
                    $s->arrival()->date(strtotime($m['timeArr'], $date));
                }

                $s->departure()->code($m['codeDep']);

                if (!empty($m['nameDep'])) {
                    $s->departure()->name($m['nameDep']);
                }

                if ($i === 0) {
                    $firstAirport = $m['codeDep'];
                }

                $s->arrival()->code($m['codeArr']);

                if (!empty($m['nameArr'])) {
                    $s->arrival()->name($m['nameArr']);
                }
            }
        }

        // Price
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/following::text()[normalize-space(.)][1]");

        if (preg_match('/^\s*(?<currency>[^\d)(\s]+)\s*(?<amount>\d[,.\'\d ]*)$/', $payment, $m)
            || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?)\s*(?<currency>[^\d)(\s]+)\s*$/', $payment, $m)
        ) {
            // ₹ 82,438.00
            // 2.432.999,0 ₫
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency'], $firstAirport))
            ;

            $fees = [];
            $feeRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Total price'))}]/ancestor::tr[1]/preceding-sibling::"
                    . "tr[ ./preceding::text()[{$this->eq($this->t('Payment summary'))}] and not({$this->starts($this->t('Fares '))})]");

            foreach ($feeRows as $feeRow) {
                $feeValue = $this->http->FindSingleNode('./td[normalize-space(.)][2]', $feeRow);

                if (preg_match('/^\s*' . preg_quote($m['currency'], '/') . '\s*(?<amount>\d[,.\'\d ]*)$/', $feeValue, $mat)
                    || preg_match('/^\s*(?<amount>\d[,.\'\d ]*?)\s*' . preg_quote($m['currency'], '/') . '\s*$/', $feeValue, $mat)
                ) {
                    $feeName = $this->http->FindSingleNode('./td[normalize-space(.)][1]', $feeRow);
                    $f->price()->fee($feeName, $this->normalizeAmount($mat['amount']));
                }
            }
        }

        return $email;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date = '')
    {
//        $this->http->log('$date = '.print_r( $date,true));
        $in = [
            '#^\s*[^\d\W]{3,}[,\s]+(\d{1,2})\s+([^\d\W]{3,})\s+(\d{4})\s*\..*#u', // Monday, 26 February 2018.; donderdag 20 juni 2019
            '#^\s*[^\d\W]{3,}[,\s]+([^\d\W]{3,})\s+(\d{1,2})[,\s]+(\d{4})\s*\..*#u', // Friday, May 11, 2018.
            '#^\s*[^\d\W]{3,}[,\s]+(\d{1,2})\s+de\s+([^\d\W]{3,})\s+de\s+(\d{4})\s*\..*#u', // // viernes, 29 de marzo de 2019.
            '#^\s*(\d{1,2})[.]?\s+([^\d\W]{3,})\s+(\d{4})\s*\..*#u', // 7. oktober 2019.
            '#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*\s*\..*#u', // 2019年3月31日.
        ];
        $out = [
            '$1 $2 $3',
            '$2 $1 $3',
            '$1 $2 $3',
            '$1 $2 $3',
            '$3.$2.$1',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string, string $airport = ''): string
    {
        $string = trim($string);
        $currences = [
            'INR' => ['₹'],
            'VND' => ['₫'],
            'RUB' => ['₽'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
            'NOK' => ['kr'],
            'HKD' => ['HK$'],
            'THB' => ['฿'],
            'TWD' => ['NT$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        if ($string === 'Rp' && !empty($airport)) {
            if (in_array($airport, ['CGK', 'DPS', 'SUB', 'LOP', 'JOG', 'BDO', 'KNO', 'UPG']) === true) {
                // Indonesia
                return 'IDR';
            }

            if (in_array($airport, ['CMB', 'RML', 'HRI']) === true) {
                // Sri Lanka
                return 'LKR';
            }
        }

        if ($string === 'P' && !empty($airport)) {
            if (in_array($airport, ['MNL', 'CEB', 'DVO', 'PPS', 'CRK']) === true) {
                // Philippines
                return 'PHP';
            }

            if (in_array($airport, ['GBE', 'MUB', 'BBK', 'FRW']) === true) {
                // Botswana
                return 'BWP';
            }
        }

        if ($string === '¥' && !empty($airport)) {
            if (in_array($airport, ['NRT', 'KIX']) === true) {
                // Japan
                return 'JPY';
            }
//            if (in_array($airport, ['']) === true) {
//                // China
//                return 'CNY';
//            }
        }

        return $string;
    }

    /* All currencies from the site
        '$' => ARS, AUD, BBD, BMD, BND, BSD, CLP, COP, CVE, FJD, GYD, KYD, LRD, MXN, NAD, NZD, SBD, SGD, SRD, ‭USD, XCD,
        '$MN' => CUP,
        '$U' => UYU,
        'AED' => AED,
        'AFN' => AFN,
        'Afl.' => AWG,
        'Ar' => MGA,
        'B/.' => PAB,
        'BDT' => BDT,
        'BZ$' => BZD,
        'Br' => BYN, ETB,
        'Bs' => BOB,
        'C$' => CAD, NIO,
        'CF' => KMF,
        'CHF' => CHF,
        'CUC' => CUC,
        'D' => GMD,
        'Db' => STD,
        'E' => SZL,
        'F' => XAF, XOF, XPF,
        'FBu' => BIF,
        'FC' => CDF,
        'FG' => GNF,
        'Fdj' => DJF,
        'Ft' => HUF,
        'G' => HTG,
        'GH¢' => GHS,
        'Gs' => PYG,
        'HK$' => HKD,
        'J$' => JMD,
        'K' => MMK, PGK,
        'KHR' => KHR,
        'Kz' => AOA,
        'Kč' => CZK,
        'L.' => HNL,
        'Le' => SLL,
        'Lek' => ALL,
        'M' => LSL,
        'MK' => MWK,
        'MOP$' => MOP,
        'MT' => MZN,
        'MVR' => MVR,
        'NAf.' => ANG,
        'NT$' => TWD,
        'Nfk' => ERN,
        'Nu.' => BTN,
        'P' => BWP, PHP,
        'Q' => GTQ,
        'R$' => BRL,
        'R' => ZAR,
        'RD$' => DOP,
        'RM' => MYR,
        'RWF' => RWF,
        'Rp' => IDR, LKR,
        'Rs' => MUR, PKR, SCR,
        'S' => KES, SOS,
        'S/.' => PEN,
        'SAR' => SAR,
        'SEK' => SEK,
        'T$' => TOP,
        'TJS' => TJS,
        'TL' => TRY,
        'TSh' => TZS,
        'TT$' => TTD,
        'UM' => MRO,
        'USh' => UGX,
        'VT' => VUV,
        'WS$' => WST,
        'ZK' => ZMW,
        'kn' => HRK,
        'kr' => NOK,
        'kr.' => DKK, ISK,
        'lei' => MDL, RON,
        'm' => TMT,
        'p.' => RUB,
        'zł' => PLN,
        '£' => ‭GBP, GIP, SHP,
        '¥' => CNY, JPY,
        'Дин.' => RSD,
        'КМ' => BAM,
        'Т' => KZT,
        'грн.' => UAH,
        'ден.' => MKD,
        'лв.' => BGN,
        'сом' => KGS,
        'сўм' => UZS,
        'դր.' => AMD,
        'ج.س.‏' => SDG,
        'ج.م.‏' => EGP,
        'د.ا.‏' => JOD,
        'د.ب.‏' => BHD,
        'د.ت.‏' => TND,
        'د.ج.‏' => DZD,
        'د.ع.‏' => IQD,
        'د.ك.‏' => KWD,
        'د.ل.‏' => LYD,
        'د.م.‏' => MAD,
        'ر.ع.‏' => OMR,
        'ر.ق.‏' => QAR,
        'ر.ي.‏' => YER,
        'ريال' => IRR,
        'ل.س.‏' => SYP,
        'ل.ل.‏' => LBP,
        'रु' => NPR,
        '฿' => THB,
        '₡' => CRC,
        '₦' => NGN,
        '₩' => KPW, KRW,
        '₪' => ILS,
        '₫' => VND,
        '€' => EUR,
        '₭' => LAK,
        '₮' => MNT,
        '₹' => INR,
        '₼' => AZN,
        '₾' => GEL,
     */
}
