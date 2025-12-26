<?php

namespace AwardWallet\Engine\hipoints\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'HarrisPoll@hpolsurveys.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome: Here's your first survey!",
            "Don't miss your chance - New Survey Opportunities!",
            "Tell us your thoughts on",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result = [];
        $result['Email'] = $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
