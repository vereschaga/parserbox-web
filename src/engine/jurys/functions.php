<?php

class TAccountCheckerJurys extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.leonardohotels.co.uk/jurysrewards/summary';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.leonardohotels.co.uk/myprofile/login";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.leonardohotels.co.uk/myprofile/login");

        if (!$this->http->ParseForm(null, "//form[@action = '/myprofile/login']")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("rememberMe", "");
        $this->http->SetInputValue("csrfp_token", "false");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Scheduled Website Maintenance
        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "Scheduled Website Maintenance")]
                | //div[contains(normalize-space(), "The website is currently undergoing general maintenance and will be available again shortly. We apologise for any inconvenience.")]
                | //h2[contains(text(), "Website Maintenance")]/following-sibling::node()[contains(., "Our website is currently undergoing general maintenance")]
                | //h2[normalize-space() = "Login is not available at the moment. We are experience technical issues with our rewards programme."]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            if (empty($this->http->Response['body'])) {
                $this->http->RetryCount = 0;
                $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
                $this->http->RetryCount = 2;

                if ($this->loginSuccessful()) {
                    return true;
                }
            }

            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The password has expired. Please reset your password using the forgot password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//section[@id = "errors"]//li[1]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Wrong username or password.'
                || $message == 'The user does not have an active account.'
                || strstr($message, "We couldn't find an account with this username, please try another or register")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "Login is locked.")
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current Points
        $this->SetBalance($this->http->FindSingleNode("(//div[@class='points']/div[@class='points-value'])[last()]"));
        // Name
        $name = $this->http->FindSingleNode("(//div[@class='details']/div[3])[last()]");
        $this->SetProperty("Name", beautifulName($name));
        // Account
        $account = $this->http->FindSingleNode("(//div[@class='details']//div[label[contains(text(), 'Rewards No')]]/following-sibling::div[1])[1]");

        // use does not have any rewards
        if (!$account && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $account = $this->http->FindSingleNode("(//div[@class='details']//div[label[contains(text(), 'Loyalty No')]]/following-sibling::div[1])[1]");

            if (!empty($this->Properties['Name']) && $account) {
                $this->SetBalanceNA();
            }
        }// if (!$account && $this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->SetProperty("Account", $account);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }
}
