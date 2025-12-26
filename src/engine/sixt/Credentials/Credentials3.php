<?php

namespace AwardWallet\Engine\sixt\Credentials;

class Credentials3 extends \TAccountCheckerExtended
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
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($s = re('#All\s+the\s+best\s+on\s+this\s+4th\s+of\s+July,\s+(.*)!#i', $text)) {
            $result['FirstName'] = $s;
        }

        return $result;
    }
}
