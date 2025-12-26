<?php

class TAccountCheckerJalhotels extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://oneharmony.com/en/Account/Detail';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
//        $this->http->setHttp2(true);
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
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/en/Account/Login')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'will be temporarily unavailable due to system maintenance')
                or contains(text(), 'The server is currently busy.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The service is unavailable.
        if ($this->http->Response['code'] == 503 && ($message = $this->http->FindPreg("/The service is unavailable\./"))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        if ($this->http->currentUrl() == 'https://oneharmony.com/en/Account/Activation') {
            throw new CheckException("Please complete your profile to activate your account.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error_msg')]/ul/li")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The user name or password provided is incorrect.')
                || strstr($message, 'The login name or password provided is incorrect.')
                || strstr($message, 'The Sign In name or password provided is incorrect.')
                || strstr($message, 'Membership number or email is incorrect.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your account is temporately locked')
                || strstr($message, 'Your password is temporately locked.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Change password
        if ($this->http->currentUrl() == 'https://oneharmony.com/en/Account/ChangePasswordFirstTime') {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current Points Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@class = "point_balance"]/text()[1]'));

        if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR) {
            $this->SetWarning($this->http->FindSingleNode('//div[contains(text(), "Due to the system maintenance, the point balance is not displayed temporarily.")]'));
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@id = "member_name"]')));
        // Membership No.
        $this->SetProperty("MembershipNumber", $this->http->FindSingleNode('//span[@class = "member_num_id"]'));
        // Joined on
        $this->SetProperty("EnrollmentDate", $this->http->FindSingleNode('//div[contains(text(), "Joined on")]', null, true, "/Joined\s*on\s*([^.]+)/"));
        // Level
        $this->SetProperty('EliteLevel', $this->http->FindSingleNode('//span[contains(@class, "member_tier_label")]'));
        // Expires on ...
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode('//span[@id = "card_expiry"]', null, true, "/Expires\s*on\s*(.+)/"));

        // Your Status Progress

        // Status Evaluation Points
        $this->SetProperty("StatusEvaluationPoints", $this->http->FindSingleNode('//div[@class = "tier_icon icon_points"]/text()[1]'));
        // Status Evaluation Nights
        $this->SetProperty("StatusEvaluationNights", $this->http->FindSingleNode('//div[@class = "tier_icon icon_nights"]/text()[1]'));

        // Expiration date
        $nodes = $this->http->XPath->query("//div[contains(text(), 'Expiring on')]/parent::div");
        $this->logger->debug("Total {$nodes->length} exp nodes were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $exp = $this->http->FindSingleNode("div[1]", $nodes->item($i), true, '/Expiring\s*on\s*(.+)/ims');
            $pointsToExpire = $this->http->FindSingleNode("div[2]", $nodes->item($i));
            $this->logger->debug("Points: " . $pointsToExpire);

            if ($pointsToExpire > 0 && strtotime($exp)) {
                // Points to Expire
                $this->SetProperty("ExpiringBalance", $pointsToExpire);
                $this->SetExpirationDate(strtotime($exp));

                break;
            }// if ($pointsToExpire > 0)
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//span[@class = "member_num_id"]')) {
            return true;
        }

        return false;
    }
}
