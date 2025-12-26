<?php

namespace AwardWallet\Engine\ichotelsgroup\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "ihg.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to Priority Club Rewards') !== false) {
            // credentials-1.eml
            $result['Name'] = re('#New Member Name:\s+(.*)#i', $text);
            $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);
        } elseif (stripos($subject, 'Your August Priority Club Rewards eStatement') !== false) {
            // credentials-2.eml
            $result['Name'] = re('#\s*(.*)\s+Membership\s+Number#i', $text);
        } elseif (stripos($subject, 'Mid-Month eStatement Plus') !== false) {
            // credentials-3.eml
            $result['Name'] = re('#\s*(.*)\s+As\s+of:#i', $text);
        }

        return $result;
    }
}
