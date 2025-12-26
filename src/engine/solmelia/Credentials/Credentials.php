<?php

namespace AwardWallet\Engine\solmelia\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "info@emailer.melia.com"',
            'FROM "info@solmelia.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $result['Login'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#\s*(.*)\s*\|\s+Card\s+number#i', $text)) {
            // credentials-1.eml
            $result['Name'] = $s;
        } elseif ($s = re('#Dear\s+user,\s+(.*)\s+Per requested#i', $text)) {
            // credentials-2.eml
            $result['Name'] = $s;
        }

        return $result;
    }
}
