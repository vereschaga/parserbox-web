<?php

namespace AwardWallet\Engine\izod\Credentials;

class RewardsCertificatesComingYourWay extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'vanheusen@email.vanheusen.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Rewards Certificates coming your way!',
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
        $result['FirstName'] = re('#Dear\s+(.*?)\s+Look\s+Out\s+For\s+Your\s+Ye#i', $this->text());

        return $result;
    }
}
