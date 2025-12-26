<?php

namespace AwardWallet\Engine\malaysia\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "enrich@malaysiaairlines.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($parser->getBody());

        if (stripos($subject, 'Welcome to Enrich') !== false) {
            // credentials-1.eml
            $result['LastName'] = re('#Dear\s+M[rs]+\.\s+(.*?)\s*,#i', $text);
            $result['Name'] = re('#Name on Card: (.*)#i', $text);
            $result['Login'] = re('#Enrich\s+Membership\s+No.\s*:\s+(\d+)#i', $text);
            $result['Password'] = re('#Enrich\s+Password\s*:\s+(.*)#i', $text);

            if ($result['Password']) {
                $result['Password'] = preg_replace('#\s#i', '', $result['Password']);
            }
        } elseif ($lastName = re('#Dear\s+M[rs]+\.\s+(\w+),#i', $text)) {
            // credentials-2.eml
            $result['LastName'] = $lastName;
        } elseif ($name = re('#Dear\s+M[rs]+\.\s+(.*?)\s*,#i', $text)) {
            // credentials-3.eml
            $result['Name'] = $name;
        }

        return $result;
    }
}
