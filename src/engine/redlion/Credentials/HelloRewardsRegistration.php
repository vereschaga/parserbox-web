<?php

namespace AwardWallet\Engine\redlion\Credentials;

class HelloRewardsRegistration extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'memberservices@redlion.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Hello Rewards: Registration',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
            'LastName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = $this->http->FindSingleNode('//td[contains(., "Name:") and not(.//td)]/following-sibling::td[1]');
        $result['LastName'] = re('#Dear\s+M[rs]+\.\s+(.*?),#i', $text);

        return $result;
    }
}
