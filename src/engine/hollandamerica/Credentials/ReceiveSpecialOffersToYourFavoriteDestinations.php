<?php

namespace AwardWallet\Engine\hollandamerica\Credentials;

class ReceiveSpecialOffersToYourFavoriteDestinations extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'hollandamerica@e.hollandamerica.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Receive special offers to your favorite destinations',
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
        $result['FirstName'] = re('#Dear\s+(.*?),#i', $text);

        return $result;
    }
}
