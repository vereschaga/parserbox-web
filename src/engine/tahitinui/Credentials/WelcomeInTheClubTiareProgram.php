<?php

namespace AwardWallet\Engine\tahitinui\Credentials;

class WelcomeInTheClubTiareProgram extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'clubtiare@airtahitinui.pf',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome in the Club Tiare Program',
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
        $result['Login'] = re("/membership\s+number\s+is\s+(\d+?)\s*and/ims", $text);
        $result['Name'] = re("/Dear\s+\w+\s+(.*?)\s*Congratulations/ims", $text);

        return $result;
    }
}
