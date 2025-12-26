<?php

namespace AwardWallet\Engine\tahitinui\Credentials;

class AccountIsBeingActivated extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'admin@airtahitinui.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '/Account\s+"\s*\d+\s*"\s+is\s+being\s+activated/ims',
            //            Subject: Account "1135934" is being activated
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
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re("/Member\s+id\s+is\s*:\s*(\d+?)\s*You\s+can/ims", $text);
        $result['Name'] = re("/Hi\s+\w+\s+(.*?),\s*Welcome/ims", $text);

        return $result;
    }
}
