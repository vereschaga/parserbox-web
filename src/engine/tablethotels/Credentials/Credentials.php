<?php

namespace AwardWallet\Engine\tablethotels\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'newsletter@e.tablethotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Super Stylish New York City Sale",
            '#Tablet#',
            "#Super.*?Sale#i",
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
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = re("#\n\s*(.*?),\n\s*You currently have#ix", $text);
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
