<?php

namespace AwardWallet\Engine\toysrus\Credentials;

class Credentials3 extends \TAccountCheckerExtended
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
            'Your Rewards Are Here',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Login!',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['Login!'] = re("#Membership Number:\s+(\d+)#ms", $text);
        $result['Name'] = beautifulName(re("#^([^,]+),\s+Membership Number#ms", $text));

        return $result;
    }
}
