<?php

namespace AwardWallet\Engine\jumeirah\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'sirius@mysiriuscard.com',
            'sirius@sirius-jumeirah.com',
            'noreply@sirius.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome To Sirius!',
            "#multiply your Sirius Points#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = re("#(?:^|\n)\s*Dear\s*([^,\n]+)#i", $text);
        $result['Login'] = re("#\n\s*(?:MEMBER ID|Membership Number)\s*:\s*(.+)#ix", $text);

        return $result;
    }
}
