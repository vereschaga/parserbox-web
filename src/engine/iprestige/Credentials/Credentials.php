<?php

namespace AwardWallet\Engine\iprestige\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "enews@iprestigerewards.com",
            "member@mail2.iprestigerewards.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#iPrestige Rewards - Your \w+ Account Summary#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Login!",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(re('#\n\s*(\w+\s+\w+)\s+Membership\s+No\.\s+(\d+)#i', $this->text()));
        $result['Login!'] = re(2);

        return $result;
    }
}
