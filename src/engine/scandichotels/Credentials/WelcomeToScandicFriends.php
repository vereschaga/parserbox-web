<?php

namespace AwardWallet\Engine\scandichotels\Credentials;

class WelcomeToScandicFriends extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'noreply@scandichotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Scandic Friends',
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
        $result['Login'] = re('#Your membership number is:\s+(\d+)#msi', $text);
        $result['FirstName'] = re('#Hi\s+([^,]+),#i', $text);

        return $result;
    }
}
