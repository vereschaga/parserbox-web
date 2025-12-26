<?php

namespace AwardWallet\Engine\toysrus\Credentials;

class Credentials2 extends \TAccountCheckerExtended
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
            'Here are Your "R"Us Reward Dollars!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
