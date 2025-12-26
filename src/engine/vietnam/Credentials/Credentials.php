<?php

namespace AwardWallet\Engine\vietnam\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = orval(
            re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text),
            re("#(?:^|\n)\s*M[RSI]+\s+(.*?)\s{2}#i", $text)
        );

        if ($login = clear("#\.$#", re("#(?:^|\n)\s*(?:Member No|Your member ID is)[:\s]+([^\s]+)#ix", $text))) {
            $result['Login'] = $login;
        }

        return $result;
    }
}
