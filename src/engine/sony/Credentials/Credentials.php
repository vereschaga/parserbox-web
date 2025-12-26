<?php

namespace AwardWallet\Engine\sony\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'members@members.sonyrewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = nice(re('#Hello,?\s*(.*)#i', $text));
        $result['Points'] = nice(re('#Points available:\s*(.*)#i', $text));

        if ($result['Points'] == null) {
            unset($result['Points']);
        }

        return $result;
    }
}
