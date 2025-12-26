<?php

namespace AwardWallet\Engine\toysrus\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'rewardsrus@toysrus.com',
            'rewardsrus@email.toysrus.com',
            'rewardsrus@em.toysrus.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Rewards R Us!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Login!',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['Login!'] = re("#Your\s+Rewards\"R\"Us\s+membership\s+card\s+number\s+is:\s+(\d+)#msi", $text);

        return $result;
    }
}
