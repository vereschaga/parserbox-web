<?php

namespace AwardWallet\Engine\shangrila\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "golden.circle@shangri-la.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Golden Circle Membership Confirmation E-mail') !== false) {
            $result['Login'] = re('#Your\s+membership\s+number\s+is\s+(\d+)\s*\.#i', $text);
            $result['LastName'] = re('#Dear\s+\w+\s+(.*?)\s*,#i', $text);
        }

        return $result;
    }
}
