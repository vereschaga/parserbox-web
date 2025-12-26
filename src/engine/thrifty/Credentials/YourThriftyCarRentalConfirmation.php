<?php

namespace AwardWallet\Engine\thrifty\Credentials;

class YourThriftyCarRentalConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'thriftycarrental@email.thrifty.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your Thrifty Car Rental Confirmation!',
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
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+(.*)\s*,#i', $text);
        $result['Login'] = re('#Blue\s+Chip\s*\#\s*:\s+([\w\-]+)#i', $text);

        return $result;
    }
}
