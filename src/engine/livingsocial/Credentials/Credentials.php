<?php

namespace AwardWallet\Engine\livingsocial\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return [
            'updates@livingsocial.com',
            'deals@livingsocial.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            ' ',
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
