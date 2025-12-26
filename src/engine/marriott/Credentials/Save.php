<?php

namespace AwardWallet\Engine\marriott\Credentials;

class Save extends \TAccountCheckerExtended
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
            "#Shop\s+\S{1,2}\s+Save#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login!',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['FirstName'] = $this->http->FindSingleNode("//*[contains(text(),'Marriott Rewards')][contains(text(),'Account')]", null, true, "#^([^']+)#");

        $result['Login!'] = trim(re("/Account #:\s+(\d+)/i", $text));

        return $result;
    }
}
