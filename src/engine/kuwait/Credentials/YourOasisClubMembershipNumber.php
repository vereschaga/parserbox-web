<?php

namespace AwardWallet\Engine\kuwait\Credentials;

class YourOasisClubMembershipNumber extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'login-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'oasisclub@kuwaitairways.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your Oasis Club Membership Number',
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
        $result['Login'] = re('#membership\s+number\s+is\s+(\d+)\s*\.#i', $this->text());
        $result['Name'] = re('#Dear\s+M[rs]+\s+(.*?),\s+#i', $this->text());

        return $result;
    }
}
