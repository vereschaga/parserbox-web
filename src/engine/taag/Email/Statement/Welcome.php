<?php

namespace AwardWallet\Engine\taag\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "taag/statements/it-83766585.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flytaag.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//node()[contains(normalize-space(),"@umbiumbiclub.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
            'statusVariants' => 'CLASSIC|SILVER|GOLD',
        ];

        $st = $email->add()->statement();

        $name = $status = $number = null;

        $name = $this->http->FindSingleNode("//p[starts-with(normalize-space(),'Dear')]", null, true, "/^Dear\s+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u");
        $st->addProperty('Name', $name);

        $status = $this->http->FindSingleNode("//p[starts-with(normalize-space(),'Attached you’ll find your')]", null, true, "/^Attached you’ll find your\s*\"\s*({$patterns['statusVariants']})\s*\"/i");
        $st->addProperty('CurrentTier', $status);

        $number = $this->http->FindSingleNode("//p[contains(normalize-space(),'membership card with the membership number')]", null, true, "/membership card with the membership number\s+([-A-Z\d]{5,})(?:\s+and|$)/i");
        $st->setNumber($number)->setLogin($number);

        if ($name || $status || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//node()[contains(normalize-space(),'It is with great pleasure that TAAG welcomes you to our Frequent Flyer Club')]")->length > 0;
    }
}
