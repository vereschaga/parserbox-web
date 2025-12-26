<?php

namespace AwardWallet\Engine\restaurant\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "restaurant_com@emailrestaurant.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Your Insider Tips#",
            "#Amazing Dining Deals#",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = re("#^(.*?),\s*(?:Your Insider Tips|Amazing Dining Deals)#ix", $parser->getSubject());
        $result['Login'] = $parser->getCleanTo();
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
