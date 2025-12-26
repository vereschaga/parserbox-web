<?php

namespace AwardWallet\Engine\worldpoints\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "Heathrowrewards@email.heathrow.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#Dear\s+M[rs]+\s+(.*)\s*,#i', $text)) {
            // credentials-2.eml
            $result['LastName'] = $s;
        } elseif ($s = re('#Dear\s+(.*)\s*,#i', $text)) {
            // credentials-3.eml
            $result['Name'] = $s;
        }
        //		if ($login = $this->http->FindSingleNode('//td[contains(., "Heathrow Rewards No:") and not(.//td)]/following-sibling::td[1]'))
        //			$result['Login2'] = $login;
        return $result;
    }
}
