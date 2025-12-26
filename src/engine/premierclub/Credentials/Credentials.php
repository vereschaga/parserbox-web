<?php

namespace AwardWallet\Engine\premierclub\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "customercare@premierclubrewards.org",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Premier Club Rewards",
            "Your Premier Club Rewards password reminder",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "Password",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Password'] = re("#Your Password is\s*:\s*(\S+)#", $this->text());
        $result['Name'] = beautifulName(re("#Dear\s+(\w+\s+\w+)#", $this->text()));

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
        $this->http->GetURL('https://www.premierclubrewards.org/index.php?option=registration&task=forgetpassword');

        if (!$this->http->ParseForm("frmForgetPass")) {
            return false;
        }

        $this->http->SetInputValue("Email", $data["Email"]);
        $this->http->SetInputValue("hdnAction", "ForgetPass");
        $this->http->SetInputValue("cmdForgetPass.x", rand(1, 120));
        $this->http->SetInputValue("cmdForgetPass.y", rand(1, 20));

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'An email will be sent to you within a few minutes.')]")) {
            return true;
        } else {
            return false;
        }
    }
}
