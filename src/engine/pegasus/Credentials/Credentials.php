<?php

namespace AwardWallet\Engine\pegasus\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "reservation@flypgs.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Pegasus Plus Membership Activation",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(re("#Dear\s+(\w+\s+\w+)#", $this->text()));

        return $result;
    }
}
