<?php

namespace AwardWallet\Engine\hallmark\Credentials;

class YourMonthEStatementIsHere extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'hallmark@update.hallmark.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '#Your\s+October\s+E-Statement\s+is\s+here#i',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#\s*(.*)\s+MEMBER\s+NUMBER#i', $text);

        return $result;
    }
}
