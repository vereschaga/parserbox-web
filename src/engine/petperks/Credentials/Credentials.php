<?php

namespace AwardWallet\Engine\petperks\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'PetSmart@email-petsmart.com',
            'petperks@mail01-petsmart.com',
            'petperks@petsmart-mail.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "PetPerks Account Update Confirmation",
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
        $result['Name'] = re("#Member Notice\s*(\S+),#ms", $text);

        return $result;
    }
}
