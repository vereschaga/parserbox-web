<?php

namespace AwardWallet\Engine\walgreens\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class InRewards extends \TAccountChecker
{
    public $mailFiles = "walgreens/statements/it-65806820.eml, walgreens/statements/it-107807522.eml";
    public $subjects = [
        '/^Reminder\! Get \$5 in rewards when you shop this month\!$/',
        '/^Game on! Get $5 in rewards when you shop 2 times in/',
        '/^Ready to save: \$5 in rewards only for you$/',
        '/^Reminder\! Get \$5 in rewards when you shop this month\!$/',
        '/^\w+\, you have a \$1 reward\! Your \w+ e-statement is here\.$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'completeDetailsAt' => [
                'Complete details at Walgreens.com/Balance',
                'See complete details and points balance at Walgreens.com/Balance',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@e.walgreens.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Walgreen')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Offer details'))}]")->count() > 0
            && ($this->isMembership() || $this->isJunk());
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.walgreens\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->isMembership() || $this->detectEmailByHeaders($parser->getHeaders())) {
            $balance = null;

            $st = $email->add()->statement();

            $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Your reward available:') or starts-with(normalize-space(),'Your Reward:')]", null, true, "/[$]\s*(\d[,.\'\d ]*)$/");

            if ($balance !== null) {
                $st->setBalance($this->normalizeAmount($balance));
            } else {
                $st->setMembership(true);
            }
        } elseif ($this->isJunk()) {
            $email->setIsJunk(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//*[{$this->contains($this->t('completeDetailsAt'))}]")->length > 0;
    }

    private function isJunk(): bool
    {
        // it-107807522.eml
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Your reward available:') or contains(normalize-space(),'Your Reward:')]")->length === 0
            && $this->http->XPath->query("//text()[contains(normalize-space(),'Contacts')]/following::text()[normalize-space()][1][contains(normalize-space(),'Shop')]")->length === 0
            && $this->http->XPath->query("//text()[normalize-space()='Preferences']/following::*[normalize-space()][1][contains(normalize-space(),'Was this forwarded from a friend? Click here to join the fun and officially subscribe. You are receiving this email because')]")->length > 0
        ;
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
}
