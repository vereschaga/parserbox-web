<?php

namespace AwardWallet\Engine\peachavia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "peachavia/it-218774695.eml, peachavia/it-219867177.eml, peachavia/it-424035066.eml, peachavia/it-427117322.eml, peachavia/it-428982017.eml, peachavia/it-432268361.eml, peachavia/it-749306975-zh.eml, peachavia/it-793142235-cancelled.eml, peachavia/it-809749455-ja.eml";

    private $subjects = [
        'en' => ['Your Booking Cancell is Completed', 'Your Booking Cancel is Completed'],
        'zh' => ['您訂購的行程內容', '您订购的行程内容'],
        'ja' => ['予約内容のお知らせ'],
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Booking Reference'],
            'direction'  => ['Out bound', 'In bound', 'stage'],
            'subtotal'   => ['Out bound Subtotal', 'In bound Subtotal', 'stage Subtotal'],

            // 'Booking Date' => '',
            // 'Payment status' => '',
            // 'Advance Seat selection' => '',
            // 'Title' => '',
            // 'Contact Information' => '',
            // 'Name' => '',
            // 'Payment details' => '',
            // 'Other Options' => '',
            // 'Payment Fee' => '',
            // 'Total' => '',

            'cancelledPhrases' => ['Your booking has been cancelled', 'Your booking has been canceled'],
        ],

        'zh' => [
            'confNumber' => ['訂單編號', '订单号'],
            'direction'  => ['去程'],
            'subtotal'   => ['小計', '小计'],

            'Booking Date'           => ['訂購日期', '订购日期'],
            'Payment status'         => ['付款狀況', '付款情况'],
            'Advance Seat selection' => ['指定座位', '預選座位', '座位'],
            'Title'                  => ['性別', '性别'],
            'Contact Information'    => '個人資料',
            'Name'                   => '姓名',
            'Payment details'        => ['費用明細', '费用明细'],
            'Other Options'          => ['其他', '其它'],
            'Payment Fee'            => ['票務手續費', '票务手续费'],
            'Total'                  => ['合計', '合计'],

            // 'cancelledPhrases' => [''],
        ],

        'ja' => [
            'confNumber' => ['予約番号'],
            'direction'  => ['往路'],
            'subtotal'   => ['小計'],

            'Booking Date'           => ['予約日時'],
            'Payment status'         => ['お支払い状況'],
            'Advance Seat selection' => ['座席指定'],
            'Title'                  => ['性別'],
            'Contact Information'    => '連絡先情報',
            'Name'                   => '氏名',
            'Payment details'        => ['お支払明細'],
            'Other Options'          => ['その他'],
            'Payment Fee'            => ['発券手数料'],
            'Total'                  => ['合計'],

            // 'cancelledPhrases' => [''],
        ],
    ];

    // used in parser peachavia/YourReservationPlain
    public static $patterns = [
        /*
            Nagasaki
            2023/07/20 (Thu) 17:00

            or

            Osaka (Kansai)　Terminal 2
            2023/07/20 (Thu) 18:10
        */
        'airport' => '/^\s*(?<nameTerminal>.{2,}?)[ ]*\n+[ ]*(?<dateTime>.*\d.*?)\s*$/',
        // Nagasaki    |    Osaka (Kansai)　Terminal 2
        'nameTerminal-1' => '/^(?<name>.{2,}?\(.{2,}?\))[　 ]+(?<terminal>\b.+)$/u', // symbol code `&#12288;`
        'nameTerminal-2' => '/^(?<name>.{2,}?)(?:[ ]{2,}|[ ]*　[ ]*)(?<terminal>\b.+)$/u', // symbol code `&#12288;`
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@resmail.flypeach.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('subject', $headers) && strpos($headers['subject'], 'Your Peach Reservation') !== false) {
            return true;
        }

        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], '【Peach】') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".flypeach.com/") or contains(@href,"www.flypeach.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"© Peach Aviation")]')->length === 0
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
        $email->setType('YourReservation' . ucfirst($this->lang));

        $this->http->FilterHTML = false; // for protect symbols `&#12288;` between Airport Name and Terminal
        $this->http->SetEmailBody($parser->getHTMLBody());

        $this->parseFlight($email);

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

    /**
     * @param string|null $text Unformatted string with date
     */
    public static function normalizeDate(?string $text): string
    {
        // used in parser peachavia/YourReservationPlain

        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 2022/10/25 (Tue) 14:30
            '/^\s*(\d{4})[- \/]+(\d{1,2})[- \/]+(\d{1,2}) .*\b(\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)\s*$/',
        ];
        $out = [
            '$1-$2-$3 $4',
        ];

        return preg_replace($in, $out, $text);
    }

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('confNumber'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr/*[ {$this->eq($this->t('confNumber'))} and following-sibling::*[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $bookingDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Booking Date'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^.*\d.*$/')));
        $f->general()->date($bookingDate);

        $segments = $this->http->XPath->query("//tr[ *[normalize-space()][1][not(.//tr) and ({$this->starts($this->t('direction'), "translate(normalize-space(),'0123456789','')")})] ][not(preceding::text()[{$this->eq($this->t('Payment status'))}])]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            if (preg_match('/^(?<full>(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:[ ]*\([ ]*(?<aircraft>.*?)[ ]*\))?)$/', $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^(?:\d{1,5}[ ]+)?{$this->opt($this->t('direction'))}\s+(.{2,})$/"), $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                if (!empty($m['aircraft'])) {
                    $s->extra()->aircraft($m['aircraft']);
                }

                $seats = array_filter($this->http->FindNodes("//tr[ *[2][{$this->eq($this->t('Advance Seat selection'))}] ]/following-sibling::tr[ *[1][{$this->eq($m['full'])}] ]/*[2]", null, '/^\d+[A-Z]$/'));

                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->eq($s->getAirlineName() . $s->getFlightNumber())}]/ancestor::tr[1][{$this->contains($seat)}]/preceding::text()[{$this->eq($this->t('Name'))}][1]/ancestor::table[1]", null, true, "/{$this->opt($this->t('Name'))}\s*(.+)/");

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, true, true, $pax);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }
            }

            $xpathAirports = "following::tr[ *[normalize-space()][2] ][1]";

            $dep = $this->htmlToText($this->http->FindHTMLByXpath($xpathAirports . "/*[normalize-space()][1]", null, $root));

            if (preg_match(self::$patterns['airport'], $dep, $m)) {
                if (preg_match(self::$patterns['nameTerminal-1'], $m['nameTerminal'], $m2)
                    || preg_match(self::$patterns['nameTerminal-2'], $m['nameTerminal'], $m2)
                ) {
                    $s->departure()->name($m2['name'])->terminal(preg_replace(['/^Terminal[- ]+/i', '/[- ]+Terminal$/i'], '', $m2['terminal']));
                } else {
                    $s->departure()->name($m['nameTerminal']);
                }
                $s->departure()->date2($this->normalizeDate($m['dateTime']))->noCode();
            }

            $arr = $this->htmlToText($this->http->FindHTMLByXpath($xpathAirports . "/*[normalize-space()][2]", null, $root));

            if (preg_match(self::$patterns['airport'], $arr, $m)) {
                if (preg_match(self::$patterns['nameTerminal-1'], $m['nameTerminal'], $m2)
                    || preg_match(self::$patterns['nameTerminal-2'], $m['nameTerminal'], $m2)
                ) {
                    $s->arrival()->name($m2['name'])->terminal(preg_replace(['/^Terminal[- ]+/i', '/[- ]+Terminal$/i'], '', $m2['terminal']));
                } else {
                    $s->arrival()->name($m['nameTerminal']);
                }
                $s->arrival()->date2($this->normalizeDate($m['dateTime']))->noCode();
            }
        }

        $travellers = $this->http->FindNodes("//tr[ *[4][{$this->starts($this->t('Title'))}] ]/*[3]/descendant::*[ *[normalize-space()][1][{$this->eq($this->t('Name'))}] ]/*[normalize-space()][2]", null, '/^' . self::$patterns['travellerName'] . '$/u');

        if (count($travellers) === 0) {
            $clientName = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Contact Information'))}]/following-sibling::*[{$this->eq($this->t('Name'), "translate(.,':','')")}]/following-sibling::*[normalize-space()]", null, true, '/^' . self::$patterns['travellerName'] . '$/u');
            $travellers = [$clientName];
        }

        $f->general()->travellers($travellers, true);

        if ($this->http->XPath->query("//*[{$this->starts($this->t('cancelledPhrases'))}]")->length > 0) {
            $f->general()->cancelled();

            return;
        }

        $totalPrice = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Payment status'))}]/following::tr[{$this->eq($this->t('Payment details'))}]/following::tr[{$this->eq($this->t('Other Options'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // ￥147,000
            $currencyCode = $this->normalizeCurrency($matches['currency']);
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $subtotalAmounts = [];
            $subtotalValues = $this->http->FindNodes("//tr[{$this->eq($this->t('Payment status'))}]/following::tr[{$this->eq($this->t('Payment details'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('subtotal'), "translate(normalize-space(),'0123456789','')")}] ]/*[normalize-space()][2]");

            foreach ($subtotalValues as $subtotalVal) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $subtotalVal, $m)
                    && is_numeric($stAmount = PriceHelper::parse($m['amount'], $currencyCode))
                ) {
                    $subtotalAmounts[] = $stAmount;
                } else {
                    $subtotalAmounts = [];

                    break;
                }
            }

            if (count($subtotalAmounts) > 0) {
                $f->price()->cost(array_sum($subtotalAmounts));
            }

            $fee = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Payment status'))}]/following::tr[{$this->eq($this->t('Payment details'))}]/following::tr[{$this->eq($this->t('Other Options'))}]/following::tr[{$this->eq($this->t('Payment Fee'))}]/following-sibling::tr[normalize-space()]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $fee, $m)) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['direction'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['direction'])}]")->length > 0
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
            '￥'         => 'CNY',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return $s;
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
