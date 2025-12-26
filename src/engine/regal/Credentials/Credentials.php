<?php

namespace AwardWallet\Engine\regal\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'news@crownclub.regmovies.com',
            'donotreply@regalcinemas.com',
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

        if ($s = re('#Dear\s+(.*?)\s*,#i', text($this->http->Response['body']))) {
            $result['FirstName'] = $s;
        }

        return $result;
    }
}
