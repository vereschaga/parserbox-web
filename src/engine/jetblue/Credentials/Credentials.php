<?php

namespace AwardWallet\Engine\jetblue\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'jetblueairways@email.jetblue.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to TrueBlue",
            "Happy Birthday from TrueBlue!",
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

        $result['Name'] = re("#(?:^|\n)\s*Hello,\s*([^\n,]+)#i", $text);

        if (!$result['Name']) {
            $result['Name'] = re("#(?:^|\n)\s*Dear\s*([^\n,]+)#i", $text);
        }

        $result['Login'] = $parser->getCleanTo();
        $result['Login2'] = re("#(?:^|\n)\s*TrueBlue number[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
