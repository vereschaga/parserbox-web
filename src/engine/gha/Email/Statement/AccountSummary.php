<?php

namespace AwardWallet\Engine\gha\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountSummary extends \TAccountChecker
{
    public $mailFiles = "gha/statements/it-469288788.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'level'   => ['LEVEL', 'Level'],
            'balance' => ['BALANCE', 'Balance'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]ghadiscovery\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return preg_match('/\bYour(?: [[:alpha:]]+)? Account Summary\b/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".ghadiscovery.com/") or contains(@href,"email.ghadiscovery.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Global Hotel Alliance. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $email->setType('AccountSummary');
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("*[normalize-space()][1]/descendant::tr[{$this->eq($this->t('level'))}]/preceding-sibling::tr[normalize-space()]", $root, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $st->addProperty('Name', $name);

        $status = $this->http->FindSingleNode("*[normalize-space()][1]/descendant::tr[{$this->eq($this->t('level'))}]/following-sibling::tr[normalize-space()][1]", $root, true, "/^(SILVER|RED|BLACK|TITANIUM|PLATINUM|GOLD)$/i");
        $st->addProperty('Status', $status);

        $statusExpires = $this->http->FindSingleNode("*[normalize-space()][1]/descendant::tr[{$this->eq($this->t('level'))}]/following-sibling::tr[normalize-space()][2]", $root, true, "/^Expires on\s+(\d{1,2}[-\s]+[[:alpha:]]+[-\s]+\d{2,4})$/i");
        $st->addProperty('StatusExpires', $statusExpires);

        $number = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::tr[{$this->eq($this->t('balance'))}]/preceding-sibling::tr[normalize-space()]", $root, true, "/^[# ]*([A-z\d]{5,})$/");
        $st->setNumber($number);

        $balance = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::tr[{$this->eq($this->t('balance'))}]/following-sibling::tr[normalize-space()][1]", $root, true, "/^D\s*[$]\s*(\d[,.\'\d ]*)$/i");
        $st->setBalance($balance);

        $balanceExpiring = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::tr[{$this->eq($this->t('balance'))}]/following-sibling::tr[normalize-space()][2]", $root, true, "/^D\s*[$]\s*(\d[,.\'\d ]*?)\s+Expiring Balance$/i");
        $st->addProperty('ExpiringBalance', $balanceExpiring);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::tr[{$this->eq($this->t('level'))}] and *[normalize-space()][2]/descendant::tr[{$this->eq($this->t('balance'))}] ]");
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
