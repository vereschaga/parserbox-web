<?php

namespace AwardWallet\Engine\swissotel\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'customer.relations@swissotelcrm.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Club Swiss Gold News',
        ];
    }

    public function getParsedFields()
    {
        return [
            'LastName',
            'Number',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Number'] = re('#Membership no:\s+([0-9A-Z]+)#msi', $text);
        $result['LastName'] = re('#Dear\s+M[rsi]+\s+(\w+),#i', $text);

        return $result;
    }
}
