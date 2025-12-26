<?php

namespace AwardWallet\Engine\germanwings\Credentials;

class AgentRegistrationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'NoReply@germanwings.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Agent Registration Confirmation',
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
        $text = $parser->getPlainBody();
        $result['Name'] = re('#Willkommen\s+(?:Herr|Frau)\s+(.*?)\s*\.#i', $text);
        $result['Login'] = re('#Benutzername\s+(\S+)#i', $text);

        return $result;
    }
}
