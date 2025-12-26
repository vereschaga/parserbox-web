<?php

namespace AwardWallet\Engine\singaporeair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'SIA_AutoResponse@singaporeair.com.sg',
            'booking@singaporeair.com.sg',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to KrisFlyer",
            "De-link of KrisFlyer and Velocity Frequent Flyer Accounts",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'LastName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result["Login"] = orval(
            re("#Membership Number\s*:\s*(\d+)#", $text),
            re("#Membership No:\s+(\d+)#", $text)
        );

        $result['LastName'] = beautifulName(trim(re('#Dear\s+M[rs]+\s+([^,]+)#i', $text)));

        return $result;
    }
}
