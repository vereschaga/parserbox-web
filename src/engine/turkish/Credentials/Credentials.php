<?php

namespace AwardWallet\Engine\turkish\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "milesandsmiles@thy.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to Turkish Airlines\' Miles&Smiles Family!') !== false) {
            // credentials-1.eml
            $result['Name'] = re('#Dear\s+(.*)\s*,#i', $text);
            $result['Login'] = re('#Your\s+Miles&Smiles\s+account\s+number\s+is:\s+([\w\-]+)#i', $text);
            $result['Password'] = re('#Your\s+Pin\s+code\s+is:\s+(\d+)#i', $text);
            $result['Address'] = nice(re('#Your\s+address:\s+(.*)\s+Your\s+card\s+will\s+automatically#is', $text));
        } elseif (stripos($subject, 'Miles&Smiles Statement') !== false
                        and preg_match('#\s*(.*)\s+Membership\s+ID:\s+(\S+)#i', $text, $m)) {
            // credentials-2.eml
            $result['Name'] = $m[1];
            $result['Login'] = $m[2];
        }

        return $result;
    }
}
