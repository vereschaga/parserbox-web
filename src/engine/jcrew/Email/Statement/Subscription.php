<?php

namespace AwardWallet\Engine\jcrew\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "jcrew/statements/it-109857624.eml, jcrew/statements/it-110200924.eml, jcrew/statements/it-109583900.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.jcrew.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".jcrew.com/") or contains(@href,"email.jcrew.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"factory.jcrew.com") or contains(.,"@email.jcrew.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1 || $this->isJunk();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->isJunk() === true) {
            $email->setIsJunk(true);

            return $email;
        }

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);

            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(),\"you're just\")]", $root, true, "/^({$patterns['travellerName']})[,\s]+you're just/u");

            if (preg_match("/\bMember\b/i", $name)) {
                // it-109857624.eml
                $name = null;
            }

            if ($name) {
                $st->addProperty('Name', $name);
            }

            $untilNextReward = $this->http->FindSingleNode("//text()[contains(normalize-space(),'points away from a reward')]", $root, true, "/\b(\d[,.\'\d ]*)points away from a reward/i");
            $st->addProperty('UntilNextReward', PriceHelper::parse($untilNextReward));

            $balance = $this->http->FindSingleNode(".", $root, true, "/[:\s]+(\d[,.\'\d ]*)$/");
            $st->setBalance(PriceHelper::parse($balance));
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[starts-with(normalize-space(),'YOUR POINTS:')]");
    }

    private function isJunk(): bool
    {
        // it-109583900.eml
        return $this->http->FindSingleNode("//*[ preceding-sibling::comment()[normalize-space()='Rewards / Points Balance banner'] and following-sibling::comment()[contains(normalize-space(),'CONTENT // START')] ]", null, true) === '';
    }
}
