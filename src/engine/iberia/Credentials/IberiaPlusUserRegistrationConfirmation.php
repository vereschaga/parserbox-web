<?php

namespace AwardWallet\Engine\iberia\Credentials;

class IberiaPlusUserRegistrationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'ibcom.webmaster1@iberia.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Iberia Plus - User registration confirmation',
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
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Iberia\s+Plus\s+Customer\s+number:\s+FQTV\s+IB\s+(\d+)#i', $text);
        $result['Name'] = re('#Dear\s+M[rs]+\s+(.*?)\s+Welcome\s+to\s+the#i', $text);

        return $result;
    }
}
