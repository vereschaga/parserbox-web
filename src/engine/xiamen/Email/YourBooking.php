<?php

namespace AwardWallet\Engine\xiamen\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "xiamen/it-651855417.eml, xiamen/it-654861064.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Booking reference:', 'Booking reference :'],
        ],

        'fr' => [
            'confNumber'            => ['Numéro de réservation：'],
            'Flight∆'               => ['vol∆'],
            'Booking class'         => ['Cabine：'],
            'Seat'                  => 'Numéro de siège',
            'Passport ID'           => 'Numéro de pièce d’identité',
            'Frequent flyer number' => 'Numéro de passager fréquent',
            'Ticket No.'            => 'Numéro de billet',
        ],
    ];

    private $subjects = [
        'en' => ['E-ticket Issued Successfully', 'Order booking success'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]xiamenair\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true && strpos($headers['subject'], 'Xiamen Airlines') === false) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".xiamenair.com/") or contains(@href,"s.xiamenair.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Your booking on Xiamen Airlines") or contains(normalize-space(),"Thank you for your booking on Xiamen Airlines")]')->length === 0
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
        $email->setType('YourBooking' . ucfirst($this->lang));

        $xpathYear = 'contains(translate(.,"0123456789","∆∆∆∆∆∆∆∆∆∆"),"∆∆∆∆")';
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆")';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $segments = $this->http->XPath->query("//*[../self::tr and {$xpathTime}]/ancestor::*[ tr[normalize-space()][2] ][1][not(contains(normalize-space(), 'Base'))]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Flight∆'), "translate(normalize-space(),'0123456789 ','∆∆∆∆∆∆∆∆∆∆')")}] and *[normalize-space()][2][{$xpathYear}] ][1]/*[normalize-space()][2]", $root)));

            // MF826    789    Booking class:I    11h40m
            $segHeader = implode("\n", $this->http->FindNodes("ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:\n|$)/", $segHeader, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/^{$this->opt($this->t('Booking class'))}[:\s]*([A-Z]{1,2})$/m", $segHeader, $m)) {
                $s->extra()->bookingCode($m[1]);
            }

            if (preg_match("/\n(\d[\d hm]+)$/i", $segHeader, $m)) {
                $s->extra()->duration($m[1]);
            }

            $timeDep = $this->http->FindSingleNode("tr[normalize-space()][1]/*[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode("tr[normalize-space()][2]/*[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $airportDep = $this->http->FindSingleNode("tr[normalize-space()][1]/*[normalize-space()][2]", $root);
            $airportArr = $this->http->FindSingleNode("tr[normalize-space()][2]/*[normalize-space()][2]", $root);

            /*
                (XMN)Xiamen Gaoqi International T3
                (LAX)Los Angeles International B
                (HAN)Hanoi Noi Bai International --
            */
            $patterns['codeNameTerminal'] = '/^[(\s]*(?<code>[A-Z]{3})[\s)]+(?<name>.{2,}?)\s+(?:(?<terminal>[A-Z\d]{1,2})|--)$/';

            if (preg_match($patterns['codeNameTerminal'], $airportDep, $m)) {
                $s->departure()->code($m['code'])->name($m['name']);

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            } else {
                $s->departure()->name($airportDep)->noCode();
            }

            if (preg_match($patterns['codeNameTerminal'], $airportArr, $m)) {
                $s->arrival()->code($m['code'])->name($m['name']);

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            } else {
                $s->arrival()->name($airportArr)->noCode();
            }

            if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                $seats = array_filter($this->http->FindNodes("//tr[ *[2][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr[ *[1][{$this->eq([$s->getDepName() . '-' . $s->getArrName()])}] ]/*[2]", null, "/^\d+[A-z]$/"));

                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t($seat))}]/ancestor::table[1]/preceding::text()[contains(normalize-space(), '(')][1]", null, true, "/^(.+)\(/");

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, true, true, $pax);
                    } else {
                        $s->extra()
                           ->seat($seat);
                    }
                }
            }
        }

        $travellers = $ffNumbers = [];
        $passengerBlocks = $this->http->XPath->query("//*[ count(node()[normalize-space()])=3 and node()[normalize-space()][2][{$this->starts($this->t('Passport ID'))}] and node()[normalize-space()][3][{$this->starts($this->t('Frequent flyer number'))}] ]");

        foreach ($passengerBlocks as $pBlock) {
            $traveller = $this->http->FindSingleNode("node()[normalize-space()][1]", $pBlock, true, "/^({$patterns['travellerName']})(?:\s*\([^)(]*\))?$/u");

            if ($traveller) {
                $travellers[] = $traveller;
            }

            $ffNumber = $this->http->FindSingleNode("node()[normalize-space()][position()=2 or position()=3][{$this->starts($this->t('Frequent flyer number'))}]", $pBlock, true, "/^{$this->opt($this->t('Frequent flyer number'))}[:\s]+([-A-Z\d ]{5,})$/");

            if ($ffNumber) {
                $ffNumbers[] = $ffNumber;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        foreach (array_filter($ffNumbers) as $ffNumber) {
            $pax = $this->http->FindSingleNode("//text()[{$this->starts('Frequent flyer number:' . $ffNumber)}]/preceding::text()[contains(normalize-space(), '(')][1]", null, true, "/^(.+)\(/");

            if (!empty($pax)) {
                $f->addAccountNumber($ffNumber, false, $pax);
            } else {
                $f->addAccountNumber($ffNumber, false);
            }
        }

        $tickets = array_unique(array_filter($this->http->FindNodes("//tr[ *[3][{$this->eq($this->t('Ticket No.'))}] ]/following-sibling::tr[normalize-space()]/*[3]", null, "/^{$patterns['eTicket']}$/")));

        foreach ($tickets as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t($ticket))}]/ancestor::table[1]/preceding::text()[contains(normalize-space(), '(')][1]", null, true, "/^(.+)\(/");

            if (!empty($pax)) {
                $f->addTicketNumber($ticket, false, $pax);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $xpathBaseFare = "{$this->starts($this->t('Base fare'))} and {$this->eq($this->t('∆asefare(∆∆∆)'), 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")')}";
        $xpathTotalPrice = "{$this->starts($this->t('Total'))} and {$this->eq($this->t('∆otal(∆∆∆)'), 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")')}";

        $currency = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$xpathTotalPrice}]", null, true, "/^{$this->opt($this->t('Total'))}\s*\(\s*([^)(]*?)\s*\)$/");
        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathTotalPrice}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^\d[,.‘\'\d ]*$/u', $totalPrice)) {
            // 2712.4
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($totalPrice, $currencyCode));

            $costName = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$xpathBaseFare}]");
            $costValue = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathBaseFare}] ]/*[normalize-space()][2]");

            if (preg_match("/^(?<amount>\d[,.‘\'\d ]*)(?:\s*[Xx]\s*(?<multiplier>\d{1,3}))?$/u", $costValue, $m)) {
                $costAmount = $m['amount'];
                $costMultiplier = empty($m['multiplier']) ? '1' : $m['multiplier'];
            } else {
                $costAmount = null;
                $costMultiplier = '1';
            }

            if ($costAmount !== null && preg_match($pattern = "/^(?<name>.{2,}?)\s*\(\s*(?<currency>[^)(]*?)\s*\)$/", $costName, $m)
                && (empty($m['currency']) || !empty($m['currency']) && $m['currency'] === $currency)
            ) {
                $f->price()->cost(PriceHelper::parse($costAmount, $currencyCode) * $costMultiplier);
            }

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathBaseFare}]] and following-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathTotalPrice}]] ]");

            foreach ($feeRows as $fRow) {
                $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $fRow);
                $feeValue = $this->http->FindSingleNode("*[normalize-space()][2]", $fRow);

                if (preg_match("/^(?<amount>\d[,.‘\'\d ]*)(?:\s*[Xx]\s*(?<multiplier>\d{1,3}))?$/u", $feeValue, $m)) {
                    $feeAmount = $m['amount'];
                    $feeMultiplier = empty($m['multiplier']) ? '1' : $m['multiplier'];
                } else {
                    $feeAmount = null;
                    $feeMultiplier = '1';
                }

                if ($feeAmount !== null && preg_match($pattern, $feeName, $m)
                    && (empty($m['currency']) || !empty($m['currency']) && $m['currency'] === $currency)
                ) {
                    $f->price()->fee($m['name'], PriceHelper::parse($feeAmount, $currencyCode) * $feeMultiplier);
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
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 22/ April/ 2024
            '/^(\d{1,2})[-\/\s]+([[:alpha:]]+)[-\/\s]+(\d{2,4})$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $str = preg_replace($in, $out, $text);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
