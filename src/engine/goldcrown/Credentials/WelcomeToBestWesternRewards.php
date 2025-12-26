<?php

namespace AwardWallet\Engine\goldcrown\Credentials;

class WelcomeToBestWesternRewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'gcc@cs.bestwestern.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Best Western Rewards',
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
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#Member:\s+(.*?)\s+(?:Number:|Member\s+ID)#i', $this->text());

        return $result;
    }
}
