<?php

namespace AwardWallet\Engine\whiteboard\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'thewhiteboard@surveyhelpcenter.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to The Whiteboard#i",
            "#Join our Elite Community#i",
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

        $result['Name'] = re("#(?:^|\n)\s*(?:Hello|Welcome)\s+([^\n,]+)#i", $text);
        $result['Login'] = $parser->getCleanTo();
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
