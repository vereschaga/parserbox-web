<?php

namespace AwardWallet\Engine\tamair\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'interativo@tam.com.br',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "TAM and LAN: Best Airlines in South America",
            "Where will you celebrate the Star Alliance network's 15th Anniversary?",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Name'] = beautifulName(trim(re('#Hello(,|)\s+(.*?),#i', $text, 2)));

        return $result;
    }
}
