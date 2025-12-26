<?php

namespace AwardWallet\Engine\kimpton\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "guestloyalty@kimptongroup.ip08.com"',
            'FROM "reservations@kimptongroup.ip08.com"',
            'FROM "noreply@kimpton-email.com"',
            'FROM "guestloyalty@kimptongroup.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        $firstName = orval(
            // credentials-1.eml
            re('#Dear\s+(.*?)\s*,\s+Welcome#i', $text),
            // credentials-4.eml
            re('#Hello,\s+(.*?)\s+\(Member#i', $text),
            // credentials-5.eml
            re('#\s*(.*),\s+we\'re\s+so\s+happy#i', $text)
        );

        if ($firstName) {
            $result['FirstName'] = $firstName;
        }

        $name = orval(
            // credentials-2.eml
            re('#As of .*\s+(.*)\s+Status#i', $text),
            // credentials-3.eml
            re('#Hello,\s+(.*)\s+Status#i', $text)
        );

        if ($name) {
            $result['Name'] = $name;
        }

        return $result;
    }
}
