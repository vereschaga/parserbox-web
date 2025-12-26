<?php

namespace AwardWallet\Engine\surveyhead\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'support@surveyhelpcenter.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Survey Now Available#i",
            "#Get Mobile With Your Opinions#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re("#\n\s*Hello\s+([^\n,]+)#i", $text);

        return $result;
    }
}
