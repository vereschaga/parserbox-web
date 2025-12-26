<?php

namespace AwardWallet\Engine\swagbucks\Credentials;

class SwagbuckscomRegistration extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'info@swagbucks.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Swagbucks.com Registration',
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
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#\s*(.*),\s+Thank\s+you\s+for\s+registering#i', $text);

        return $result;
    }
}
