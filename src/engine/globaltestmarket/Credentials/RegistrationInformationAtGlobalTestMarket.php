<?php

namespace AwardWallet\Engine\globaltestmarket\Credentials;

class RegistrationInformationAtGlobalTestMarket extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'password-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'frontdesk@globaltestmarket.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Registration information at GlobalTestMarket',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'FirstName',
            'Email',
        ];
    }

    //	function getRetrieveFields() {
    //		return [
    //			...
    //		];
    //	}
//
    //	function RetrieveCredentials($data) {
    //		$this->http->GetURL(...);
    //		$res = $this->http->ParseForm(...);
    //		if (!$res) {
    //			$this->http->Log('Failed to parse form', LOG_LEVEL_ERROR);
    //			return false;
    //		}
//
    //		$this->http->SetInputValue(..., ...);
//
    //		$res = $this->http->PostForm();
    //		if (!$res) {
    //			$this->http->Log('Failed to post form', LOG_LEVEL_ERROR);
    //			return false;
    //		}
//
    //		if ($this->http->FindPreg(...))
    //			return true;
    //		else
    //			return false;
    //	}

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Hi\s+(.*?),#i', $this->text());
        $result['Password'] = re('#Password\s*:\s+(\S+)#i', $this->text());

        return $result;
    }
}
