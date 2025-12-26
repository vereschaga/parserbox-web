<?php
/**
 * Class TAccountCheckerStride
 * Display name: Stride Rite Rewards
 * Database ID: 758
 * Author: MTomilov
 * Created: 22.05.2015 13:00.
 */
class TAccountCheckerStride extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.striderite.com/en/account");
        $forms = $this->http->findNodes('//form[@id = "dwfrm_login"]/@action');
        $this->logger->debug('>>> forms');
        $this->logger->debug(print_r($forms, true));

        if (!$this->http->ParseForm("dwfrm_login")) {
            return false;
        }
        $this->logger->debug('>>> parsed form');
        $this->logger->debug(print_r($this->http->Form, true));

        if (false === filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL)) {
            throw new CheckException('The email address is invalid', ACCOUNT_INVALID_PASSWORD);
        }

        $usernameKey = $this->GetUsernameKey();

        if (!$usernameKey) {
            $this->logger->debug('Cannot find username field.');

            return false;
        }

        $this->http->SetInputValue($usernameKey, $this->AccountFields['Login']);
        $this->http->SetInputValue("dwfrm_login_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue('dwfrm_login_login', 'login');

        return true;
    }

    /*function GetRedirectParams($targetURL = null) {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.striderite.com";
        $arg["SuccessURL"] = "https://www.striderite.com/en/account";

        return $arg;
    }*/

    public function GetUsernameKey()
    {
        return $this->http->findPreg('/(dwfrm_login_username_(\w+))/');
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // yes right after post
        if ($this->http->FindPreg('/"null"\.replace/')) {
            throw new CheckException('Sorry, this does not match our records. Check your spelling and try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($url = $this->http->FindPreg('/var url = "([^\"]+)/')) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            // If you are a registered user, please enter your email and password.
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, the email/password combination entered was not found.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        $this->http->GetURL('https://www.striderite.com/en/account');

        if ($this->http->FindSingleNode("(//a[contains(@href, 'Logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode('//*[@class = "account-logout"]/preceding::span[1]');
        $this->SetProperty("Name", beautifulName($name));

        /*/ To Next Reward
        $toNextReward = $this->http->FindPreg(
            '/You are\s+(.+?)\s+away from your next Reward!/i');
        $this->SetProperty("ToNextReward", CleanXMLValue($toNextReward));*/

        $this->http->GetURL('https://www.striderite.com/on/demandware.store/Sites-striderite_us-Site/default/Loyalty_Rewards-Start');

        // Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Current Balance:')]", null, false, '/:\s*(\d+)\s*$/'));

        // Membership Number
        $memberNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Member Number:')]", null, false, '/:\s*(\d+)\s*$/');
        $this->SetProperty('MemberNumber', $memberNumber);

        // Rewards Member Since
        $memberSince = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Rewards Member Since:')]", null, false, '/:\s*(\d+\/\d+\/\d{4})/');
        $this->SetProperty('MemberSince', $memberSince);

        if ($this->http->FindSingleNode("//legend[. = 'Available Rewards']/following-sibling::text()[1][normalize-space(.) != 'No Available Rewards']")) {
            $this->sendNotification('stride: Appeared available rewards.');
        }
    }
}
