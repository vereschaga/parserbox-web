<?php

namespace AwardWallet\Engine\vamoose\Credentials;

class Pin extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["reservations@vamoosebus.com", "support@vamoosebus.com"];
    }

    public function getCredentialsSubject()
    {
        return ["#Reminder of your password#i"];
    }

    public function getParsedFields()
    {
        return ["Password", "Name"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Name"] = $this->http->FindPreg("#Dear ([^.]+)\.#ms");
        $pass = $this->http->FindPreg("#Your Password Is:([^\n]+)#ms");
        $result["Password"] = $pass;

        return $result;
    }

    public function GetRetrieveFields()
    {
        return ["Name", "Email"];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL("https://www.vamoosebus.com/site/modules/members/forgotPassword.aspx");

        if (!$this->http->ParseForm("form1")) {
            return false;
        }

        foreach ($this->http->From as $field=>$value) {
            if (strpos($field, 'userName') !== false) {
                $this->http->SetInputValue($field, $data["Login"]);
            } elseif (strpos($field, 'userEmail') !== false) {
                $this->http->SetInputValue($field, $data["Email"]);
            }
        }

        $this->http->PostForm();

        if (!$this->http->FindSingleNode("//*[contains(text(), 'Incorrect Data')]")) {
            return true;
        } else {
            return false;
        }
    }
}
