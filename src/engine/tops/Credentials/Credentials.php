<?php

namespace AwardWallet\Engine\tops\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'tops@webstop.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your TOPS",
            "#TOPS .* You Shop#",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "LastName",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['LastName'] = re("#Hello\s+(\S+)\s+Household#ms", $text);

        return $result;
    }
}
