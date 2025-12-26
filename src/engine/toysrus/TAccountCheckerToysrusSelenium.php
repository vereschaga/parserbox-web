<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerToysrusSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var HttpBrowser
     */
    public $browser;

    protected $collectedHistory = true;
    protected $endHistory = false;
    private $memberid = null;
    private $proxyFound;

    public function InitBrowser()
    {
        parent::InitBrowser();
//        $this->AccountFields['BrowserState'] = null;
//        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
//            $this->http->SetProxy('localhost:8000'); // This provider should be tested locally via proxy
//        }
        $this->UseSelenium();
//        $this->useSeleniumServer("selenium-dev.awardwallet.com");
        $this->useChromium();
//        $this->proxyAll();
        $this->KeepState = false;
        $this->http->setRandomUserAgent();
//        $this->proxyFound = $this->findLuminatiProxy(new HttpDriverRequest("https://rewardsrus.toysrus.com/index.cfm/account#summary", 'HEAD'), 10, 3, function(HttpDriverResponse $response){
//            $this->http->Log("response: $response->httpCode, headers: " . json_encode($response->headers));
//           return ($response->httpCode >= 300 && $response->httpCode < 400) || (!empty($response->headers['content-length']) && intval($response->headers['content-length']) > 500);
//        });
    }

    public function LoadLoginForm()
    {
//        if(!$this->proxyFound)
//            return false;

        $this->http->removeCookies();

//        $this->http->GetURL("http://ipinfo.io");
        $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account#summary");
        $this->providerRetries();

        $formXpath = "//div[@class = 'top-bar']//form[contains(@action, '/index.cfm/login')]";
        $login = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@name = 'strAccountOrEmail']"), 15, true);
        $this->saveResponse();

        if (empty($login)) {
            $this->logger->error('Failed to find "login" input');

            return $this->checkErrors();
        }

        if (!$this->http->ParseForm(null, 1, true, $formXpath)) {
            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);

        $passwordInput = $this->waitForElement(WebDriverBy::xpath($formXpath . "//input[@name = 'strPassword']"), 0);

        if (!$passwordInput) {
            $this->logger->error('Failed to find "password" input');

            return false;
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'something has gone wrong on')]")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'our website is out taking a coffee break')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (preg_match("/static\/unavailable\/section\.html/ims", $this->http->currentUrl())) {
            throw new CheckException("www.starbucks.com is currently unavailable. We are working to resolve the issue
             as quickly as possible and apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR); /*checked*/
        }
        //# The service is unavailable
        if ($message = $this->http->FindSingleNode("//body[contains(text(), 'The service is unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in '\/' Application\.)/ims")
            // Service Temporarily Unavailable
            || $this->http->FindPreg("/(Service Temporarily Unavailable)/ims")
            // We’re sorry — something has gone wrong on our end.
            || $this->http->currentUrl() == 'http://www.starbucks.com/static/error/index.html') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->providerRetries();

        return false;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->setDefaultHeader("User-Agent", $this->http->userAgent);
        $this->browser->GetURL($this->http->currentUrl());
    }

    public function Login()
    {
        $loginButton = $this->waitForElement(WebDriverBy::id('btnLogin'), 0);

        if (!$loginButton) {
            $this->logger->error('Failed to find login button');

            return false;
        }
        $loginButton->click();
        $this->saveResponse();

        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), 10);
        $this->saveResponse();

        if ($logout
            || $this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            $this->browser = $this->http;
//            $this->parseWithCurl();
            return true;
        }

        //# Error while displaying the balance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "an unexpected error occurred")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Error Executing Database Query.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Error Executing Database Query")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Member login or password is incorrect
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Member login or password is incorrect')]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // There was an error processing your request.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There was an error processing your request.')]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // A Valid Account or Email Address is Required.
        if ($message = $this->http->FindPreg("/A Valid Account or Email Address is Required\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Reward Points
        if (!$this->SetBalance($this->browser->FindSingleNode("//span[contains(text(), 'Reward Point')]/following-sibling::span[1]"))) {
            // Our system has identified your account as being associated with purchasing items for resale and as such is no longer eligible for the Rewards“R”Us membership.
            if ($message = $this->browser->FindSingleNode("//h5[contains(text(), 'Our system has identified your account as being associated with purchasing items for resale and as such is no longer eligible for the Rewards“R”Us membership.')]")) {
                $this->ErrorCode = ACCOUNT_WARNING;
                $this->ErrorMessage = $message;
            }
            $this->SetWarning($this->http->FindSingleNode("//b[contains(text(), 'Please be advised that the Rewards\"R\"Us loyalty program has ended on March 22, 2018.')]"));
        }

        $this->browser->GetURL("https://rewardsrus.toysrus.com/index.cfm/account#summary");
        // Member #
        $this->SetProperty("Number", $this->browser->FindSingleNode("(//div[contains(text(), 'Member #')])[1]", null, true, "/Member\s*#\s*([^<]+)/ims"));
        // Name
        $name = $this->browser->FindSingleNode("//input[@name = 'member[fname]']/@value") . ' ' . $this->browser->FindSingleNode("//input[@name = 'member[lname]']/@value");
        $name = str_replace('  ', ' ', trim($name));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        $this->memberid = $this->browser->FindPreg("/memberid\s*=\s*(\d+)/");
        $this->http->Log("memberid -> {$this->memberid}");

        $this->browser->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/summary?randId=66123");
        // "R"Us Reward Dollars
        $this->SetProperty("RewardDollars", $this->browser->FindSingleNode("//h4[contains(text(), 'Rewards Available:')]/following-sibling::h4"));
        // points until your next "R"Us Reward -  points until next Reward
        $this->SetProperty("PointsUntilNextReward", $this->browser->FindSingleNode("//p[contains(., 'points until your next')]", null, true, "/([\d\.\,]+)\s*point/ims"));
        // Pending points
        $this->SetProperty("PendingPoints", $this->browser->FindSingleNode("//h4[contains(text(), 'Pending Points:')]/following-sibling::h4[1]"));

        // SubAccounts - My Rewards
        if (isset($this->memberid)) {
            $this->browser->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getmyrewards?memberid={$this->memberid}&_=" . time() . date("B"));
        }
//        $response = $this->browser->JsonLog(null, false);
        $response = $this->browser->JsonLog($this->http->FindPreg("/<pre[^>]*>(.+)\s*<\/pre>/"), false);

        if (isset($response->certs)) {
            $this->http->Log("Total " . count($response->certs) . " rewards were found");

            foreach ($response->certs as $cert) {
                // Expiration Date
                $release = DateTime::createFromFormat('M, d Y h:i:s', $cert->certexpiredate);
                $exp = $release ? $release->getTimestamp() : null;

                $this->http->Log("Exp date [$cert->certexpiredate]: {$exp}");

                if (isset($cert->certvalue, $cert->suc) && $exp) {
                    $subAccounts[] = [
                        'Code'           => 'toysrusRewards' . $cert->suc,
                        'DisplayName'    => "$" . $cert->certvalue . " \"R\"Us Rewards",
                        'Balance'        => $cert->certvalue,
                        'ExpirationDate' => $exp,
                        'BarCode'        => $cert->suc,
                        "BarCodeType"    => BAR_CODE_CODE_128,
                    ];
                }// if (isset($dollars, $displayName) && $exp && $exp > time())
            }// foreach ($response->certs as $cert)
        }// if (isset($response->certs))

        // SubAccounts - Additional Offers
        if (isset($this->memberid)) {
            $this->browser->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/rendercurrentoffers?memberid={$this->memberid}&_=" . time() . date("B"));
        }
        $this->browser->Response['body'] = str_replace('\/', '/', $this->browser->Response['body']);
        $nodes = $this->browser->XPath->query("//table/tr[td[3]]");
        $this->browser->Log("Total {$nodes->length} Additional Offers were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $displayName = $this->browser->FindSingleNode('td[2]', $nodes->item($i), true, "/([^<]+)/ims");
            $exp = str_replace('\/', '/', $this->browser->FindSingleNode('td[3]', $nodes->item($i), true, "/Through\s*([^<]+)/ims"));
            $this->browser->Log("Exp date: {$exp}");
            $subAccounts[] = [
                'Code'           => 'toysrusOffers' . $i,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ];
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($subAccounts)) {
            //# Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            $this->browser->Log("Total subAccounts: " . count($subAccounts));
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))

        // Expiration date  refs #10812
        if (isset($this->memberid)) {
            $this->browser->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getMemberPurchases?memberid={$this->memberid}&sort=transdate&order=desc&limit=10&offset=0");
        }
//        $response = $this->browser->JsonLog(null, false);
        $response = $this->browser->JsonLog($this->http->FindPreg("/<pre[^>]*>(.+)\s*<\/pre>/"), false);

        if (isset($response->rows)) {
            $this->http->Log("Total " . count($response->rows) . " exp nodes were found");

            foreach ($response->rows as $transaction) {
                $lastActivity = $transaction->transdate;
                // Last Activity
                $this->SetProperty("LastActivity", $lastActivity);
                // Points Earned
                $points = CleanXMLValue($transaction->transpoints);

                $this->browser->Log("[Last Activity]: {$transaction->transdate} - {$points}");

                if ($points > 0 && strtotime($lastActivity)) {
                    $this->SetExpirationDate(strtotime("+24 months", strtotime($lastActivity)));

                    break;
                }// if (($points > 0 || $points < 0) && strtotime($lastActivity))
            }// foreach ($response->rows as $transaction)
        }// if (isset($response->rows))
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);

        if (!$this->collectedHistory) {
            return $result;
        }

        $page = 0;
//        $endDate = date("m/d/Y");
//        $start = date("m/01/Y", strtotime("-6 month"));
//        $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getpurchases?startDate={$start}&endDate={$endDate}&_=".time().date("B"));
//        do {
//            $page++;
//            $this->http->Log("[Page: {$page}]");
//            if ($page > 1) {
//                $endDate = date("m/d/Y", strtotime("-1 day", strtotime($start)));
//                $start = date("m/d/Y", strtotime("-3 year", strtotime($start)));
//                $this->http->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getpurchases?startDate={$start}&endDate={$endDate}&_=".time().date("B"));
//            }
//            $startIndex = sizeof($result);
//            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
//        } while ($page <= 1 && !$this->endHistory);

        $this->http->Log("[Page: {$page}]");

        if (isset($this->memberid)) {
            $this->browser->GetURL("https://rewardsrus.toysrus.com/index.cfm/account/getMemberPurchases?memberid={$this->memberid}&sort=transdate&order=desc&limit=10000&offset=0");
        }
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"   => "PostingDate",
            "Store"  => "Description",
            "Qty"    => "Info",
            "Total"  => "Info",
            "Points" => "Miles",
        ];
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->browser->JsonLog(null, false);

        if (!isset($response->rows)) {
            return $result;
        }
        $this->http->Log("Found " . count($response->rows) . " items");

        foreach ($response->rows as $row) {
            $dateStr = $row->transdate;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->http->Log("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Store'] = $row->ace01description;
            $result[$startIndex]['Qty'] = $row->transitems;
            $result[$startIndex]['Total'] = $row->traspurchase;
            $result[$startIndex]['Points'] = trim($row->transpoints);
            $startIndex++;
        }

        return $result;
    }

    protected function providerRetries()
    {
        $this->logger->notice(__METHOD__);
        // retries
        if ($message = $this->http->FindPreg("/(?:The requested URL was rejected\.|The <span jscontent=\"hostName\" jstcache=\"16\">rewardsrus.toysrus.com<\/span> page isn’t working<\/h1>)/")) {
            $this->DebugInfo = strip_tags($message);

            throw new CheckRetryNeededException(3, 1);
        }
    }
}
