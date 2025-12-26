<?php

namespace AwardWallet\Engine\trident\Credentials;

class Pass extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "tridentprivilege@tridenthotels.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Trident Privilege password",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = orval(
            re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text),
            nice(re("#\n\s*Name\s*:\s*(.*?)\s+Membership#is", $text))
        );

        $result['Password'] = orval(
            re("#\n\s*Password[:\s]+([^\s]+)#ix", $text),
            re("#\n\s*Your password is[:\s]+([^\s]+)#ix", $text)
        );

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Login',
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('http://www.tridentprivilege.com/ForgotPassword.aspx');

        if (!$this->http->ParseForm(null, 1, true, "//form[@id='Form1']")) {
            return false;
        }

        $this->http->SetInputValue("txtEmail", $data["Email"]);
        $this->http->SetInputValue("txt_CardNo", $data["Login"]);
        $this->http->SetInputValue("cmbDobdate", 0);
        $this->http->SetInputValue("cmbDobyear", 0);
        $this->http->SetInputValue("cmdSubmit", 'Submit');

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("#Your\s+password\s+has\s+been\s+sent\s+to\s+your\s+email\s+account#msi")) {
            return true;
        } else {
            return false;
        }
    }
}
