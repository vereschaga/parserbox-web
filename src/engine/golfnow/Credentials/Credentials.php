<?php

namespace AwardWallet\Engine\golfnow\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'GolfNews@mail.golfnow.com',
            'golfnews@mail.golfnow.com',
            'TeeTimes@mail.golfnow.com',
            'teetimes@mail.golfnow.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Happy Birthday from GolfNow!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re("#Happy\s+Birthday\s*,?\s+(\w+)#", $this->text());

        return $result;
    }
}
