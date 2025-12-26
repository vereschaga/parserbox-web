<?php

namespace AwardWallet\Engine\pia\Credentials;

class FwPIAAWARDSMembershipConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'servicecenter@piac.aero',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'PIAAWARDS Membership confirmation',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Name'] = reni('Name : (.+?) \n', $text);
        $result['Login'] = reni('Membership No : (\w+)', $text);

        return $result;
    }
}
