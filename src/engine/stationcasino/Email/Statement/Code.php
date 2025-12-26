<?php

namespace AwardWallet\Engine\stationcasino\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Code extends \TAccountChecker
{
    public $mailFiles = "stationcasino/statements/it-379700706.eml";

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@stationcasinos.com') !== false) {
            return stripos($headers['subject'], 'Verify Your Email Address') !== false;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".stationcasinos.com/")]')->length === 0
            && $this->http->XPath->query('//text()[contains(.,"©") and contains(normalize-space(),"Station Casinos, LLC")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@stationcasinos.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Code not found!');

            return $email;
        }
        $root = $roots->item(0);

        $code = $this->http->FindSingleNode('.', $root, true, "/^(\d{3,})(?:\s*[,.;:!?]|$)/");

        if ($code) {
            $otp = $email->add()->oneTimeCode();
            $otp->setCode($code);

            $st = $email->add()->statement();
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Please enter the code below')]/following::tr[normalize-space()][1]");
    }
}
