<?php

namespace AwardWallet\Engine\nordic\Credentials;

class NordicChoiceClubStatement extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'newsletter@choice.no',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Nordic Choice Club statement',
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
        $result['Email'] = $parser->getCleanTo();
        $text = str_replace('-->', '', $this->text());

        $result['Login'] = re('#CC\s+no\s*:\s*(\d+)#', $text);
        $result['Name'] = re('#Name:\s+(.*)\s+CC\s+no:#i', $text);

        return $result;
    }
}
