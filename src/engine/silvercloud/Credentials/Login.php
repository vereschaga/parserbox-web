<?php

namespace AwardWallet\Engine\silvercloud\Credentials;

class Login extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'rewards@silvercloud.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Silver Rewards Member ID",
        ];
    }

    public function getParsedFields()
    {
        return ["Login"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Login'] = re('#Your\s+Silver\s+Rewards\s+Member\s+ID\s+is\s+(\d+)#i', $this->text());

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://www.silverrewards.com/silverrewardsmember/getidform.cfm');

        if (!$this->http->ParseForm(null)) {
            return false;
        }

        $this->http->SetInputValue("email", $data["Email"]);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("#Your\s+Silver\s+Rewards\s+Member\s+ID\s+has\s+just\s+been\s+sent\s+to#i")) {
            return true;
        } else {
            return false;
        }
    }
}
