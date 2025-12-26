<?php

namespace AwardWallet\Engine\spirit\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'freespirit@p.spiritairlines.com',
            'freespirit@email.spiritairlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your FREE SPIRIT Statement",
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
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = beautifulName($this->http->FindSingleNode("//*[contains(text(), 'Member:')]/../following-sibling::*[1]"));

        return $result;
    }
}
