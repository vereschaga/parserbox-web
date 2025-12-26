<?php

namespace AwardWallet\Engine\s7\Credentials;

class WelcomeToS7Priority extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'priority@s7.ru',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to S7 Priority',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['Password'] = re('#PIN\s*:\s+(\d+)#i', $this->text());
        $result['Name'] = re('#Dear\s+M[RS]+\s+(.*?),#i', $this->text());

        return $result;
    }
}
