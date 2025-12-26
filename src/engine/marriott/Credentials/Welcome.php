<?php

namespace AwardWallet\Engine\marriott\Credentials;

class Welcome extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'Marriott@marriott-email.com',
            'marriott@marriott-email.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to Marriott Rewards#i",
            "#Save\s+\S{1}\d+\s+on\s+#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'LastName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = beautifulName(trim($this->http->FindSingleNode("//td[contains(text(),'Member')]/../../tr[1]/td")));
        $result['LastName'] = beautifulName(trim($this->http->FindSingleNode("//td[contains(text(),'Member')]/../../tr[2]/td")));

        return $result;
    }
}
