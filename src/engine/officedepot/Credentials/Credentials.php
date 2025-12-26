<?php

namespace AwardWallet\Engine\officedepot\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "MemberServices@myworkliferewards.com"',
            'FROM "specials@email.officedepot.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to Worklife Rewards') !== false) {
            // credentials-1.eml
            $result['Name'] = re('#Dear\s+(.*)\s*,#i', $text);
            $result['Login'] = re('#Member\s+Number\s*:\s+(\d+)#i', $text);
        } elseif (stripos($subject, 'Your Rewards Status') !== false) {
            // credentials-2.eml
            $result['FirstName'] = re('#Dear\s+(.*)\s*,#i', $text);
            $result['Login'] = re('#Member\s*\#\s*(\d+)#i', $text);
        } elseif ($firstName = re('#Dear\s+(.*)\s*:#i', $text)) {
            // credentials-3.eml
            $result['FirstName'] = $firstName;
        }

        return $result;
    }
}
