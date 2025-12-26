<?php

class TAccountCheckerSeveneleven extends TAccountChecker
{
    private $xml = '';

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //		$this->http->GetURL("https://www.slurpee.com/");
//        if (!$this->http->ParseForm("logOnModalForm"))
//            return false;
        $this->http->FormURL = 'https://www.slurpee.com/api/membership/login';
        $this->http->Form["UserName"] = $this->AccountFields['Login'];
        $this->http->Form["Password"] = $this->AccountFields['Pass'];

        return true;
    }

    public function Login()
    {
        $this->http->PostURL("https://www.slurpee.com/api/membership/login",
            [
                "Password" => $this->AccountFields['Pass'],
                "UserName" => $this->AccountFields['Login'],
            ]);

        if ($message = $this->http->FindPreg("/(The username or password is incorrect.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/(Validation failure: Valid email address is required\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->xml = @new SimpleXmlElement($this->http->Response['body']);
        $this->http->Log("<pre>" . var_export($this->xml, true) . "</pre>", false);

        if (isset($this->xml->UserId)) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        //# Name
        if (isset($this->xml->FirstName, $this->xml->LastName)) {
            $name = CleanXMLValue($this->xml->FirstName . ' ' . $this->xml->LastName);
        }

        if (isset($name) && $name != '(no name)') {
            $this->SetProperty("Name", beautifulName($name));
        }

        //# Balance - points
        if (isset($this->xml->RewardsPointsAvailable)) {
            $this->SetBalance($this->xml->RewardsPointsAvailable);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.slurpee.com/';
        $arg['SuccessURL'] = 'https://www.slurpee.com/';

        return $arg;
    }
}
