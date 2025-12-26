<?php

namespace AwardWallet\Engine\tapportugal\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'victoria@tap.pt',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to TAP Victoria!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#This\s+is\s+your\s+Membership\s+number:\s+(?:TP)?(\d+)#i', $text);

        return $result;
    }
}
