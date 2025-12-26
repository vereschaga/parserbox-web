<?php

class TAccountCheckerSuperamerica extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // reset cookie
        $this->http->removeCookies();
        // for field 'Last Name'
        if (empty($this->AccountFields['Login2'])) {
            throw new CheckException("To update this SuperAmerica account you need to fill in the 'Last Name' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->http->GetURL('https://superamerica.encryptedrequest.com/LoyaltyHome');

        if (!$this->http->ParseForm('form1')) {
            return false;
        }
        $this->http->Form = [];
        $this->http->setDefaultHeader('X-AjaxPro-Method', 'CheckPinAndCreateLoyaltySession');
        $this->http->PostURL('https://superamerica.encryptedrequest.com/ajaxpro/StationWebsites.SharedContent.Web.Common.Controls.Loyalty.KickBack.Login,StationWebsites.ashx', '{"cardOrPhoneNumber":"' . $this->AccountFields['Login'] . '","pin":"' . $this->AccountFields['Pass'] . '","lastName":"' . $this->AccountFields['Login2'] . '"}');

        return true;
    }

    public function Login()
    {
        $jsonObj = json_decode($this->http->Response['body']);
        $this->http->Log("<pre>" . var_export($jsonObj, true) . "</pre>", false);

        if (isset($jsonObj->value)) {
            switch ($jsonObj->value->ResponseCode) {
                case 1:
                    return true;

                default:
                    // Invalid card, user id, or pin
                    if (isset($jsonObj->value->Message)) {
                        $this->CheckError($jsonObj->value->Message, ACCOUNT_INVALID_PASSWORD);
                    }
            }// switch ($jsonObj->value->ResponseCode)
        }// if (isset($jsonObj->value))

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://superamerica.encryptedrequest.com/LoyaltyHome");
        // Card Number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("//span[@id = 'kickBackHeaderLoggedInAsSpan']"));
        // Balance - Current point balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'kickBackHeaderPoints']"));
    }
}
