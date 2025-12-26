<?php

namespace AwardWallet\Engine\showpoints\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "support@audiencerewards.com",
            "customerservice@audiencerewards.com",
            "membership.services@audiencerewards.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Audience Rewards Enrollment Information',
            'Your Audience Rewards Account Information',
            'Exclusive pre-sale for ROCKY on Broadway',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#Welcome\s+(.*?)\s*,#i', $text)) {
            // credentials-1.eml
            $result['Name'] = $s;
        } elseif ($s = re('#MY\s+INFORMATION\s*(.*?)\s+\|\s+Account#i', $text)) {
            // credentials-2.eml
            $result['Name'] = $s;
        }

        return $result;
    }
}
