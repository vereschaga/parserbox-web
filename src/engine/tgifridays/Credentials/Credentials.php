<?php

namespace AwardWallet\Engine\tgifridays\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'givememorestripes@reply.fridays.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "you're close to getting something Xtra!",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result['FirstName'] = beautifulName(re('#Hi\s+([^\s\.,!]+)#i', $text));

        return $result;
    }
}
