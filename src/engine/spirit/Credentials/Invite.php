<?php

namespace AwardWallet\Engine\spirit\Credentials;

class Invite extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'freespirit@email.spiritairlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "By Invitation Only",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login!',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['Login!'] = re("#Account Number\s*:\s*(\d+)#", $text);
        $result['FirstName'] = beautifulName(re("#Hi\s+(.*?),#", $text));

        return $result;
    }
}
