<?php

namespace AwardWallet\Engine\sixt\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'sixt.com@newsletter.sixt.info',
            'no-reply@sixt.de',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#.#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#Mrs\.\s+or\s+Mr\.\s+(.*?)\s*,#i', $text)) {
            $result['LastName'] = $s;
        }

        return $result;
    }
}
