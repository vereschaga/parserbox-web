<?php

namespace AwardWallet\Engine\niugini\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "destinations@airniugini.com.pg",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "AirNiugini Registration",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();

        if ($login = re("#Membership Number\s*:\s*(\d+)#", $this->text())) {
            $result['Login'] = beautifulName($login);
        }

        if ($name = re("#Dear\s+(\w+\s+\w+)#", $this->text())) {
            $result['Name'] = beautifulName($name);
        }

        if ($pass = re("#Password\s*:\s*(\S+)#", $this->text())) {
            $result['Password'] = beautifulName($pass);
        }

        return $result;
    }
}
