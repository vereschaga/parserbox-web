<?php

namespace AwardWallet\Engine\velocity\Credentials;

class Offer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
        'credentials-4.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'velocity@email.velocityfrequentflyer.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Triple Points on Virgin Australia flights',
            'Want double Points on your Christmas shopping?',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['LastName'] = re('#Dear\s+M[rs]+\s+(.*)\s*,#i', $text);
        $result['Login'] = re('#MEMBERSHIP\s+NO:\s+(\d+)#i', $text);

        return $result;
    }
}
