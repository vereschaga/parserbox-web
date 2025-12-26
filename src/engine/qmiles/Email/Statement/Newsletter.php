<?php

namespace AwardWallet\Engine\qmiles\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Newsletter extends \TAccountChecker
{
    public $mailFiles = "qmiles/statements/it-105682284.eml, qmiles/statements/it-63720474.eml, qmiles/statements/it-66909081.eml, qmiles/statements/it-67204642.eml, qmiles/statements/it-729022080.eml, qmiles/statements/it-73241103.eml, qmiles/statements/it-89550737.eml, qmiles/statements/it-99032994.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@qatarairways.com') !== false
            || stripos($from, '@qr.qatarairways.com') !== false
            || stripos($from, '@qmiles.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Qatar Airways') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Account Registration') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".qatarairways.com/") or contains(@href,"qr.qatarairways.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Qatar Airways. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership() || $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        $email->setType('Newsletter');
        $root = $roots->length === 1 ? $roots->item(0) : null;

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("//a[contains(normalize-space(),'modify your preferences')]/@href", null, true, "/&id=([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-.]+)&/");

        if ($login) {
            $st->setLogin($login);
        }

        $membershipNo = $this->http->FindSingleNode("preceding::tr[normalize-space()='Membership No.']/preceding-sibling::tr[normalize-space()][1]", $root, true, '/^[-A-Z\d]{5,}$/')
            ?? $this->http->FindSingleNode("preceding::text()[normalize-space()='Membership number:' or normalize-space()='Membership Number:']/following::text()[normalize-space()][1]", $root, true, '/^[-A-Z\d]{5,}$/')
        ;

        if ($membershipNo) {
            $st->setNumber($membershipNo);

            if (empty($st->getLogin())) {
                $st->setLogin($membershipNo);
            }
        }

        $name = $this->http->FindSingleNode("preceding::text()[contains(normalize-space(),'we remain committed to')]", $root, true, "/^({$patterns['travellerName']})\s*,\s*we remain committed to/iu")
            ?? $this->http->FindSingleNode("preceding::text()[contains(normalize-space(),'plan your next vacation and travel')]/ancestor::*[1]", $root, true, "/^({$patterns['travellerName']})\s*,\s*plan your next vacation and travel/iu")
            ?? $this->http->FindSingleNode("preceding::text()[contains(normalize-space(),'we are delighted to welcome')]/ancestor::*[1]", $root, true, "/^({$patterns['travellerName']})\s*,\s*we are delighted to welcome/iu")
            ?? $this->http->FindSingleNode("preceding::text()[starts-with(normalize-space(),'Dear')]", $root, true, "/^Dear\s+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u")
            ?? $this->http->FindSingleNode("preceding::text()[normalize-space()='Dear']/following::text()[normalize-space()][1][ following::text()[normalize-space()][1][normalize-space()=','] ]", $root, true, "/^{$patterns['travellerName']}$/u")
        ;

        if (preg_match("/^(?:traveller|Customer|Valued Customer)$/i", trim($name))) {
            $name = null;
        }

        if ($name) {
            $st->addProperty('Name', preg_replace("/^(?:MRS|MR|MS|DR)[.\s]+(.+)$/i", '$1', $name));
        }

        if ($login || $membershipNo || $name) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership() === true) {
            $st->setMembership(true);
        }

        $rootText = $this->http->FindSingleNode('.', $root);

        if (preg_match("/Use\s+(\d{5,})\s+as your one time password/i", $rootText, $m)
            || preg_match("/Your one time password.* to proceed with your Login is\s+(\d{5,})(?:\s*[,.:;!?]|$)/i", $rootText, $m)
        ) {
            // it-99032994.eml
            $email->add()->oneTimeCode()->setCode($m[1]);
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Verify email address') or contains(normalize-space(), 'Verify my e-mail')]/ancestor::a[1]")->length > 0) {
            $code = $email->add()->oneTimeCode();
            $code->setCodeAttr("/^\<\d+\>\-\<https:\/\/www\.qatarairways\.com(?:\/content\/global)?\/[a-z]{2}\/Privilege-Club\/login.*\.html[?](?:token\=[A-Z\d]+&)?evt\=[\dA-z\=\&]+\>$/", 1000);
            $link = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Verify email address') or contains(normalize-space(), 'Verify my e-mail')]/ancestor::a[1]/@href");
            $code->setCode('<' . $membershipNo . '>-<' . $link . '>');
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Please use') and contains(normalize-space(),'as your verification code to reset your password')]")->length > 0 // it-105682284.eml
            || $this->http->XPath->query("//*[contains(normalize-space(),'Thank you for creating a Qatar Airways account')]")->length > 0 // it-63720474.eml
        ;
    }

    private function findRoot(): \DOMNodeList
    {
        $roots = $this->http->XPath->query("//tr[not(.//tr) and descendant::a[normalize-space()='Unsubscribe' and contains(@href,'.qatarairways.com/')]]/descendant::a[normalize-space()='Manage My Profile' and contains(@href,'.qatarairways.com/')]");

        if ($roots->length !== 1) {
            $roots = $this->http->XPath->query("//text()[contains(normalize-space(), 'Qatar Airways') and contains(normalize-space(), 'All rights reserved')]");
            $this->logger->debug('debug' . $roots->length);
        }

        if ($roots->length !== 1) {
            // it-99032994.eml
            $roots = $this->http->XPath->query("//text()[contains(normalize-space(),'as your one time password') or contains(normalize-space(),'Your one time password (OTP) to proceed with your')]");
        }

        return $roots;
    }
}
