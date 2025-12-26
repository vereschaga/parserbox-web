<?php

namespace AwardWallet\Engine\hhonors\Credentials;

class Pin extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["NoReply-Reservations@hilton.com", "NoReply-HHonorsReservations@Hilton.com"];
    }

    public function getCredentialsSubject()
    {
        return ["Hilton Profile Request"];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($pass = $this->http->FindPreg("/Your \"HHonors PIN\" is: (\d{4})/")) {
            $result["Password"] = $pass;
        } elseif ($pass = $this->http->FindPreg("/Your \"Password\" is: ([\w\d\.\,]+)/")) {
            $result["Password"] = $pass;
        }

        return $result;
    }

    public function GetRetrieveFields()
    {
        return ["Login", "Email"];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL("https://secure3.hilton.com/en/hi/customer/login/forgotPassword.htm?forwardPageURI=%2Fen%2Findex.html&WT.bid=Home,,,ac");

        if (!$this->http->ParseForm("formForgotPassword")) {
            return false;
        }
        $this->http->SetInputValue("username", $data["Login"]);
        $this->http->SetInputValue("emailAddress", $data["Email"]);
        $this->http->PostForm();

        if ($this->http->FindSingleNode("//*[contains(text(), 'Please check your email and sign in to continue')]")) {
            return true;
        } else {
            return false;
        }
    }
}
