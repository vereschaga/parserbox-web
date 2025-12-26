<?php

namespace AwardWallet\Engine\thrifty\Credentials;

class BlueChipAccountInformation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
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
            'Blue Chip Account Information',
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
        $result['Login'] = re('#Blue\s+Chip\s+Member\s+number\s+is\s*:\s*([\w\-]+)\.#i', $text);

        return $result;
    }
}
