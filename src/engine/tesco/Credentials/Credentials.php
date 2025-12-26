<?php

namespace AwardWallet\Engine\tesco\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "Online@mailing.tesco.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#(?:Hello|Dear)\s+M[rs]+\s+(.*?)\s*,#i', $text)) {
            $result['LastName'] = $s;
        }

        return $result;
    }
}
