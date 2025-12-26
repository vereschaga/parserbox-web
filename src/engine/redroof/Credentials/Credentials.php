<?php

namespace AwardWallet\Engine\redroof\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "redicard@redroofinn.bfi0.com"',
            'FROM "redicard@redroof.com"',
            'FROM "welcome@redroof.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if (preg_match('#RediCard.*?Rewards\s*:\s*(.*),\s*\#(\d+)#i', $text, $m)) {
            // credentials-1.eml
            $result['Name'] = $m[1];
            $result['Login'] = $m[2];
            $result['FirstName'] = re('#Hello\s+(.*?)\s*,#i', $text);
        } elseif ($login = re('#RediCard.*Rewards\s*:\s*(\d+)#i', $text)) {
            // credentials-2.eml
            $result['Login'] = $login;
        } elseif ($login = re('#making\s+a\s+reservation\s+or\s+checking\s+in:\s*(\d+)#i', $text)) {
            // credentials-3.eml
            $result['Login'] = $login;
            $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);
        }

        return $result;
    }
}
