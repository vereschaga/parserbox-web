<?php

namespace AwardWallet\Engine\redspottedhanky\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'noreply@redspottedhanky.com',
            'newsletter@clickmail.redspottedhanky.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Red\s*spotted#i",
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

        $result['Login'] = orval(
            re("#\n\s*Your username is[:\s]+([^\s]+)#i", $text),
            $parser->getCleanTo()
        );

        $result['Name'] = re([
            "#\n\s*Dear(?:\s+Mrs?)?\s+([^\n,]+)#i",
            "#\n\s*Hi\s+([^\n,]+)#i",
        ], $text);

        return $result;
    }
}
