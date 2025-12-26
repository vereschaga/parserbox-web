<?php

namespace AwardWallet\Engine\grandguest\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'info@grandlifehotels.com',
            'support@grandliferewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#GRANDLIFE\s*REWARDS#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Login"] = $parser->getCleanTo();
        $result['Password'] = re("#\n\s*Password\s*:\s*([^\s]+)#", $text);

        return $result;
    }
}
