<?php

namespace AwardWallet\Engine\hongkongairlines\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketItinerary2 extends \TAccountChecker
{
    public $mailFiles = "hongkongairlines/it-446393579.eml, hongkongairlines/it-846634308.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'orderNumber'    => ['Order Number'],
            'direction'      => ['Departure', 'Return'],
            'statusPhrases'  => ['Your flight booking is'],
            'statusVariants' => ['confirmed', 'confirmed'],
            'flightNo'       => ['Flight No', 'Flight no', 'FlightNo', 'Flightno'],
        ],
        'ja' => [
            'orderNumber'    => ['注文番号'],
            'direction'      => ['往路', '復路'],
            //'statusPhrases'  => ['Your flight booking is'],
            //'statusVariants' => ['confirmed', 'confirmed'],
            'flightNo'                => ['便名'],
            'Seat'                    => '座席',
            'Ticket Number'           => 'Eチケット番号:',
            'Membership Number'       => '会員番号:',
            'Grand Total'             => '合計',
            'Fare'                    => '運賃および航空会社により課される手数料',
            'Taxes, Fees and Charges' => '税金と各種手数料',
        ],
    ];

    private $subjects = [
        'en' => ['Your flight booking is confirmed, please refer to eTicket Itinerary & receipt'],
        'ja' => ['ご予約は完了いたしました。Eチケットにてご旅程をご確認ください。'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]hkairlines\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".hongkongairlines.com/") or contains(@href,"www.hongkongairlines.com") or contains(@href,"new.hongkongairlines.com")]')->length === 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('Thank you for choosing Hong Kong Airlines'))} or {$this->contains($this->t('Thank you for choosing Hong Kong Airlines'))}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ETicketItinerary2' . ucfirst($this->lang));

        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';
        $xpathTime = '(starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        if (preg_match("/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i", $parser->getSubject(), $m)) {
            $f->general()->status($m[1]);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('orderNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('orderNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->http->XPath->query("//tr[ count(*)=3 and *[1]/descendant::p[{$xpathTime} and descendant::text()[{$xpathAirportCode}]] and *[3]/descendant::p[{$xpathTime} and descendant::text()[{$xpathAirportCode}]] ]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('direction'))}\s*(.+)$/u"));
            /* if (empty($date))
                 $date = $this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $root);
             $this->logger->debug($date);*/
            /*
                Hong Kong
                01:55 HKG
                Hong Kong International AirportT1
            */
            $pattern = "/^"
            . ".{2,}\n"
            . "(?<time>{$patterns['time']})[ ]*(?<code>[A-Z]{3})\n"
            . "(?<name>.{3,})"
            . "$/";

            $departure = implode("\n", $this->http->FindNodes("*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $departure, $m)) {
                if ($date) {
                    $s->departure()->date(strtotime($m['time'], $date));
                }

                $s->departure()->code($m['code']);

                if (preg_match("/^(?<n>.{2,}?(?:Airport|[^t]))[ ]*(?-i)T[- ]*(?<t>[A-z\d])$/i", $m['name'], $m2)) {
                    $s->departure()->name($m2['n'])->terminal($m2['t']);
                } else {
                    $s->departure()->name($m['name']);
                }
            }

            $arrival = implode("\n", $this->http->FindNodes("*[3]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $arrival, $m)) {
                if ($date) {
                    $s->arrival()->date(strtotime($m['time'], $date));
                }

                $s->arrival()->code($m['code']);

                if (preg_match("/^(?<n>.{2,}?(?:Airport|[^t]))[ ]*(?-i)T[- ]*(?<t>[A-z\d])$/i", $m['name'], $m2)) {
                    $s->arrival()->name($m2['n'])->terminal($m2['t']);
                } else {
                    $s->arrival()->name($m['name']);
                }
            }

            $extra = implode("\n", $this->http->FindNodes("*[2]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match("/^(\d[hm\d ]+)(?:\n|$)/i", $extra, $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/(?:^|\n)(\d{1,3})[ ]*{$this->opt($this->t('Stop'))}/i", $extra, $m)) {
                $s->extra()->stops($m[1]);
            }

            $xpathNextRow = "following::tr[normalize-space()][1]/descendant-or-self::tr[ *[2] ][1]";

            // HX775 |A330-300 (333)
            $flight = $this->http->FindSingleNode($xpathNextRow . "/*[1]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:[ ]*\||$)/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                $seats = array_filter($this->http->FindNodes("//tr[ *[2][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr[ *[1][{$this->eq([$m['name'] . $m['number'], $m['name'] . ' ' . $m['number']])}] ]/*[2][normalize-space() and normalize-space()!='-']", null, "/^\d+[- ]*[A-Z]$/"));

                if (count($seats) > 0) {
                    $s->extra()->seats($seats);
                }

                $meals = array_filter($this->http->FindNodes("//tr[ *[5][{$this->eq($this->t('Meal'))}] ]/following-sibling::tr[ *[1][{$this->eq([$m['name'] . $m['number'], $m['name'] . ' ' . $m['number']])}] ]/*[5][normalize-space() and normalize-space()!='-']"));

                if (count($meals) > 0) {
                    $s->extra()->meals(array_unique($meals));
                }
            }

            if (preg_match("/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+[ ]*\|[ ]*([^\|]{2,})$/", $flight, $m)) {
                $s->extra()->aircraft($m[1]);
            }

            $cabin = $this->http->FindSingleNode($xpathNextRow . "/*[2]", $root, true, "/^([^\|]+?)(?:[ ]*\||$)/");

            if (preg_match("/^(?<cabin>.{2,}?)\s*\(\s*(?<bookingCode>[A-Z]{1,2})\s*\)$/", $cabin, $m)) {
                // ECONOMY (S)
                $s->extra()->cabin($m['cabin'])->bookingCode($m['bookingCode']);
            } elseif ($cabin) {
                // ECONOMY
                $s->extra()->cabin($cabin);
            }

            $operator = $this->http->FindSingleNode($xpathNextRow . "/following::tr[normalize-space()][1]", $root, true, "/^{$this->opt($this->t('Operated by'))}\s*(.{2,})$/");
            $s->airline()->operator($operator, false, true);
        }

        $travellers = $tickets = $accounts = [];

        $travellerRows = $this->http->XPath->query("//tr[ *[1][{$this->starts($this->t('flightNo'))}] and *[2][{$this->eq($this->t('Seat'))}] ]/ancestor::table[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1][{$this->starts($this->t('Ticket Number'))} or {$this->starts($this->t('Membership Number'))}]");

        foreach ($travellerRows as $tRow) {
            // $travellers[] = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][1]", $tRow, true, "/^{$patterns['travellerName']}$/u");
            //
            // $ticket = $this->http->FindSingleNode("descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Ticket Number'))}] ]/*[normalize-space()][2]", $tRow, true, "/^{$patterns['eTicket']}$/u");
            //
            // if ($ticket) {
            //     $tickets[] = $ticket;
            // }
            //
            // $account = $this->http->FindSingleNode("descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Membership Number'))}] ]/*[normalize-space()][2]", $tRow, true, "/^[-A-Z\d]{5,}$/u");
            //
            // if ($account) {
            //     $accounts[] = $account;
            // }

            $traveller = preg_replace("/^(?:Mrs|Mr|Ms)\.?/", "", $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][1]", $tRow, true, "/^{$patterns['travellerName']}$/u"));

            if (!empty($traveller) && !in_array($traveller, $travellers)) {
                $travellers[] = $traveller;
                $f->general()
                    ->traveller($traveller);
            }

            $ticket = $this->http->FindSingleNode("descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Ticket Number'))}] ]/*[normalize-space()][2]", $tRow, true, "/^{$patterns['eTicket']}$/u");

            if ($ticket) {
                // $tickets[] = $ticket;
                $f->issued()->ticket($ticket, false, $traveller);
            }

            $account = $this->http->FindSingleNode("descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Membership Number'))}] ]/*[normalize-space()][2]", $tRow, true, "/^[-A-Z\d]{5,}$/u");

            if ($account && !in_array($account, array_column($f->getAccountNumbers()))) {
                $accounts[] = $account;
                $f->program()->account($account, false, $traveller);
            }
        }

        $xpathTotal = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Grand Total'))}]";
        $totalPrice = $this->http->FindSingleNode("//tr[{$xpathTotal}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // HKD 12,370
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $fareCurrencies = $fareAmounts = [];
            $xpathFare = "count(*[normalize-space()])>1 and *[normalize-space()][1][{$this->eq($this->t('Fare'))}]";
            $fareValues = $this->http->FindNodes("//tr[{$xpathFare}]/*[normalize-space()][position()>1]");
            // it-846634308.eml - lang ja
            if (count($fareValues) === 0) {
                $fareValues = $this->http->FindNodes("//p[{$this->eq($this->t('Fare'))}]/following::table[1]/descendant::tr/descendant::td[last()]");
            }

            foreach ($fareValues as $fareVal) {
                if (preg_match($patternPrice = '/^(?<multiplier>\d{1,3})[ ]*[Xx][ ]*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $fareVal, $m)) {
                    // 2xHKD 3,662
                    $fareCurrencies[] = $m['currency'];
                    $fareAmounts = array_merge($fareAmounts, array_fill(0, $m['multiplier'], PriceHelper::parse($m['amount'], $currencyCode)));
                }
            }

            if (count(array_unique($fareCurrencies)) === 1 && $fareCurrencies[0] === $matches['currency']) {
                $f->price()->cost(array_sum($fareAmounts));
            }

            $feesRows = $this->http->XPath->query(
                "//tr[ not(.//tr) and count(*[normalize-space()])>1 and preceding::tr[{$xpathFare}] and following::*[{$this->eq($this->t('Add-ons'))}] ]"
                . " | //tr[ not(.//tr) and count(*[normalize-space()])>1 and preceding::*[{$this->eq($this->t('Add-ons'))}] and following::*[{$this->eq($this->t('Taxes, Fees and Charges'))}] ]"
                . " | //tr[ not(.//tr) and count(*[normalize-space()])>1 and preceding::*[{$this->eq($this->t('Taxes, Fees and Charges'))}] and following::tr[{$xpathTotal}] ]"
            );

            foreach ($feesRows as $fRow) {
                $feeCurrencies = $feeAmounts = [];
                $feeValues = $this->http->FindNodes("*[normalize-space()][position()>1]", $fRow);

                foreach ($feeValues as $feeVal) {
                    if (preg_match($patternPrice, $feeVal, $m)) {
                        // 2xHKD 3,662
                        $feeCurrencies[] = $m['currency'];
                        $feeAmounts = array_merge($feeAmounts, array_fill(0, $m['multiplier'], PriceHelper::parse($m['amount'], $currencyCode)));
                    }
                }

                if (count(array_unique($feeCurrencies)) === 1 && $feeCurrencies[0] === $matches['currency']) {
                    $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $fRow);
                    $f->price()->fee($feeName, array_sum($feeAmounts));
                }
            }
        }

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['orderNumber']) || empty($phrases['direction'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->eq($phrases['orderNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($phrases['direction'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
            "#^(\d{4})\S(\d{2})\S(\d{2})\D+$#u", //2025年02月17日 月曜日
        ];
        $out = [
            "$2 $1 $3, $4",
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
