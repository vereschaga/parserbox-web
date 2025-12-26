<?php

namespace AwardWallet\Engine\thanksagain\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "customer.service@thanksagain.com"',
            'FROM "member.email@thanksagain.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if (preg_match('#\s*(.*)\s+Username:\s+(\S+)#i', $text, $m)) {
            // credentials-1.eml
            $result['Name'] = $m[1];
            $result['Login'] = $m[2];
        } elseif ($s = re('#\s*(.*),\s+(?:Join\s+Thanks\s+Again|Enjoy\s+Special\s+Offers|Earn\s+Miles)#i', $text)) {
            // credentials-{2,3,4}.eml
            $result['Name'] = $s;
        }

        return $result;
    }
}
