<?php

namespace AwardWallet\Engine\porter\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'PorterAirlines@flyporter.com',
            'porterairlines@flyporter.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to VIPorter",
            "Registration confirmatio",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($parser->getBody());

        $result['Name'] = orval(
            re('#M[rs]+\.\s+(.*)\s+Membership#i', $text),
            re('#Name\s*:\s*M[RSI]+\s+([^\n]+)#msi', $text)
        );

        return $result;
    }
}
