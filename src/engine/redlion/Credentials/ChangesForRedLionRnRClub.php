<?php

namespace AwardWallet\Engine\redlion\Credentials;

class ChangesForRedLionRnRClub extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'redlion@response.redlion.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Changes for Red Lion R&R Club',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+R&R\s+Club\s+Member,\s+(.*?)\s+YOUR\s+ACCOUNT#i', $text);

        return $result;
    }
}
