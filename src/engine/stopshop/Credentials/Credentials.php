<?php

namespace AwardWallet\Engine\stopshop\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'account_info@stopandshop.com',
            'techsupport@stopandshop.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to My Stop & Shop',
            'The Information You Requested',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = re("#\n\s*Name\s*:\s*(.+)#i", $text);
        $result['Login'] = re("#\n\s*Username\s*:\s*(.+)#i", $text);

        if (!$result['Login']) {
            $result['Login'] = re("#Your username is[:\s]+([^\s]+)#ix", $text);
        }
        $result['Email'] = re("#\n\s*Email\s*:\s*(.+)#i", $text);

        return $result;
    }
}
