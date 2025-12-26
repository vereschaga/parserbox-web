<?php

namespace AwardWallet\Engine\loews\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "loewshotels@youfirst.loewshotels.com"',
            'FROM "loewshotels@yf.loewshotels.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re('#Member\s+Name:\s+(.*)#i', text($this->http->Response['body']))) {
            $result['Name'] = $name;
        }

        return $result;
    }
}
