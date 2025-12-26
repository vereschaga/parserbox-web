<?php

namespace AwardWallet\Engine\tradewind\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "tradewind/it-259377147.eml, tradewind/it-767461370.eml, tradewind/it-767461372.eml, tradewind/it-767461750.eml";

    public $lang = '';

    public static $providers = [
        "tradewind" => ['flytradewind', 'Tradewind'],
        "airpeace"  => ['flyairpeace', 'Air Peace'],
    ];

    public $emails = ["@flyairpeace.com", "@flytradewind.com"];

    public static $dictionary = [
        'en' => [
            'Flight'       => ['Flight'],
            'Arrive'       => ['Arrive'],
            'confNumber'   => ['Booking Reference', 'Booking Reference:', 'Booking Reference :'],
            'provLink'     => ['www.flytradewind.com', 'booking.flytradewind.com', '.flytradewind.com', 'airpeace.com'],
            'detectPhrase' => ['Thank you for booking with Tradewind', 'Thank you for booking with Air Peace', 'Please review your AIR PEACE flight details below.'],
        ],
    ];

    private $subjects = [
        'en' => ['Flight Itinerary', 'Your reservation Info'],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->emails as $email) {
            if (stripos($from, $email) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->emails as $email) {
            if (isset($headers['from']) && stripos($headers['from'], $email) !== false) {
                foreach ($this->subjects as $phrases) {
                    foreach ($phrases as $phrase) {
                        if (stripos($headers['subject'], $phrase) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('provLink'), '@href')}]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('detectPhrase'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (!empty($providerCode = $this->getProviderCode())) {
            $email->setProviderCode($providerCode);
        }

        $email->setType('FlightItinerary' . ucfirst($this->lang));

        $patterns = [
            'time'           => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName'  => '[[:alpha:]][-.\'’/[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
            'eTicket'        => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellersRows = $this->http->XPath->query("//*[ *[1][{$this->eq($this->t('Passenger'))}] and *[4][{$this->eq($this->t('E-ticket Numbers'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]/descendant-or-self::*[ *[4] ][1]");

        if ($travellersRows->length === 0) {
            $travellersRows = $this->http->XPath->query("//*[ *[1][{$this->eq($this->t('Passenger'))}] and *[2][{$this->eq($this->t('Email Contact'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]/descendant-or-self::*[ *[2] ][1]");
        }

        foreach ($travellersRows as $tRow) {
            $traveller = preg_replace("/(?:MRS|MR|MS|MISS)$/", "", $this->http->FindSingleNode('*[1]', $tRow, true, "#^{$patterns['travellerName']}$#u"))
                ?? $this->http->FindSingleNode('*[1]/tr', $tRow, true, "#^({$patterns['travellerName2']}?)[ ]*(?:MRS|MR)$#u");

            $f->general()
                ->traveller($traveller, true);

            $tickets = $this->http->FindNodes('*[4]/descendant::tr', $tRow, "/^{$patterns['eTicket']}(?:\/\d+)?$/u");

            foreach (array_unique($tickets) as $ticket) {
                $f->issued()
                    ->ticket($ticket, false, $traveller);
            }
        }

        $segments = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('Flight'))}] and *[6][{$this->eq($this->t('Arrive'))}] ]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()]/descendant-or-self::tr[ *[6] ][1]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->http->FindSingleNode('*[1]', $root, true, "/^\d{1,2}\s+[[:alpha:]]+\s+\d{2,4}$/u"));

            $flight = $this->http->FindSingleNode('*[2]', $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $airportDep = $this->http->FindSingleNode('*[3]', $root);
            $airportArr = $this->http->FindSingleNode('*[5]', $root);

            $airportCodes = $this->http->FindNodes("//text()[{$this->contains($flight)}]/ancestor::td[1]/preceding-sibling::td[{$this->contains('Travel Insurance')}]");

            if (isset($airportCodes[0]) && preg_match("/^Travel\s*Insurance\s*(?<depCode>[A-Z]{3})\s*(?<arrCode>[A-Z]{3})$/", $airportCodes[0], $m)) {
                $s->departure()->name($airportDep)->code($m['depCode']);
                $s->arrival()->name($airportArr)->code($m['arrCode']);
            } else {
                $s->departure()->name($airportDep)->noCode();
                $s->arrival()->name($airportArr)->noCode();
            }

            $timeDep = $this->http->FindSingleNode('*[4]', $root, true, "/^{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode('*[6]', $root, true, "/^{$patterns['time']}/");

            if ($timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }
        }

        $xpathPrice = "//tr[ *[1][{$this->eq($this->t('Charges'))}] and *[3][{$this->eq($this->t('Price'))}] ]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()]";
        $totalPrice = $this->http->FindSingleNode($xpathPrice . "/descendant-or-self::tr[ *[1][{$this->eq($this->t('Total'))}] ][1]/*[3]", null, true, '/^\d[,.‘\'\d ]*$/u'); // 1635.90

        if ($totalPrice !== null) {
            $currency = $this->http->FindSingleNode($xpathPrice . "/descendant-or-self::tr[ *[1][{$this->eq($this->t('Total'))}] ][1]/*[2]", null, true, '/^[^\-\d)(]+$/');
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currencyCode ?? $currency)->total(PriceHelper::parse($totalPrice, $currencyCode));

            $fareCurrency = $this->http->FindSingleNode($xpathPrice . "/descendant-or-self::tr[ *[1][{$this->eq($this->t('Fare'))}] ][1]/*[2]", null, true, '/^[^\-\d)(]+$/');
            $fareAmount = $this->http->FindSingleNode($xpathPrice . "/descendant-or-self::tr[ *[1][{$this->eq($this->t('Fare'))}] ][1]/*[3]", null, true, '/^\d[,.‘\'\d ]*$/u');

            if ($fareAmount !== null && $fareCurrency === $currency) {
                $f->price()->cost(PriceHelper::parse($fareAmount, $currencyCode));
            }

            $feeRows = $this->http->XPath->query($xpathPrice . "[ preceding-sibling::tr[descendant-or-self::tr[*[1][{$this->eq($this->t('Fare'))}]]] and following-sibling::tr[descendant-or-self::tr[*[1][{$this->eq($this->t('Total'))}]]] ]");

            foreach ($feeRows as $feeRow) {
                $feeCurrency = $this->http->FindSingleNode("descendant-or-self::tr[ *[3] ][1]/*[2]", $feeRow, true, '/^[^\-\d)(]+$/');
                $feeAmount = $this->http->FindSingleNode("descendant-or-self::tr[ *[3] ][1]/*[3]", $feeRow, true, '/^\d[,.‘\'\d ]*$/u');

                if ($feeAmount !== null && $feeCurrency === $currency) {
                    $feeName = $this->http->FindSingleNode("descendant-or-self::tr[ *[3] ][1]/*[1]", $feeRow);
                    $f->price()->fee($feeName, PriceHelper::parse($feeAmount, $currencyCode));
                }
            }
        }

        return $email;
    }

    public function getProviderCode()
    {
        foreach (self::$providers as $code => $provider) {
            foreach ($provider as $item) {
                if ($this->http->XPath->query("//*[{$this->contains($item)}]")->length > 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
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
            if (!is_string($lang) || empty($phrases['Flight']) || empty($phrases['Arrive'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr/*[{$this->eq($phrases['Flight'])}]/following-sibling::*[{$this->eq($phrases['Arrive'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, string $node = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($node) {
            return "contains({$node}, \"{$s}\")";
        }, $field));
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
}
