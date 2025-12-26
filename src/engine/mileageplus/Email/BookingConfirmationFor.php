<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmationFor extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-704614470.eml, mileageplus/it-705804436.eml, mileageplus/it-706535129.eml, mileageplus/it-706875614.eml, mileageplus/it-706964352.eml, mileageplus/it-707312710.eml, mileageplus/it-707320414.eml, mileageplus/it-707324481.eml, mileageplus/it-707333036.eml, mileageplus/it-707342936.eml, mileageplus/it-709483394-es.eml, mileageplus/it-785583548-de.eml, mileageplus/it-848003165-ja.eml";
    public $detectSubjects = [
        'Booking confirmation for PNR ',
        'Confirmación de reserva para PNR ', // es
        'Buchungsbestätigung – ', // de
        'ご予約確認書 – ', // ja
    ];

    public $emailSubject;

    public $lang = '';
    public $nextDay;

    public $subject;

    public $detectLang = [
        'en' => ['Purchase summary', 'purchase summary'],
        'es' => ['Resumen de la compra', 'Nuevo resumen de compra'],
        'de' => ['Zusammenfassung des Kaufs', 'Bestätigungsnummer:'],
        'ja' => ['ご購入内容の概要', 'ご予約番号:', 'ご予約番号：'],
    ];

    public static $dictionary = [
        "en" => [
            'FLIGHT INFO'  => 'FLIGHT INFO',
            // 'Confirmation number:' => '',
            // 'Booking confirmation for PNR' => '',
            // 'Seats:' => '',
            'ffNumber'     => 'Frequent flyer',
            'Duration:'    => 'Duration:',
            // 'Operated by' => '',
            'Flight to '   => 'Flight to ',
            // 'Purchase summary' => '',
            // 'Total' => '',
            'Total (paid ' => ['Total (paid ', 'Total (pay on '],
            'Fare'         => ['Fare', 'Corporate fare'],
            // 'New purchase summary' => '',
            // 'Fare (new trip)' => '',
            // 'to' => '',
            // 'miles' => '',
        ],

        "es" => [
            'FLIGHT INFO'          => 'INFORMACIÓN DEL VUELO',
            'Confirmation number:' => 'Número de confirmación:',
            'Booking confirmation for PNR' => 'Confirmación de reserva para PNR',
            'Seats:'           => ['Asientos:', 'Sitzplätze:'],
            'ffNumber'         => 'Viajero frecuente',
            'Duration:'        => 'Duración:',
            'Operated by'      => 'Operado por',
            'Flight to '       => 'Vuelo a',
            'Purchase summary' => 'Resumen de la compra',
            'Total'            => 'Total',
            //'Total (paid ' => '',
            'Fare'                 => 'Tarifa',
            'New purchase summary' => 'Nuevo resumen de compra',
            'Fare (new trip)'      => 'Tarifa (nuevo viaje)',
            'to'                   => ['hacia:', 'nach'],
            'miles'                => 'millas',
        ],

        "de" => [
            'FLIGHT INFO'          => 'FLUGINFO',
            'Confirmation number:' => 'Bestätigungsnummer:',
            //'Booking confirmation for PNR' => '',
            'Seats:'           => ['Seats:'],
            'ffNumber'         => 'Vielflieger',
            'Duration:'        => 'Dauer:',
            'Operated by'      => 'Durchgeführt von',
            'Flight to '       => 'Flug nach',
            'Purchase summary' => 'Zusammenfassung des Kaufs',
            'Total'            => 'Gesamtsumme',
            //'Total (paid ' => '',
            //'Fare'                 => '',
            //'New purchase summary' => '',
            //'Fare (new trip)'      => '',
            'to'                   => ['to'],
            'miles'                => 'Meilen',
        ],

        "ja" => [
            'FLIGHT INFO'          => 'フライト情報',
            'Confirmation number:' => ['ご予約番号:', 'ご予約番号：'],
            //'Booking confirmation for PNR' => '',
            'Seats:'           => ['座席:', '座席：'],
            'ffNumber'         => 'フリークエントフライヤー',
            'Duration:'        => ['飛行時間:', '飛行時間：'],
            'Operated by'      => '運航',
            'Flight to '       => ['目的地:', '目的地：'],
            'Purchase summary' => 'ご購入内容の概要',
            'Total'            => '合計',
            //'Total (paid ' => '',
            'Fare'                 => '料金',
            'New purchase summary' => '新しいご購入内容の概要',
            'Fare (new trip)'      => '運賃（新規旅行）',
            // 'to' => '',
            'miles'                => 'マイル',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('from', $headers) && stripos($headers['from'], 'notifications@united.com') !== false
            || array_key_exists('subject', $headers) && preg_match('/(?:United Airlines|ユナイテッド航空)/', $headers['subject']) > 0
        ) {
            foreach ($this->detectSubjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.united.com')]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains(['United Airlines, Inc.', 'for choosing United'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Flight to ']) && !empty($dict['FLIGHT INFO']) && !empty($dict['Duration:'])
                && $this->http->XPath->query("//text()[{$this->starts($dict['Flight to '])}]/following::text()[{$this->eq($dict['FLIGHT INFO'])}]"
                    . "/following::text()[normalize-space()][1][{$this->starts($dict['Duration:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]united\.com$/', $from) > 0;
    }

    private function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('FLIGHT INFO'))}][1]/preceding::text()[{$this->starts($this->t('Confirmation number:'))}][1]", null, true,
            "/{$this->opt($this->t('Confirmation number:'))}\s*([A-Z\d]{5,})\s*$/u");

        if (empty($conf)) {
            $conf = $this->re("/booking confirmation[\s\–]+([A-Z\d]{6})/", $this->subject);
        }

        if (empty($conf) && preg_match("/(?:^|:\s*){$this->opt($this->t('Booking confirmation for PNR'))}\s*$/i", $this->emailSubject, $m)) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($conf);
        }

        $travellerRowsXpath = "//text()[{$this->eq($this->t('Seats:'))}]/ancestor::tr[1]";
        $travellerRows = $this->http->XPath->query($travellerRowsXpath);

        $seats = [];

        foreach ($travellerRows as $tRoot) {
            $name = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][1][count(.//text()[normalize-space()]) = 1][1]", $tRoot);
            $f->general()
                ->traveller($name, true);

            $account = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('ffNumber'), "translate(.,':：','')")}]/following::text()[normalize-space()][1]", $tRoot);
            $accountTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('ffNumber'), "translate(.,':：','')")}]", $tRoot, true, '/^(.+?)[\s:：]*$/u');

            if (preg_match('/^\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])-(?<number>[*]{3,}\d{3,})\s*$/', $account, $m)) {
                $f->program()->account($m['number'], true, $name, $accountTitle ? $accountTitle . " ({$m['airline']})" : $m[1]);
            }

            $seatsText = implode("\n", $this->http->FindNodes("*[{$this->starts($this->t('Seats:'))}]//text()[normalize-space()]", $tRoot));

            if (preg_match_all("/^[ ]*([A-Z]{3})[ ]+(?:[–-]|{$this->opt($this->t('to'))})[ ]+([A-Z]{3})\b[:：\s]*([^:：\s].*)$/m", $seatsText, $m)) {
                foreach ($m[0] as $i => $v) {
                    if (trim($m[3][$i]) !== '--' && trim($m[3][$i]) !== '***') {
                        $seats[$m[1][$i] . $m[2][$i]][] = ['seat' => $m[3][$i], 'name' => $name];
                    }
                }
            }
        }

        $xpath = "//text()[{$this->eq($this->t('FLIGHT INFO'))}][1]/preceding::text()[normalize-space()][1]/ancestor::*/following-sibling::*[normalize-space()][1][{$this->starts($this->t('FLIGHT INFO'))}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            $flightInfo = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root)) . "\n\n";

            $re = "/\b{$this->opt($this->t('Duration:'))}\s*(?<duration>.+)\n\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,4})"
                . "(?<operator> +\S.+)?\n"
                . "(?<aircraft>.+)\n(?<cabin>.+)\n(?<meal>.+)?\n/u";
            $re2 = "/\b{$this->opt($this->t('Duration:'))}\s*(?<duration>.+)\n\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,4})"
                . "(?<operator> +\S.+)?\n"
                . "(?<aircraft>.+\d.*)\s*$/u";

            if (preg_match($re, $flightInfo, $m)
                || preg_match($re2, $flightInfo, $m)
            ) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if (!empty($m['operator'])) {
                    $s->airline()
                        ->operator(preg_replace(["/^\s*{$this->opt($this->t('Operated by'))}\s*(.+)/", '/(.+?)\s+dba \S.+/'], '$1', $m['operator']));
                }

                $s->extra()
                    ->duration($m['duration'])
                    ->aircraft($m['aircraft'])
                    ->cabin($m['cabin'] ?? null, true, true)
                    ->meal($m['meal'] ?? null, true, true)
                ;
            }

            $row = 1;
            $names = $this->http->FindNodes("./preceding-sibling::*[normalize-space()][{$row}]/descendant::td[not(.//td)][normalize-space()]", $root);

            if (count($names) === 2) {
                $s->departure()
                    ->name($names[0]);
                $s->arrival()
                    ->name($names[1]);
            }

            $row++;
            $stopsCodes = [];
            $codes = $this->http->FindNodes("./preceding-sibling::*[normalize-space()][{$row}]/descendant::td[not(.//td)][normalize-space()]", $root);

            if (count($codes) === 1 && preg_match("/^\s*[A-Z]{3}\s*-\s*( *\d+ ?[hm])+\s*/", $codes[0])) {
                if (preg_match_all("/\b([A-Z]{3})\b/", $codes[0], $m)) {
                    $stopsCodes = $m[1];
                    $s->extra()
                        ->stops(count($m[0]));
                }

                $row++;
                $codes = $this->http->FindNodes("./preceding-sibling::*[normalize-space()][{$row}]/descendant::td[not(.//td)][normalize-space()]", $root);
            }

            $codes = array_values(array_filter(preg_replace("/([�])/", "", $codes)));

            if (count($codes) === 3 && preg_match("/^\s*[A-Z]{3}\s*$/u", $codes[0]) && preg_match("/^\s*[A-Z]{3}\s*$/u", $codes[2])) {
                $s->departure()
                    ->code($codes[0]);
                $s->arrival()
                    ->code($codes[2]);

                if (isset($seats[$codes[0] . $codes[2]])) {
                    foreach ($seats[$codes[0] . $codes[2]] as $seatValues) {
                        $s->extra()
                            ->seat($seatValues['seat'], false, false, $seatValues['name']);
                    }
                } elseif (!empty($stopsCodes)) {
                    $allSeats = [];

                    foreach ($stopsCodes as $i => $sCode) {
                        if (isset($seats[$codes[0] . $sCode])) {
                            foreach ($seats[$codes[0] . $sCode] as $seatValues) {
                                $allSeats[$seatValues['name']][] = $seatValues['seat'];
                            }
                        }

                        if ($i === (count($stopsCodes) - 1)) {
                            if (isset($seats[$sCode . $codes[2]])) {
                                foreach ($seats[$sCode . $codes[2]] as $seatValues) {
                                    $allSeats[$seatValues['name']][] = $seatValues['seat'];
                                }
                            }
                        }
                    }

                    foreach ($allSeats as $name => $aSeat) {
                        if (count(array_unique($aSeat)) === 1) {
                            $s->extra()
                                ->seat($aSeat[0], false, false, $name);
                        }
                    }
                }
            }

            $row++;

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Flight to '))}][1]/following::text()[normalize-space()][1]", $root, true, "/.*\b\d{4}\b.*/")));

            $times = $this->http->FindNodes("./preceding-sibling::*[normalize-space()][{$row}]/descendant::td[not(.//td)][normalize-space()]", $root);

            if (!empty($date) && count($times) === 2) {
                $s->departure()->date(strtotime($this->normalizeTime($times[0]), $date));
                $s->arrival()->date(strtotime($this->normalizeTime($times[1]), $date));
            }

            if ($this->http->XPath->query("./preceding::text()[{$this->starts($this->t('Flight to '))}][1]/following::text()[normalize-space()][1]/ancestor::tr[1][contains(normalize-space(), '+1 day arrival')]", $root)->length > 0
            && $nodes->length === 1) {
                $s->arrival()
                    ->date(strtotime('+1 day', $s->getArrDate()));
            }
        }

        // Price
        $priceXpath = "//text()[{$this->eq($this->t('Purchase summary'))} or {$this->eq($this->t('New purchase summary'))}]/following::";

        $totalText = $this->http->FindSingleNode($priceXpath . "tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Total'))}]]/*[2]");

        if (empty($totalText)) {
            $totalText = implode(' + ', $this->http->FindNodes($priceXpath . "tr[not(.//tr)][count(*) = 2][*[1][{$this->starts($this->t('Total (paid '))}]]/*[2]"));
        }
        $total = $this->getTotal($totalText);

        if (!empty(array_filter($total))) {
            if ($total['miles'] !== null) {
                $f->price()
                    ->spentAwards($total['miles']);
            }

            if ($total['amount'] !== null) {
                $f->price()
                    ->total($total['amount'])
                    ->currency($total['currency']);
            }

            $cost = $this->getTotal($this->http->FindSingleNode($priceXpath . "tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Fare'))}]]/*[2]"));

            if ($cost['amount'] !== null) {
                $f->price()
                    ->cost($cost['amount'])
                    ->currency($cost['currency']);
            }
            $fees = $this->http->XPath->query($priceXpath . "tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Fare'))}]]/following-sibling::*[following-sibling::tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Total'))}]]]");

            if ($fees->length === 0) {
                $fees = $this->http->XPath->query($priceXpath . "tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Fare'))}]]/following-sibling::*[not({$this->starts($this->t('Total (paid '))})][following-sibling::tr[not(.//tr)][count(*) = 2][*[1][{$this->starts($this->t('Total (paid '))}]]]");
            }

            foreach ($fees as $fRoot) {
                $feeName = $this->http->FindSingleNode("*[1]", $fRoot);
                $feeAmount = $this->getTotal($this->http->FindSingleNode("*[2]", $fRoot));

                if ($feeAmount['amount'] !== null) {
                    $f->price()
                        ->fee($feeName, $feeAmount['amount'])
                        ->currency($feeAmount['currency']);
                }
            }
        } else {
            // it-706875614.eml
            $priceXpath = "//text()[{$this->eq($this->t('New purchase summary'))}]/following::";
            $totalText = $this->http->FindSingleNode($priceXpath . "tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Fare (new trip)'))}]]/*[2]");
            $total = $this->getTotal($totalText);

            if (!empty(array_filter($total))) {
                if ($total['miles'] !== null) {
                    $f->price()
                        ->spentAwards($total['miles']);
                }

                if ($total['amount'] !== null) {
                    $f->price()
                        ->total($total['amount'])
                        ->currency($total['currency']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $this->assignLang();

        $this->emailSubject = $parser->getSubject();
        $this->ParseFlight($email);

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

    private function getTotal($text): array
    {
        $result = ['amount' => null, 'currency' => null, 'miles' => null];
        $values = preg_split('/\s*\+\s*/', $text);

        foreach ($values as $value) {
            if (stripos($value, $this->t('miles')) !== false || stripos($value, 'PlusPoints') !== false) {
                $result['miles'] = $value;
            } elseif (
                preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $value, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $value, $m)
                // $232.83 USD
                || preg_match("/^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$/", $value, $m)
            ) {
                $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

                if (is_numeric($m['amount'])) {
                    $m['amount'] = (float) $m['amount'];
                } else {
                    $m['amount'] = null;
                }
                $result['amount'] = is_numeric($result['amount']) ? $result['amount'] + $m['amount'] : $m['amount'];
                $result['currency'] = $this->normalizeCurrency($m['currency']);
            }
        }

        return $result;
    }

    private function normalizeDate($str): string
    {
        $this->logger->debug($str);
        $in = [
            "/^([[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u", // ago. 25, 2024
            "/^(\d{1,2})\s*月\s*(\d{1,2})\s*,\s*(\d{4})$/", // 6月 21, 2025
        ];
        $out = [
            "$2 $1 $3",
            '$1/$2/$3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/([AaPp])\.[ ]*([Mm])\.?/', // 2:04 p. m.    ->    2:04 pm
            '/(\d)[ ]*([AaPp])\.?$/', // 9:30P    ->    9:30 PM
        ], [
            '$1$2',
            '$1 $2m',
        ], $s);
        $s = str_replace(['午前', '午後', '下午'], ['AM', 'PM', 'PM'], $s); // 10:36 午前    ->    10:36 AM
        return $s;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $arrayWords) {
            foreach ($arrayWords as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'CAD' => ['C$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'JPY' => ['円'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
