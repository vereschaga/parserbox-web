<?php

namespace AwardWallet\Engine\gha\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "ghadiscovery@gha.com"',
            'FROM "noreply@gha.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Global Hotel Alliance: new membership information') !== false) {
            // credentials-1.eml
            $result['Login'] = re('#Your\s+username:\s+(\S+)#i', $text);
            $result['Password'] = re('#Your\s+password:\s+(\S+)#i', $text);
            $result['Name'] = re('#First\s+name\s+Last\s+name:\s+(.*)#i', $text);
        } elseif (stripos($subject, 'Global Hotel Alliance: new login information') !== false) {
            // credentials-2.eml
            $result['Login'] = re('#Your\s+username:\s+(\S+)#i', $text);
            $result['Password'] = re('#Your\s+password:\s+(\S+)#i', $text);
            $result['Name'] = re('#Dear\s+(.*?)\s*,#i', $text);
        }

        return $result;
    }
}
