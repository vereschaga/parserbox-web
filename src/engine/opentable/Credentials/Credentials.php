<?php

namespace AwardWallet\Engine\opentable\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'member_services@opentable.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to OpenTable!#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);

        return $result;
    }
}
