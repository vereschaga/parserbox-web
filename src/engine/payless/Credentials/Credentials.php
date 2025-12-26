<?php

namespace AwardWallet\Engine\payless\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'perks@paylesscar.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Thank you for joining Payless Perks#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        $result['Login'] = re("#(?:^|\n)\s*Your member name is[:\s]+([^\s]+)#ix", $text);
        $result['Password'] = re("#(?:^|\n)\s*Your Password is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
