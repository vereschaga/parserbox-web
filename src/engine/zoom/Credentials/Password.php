<?php

namespace AwardWallet\Engine\zoom\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'support@zoombucks.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "ZoomBucks.com - Lost Password",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Password",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Password'] = re('#Your password is as follows:\s+(\S+)#i', text($this->http->Response['body']));
        $result['Login'] = re('#\(for\s+account\s+(.*?)\)#i', text($this->http->Response['body']));

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
        $this->http->FilterHTML = false;
        $this->http->GetURL('http://www.zoombucks.com/forgot_password.php');

        if (!$this->http->ParseForm(null, 1, false, "//form[@name='forgot_password']")) {
            return false;
        }

        $this->http->SetInputValue("email_address", $data["Email"]);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'The lost password was sent to the specified email address')]")) {
            return true;
        } else {
            return false;
        }
    }
}
