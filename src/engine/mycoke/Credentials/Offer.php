<?php

namespace AwardWallet\Engine\mycoke\Credentials;

class Offer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'MyCokeRewards@email-icoke.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'More reasons to smile in 2013',
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
        $result['FirstName'] = re('#Hi\s+(.*?)\s+My\s+Coke\s+Rewards#i', $text);

        return $result;
    }
}
