<?php

namespace AwardWallet\Engine\surveysavvy\Credentials;

class ProjectGoldParticipateInANewSavvyConnectStudy extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'invite@surveysavvy.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Project Gold - Participate in a New SavvyConnect Study',
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
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#simply\s+surfing\s+the\s+web!\s+(.*?),\s+SurveySavvy\s+invites\s+you\s+to\s+become#i', $this->text());

        return $result;
    }
}
