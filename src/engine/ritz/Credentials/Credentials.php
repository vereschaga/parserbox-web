<?php

namespace AwardWallet\Engine\ritz\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "rewards@ritzcarlton-email.com"',
            'FROM "ritzcarlton@ritzcarlton-email.com"',
            'FROM "ritzcarltonrewards@ritzcarlton.com"',
            'FROM "online.account@marriott.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $nameRegexs = [
            // credentials-1.eml
            '#Dear\s+(.*?)\s*,#i',
            // credentials-3.eml
            '#\s*(.*)\s+Member\s*\##i',
            // credentials-4.eml
            '#\s*(.*)\s+Rewards\s*\##i',
        ];

        foreach ($nameRegexs as $r) {
            if ($s = re($r, $text)) {
                $result['Name'] = $s;
            }
        }

        if ($s = re('#\s*(.*)\s+Account\s+Number\s*:#i', $text)) {
            // credentials-2.eml
            $result['FirstName'] = $s;
        }

        return $result;
    }
}
