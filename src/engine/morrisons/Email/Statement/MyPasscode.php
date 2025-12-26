<?php

namespace AwardWallet\Engine\morrisons\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class MyPasscode extends \TAccountChecker
{
    public $mailFiles = "morrisons/statements/it-100678639.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@notifications.email.morrisons.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".morrisons.com/") or contains(@href,"groceries.morrisons.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.morrisons.com") or contains(.,"@notifications.email.morrisons.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $name = $oneTimeCode = null;

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);
            $name = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root, true, "/^Hello\s+({$patterns['travellerName']})(?:\s*[,:;!?]+|$)/u");
            $oneTimeCode = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root, true, "/^\d{3,}$/");
        }

        if ($name) {
            $st = $email->add()->statement();
            $st->addProperty('Name', $name);
            $st->setNoBalance(true);
        }

        if ($oneTimeCode !== null) {
            $code = $email->add()->oneTimeCode();
            $code->setCode($oneTimeCode);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[normalize-space()='Your one time access code is:']");
    }
}
