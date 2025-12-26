<?php

// Feature #4624
class TAccountCheckerLabatt extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.labattbluerewards.com/rewards");
        // Site checks user's age on the first visit
        if (!$this->http->ParseForm(null, 1, "//form[@action = '/info/lda']")) {
            return false;
        }
        $this->http->Form['month'] = '5';
        $this->http->Form['day'] = '17';
        $this->http->Form['year'] = '1985';
        $this->http->PostForm();
        // Then redirects to the formal login URL with the login form
        if (!$this->http->ParseForm(null, 1, "//form[@id = 'login-area']")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('login[username]', $this->AccountFields['Login']);
        $this->http->SetInputValue('login[password]', $this->AccountFields['Pass']);
        // Actual login URL
        $this->http->FormURL = 'http://www.labattbluerewards.com/auth/ajaxLogin/';

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['URL'] = 'http://www.labattbluerewards.com/auth/ajaxLogin/';
        $arg['PostValues'] = [
            "fbconnect"       => "0",
            "login[username]" => $this->AccountFields['Login'],
            "login[password]" => $this->AccountFields['Pass'],
        ];
        $arg['SuccessURL'] = 'http://www.labattbluerewards.com/account';

        return $arg;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindPreg("/(Oops\.\s*It looks like something unexpected must have happened\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        // by some reason the sandbox gives a "failed to parse form:" message after PostForm(), probably because of the ajax nature of the script
        if (!$this->http->PostForm()) {
            return false;
        }
        // {"isValid":true,"redirect":"\/account","firstName":"...","points":"..."} - ajax result example for a valid login/password pair
        $response = json_decode($this->http->Response['body']);

        if (isset($response->isValid) && $response->isValid) {
            return true;
        }

        // 'Sorry, this username and password is not recognized.' is quoted from the site.
        if (isset($response->isValid) && $response->isValid == false) {
            throw new CheckException('Sorry, this username and password is not recognized.', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL('http://www.labattbluerewards.com/account');
        // Available Points
        $this->SetBalance($this->http->FindPreg('#Available Points:<.*?"points-value">(.*?)</td>#ims'));
        // Career Points
        $this->SetProperty("CareerPoints", $this->http->FindSingleNode("//tr[@id='points-your-score']/td[@class='points-value']"));
        // Your Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//tr[@id='points-your-rank']/td[@class='points-value']"));
        // Your next status
        $this->SetProperty("YourNextStatus", $this->http->FindSingleNode("//tr[@id='points-next-rank']/td[@class='points-value']/span[@class='rank']"));
        // You need Career Points of 1,000 and above to get this status
        $this->SetProperty("PointsTillTheNextStatus", $this->http->FindSingleNode("//tr[@id='points-next-rank']/td[@class='points-value']/span[@class='points']"));
        // Welcome\n$Name
        $this->SetProperty("Name", $this->http->FindPreg('#Welcome<br><span>(.*?)</s#ims'));
    }
}
