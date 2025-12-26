<?php

namespace AwardWallet\Engine\harrah\Credentials;

class News extends \TAccountCheckerExtended
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
            "Total Rewards News",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login!',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Login!'] = re('#TOTAL\s+REWARDS\s*\#\D+(\d+)#msi', $text);
        $result['Name'] = beautifulName(re('#Dear\s+(.*?),#i', $text));

        return $result;
    }
}
