<?php

namespace AwardWallet\Engine\hotels\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'info@mail.hotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Account Summary",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Number',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);
        $result['Number'] = re('#Account\s+Number\s*:\s+(\w+)#i', $text);

        return $result;
    }
}
