<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1 extends \TAccountCheckerExtended
{
    public $mailFiles = "porter/it-200458534.eml, porter/it-210661943-fr.eml, porter/it-2796908.eml, porter/it-31061146.eml, porter/it-4303553.eml, porter/it-4321272.eml, porter/it-468772318.eml, porter/it-4715862.eml, porter/it-4715882.eml, porter/it-4715884.eml, porter/it-4814198.eml, porter/it-590952953.eml";
    public $lang = '';
    public static $dictionary = [
        'fr' => [
            'confNumber'     => ['Porter numéro de confirmation'],
            'Booking status' => 'État de la réservation',
            'Passenger'      => 'Passager',
            'Duration'       => 'Durée',
            'Seats'          => 'Sièges',
            'totalPrice'     => 'Total',
            'cost'           => 'Frais de transport aérien',
            'taxes'          => 'Impôts, taxes et redevances',
        ],
        'en' => [
            'confNumber' => ['Porter confirmation number', 'Porter Confirmation number'],
            'Passenger'  => ['Passenger', 'PASSENGERS'],
            'totalPrice' => 'Total Fare Price',
            'cost'       => 'Air transportation charges',
            'taxes'      => 'Taxes, fees and charges',
        ],
    ];
    private $subjects = [
        'fr' => ['Itinéraire Porter'],
        'en' => ['Porter Airlines Itinerary', 'Porter Airlines is proud to be rated'],
    ];
    private $detectors = [
        'fr' => ['vous remercions de voyager avec Porter'],
        'en' => ['you for flying Porter', 'Porter Airlines is proud to be rated'],
    ];

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $status = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking status'))}]/..", null, true, "/{$this->opt($this->t('Booking status'))}\s*:(.*?){$this->opt($this->t('confNumber'))}/i"));

        if (empty($status)) {
            $status = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking status'))}]/..", null, true, "/{$this->opt($this->t('Booking status'))}\s*:(.*?)$/i"));
        }

        if (empty($status)) {
            $status = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking status'))}]/..", null, true, "/{$this->opt($this->t('Booking status'))}\s*:(.*?)$/i"));
        }

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Trip'))}]/preceding::text()[{$this->contains($this->t('Booking status'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking status'))}\s*:\s*(\w+)/i");
        }

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking has been'))}]", null, true, "/{$this->opt($this->t('Your booking has been'))}\s*(\w+)/i");

            if (stripos($status, 'cancelled') !== false) {
                $f->general()
                    ->cancelled();
            }
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/'))
            ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Passenger'))}]/../ancestor-or-self::tr[1]/following-sibling::tr[normalize-space() and not(contains(.,'VIPorter') or contains(.,'special') or contains(normalize-space(),'Free Cabin') or contains(normalize-space(),'Carriage is'))]/td[1][not(.//a) and not(ancestor-or-self::*[{$this->contains(['#c4e5f6', '#C4E5F6'], '@style')}])]", null, "/^{$patterns['travellerName']}$/u"))))
            ->status($status);

        $accounts = array_filter(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'VIPorter')]", null, "/VIPorter\D*\s+(\d{5,})/")));

        if (count($accounts) > 0) {
            $f->program()
                ->accounts($accounts, false);
        }

        $url = $this->http->FindSingleNode("//img[contains(@alt, 'click on See Complete Receipt or visit My Bookings')]/ancestor::a[1]/@href");

        if (!empty($url)) {
            $http1 = clone $this->http;

            $headers = [
                'authority'                 => 'www.flyporter.com',
                'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0',
                'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'upgrade-insecure-requests' => '1',
                'Referer'                   => $url,
            ];

            $http1->GetURL($url, $headers);

            $totalPrice = $this->getTotal(
                $this->re("/^(.+\n.+)/", implode("\n", $http1->FindNodes("//td[normalize-space()='Total cost']/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"))));

            if (!empty($totalPrice['currencySign'])) {
                $f->price()
                    ->currency($totalPrice['currency'])
                    ->total($totalPrice['amount']);

                $costs = $http1->FindNodes("//td[normalize-space()='Taxes, Fees and Charges']/preceding::td[not(.//td)][normalize-space()][position() < 6][contains(., '(') and contains(normalize-space(), ' to ') and contains(normalize-space(), ':')]/following-sibling::td",
                    null, "/^\D*(\d[\d ,.]*?)\D*$/");
                $cost = 0.0;

                foreach ($costs as $costText) {
                    $cost += $this->getTotal($costText)['amount'];
                }
                $f->price()
                    ->cost($cost);

                $discounts = $http1->FindNodes("//td[normalize-space()='Discount']/preceding::td[not(.//td)][normalize-space()][position() < 6][contains(., '(') = 2]/following-sibling::td[normalize-space()]",
                    null, "/^\D*(\d[\d ,.]*?)\D*$/");
                $discount = 0.0;

                foreach ($discounts as $discountText) {
                    $discount += $this->getTotal($discountText)['amount'];
                }
                $f->price()
                    ->discount($discount);

                $feesNodes = $http1->XPath->query("//tr[contains(@class, 'booking-receipt__breakdown')]");
                $fees = [];

                foreach ($feesNodes as $feesRoot) {
                    $name = trim($http1->FindSingleNode("td[normalize-space()][1]", $feesRoot));
                    $amount = $this->getTotal($http1->FindSingleNode("td[normalize-space()][2]", $feesRoot))['amount'];

                    if (count($http1->FindNodes("td[normalize-space()]", $feesRoot)) === 3) {
                        $name = trim($http1->FindSingleNode("td[normalize-space()][2]", $feesRoot));
                        $amount = $this->getTotal($http1->FindSingleNode("td[normalize-space()][3]", $feesRoot))['amount'];
                    }

                    if (isset($fees[$name])) {
                        $fees[$name] += $amount;
                    } else {
                        $fees[$name] = $amount;
                    }
                }

                foreach ($fees as $name => $amount) {
                    $f->price()
                        ->fee($name, $amount);
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (!$f->getPrice()
            && preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)\s*(?<currencyCode>[A-Z]{3})?$/u', $totalPrice, $matches)
        ) {
            // $620.00 CAD    |    $234.26
            $currency = empty($matches['currencyCode']) ? $matches['currency'] : $matches['currencyCode'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? null : $currency;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('cost'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')$/u', $baseFare, $m)
                || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)
            ) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('taxes'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')$/u', $taxes, $m)
                || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
            ) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';
        $segments = $this->http->XPath->query($xpath = "//tr[ count(*[{$xpathTime}])=2 or *[normalize-space()][4][{$this->starts($this->t('Duration'))}] ]");

        if ($segments->length === 0) {
            $this->logger->debug('segments roots not found: ' . $xpath);
        }

        $date = null;

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('*[1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $depCode = trim($this->http->FindSingleNode("./td[normalize-space()][2]", $root, true, "#(?:AM|PM|\()([A-Z]{3})#"));

            if (empty($depCode)) {
                $depCode = trim($this->http->FindSingleNode('./preceding-sibling::tr[2]/td//tr[2]', $root, false, '/([A-Z]{3})\s+to/'));
            }

            $dateTmp = $this->http->FindSingleNode('preceding-sibling::tr[2]/descendant::text()[normalize-space()][1]', $root, false, '/\d+[.\s]*[[:alpha:]]+[.\s]*\d{4}/u');

            if (preg_match("/^\s*[A-z]\s*$/", $dateTmp) || empty($dateTmp)) {
                $dateTmp = $this->http->FindSingleNode('preceding-sibling::tr[2]/descendant::text()[normalize-space()][2]', $root, false, '/\d+[.\s]*[[:alpha:]]+[.\s]*\d{4}/u');
            }

            if (!empty($dateTmp)) {
                $date = strtotime($this->normalizeDate($dateTmp));
            }

            $node = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, true, "/{$patterns['time']}/");

            $s->departure()
                ->date(strtotime($node, $date))
                ->code($depCode);

            $depTerminal = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space()!=''][last()][contains(.,'Terminal')]",
                $root, false, "#Terminal\s*(.+)#");

            if (empty($depTerminal)) {
                $depTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::td[2]",
                    $root, false, "#Terminal\s*(.+)#");
            }

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            // 5 Apr 2016 5:00 AM Day+1
            $node = $this->http->FindSingleNode("./td[normalize-space()][3]/descendant::text()[normalize-space()!=''][1]", $root, true, "/{$patterns['time']}/");

            $arrCode = trim($this->http->FindSingleNode("./td[normalize-space()][3]", $root, true, "#(?:AM|PM|\()([A-Z]{3})#"));

            if (empty($arrCode)) {
                $arrCode = trim($this->http->FindSingleNode("./td[normalize-space()][3]", $root, true, "#\s*day\s*[+]\d([A-Z]{3})#i"));
            }

            if (empty($arrCode)) {
                $arrCode = trim($this->http->FindSingleNode('./preceding-sibling::tr[2]/td//tr[normalize-space()][2]', $root, false, '/\s+to\s+([A-Z]{3})/'));
            }

            $arrDate = strtotime($node, $date);

            if ($s->getDepDate() > $arrDate) {
                $date = strtotime('+1 day', $date);
                $arrDate = strtotime($node, $date);
            }

            $s->arrival()
                ->date($arrDate)
                ->code($arrCode);

            $arrTerminal = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][last()][contains(.,'Terminal')]",
                $root, false, "#Terminal\s*(.+)#");

            if (empty($arrTerminal)) {
                $arrTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::td[4]",
                    $root, false, "#Terminal\s*(.+)#u");
            }

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $lastColumn = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));
            $duration = $this->re("/{$this->opt($this->t('Duration'))}\s*:\n*(\s*\d.+)/", $lastColumn);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $seats = array_filter(explode(', ', $this->re("/{$this->opt($this->t('Seats'))}\s*[:]+\s*(.+)/", $lastColumn)));

            if (count($seats) == 0) {
                $seats = array_filter(explode(', ', trim($this->http->FindSingleNode("*[normalize-space()][4]/following::tr[1]", $root, true, "/{$this->opt($this->t('Seats'))}\s*[:]+\s*(.+)/u"))));
            }

            if (count($seats) == 0) {
                $seats = array_filter(explode(', ', trim($this->http->FindSingleNode("following::tr[1]/descendant::td[last()]", $root, true, "/{$this->opt($this->t('Seats'))}\s*[:]+\s*(.+)/u"))));
            }

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyporter.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".flyporter.com/") or contains(@href,"www.flyporter.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        $this->assignLang();
        $this->ParseFlight($email);
        $email->setType('It1' . ucfirst($this->lang));

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

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null, 'currencySign' => null];

        // $232.83 USD
        if (preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*?)\s*(?<currency>)\s*$#", $text, $m)
            || preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*?)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
        ) {
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency'], 'currencySign' => $m['currency']];
        }

        return $result;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[-,.\s]*([[:alpha:]]{3,})[-,.\s]*(\d{4})$/u', $text, $m)) {
            // 8 Apr 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
