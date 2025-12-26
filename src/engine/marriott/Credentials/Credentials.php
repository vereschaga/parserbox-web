<?php

namespace AwardWallet\Engine\marriott\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'Marriott@marriott-email.com',
            'marriott@marriott-email.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Earn [0-9,]+ Bonus Points#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(trim(re("#Dear\s+([^,]+),#i", $text)));

        return $result;
    }
}
