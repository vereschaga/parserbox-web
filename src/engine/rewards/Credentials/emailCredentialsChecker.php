<?php

namespace AwardWallet\Engine\rewards\Credentials;

class emailCredentialsChecker extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@idine.com",
            "info@email.idine.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your iDine Login ID Request",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re("#Your\s+Login\s+ID\s+is\s+(\w+)#", $this->text());
        $result['FirstName'] = re('#\s+Dear\s+(.*?),\s+You\s+requested#msi', $this->text());

        return $result;
    }
}
