<?php

namespace AwardWallet\Engine\srilankan\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'flysmiles@srilankan.aero',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Forgotten Password",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);
        $result = [];
        $result["Login"] = re("#Your\s+FlySmiLes\s+Membership\s+Number\D+(\d+)#i", $text);
        $result["Name"] = beautifulName(re("#Dear\s+M[rsi]+\s+(\w+\s+\w+)#", $text));
        $result['Password'] = re('#Your\s+Password\s+is\s+(\S+)#i', $text);

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
        $this->http->FormURL = "https://www.flysmiles.com/home/SubmitForgetPassword";
        $this->http->SetInputValue("fsn", $data["Login"]);

        if (!$this->http->PostForm()) {
            return false;
        }

        $response = json_decode($this->http->Response['body']);

        if ($response->SuccessMsg == 'ok') {
            return true;
        } else {
            return false;
        }
    }
}
