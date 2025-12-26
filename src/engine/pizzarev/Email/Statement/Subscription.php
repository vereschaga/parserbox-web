<?php

namespace AwardWallet\Engine\pizzarev\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "pizzarev/statements/it-82266063.eml, pizzarev/statements/it-82267454.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@pizzarev.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".pizzarev.com/") or contains(@href,".pizzarev.com%2F") or contains(@href,"www.pizzarev.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Cheers! PizzaRev") or contains(normalize-space(),"Thanks, PizzaRev") or contains(normalize-space(),"Happy Eating! PizzaRev")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $st = $email->add()->statement();

        $name = null;

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hi ')]", null, true, "/^Hi\s+({$patterns['travellerName']})(?:[ ]*[,;:!?]|$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($name) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        $singlePhrases = [
            'Your one-time Rev Rewards redemption code',
            'Your Dog Days BOGO Pizza is about to expire in',
            'A redeemable has been added to your Rev Rewards account',
            'A redeemable has been added to your account', 'A redeemable has been added to youraccount',
            'redeemable has been added to your account and expires',
            'To redeem your reward, simply login to the PizzaRev app, tap',
        ];

        return $this->http->XPath->query("//*[{$this->contains($singlePhrases)} or (contains(normalize-space(),'check the Rewards tab for our gift to you') and contains(normalize-space(),'Your gift will expire in'))]")->length > 0
            || $this->http->FindSingleNode("//text()[contains(normalize-space(),'earning')]", null, true, "/You banked.* for earning\s*\d+\s*point/") !== null;
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
}
