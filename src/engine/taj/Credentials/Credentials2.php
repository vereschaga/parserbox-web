<?php

namespace AwardWallet\Engine\taj\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'Innercircle@tajhotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to the Taj InnerCircle",
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
        $text = text($this->http->Response['body']);

        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re('#Your\s+12-digit\s+membership\s+number\s+is\s+-\((\d+)\).#', $text);

        return $result;
    }
}
