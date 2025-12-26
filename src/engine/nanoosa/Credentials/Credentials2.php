<?php

namespace AwardWallet\Engine\nanoosa\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "customerservice@nanoosa.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Still looking or out of ideas? Deals and Coupons Inside",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re("#Dear\s+(\w+)#i", $text);

        return $result;
    }
}
