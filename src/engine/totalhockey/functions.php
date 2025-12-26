<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerTotalhockey extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://www.purehockey.com/myaccount.aspx";

    private $selenium = true;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        if ($this->selenium === true) {
            $this->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->setScreenResolution($resolution);
            $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $this->setKeepProfile(true);
            $this->disableImages();
            $this->useCache();
            $this->http->saveScreenshots = true;
        }
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

        if ($this->selenium === true) {
            $this->selenium();

            return true;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->http->RetryCount = 2;
        $wsh = $this->http->FindPreg("/wsh\s*=\s*'([^\']+)/");

        if (!isset($wsh)) {
            return false;
        }

        $headers = [
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
        ];
        $data = json_encode([
            "wsh"             => $wsh,
            "email"           => $this->AccountFields['Login'],
            "md5_pwd"         => md5($this->AccountFields['Pass']),
            "enableLoyalty"   => false,
            "isFromCheckout"  => "false",
            "isKioskTransfer" => "false",
        ]);
        $this->http->PostURL('https://www.purehockey.com/WebServices/AccountService.asmx/Login', $data, $headers);

        return true;
    }

    public function Login()
    {
        if ($this->selenium === true) {
            if ($this->loginSuccessful()) {
                return true;
            }

            if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error-box')]")) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'Incorrect password.')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'The account associated with this email is locked.')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return false;
        }

        $response = $this->http->JsonLog();

        if (isset($response->d->returnData->errors[0]->message)) {
            $message = $response->d->returnData->errors[0]->message;
            $this->logger->error("[Error]: {$message}");

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/"success":true/')) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return $this->loginSuccessful();
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", Html::cleanXMLValue($this->http->FindHTMLByXpath("//*[@id='settings']/div/text()[1]")));
        // Available Rewards
        $ar = explode(' ', Html::cleanXMLValue($this->http->FindHTMLByXpath("//*[@id='loyalty-info']//div[contains(@class, 'total') and contains(text(), 'Rewards')]/text()")));
        $this->SetProperty("AvailableRewards", $ar[0]);

        // refs #15930

        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//h2[starts-with(normalize-space(text()), 'Pure Rewards ')]", null, false, '/Pure Rewards (.+)/'));
        // Breakaway ID
        $this->SetProperty("BreakawayID", $this->http->FindSingleNode("//div[@class = 'card-num']"));
        // Member Since
        $this->SetProperty("ActivatedOn", $this->http->FindSingleNode("//h3[contains(text(), 'Member Since')]", null, true, "/:\s*([^<]+)/"));
        // Lifetime Points
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode("//div[contains(., 'LIFETIME PURE REWARD POINTS')]/span"));
        // Points to Next Reward
        $this->SetProperty("PointsToNextReward", $this->http->FindSingleNode("//div[contains(text(), 'TO NEXT GIFT REWARD')]", null, true, "/\(([\d\,\.\s]+)/"));
        // Available Rewards
        $rewards = $this->http->XPath->query("//div[@class = 'loyalty-rewards']/table//tr[td]");
        $this->logger->debug("Total {$rewards->length} were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            // Reward Number
            $number = $this->http->FindSingleNode('td[1]', $reward);
            // Amount
            $balance = $this->http->FindSingleNode('td[2]', $reward);
            // Expires
            $exp = $this->http->FindSingleNode('td[3]', $reward);
            $subAccount = [
                'Code'        => "totalhockeyRewards" . $number,
                'DisplayName' => "Reward #{$number}",
                'Balance'     => $balance,
            ];

            if (strtotime($exp)) {
                $subAccount['ExpirationDate'] = strtotime($exp);
            }

            if (isset($balance)) {
                $this->AddSubAccount($subAccount, true);
            }
        }// foreach ($rewards as $reward)

        if ($profile = $this->http->FindSingleNode("//a[contains(text(), 'Details')]/@href")) {
            $this->http->NormalizeURL($profile);
            $this->http->GetURL($profile);

            // refs #15930
            if ($this->http->currentUrl() == 'https://www.purehockey.com/Error.aspx?aspxerrorpath=/loyaltyinfo.aspx'
                && !empty($this->Properties['BreakawayID']) && !empty($this->Properties['ActivatedOn'])
                && !empty($this->Properties['LifetimePoints']) && !empty($this->Properties['PointsToNextReward'])) {
                $this->SetBalanceNA();
            }
        }
        // Balance - Unused Points
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Unused Points')]/following-sibling::td[1]"));
        // Breakaway ID
        $this->SetProperty("BreakawayID", $this->http->FindSingleNode("//td[contains(text(), 'Rewards ID')]/following-sibling::td[1]"));
        // Member Since
        $this->SetProperty("ActivatedOn", $this->http->FindSingleNode("//td[contains(text(), 'Member Since')]/following-sibling::td[1]"));
        // Last Used On
        $this->SetProperty("LastUsedOn", $this->http->FindSingleNode("//td[contains(text(), 'Last Used On')]/following-sibling::td[1]"));
        // Lifetime Points
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode("//td[contains(text(), 'Lifetime Points')]/following-sibling::td[1]"));
        // Lifetime Rewards
        $this->SetProperty("LifetimeRewards", $this->http->FindSingleNode("//td[contains(text(), 'Lifetime Rewards')]/following-sibling::td[1]"));
        // Points to Next Reward
        $this->SetProperty("PointsToNextReward", $this->http->FindSingleNode("//td[contains(text(), 'Points to Next Reward')]/following-sibling::td[1]"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//h1[starts-with(normalize-space(text()), 'Pure Rewards ')]", null, false, '/Pure Rewards (.+?) Summary/'));
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL(self::REWARDS_PAGE_URL);
        if ($closeBtn = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'us-close']"), 3)) {
            $closeBtn->click();
            $this->saveResponse();
        }
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 5);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'pswd']"), 0);
        $this->saveResponse();

        try {
            if (!$login && $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Checking your browser before accessing')]"), 0)) {
                // save page to logs
                $this->saveResponse();
                $this->http->GetURL(self::REWARDS_PAGE_URL);
                $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 10);
            }
        } catch (StaleElementReferenceException | UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        if ($closeBtn = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'us-close']"), 0)) {
            $closeBtn->click();
            $this->saveResponse();
        }

        if ($login && $pass) {
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name = "sign-in"]'), 0);
            $this->saveResponse();
            $btn->click();

            $this->waitForElement(WebDriverBy::xpath("//div[@class = 'card-num'] | //div[contains(@class, 'error-box')]"), 20);
            $this->saveResponse();

            return true;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // Access is allowed
        if ($this->http->FindSingleNode("//div[@class = 'card-num']")) {
            return true;
        }

        return false;
    }
}
