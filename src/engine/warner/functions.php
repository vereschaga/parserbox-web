<?php

class TAccountCheckerWarner extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        return true;
    }

    public function Login()
    {
        $this->http->GetURL("http://insiderrewards.warnerbros.com/loyalty/loyaltyJSON/loginJSON.action?_dc=1288542327664&disableCaching=true" .
                                    "&email=" . urlencode($this->AccountFields['Login']) .
                                        "&pw=" . urlencode($this->AccountFields['Pass']));

        //# You must complete registration to be able to login
        if ($message = $this->http->FindPreg("/(You must complete registration to be able to login)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $loginResult = $this->http->FindPreg("/loginResult\":\"([^\"]*)/ims");

        if (isset($loginResult) && $loginResult == "success") {
            return true;
        }

        throw new CheckException("Could not log you in. Please check your email and password", ACCOUNT_INVALID_PASSWORD);

        return false;
    }

    public function Parse()
    {
        $fname = $this->http->FindPreg("/firstName\":\"([^\"]*)/ims");
        $lname = $this->http->FindPreg("/lastName\":\"([^\"]*)/ims");
        $sessionKey = $this->http->FindPreg("/bunchballSessionKey\":\"([^\"]*)/ims");

        if (isset($fname) && isset($lname)) {
            $this->SetProperty("Name", beautifulName("$fname $lname"));
        }
        $this->http->GetURL("http://wb.nitro.bunchball.net/nitro/json?method=user.getPointsBalance&pointCategory=all&jsCallback=WB.Application.session.onGetPointsAndCredits&sessionKey=" . $sessionKey . "&noCacheIE=1288546297844");
        $this->SetBalanceNA();
        $subAccounts = [];
        $subAccounts[] = [
            "Code"                  => "Credits",
            "DisplayName"           => "Credits Total",
            "Balance"               => $this->http->FindPreg("/[^\"]*points\":\"([^\"]*)\",\"shortName\":\"Cr\"/ims"),
            "LifetimeCreditsEarned" => $this->http->FindPreg("/[^\"]*lifetimeBalance\":\"([^\"]*)\",\"[^\"]*points\":\"[^\"]*\",\"shortName\":\"Cr\"/ims"),
        ];
        $subAccounts[] = [
            "Code"                 => "Points",
            "DisplayName"          => "Points Total",
            "Balance"              => $this->http->FindPreg("/[^\"]*points\":\"([^\"]*)\",\"shortName\":\"Pts\"/ims"),
            "LifetimePointsEarned" => $this->http->FindPreg("/[^\"]*lifetimeBalance\":\"([^\"]*)\",\"[^\"]*points\":\"[^\"]*\",\"shortName\":\"Pts\"/ims"),
        ];
        $this->SetProperty("SubAccounts", $subAccounts);
    }
}
