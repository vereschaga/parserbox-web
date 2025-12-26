<?php

namespace AwardWallet\Engine\scene\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "newsletter@email.scene.ca"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = re("#membership\s+ID\s+number\s+is\s+([\d]+)#", $text);

        if ($result['Login'] == null) {
            $result['Login'] = re("#SCENE\s+\#\s+is\s+([\d]+)#", $text);
        }

        $result['Points'] = re("#you\s+have:\s*([\d]+)\s*pts\.#", $text);

        if ($result['Points'] == null) {
            $result['Points'] = re("#You\s+have\s*([\d]+)\s*points#", $text);
        }

        $result['FirstName'] = re("#([A-Za-z]+)\s*as\s*of#", $text);

        if ($result['FirstName'] == null) {
            $result['FirstName'] = re("#([A-Za-z]+)\, you#", $text);
        }

        return $result;
    }
}
