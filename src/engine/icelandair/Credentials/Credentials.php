<?php

namespace AwardWallet\Engine\icelandair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "icc@icelandair.is"',
            'FROM "usamarketing@icelandair.is"',
            'FROM "icc@reply.icelandair.is"',
            'FROM "no-reply@icelandair.us"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re('#Dear\s+Saga\s+Club\s+Member,\s+(.*)#i', $text)) {
            // credentials-{1,3}.eml
            $result['Name'] = nice($name);
        } elseif ($name = re('#Dear\s+Saga\s+Club\s+Member\s+-\s+(.*)#i', $text)) {
            // credentials-2.eml
            $result['Name'] = $name;
        }

        return $result;
    }
}
