<?php

namespace AwardWallet\Engine\jetairways\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "jetonline@jetairways.com"',
            'FROM "JPNewsletter@my.jetprivilege.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to JetPrivilege!') !== false) {
            // credentials-1.eml
            $result['Login'] = re('#Your\s+JetPrivilege\s+membership\s+number\s+is\s+(\d+)\s*\.#i', $text);
            $result['LastName'] = re('#Dear\s+M[rs]+\.\s+(.*?)\s*,#i', $text);
            $result['Name'] = nice(re('#Your name recorded in the JetPrivilege account is (.*)#i', $text));
        } else {
            // credentials-{2,3}.eml
            $result['Login'] = re('#JetPrivilege\s+membership\s*:\s+(\d+)#i', $text);
            $result['LastName'] = re('#Dear\s+M[rs]+\.\s+(.*?)\s*,#i', $text);
        }

        return $result;
    }
}
