<?php

namespace AwardWallet\Engine\go\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "go/it-82619854.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Flight No.' => ['Flight No.'],
            'Arrival'    => ['Arrival'],
        ],
    ];

    private $detectors = [
        'en' => ['Here is your flight information'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mokuleleairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'Your Mokulele Airlines Itinerary') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Mokulele Airlines") or contains(.,"@mokuleleairlines.com") or contains(.,"www.MokuleleAirlines.com") or contains(.,"www.mokuleleairlines.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourItinerary' . ucfirst($this->lang));

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

    private function parseFlight(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Confirmation'))}]/following-sibling::h1[normalize-space()]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//node()[ {$this->eq($this->t('Confirmation'))} and following-sibling::h1[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathThead = "not(.//tr) and *[1][{$this->starts($this->t('Flight No.'))}] and *[2][{$this->starts($this->t('Departure'))}] and *[3][{$this->starts($this->t('Arrival'))}]";

        $segments = $this->http->XPath->query("//tr[{$xpathThead}]/ancestor::thead[1]/following-sibling::tbody/tr[count(*[normalize-space()])=3]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateValue = $this->http->FindSingleNode("ancestor::tbody[1]/preceding-sibling::thead/tr[{$xpathThead}]/preceding-sibling::tr[normalize-space()]", $root);
            $date = strtotime($dateValue);

            $flight = $this->http->FindSingleNode('*[1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $departure = $this->http->FindSingleNode('*[2]', $root);

            if (preg_match("/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $departure, $m)) {
                $s->departure()->name($m['name'])->code($m['code']);
            }

            $arrival = $this->http->FindSingleNode('*[3]', $root);

            if (preg_match("/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $arrival, $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);
            }

            $timeDep = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]/*[2]', $root, true, "/^{$patterns['time']}$/");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            $timeArr = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]/*[3]', $root, true, "/^{$patterns['time']}$/");

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }
        }

        $travellers = array_filter($this->http->FindNodes("//tr[ not(.//tr) and *[1][{$this->starts($this->t('Name'))}] and *[2][{$this->starts($this->t('Type'))}] ]/ancestor::thead[1]/following-sibling::tbody/tr[normalize-space() and count(*)=2]/*[1]", null, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u"));
        $f->general()->travellers($travellers);

        $xpathPayment = "//tr[ not(.//tr) and *[1][{$this->starts($this->t('Form of Payment'))}] and *[2][{$this->starts($this->t('Amount'))}] ]/ancestor::thead[1]/following-sibling::tbody/tr[count(*)=2 and *[2][normalize-space()]]";

        $totalPrice = $this->http->FindSingleNode($xpathPayment . "[last()][ *[1][{$this->eq($this->t('Total'))}] ]/*[2]");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // 136.58
            $f->price()->total($this->normalizeAmount($m['amount']));
        }

        $ticketStatuses = [];

        $ticketRows = $this->http->XPath->query("//tr[ not(.//tr) and *[1][{$this->starts($this->t('Special Service Requests'))}] and *[2] and *[3] ]/ancestor::thead[1]/following-sibling::tbody/tr[count(*[normalize-space()])=3]");

        foreach ($ticketRows as $tRow) {
            $ticketNumber = $this->http->FindSingleNode("*[3]", $tRow, true, "/^[.\s]*(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?[A-Z\d]{1,3})$/");

            if ($ticketNumber) {
                $f->issued()->ticket($ticketNumber, false);

                if (($ticketStatus = $this->http->FindSingleNode("*[2]", $tRow))) {
                    $ticketStatuses[] = $ticketStatus;
                }
            }
        }

        if (count(array_unique($ticketStatuses)) === 1) {
            $f->general()->status(array_shift($ticketStatuses));
        }
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
            if (!is_string($lang) || empty($phrases['Flight No.']) || empty($phrases['Arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Flight No.'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Arrival'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
}
