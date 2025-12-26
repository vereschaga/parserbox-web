<?php

namespace AwardWallet\Engine\saudisrabianairlin\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'alfursan@saudiairlines.com',
            'enews@alfursanonline.info',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Alfursan Miles",
            "Welcome to Frequent Flyer",
            "Your Alfursan Newsletter is here",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = orval(
            text($this->http->Response['body']),
            text($parser->getBody())
        );
        $result['Name'] = trim(re("#Dear\s*([^,\n]+)#i", $text));
        $result['Login'] = re("#Your membership number is\s*:\s*([^\s.]+)#ix", $text);

        if (!$result['Login']) {
            $result['Login'] = re("#Membership No[.\s:]+([^\s.]+)#ix", $text);
        }

        if (!$result['Login']) {
            $result['Login'] = re("#Membership Number[.\s:]+([^\s.]+)#ix", $text);
        }

        return $result;
    }
}
