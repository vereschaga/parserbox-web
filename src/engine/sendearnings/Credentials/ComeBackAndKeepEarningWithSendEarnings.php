<?php

namespace AwardWallet\Engine\sendearnings\Credentials;

class ComeBackAndKeepEarningWithSendEarnings extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'paidemail@sendearnings.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Come back and keep earning with SendEarnings',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['FirstName'] = re('#Hello\s+(.*?),#i', $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
