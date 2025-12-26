<?php

namespace AwardWallet\Engine\vueling\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'vueling@news.vueling.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '#.#',
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
        $text = text($this->http->Response['body']);

        if ($name = re("#Hi\s+(\w+)#", $text)) {
            $result['FirstName'] = beautifulName($name);
        }

        return $result;
    }
}
