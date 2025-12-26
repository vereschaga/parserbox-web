<?php

namespace AwardWallet\Engine\vietnam\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'profile-email@vietnamair.com.vn',
            'noreply@vietnamairlines.com',
            'Auto_sender.glp@vietnamairlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Profile Created",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = orval(
            re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text),
            re("#(?:^|\n)\s*M[RSI]+\s+(.*?)\s{2}#i", $text)
        );

        if ($password = re("#Your\s+password\s+is:\s+(\S+)#ix", $text)) {
            $result['Password'] = $password;
        }

        return $result;
    }
}
