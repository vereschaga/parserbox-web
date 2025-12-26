<?php

namespace AwardWallet\Engine\harrah\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'emails@em.harrahs-marketing.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Total Rewards, Thank You for Signing Up",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login!',
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Login!'] = re('#Total\s+Rewards\s+Account\s+Number\s+is[:\s]+(\d+)#i', $text);
        $result['Login'] = re('#Total\s+Rewards\s+User\s+Name\s+is[:\s]+(\S+)#i', $text);
        $result['Name'] = beautifulName(re('#Dear\s+(.*?),#i', $text));

        return $result;
    }
}
