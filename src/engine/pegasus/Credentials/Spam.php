<?php

namespace AwardWallet\Engine\pegasus\Credentials;

class Spam extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "newsletter@e-flypgs.com",
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
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $parser->getCleanTo();

        if ($name = re("#(?:Dear|Hello)\s+(\w+\s+\w+)#", $this->text())) {
            $result['Name'] = beautifulName($name);
        }

        if ($name = re("#\n\s*(\w+)\s*,\s*with\s+\d+%\s+discount#", $this->text())) {
            $result['FirstName'] = beautifulName($name);
        }

        return $result;
    }
}
