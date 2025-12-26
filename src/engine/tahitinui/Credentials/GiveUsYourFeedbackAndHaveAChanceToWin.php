<?php

namespace AwardWallet\Engine\tahitinui\Credentials;

class GiveUsYourFeedbackAndHaveAChanceToWin extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'news@airtahitinui.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Give us your feedback and have a chance to win the JACKPOT',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
            'LastName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re("/Frequent\s*Flyer\s*Number\s*:\s*(\d+)/ims", $text);
        $result['Name'] = re("/Member's\s+Name\s*:\s*(.*?)\s*Frequent\s+Fly/ims", $text);
        $result['LastName'] = re("/Dear\s+\w+\s+(.*?),\s*Thank/ims", $text);

        return $result;
    }
}
