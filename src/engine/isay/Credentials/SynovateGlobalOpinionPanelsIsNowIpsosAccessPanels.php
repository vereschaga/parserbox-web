<?php

namespace AwardWallet\Engine\isay\Credentials;

class SynovateGlobalOpinionPanelsIsNowIpsosAccessPanels extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'questions@i-say.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Synovate Global Opinion Panels is Now Ipsos Access Panels!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
