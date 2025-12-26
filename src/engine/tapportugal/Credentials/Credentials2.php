<?php

namespace AwardWallet\Engine\tapportugal\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'victoria@tap.pt',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Confidential',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Password',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['LastName'] = re('#Dear\s+M[rs]+\.\s+(.*)#i', $text);
        $result['Password'] = re('#your\s+personal\s+PIN\s+code:\s+(\S+)#i', $text);

        return $result;
    }
}
