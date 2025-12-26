<?php

namespace AwardWallet\Engine\sunshine\Credentials;

class Pass extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@sunshinerewards.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Sunshine Rewards Password",
        ];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($pass = re('#Password\s*:\s*(\S+)#i', text($this->http->Response['body']))) {
            $result['Password'] = $pass;
        }

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Login',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('http://www.sunshinerewards.com/members.php');

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'members.php?sid')]")) {
            return false;
        }

        $this->http->SetInputValue("email", $data["Login"]);
        $this->http->SetInputValue("action", "resend");

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("#The e-mail should arrive anywhere from \d+ to \d+ minutes from now#i")) {
            return true;
        } else {
            return false;
        }
    }
}
