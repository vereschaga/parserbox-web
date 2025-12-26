<?php

namespace AwardWallet\Engine\petco\Credentials;

class PetcoComPasswordRequest extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'orders@emailservice.petco.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Petco.com Password Request',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#\s*(.*),\s+Thank\s+you#i', $text);

        return $result;
    }
}
