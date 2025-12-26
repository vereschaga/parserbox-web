<?php

namespace AwardWallet\Engine\niugini\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'destinations@airniugini.com.pg',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Air Niugini Destinations forgot password details",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Password",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($pass = re('#Password\s*:\s*(\S+)#i', $this->text())) {
            $result['Password'] = $pass;
        }

        if ($name = re("#Dear\s+(\w+)#", $this->text())) {
            $result['FirstName'] = beautifulName($name);
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
        $this->http->GetURL('http://www.destinations.com.pg/ForgetPassword.aspx');

        if (!$this->http->ParseForm("form1")) {
            return false;
        }

        $this->http->SetInputValue("txt_memberNumber", $data["Login"]);
        $this->http->SetInputValue("btn_GetDetails", "Send Password");

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(),'Your password has been successfully sent to your email address.')]")) {
            return true;
        } else {
            return false;
        }
    }
}
