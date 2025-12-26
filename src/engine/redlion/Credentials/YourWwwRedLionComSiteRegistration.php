<?php

namespace AwardWallet\Engine\redlion\Credentials;

class YourWwwRedLionComSiteRegistration extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'SysAdmin@RedLion.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your www.RedLion.com Site Registration',
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
        $result['Name'] = re('#Dear\s+(.*?)\s+Thank\s+you#i', $text);

        return $result;
    }
}
