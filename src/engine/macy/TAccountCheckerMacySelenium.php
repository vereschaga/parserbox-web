<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMacySelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        //$this->KeepState = true;
        $this->UseSelenium();
        $this->useChromium();
        //$this->http->SetProxy($this->proxyDOP());
        //$this->http->setRandomUserAgent(30);
        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36');
        //$this->http->setHttp2(true);

        //$this->useCache();
        //$this->disableImages();
        $this->keepCookies(false);
        //$this->keepSession(false);
        $this->http->LogHeaders = true;
        $this->http->saveScreenshots = true;
    }

//    function IsLoggedIn() {
//        $this->http->GetURL('https://www.macys.com/xapi/loyalty/v1/starrewardssummary?_=' . time() . date("B"));
//        $response = $this->http->JsonLog($this->http->FindPreg('#<pre.+?(.+?)</pre>#'), true);
//        if (isset($response->cardDefaultToWallet))
//            return true;
//        return false;
//    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter your email address in this format: jane@company.com", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL('https://www.macys.com/account/signin');
        sleep(rand(1, 3));
        $login = $this->waitForElement(WebDriverBy::id('email'), 7);
        $pass = $this->waitForElement(WebDriverBy::id('pw-input'), 0);
        $button = $this->waitForElement(WebDriverBy::id('sign-in'), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return $this->checkErrors();
        }

        $login->click();
        $login->sendKeys($this->AccountFields['Login']);
        $pass->click();
        $password = $this->AccountFields['Pass'];
        $pass->sendKeys($password);
        $this->saveResponse();
        //$button->click();
        $this->driver->executeScript("document.getElementById('sign-in').click()");

        return true;
    }

    public function checkErrors()
    {
        // maintenance
//        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Thank you for visiting bloomingdales.com. We are in the process of upgrading our site')]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // Access Denied
        if ($this->http->ParseForm("memberSignInForm") || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            throw new CheckRetryNeededException(3, 7);
        }

        return false;
    }

    public function Login()
    {
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::id("sec-cpt-if"), 0)) {
            $this->waitFor(function () {
                return !$this->waitForElement(\WebDriverBy::id("sec-cpt-if"), 0);
            }, 120);

            // broken script workaround
            if ($this->waitForElement(\WebDriverBy::id("sec-cpt-if"), 0)) {
                throw new CheckRetryNeededException();
            }

            $this->saveResponse();
        }

        if ($this->waitForElement(WebDriverBy::xpath("//a[@id='myRewardsLabel-status']/span[contains(text(),'Hi, ')]"), 7)) {
            return true;
        }

        // That email address & password combination isn’t in our records. Forgot your password?
        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id='msg' and contains(text(),'hat email address & password combination isn')]"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $this->browser->LogHeaders = true;
        $this->browser->setUserAgent($this->http->getDefaultHeader("User-Agent"));

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $headers = [
            "Accept"       => "application/json, text/javascript, */*; q=0.01",
            "Content-Type" => "application/json",
        ];
        $data = [
            "SignIn" => ["email" => $this->AccountFields['Login']],
        ];
        $this->browser->PostURL("https://www.macys.com/account-xapi/api/account/signin?cm_sp=navigation-_-top_nav-_-account", json_encode($data), $headers);
        $response = $this->browser->JsonLog();

        if (!isset($response->user->tokenCredentials->userGUID)) {
            $this->logger->error("userGUID not found");

            return;
        }
        // Name
        $this->SetProperty("Name", beautifulName(($response->user->firstName ?? null) . " " . ($response->user->lastName ?? null)));

//        $this->browser->GetURL('https://www.macys.com/xapi/loyalty/v1/starrewardssummary?https://www.macys.com/xapi/loyalty/v1/starrewardssummary?_application=SITE&_deviceType=DESKTOP&_fields=cardDefaultToWallet,emailLookupMembershipExists,tierInfo(tierName,pointsTrackerMediaPool),rewardsInfo(currentPoints,totalStarRewardCardsBalance,anyRewardCardExpiringSoon,showExclusionsAndDetailsLink),ltySRAccountDefaultPool&_=' . time() . date("B"), [
//            'Accept' => 'application/json, text/javascript, */*; q=0.01',
//            'x-macys-requestid' => $response->user->tokenCredentials->userGUID,
//            'x-macys-signedin' => 1,
//            'x-macys-uid' => $response->user->tokenCredentials->userID,
//            'x-requested-with' => 'XMLHttpRequest'
//        ]);

        $this->browser->GetURL('https://www.macys.com/xapi/loyalty/v1/starrewardssummary?_='
            . time() . date("B"), [
                'Accept'            => 'application/json, text/javascript, */*; q=0.01',
                'x-macys-requestid' => $response->user->tokenCredentials->userGUID,
                'x-macys-signedin'  => 1,
                'x-macys-uid'       => $response->user->tokenCredentials->userID,
                'x-requested-with'  => 'XMLHttpRequest',
            ]);
        $response = $this->browser->JsonLog();

        if (isset($response->rewardsInfo, $response->rewardsInfo->currentPoints, $response->tierInfo->tierName)) {
            // 0 current points
            $this->SetBalance($response->rewardsInfo->currentPoints);
            // Status
            $this->SetProperty('Status', $response->tierInfo->tierName);
            /*
            // 0 pending points
            $this->SetProperty('PendingPoints', intval($response->rewardsInfo->pendingPoints));
            // 1000 points until your next reward!
            $this->SetProperty('PointsUntilNextReward', intval($response->rewardsInfo->pointsToNextAward));
            */
            // YOU'VE SPENT:
            $this->SetProperty('MoneySpent', '$' . $response->tierInfo->yearToDateSpend);

            // Exp Date - Enjoy Platinum benefits through 12/31/2018!
            if (isset($response->tierInfo->tierTrackerInfo->currentTierDescription)) {
                $currentTierDescription = $response->tierInfo->tierTrackerInfo->currentTierDescription;

                if ($exp = $this->browser->FindPreg('#through (\d+/\d+/\d{4})[!.]#i', false, $currentTierDescription)) {
                    if (strtotime($exp, false)) {
                        $this->SetProperty('StatusExpiration', $exp);
                    }
                }
            }

            // 	Spend to the next tier
            $this->SetProperty('SpendToTheNextTier', '$' . $response->tierInfo->spendToNextUpgrade);

            // MoneyRetainStatus - To maintain your Platinum benefits through 12/31/2019, spend an additional $1066.35 at Macy\'s on your Macy\'s Credit Card by 12/31/2018.
            if (isset($response->tierInfo->tierTrackerInfo->maintainCurrentTierDescription)) {
                $maintainCurrentTierDescription
                    = $response->tierInfo->tierTrackerInfo->maintainCurrentTierDescription;

                if ($value
                    = $this->http->FindPreg('# spend an additional (\$[\d.,]+) at #', false,
                    $maintainCurrentTierDescription)
                ) {
                    $this->SetProperty('MoneyRetainStatus', $value);
                }
            }
        } elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (isset($response->cardDefaultToWallet) && !isset($response->maskedCardNumber) && $response->cardDefaultToWallet === false) {
                $this->SetBalanceNA();
            }
            // AccountID: 5265619
            elseif (isset($response->walletCardsIndicator) && !isset($response->maskedCardNumber) && $response->walletCardsIndicator === "NoCards") {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
            // We're sorry! It looks like there's an issue with Star Rewards. Please try again later.
            elseif (isset($response->errors->error[0]->message) && strstr($response->errors->error[0]->message, 'We\'re sorry! It looks like there\'s an issue with Star Rewards. Please try again later.')) {
                $this->SetWarning($response->errors->error[0]->message);
            }
        }
    }
}
