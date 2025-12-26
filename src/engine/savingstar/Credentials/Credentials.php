<?php

namespace AwardWallet\Engine\savingstar\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'savings@savingstar.com',
            'support@savingstar.com',
            'welcome@savingstar.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#You joined Saving\s*Star#i",
            "#Welcome to Saving\s*Star#i",
            "#Saving\s*Star password#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
