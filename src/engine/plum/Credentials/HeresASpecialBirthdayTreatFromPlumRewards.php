<?php

namespace AwardWallet\Engine\plum\Credentials;

class HeresASpecialBirthdayTreatFromPlumRewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'plumrewards@email.indigo.ca',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'here\'s a special birthday treat from plum rewards!',
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
        $result['FirstName'] = re('#list.\s+Details\s*.\s+(.*?),\s+you\s+have\s+[\d+,.]+\s+plum#i', $this->text());

        return $result;
    }
}
