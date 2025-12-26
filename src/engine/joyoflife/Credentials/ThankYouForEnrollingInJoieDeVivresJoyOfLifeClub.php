<?php

namespace AwardWallet\Engine\joyoflife\Credentials;

class ThankYouForEnrollingInJoieDeVivresJoyOfLifeClub extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'cs@loyaltymarketing.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Thank you for enrolling in Joie de Vivre\'s Joy of Life Club',
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
        $result['Login'] = re('#\s+Your\s+Joy\s+ID\s+is\s*:\s+(\d+)#i', $this->text());
        $result['FirstName'] = re('#\s+Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
