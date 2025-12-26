<?php

namespace AwardWallet\Engine\quidco\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountInfo extends \TAccountChecker
{
    public $mailFiles = "quidco/statements/it-83231636.eml, quidco/statements/it-83983690.eml, quidco/statements/it-83241750.eml";

    private $subjects = [
        'en' => ['Your account statement', '- Account Activation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]quidco\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Quidco -') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".quidco.com/") or contains(@href,"sys.quidco.com") or contains(@href,"comms.quidco.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Sent by Quidco") or contains(.,"www.quidco.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $login = null;

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hi')]", null, true, "/^Hi\s+({$patterns['travellerName']})(?:[ ]*[,;:!?]|$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//*[ count(*)=2 and *[1][normalize-space()='Account statement'] ]/*[2]", null, true, "/^\S+@\S+$/");

        if ($login) {
            $st->setLogin($login);
        }

        if ($name || $login) {
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
        return $this->http->XPath->query("//*[contains(normalize-space(),'Check out your account summary for') or contains(normalize-space(),'to authenticate this email address and activate your Quidco account.')]")->length > 0;
    }
}
