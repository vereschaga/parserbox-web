<?php

namespace AwardWallet\Engine\thalys\Credentials;

class YourThalysTheCardRegistrationRequest extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'thalysthecard@campaigns.thalys.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your Thalys TheCard registration request',
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
        $result['Login'] = re('#Thalys\s+No\.\s*:\s+(\d+)#i', $this->text());
        $result['Name'] = re('#\s+Dear\s+M[rs]+\s+(.*?),#i', $this->text());

        return $result;
    }
}
