<?php

namespace AwardWallet\Engine\southernsun\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'tsogosunhotels.noreply@tsogosun.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Rewards Programme Registration#",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result["Name"] = re("#Dear (.*?)\s*\n#", $text);

        $result["Login"] = re("#Card number:\s*(\d+)#", $text);

        $result['Password'] = re("#PIN:\s*(\d+)#", $text);

        return $result;
    }
}
