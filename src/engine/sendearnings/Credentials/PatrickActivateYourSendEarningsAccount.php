<?php

namespace AwardWallet\Engine\sendearnings\Credentials;

class PatrickActivateYourSendEarningsAccount extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'support@sendearnings.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Activate your SendEarnings Account!',
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
        $result['FirstName'] = re('#\s+Hi\s+(.*?),#i', $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
