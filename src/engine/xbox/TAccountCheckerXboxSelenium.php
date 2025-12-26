<?php
require_once __DIR__ . "/../bing/TAccountCheckerBingSelenium.php";

class TAccountCheckerXboxSelenium extends TAccountCheckerBingSelenium
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        // $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);

//        // Switch to english
//        $this->http->GetURL("https://rewards.xbox.com/myrewards/");
//        if ($selector = $this->waitForElement(WebDriverBy::xpath('//a[@id = "locale_selector"]'), 10)) {
//            $this->http->Log('Switch to english');
//            $selector->click();
//            if ($english = $this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'United States')]"), 5))
//                $english->click();
//            $this->waitForElement(WebDriverBy::xpath('//a[@id = "locale_selector"]'), 5);
//        }// if ($selector = $this->waitForElement(WebDriverBy::xpath('//a[@id = "locale_selector"]'), 20))
//        $this->saveResponse();

        return parent::LoadLoginForm();
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        return parent::Login();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        // not a member
        if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Before becoming an Xbox Live Rewards member, you\'ll have to agree to these conditions.")]'), 0)) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // it's from TAccountCheckerBingSelenium.php
//        if ($this->driver->findElements(WebDriverBy::xpath('//*[contains(normalize-space(.), "Tired of waiting for security codes?")]'))) {
//            $this->http->Log('Got "Tired of waiting for security codes?" screen, skipping');
//            try {
//                $this->driver->executeScript('document.getElementById("iDoLater").click()');
//                sleep(5);
//                $this->saveResponse();
//            } catch (UnexpectedAlertOpenException $e) {
//                $this->handleSecurityException($e);
//            }
//        }

        // prevent parsing bugs
        // $logoutLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Sign out")]'), 10, false);
        // if (!$logoutLink) {
        //     $this->logger->error('Authorization failed');
        //     $this->saveResponse();
        //     return;
        // }
        $this->http->GetURL("https://rewards.xbox.com/myrewards/");
        $h5 = $this->waitForElement(WebDriverBy::cssSelector('h5.c-heading'), 10);

        if (!$h5) {
            $this->logger->error('Authorization failed');
            /*
             * Xbox Live Rewards is becoming Microsoft Rewards in Canada
             *
             * Xbox Live Rewards will become Microsoft Rewards in Canada in October.
             * All Xbox Live Rewards offers have ended as of September 30, 2017.
             *
             * Xbox Live Rewards is now Microsoft Rewards in the United States.
             * All Xbox Live Rewards missions have ended as of May 31, 2018.
             */
            if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), \'Xbox Live Rewards will become Microsoft Rewards in Canada in October.\') or contains(text(), "Xbox Live Rewards is now Microsoft Rewards in Canada.") or contains(text(), "Xbox Live Rewards is now Microsoft Rewards in the United States.") or contains(text(), "Later this month, Xbox Live Rewards will become Microsoft Rewards in the United States.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            $this->saveResponse();

            return;
        }

        $this->logger->notice('Proceeding to parsing');
        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "credits_current")]'), 10);
        $this->saveResponse();

        // Balance - You have ... Credits
        $this->SetBalance($this->http->FindSingleNode('//p[contains(@class, "credits_current")]', null, true, '/You have ([\d\.\,]+) Credit/i'));

        if (!isset($this->Balance)) {
            $this->SetBalance($this->http->FindSingleNode('//progress[contains(@class, "c-progress")]/@aria-label', null, true, '/You have ([\d\.\,]+) Credit/i'));
        }
        // Username
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[contains(@class, 'header_gamertag')]"));

        if (!isset($this->Properties['Name'])) {
            $this->SetProperty("Name", $this->http->FindSingleNode("//h5[contains(@class, 'c-heading')]"));
        }
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindPreg("/Member\s*since\s*:\s*([^<]+)/i"));
        // My VIP Status
        $this->SetProperty("MyVIPStatus", $this->http->FindSingleNode('//dt[contains(text(), "MyVIP Status:")]/following-sibling::dd[1]'));

        if (empty($this->Properties['MyVIPStatus'])) {
            $this->SetProperty("MyVIPStatus", $this->http->FindSingleNode('//span[contains(@class, "radial_txt ng-binding")]'));
        }

        if (empty($this->Properties['MyVIPStatus'])) {
            $this->SetProperty("MyVIPStatus", $this->http->FindSingleNode('//span[@id = "myStatsVipLevel"]'));
        }

        if (!empty($this->Properties['MyVIPStatus']) && strtolower($this->Properties['MyVIPStatus']) == 'unlock now') {
            if (!empty($this->Properties['MyVIPStatus']) && strtolower($this->Properties['MyVIPStatus']) == 'unlock now') {
                unset($this->Properties['MyVIPStatus']);
            }
        }
        // MyAchievement Status
        $this->SetProperty("MyAchievementStatus", $this->http->FindSingleNode('//dt[contains(text(), "MyAchievement Status:")]/following-sibling::dd[1]'));

        if (!isset($this->Properties['MyAchievementStatus'])) {
            $this->SetProperty("MyAchievementStatus", $this->http->FindSingleNode('//p[contains(text(), "MyAchievement Tier")]/preceding-sibling::h4[1]'));
        }
        // Total Pending Rewards Credits
        $this->SetProperty("PointsPending", $this->http->FindSingleNode("//h3[@id = 'total_pending']/span"));
        // Lifetime Credits Earned
        $this->SetProperty("LifetimeCreditsEarned", $this->http->FindSingleNode("//h3[contains(text(), 'Lifetime Credits Earned:')]/span"));

        if (!isset($this->Properties['LifetimeCreditsEarned'])) {
            $this->SetProperty("LifetimeCreditsEarned", $this->http->FindSingleNode("//p[contains(text(), 'Lifetime Credits')]/preceding-sibling::h4[1]"));
        }
        // Lifetime Microsoft Points Earned
        $this->SetProperty("LifetimeMicrosoftPointsEarned", $this->http->FindSingleNode("//h3[contains(text(), 'Lifetime Microsoft Points Earned:')]/span"));
        // Next Deposit Date
        $this->SetProperty("NextDepositDate", $this->http->FindSingleNode("//p[@class = 'deposit']", null, true, "/Next\s*Deposit\s*Date\s*([^<]+)/i"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindPreg("/We've received your request to become an Xbox Live Rewards member\. It'll take about 2 days for us to process your request and unlock your door to the entire program\./")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Xbox Live Rewards will become Microsoft Rewards in Australia at the end of April.
             * All Xbox Live Rewards offers have ended as of 31 March 2017.
             */
            if ($message = $this->waitForElement(WebDriverBy::xpath("//p[contains(., 'offers have ended as of 31 March 2017.')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function startURL()
    {
        return 'http://rewards.xbox.com/sign-in';
    }
}
