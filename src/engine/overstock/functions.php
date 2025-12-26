<?php

class TAccountCheckerOverstock extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const REWARDS_PAGE_URL = 'https://www.overstock.com/account';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);
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

        $this->http->GetURL('https://www.overstock.com/myaccount');

        if ($this->http->currentUrl() == 'https://login.overstock.com/?redirect_to=https:%2F%2Fwww.overstock.com%2Faccount') {
            $this->SetWarning('Welcome Rewards can be earned and used only at Bed Bath & Beyond. Look for an Overstock loyalty program to launch in the coming months.');

            return false;
        }

        /*if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }*/

        $headers = [
            'Accept'                 => '*/*',
            'Content-Type'           => 'application/json;charset=utf-8',
            'Origin'                 => 'https://login.overstock.com',
            'Next-Action'            => '54f560c931699a3664caf26ba28e26fbde35ccd0',
            'Next-Router-State-Tree' => '%5B%22%22%2C%7B%22children%22%3A%5B%22__PAGE__%3F%7B%5C%22redirect_to%5C%22%3A%5C%22https%3A%2F%2Fwww.overstock.com%2F%5C%22%7D%22%2C%7B%7D%5D%7D%2Cnull%2Cnull%2Ctrue%5D',
        ];
        $this->http->PostURL('https://login.overstock.com/?redirect_to=https:%2F%2Fwww.overstock.com%2Faccount',
            json_encode([$this->AccountFields['Login']]), $headers);

        if ($this->http->FindPreg('/1:false$/')) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $headers = [
            'Accept'                 => '*/*',
            'Content-Type'           => 'application/json;charset=utf-8',
            'Origin'                 => 'https://login.overstock.com',
            'Referer'                => 'https://login.overstock.com/?redirect_to=https:%2F%2Fwww.overstock.com%2Faccount',
            'Next-Action'            => 'cbc836c829ebddd7cca033d708148b6186043f79',
            'Next-Router-State-Tree' => '%5B%22%22%2C%7B%22children%22%3A%5B%22__PAGE__%3F%7B%5C%22redirect_to%5C%22%3A%5C%22https%3A%2F%2Fwww.overstock.com%2F%5C%22%7D%22%2C%7B%7D%5D%7D%2Cnull%2Cnull%2Ctrue%5D',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://login.overstock.com/?redirect_to=https:%2F%2Fwww.overstock.com%2Faccount',
            json_encode([
                $this->AccountFields['Login'],
                $this->AccountFields['Pass'],
                "https://www.overstock.com/",
            ]), $headers);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg('#1:"Email and/or password incorrect\. Please try again or reset your password\."$#')) {
            throw new CheckException('Email and/or password incorrect. Please try again or reset your password.', ACCOUNT_INVALID_PASSWORD);
        }
        $redirectUrl = $this->http->Response['headers']['x-action-redirect'] ?? null;

        if ($redirectUrl) {
            $this->http->GetURL($redirectUrl);

            return true;
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.overstock.com/intlcountryselect?proceedasus=true';
        $arg['SuccessURL'] = 'https://www.overstock.com/myaccount?myacckey=clubo_rewards';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->debug(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'We are currently performing a planned maintenance to our website.')]
                | //div[contains(text(), 'We are currently performing a planned maintenance to our website and customer service systems.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->GetURL('https://www.overstock.com/account');
//        $this->sendSensorData();

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // forcing to be a US customer
        //$this->http->GetURL('https://www.overstock.com/intlcountryselect?proceedasus=true');
        //$this->http->GetURL('https://www.overstock.com/myaccount/#/clubo?TID=MyAcct:Nav:MyClubO');
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(@class, 'clubo-rewards-link')]/text()[1]", null, true, "/My Club O\s*([^<]+)/"));

        $this->http->setDefaultHeader('Referer', 'https://www.overstock.com/myaccount/');
        // load json
        $this->http->GetURL('https://www.overstock.com/api2/myaccount/clubo?noCache=' . time() . date("B"));
        $response = $this->http->JsonLog(null, 3, true);
        // Balance - Available Balance
        if (isset($response['availableBalance'])) {
            $this->SetBalance(ArrayVal($response['availableBalance'], 'formattedValue'));
            // Pending
            $this->SetProperty("RewardsPending", ArrayVal($response['pendingRewards'], 'formattedValue'));
            // Earned
            $this->SetProperty("RewardsEarned", ArrayVal($response['earnedRewards'], 'formattedValue'));
            // Redeemed
            if (isset($response['redeemedRewards'])) {
                $this->SetProperty("RewardsRedeemed", ArrayVal($response['redeemedRewards'], 'formattedValue'));
            }
        } else {
            // isLoggedIn issue
            if (isset($response['errors'][0]['customerMessage']) && $response['errors'][0]['customerMessage'] == 'This request requires you to be signed-in.  Please sign-in and try your request again.') {
                throw new CheckRetryNeededException();
            }

            // not a member
            if (isset($response['membershipStatus'][0]) && $response['membershipStatus'][0] == 'NOT_CLUBO') {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }
        // Expiration Date - Club O Expiration
        $exp = preg_replace("/T.+/", '', ArrayVal($response, 'membershipEndDate'));
        $this->logger->debug("Exp date -> $exp / " . strtotime($exp));

        if (($exp = strtotime($exp)) && $exp > time()) {
            $this->SetExpirationDate($exp);
        }

        //		$this->http->GetURL('https://www.overstock.com/myaccount/#/account/payment-settings/?TID=MyAcct:Nav:PaymentBalances');
        $this->http->GetURL('https://www.overstock.com/api2/myaccount/balances?noCache=' . time() . date("B"));
        $response = $this->http->JsonLog(null, 3, true);
        $balances = ArrayVal($response, 'balances', []);

        foreach ($balances as $balance) {
            switch ($balance['type']) {
                case 'GIFT_CARD':
                    // Gift Card Balance
                    $this->SetProperty("GiftCardBalance", ArrayVal($balance['amount'], 'formattedValue'));

                    break;

                case 'INSTORE_CREDIT':
                    // In-Store Credit
                    $this->SetProperty("InStoreCredit", ArrayVal($balance['amount'], 'formattedValue'));

                    break;

                default:
                    $this->logger->debug("Unknown Balance: {$balance['type']}");

                    break;
            }// switch ($balance['type'])
        }// foreach ($balances as $balance)

        $this->http->GetURL('https://www.overstock.com/myaccount?myacckey=electronic_gift_card&newMyAccount=true&page=gift&TID=MyAcct:Nav:Giftcards');
        // set Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@id="userName"]', null, true, "/Welcome\s*([^!\,<]+)/")));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(text(), "Sign Out") or contains(text(), "Log out")]')) {
            return true;
        }

        return false;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_virgin" . sha1($this->http->getDefaultHeader("User-Agent"));
        $data = $cache->get($cacheKey);

        if (!empty($data)) {
            return $data;
        }

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            /*
            if ($this->http->FindPreg('#Chrome|Safari|WebKit#ims', false, $this->http->getDefaultHeader("User-Agent"))) {
                if (rand(0, 1) == 1)
                    $selenium->useGoogleChrome();
                else
                    $selenium->useChromium();
            }
            else
            */
            $selenium->useFirefox59();

            $selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.overstock.com/myaccount");
            $login = $selenium->waitForElement(WebDriverBy::id('loginEmailInput'), 5);

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($login) {
                $this->logger->info("login form loaded");
                $selenium->driver->executeScript("(function(send) {
                    XMLHttpRequest.prototype.send = function(data) {
                      console.log('ajax');
                      console.log(data);
                      localStorage.setItem('sensor_data', data);
                    };
                })(XMLHttpRequest.prototype.send);");
                $login->click();
                sleep(1);
                $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");
                $this->logger->info("got sensor data: " . $sensor_data);

                if (!empty($sensor_data)) {
                    $data = @json_decode($sensor_data, true);

                    if (is_array($data) && isset($data["sensor_data"])) {
                        $cache->set($cacheKey, $data["sensor_data"], 500);

                        return $data["sensor_data"];
                    }
                }
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $abck = [
            "001332A873F0150EEFDF2592E20EC2EE~0~YAAQEt/aF/igPUCEAQAA1LuJVQj1cQYk5inIHE3I5ON6WvgElpQf0PL2n6BCHlsLm2ec3XfqZcya7MjdqbBzpYio0efpwQy1pjh3Agy9f0Fna4bWwCOvQB3j/ExGu9XM+yab2QtclyaD+zoIUaxQN2PW6DpjualA/w71sQyqgQVpz8azdO80UIVdUmWqOa8fJBSK/4urJOcDRQOJuzr3RVihi4zlsSu17ObjoDeVfrIbAG0WxhZspW9Dao0yMWXTAXPvCOPa6A1XEH9wKh3cO6E3bWDovSrKly4+ciLGxmfQQFxnwsEdhrdcZDUtgNphT46yGRNR0Qqu4ED8j04mka4fEO3HDwnUCmYT9qezyalPMFn43RwJGlYYzpzxxRkERfsnaoSF5caIpqhmPAXmrsj2ZzTIR7t8KvWo~-1~-1~-1",

            "BB972E901DC6727A07156C139327F4F2~0~YAAQG9/aF9V2NAmEAQAAVT20Ewh0w8ADYtZoqNts3fyr60w5MMBqHo7UE1Ep6CzGBl8DnRXmLwgXoZa4OJc7ULDIKG37d8aUUtLOZrs7RxQpkQ8FoqhmZZylQs9LRlVe5NxbBMuoj29Tdk0WAuUKpEK0BA46IbIauFm59kodCoeHsmvCjwij0GBl56wrXZyrqsu/2IjXGpJmPhgpPAj9Qkpkb23umUsbcXlQzC8d/1qf/dACrO8pUmkbdpOWCCMnmkoD08Lx/JeWZnCFi92r3WAZVSh2TlHp8oqVz+60qDubfMqQmvcOD/4lPq/EhgswTGyR+swu2zlw4oSQG7QU3/ZALq4sPd5weVveh78VTwN6IK1obKqDkRqe6ynKzblzYD0s1wWer8o1TCjymYIz7JZou/UoYRiTrg0P~-1~-1~-1",

            "7BF8E7882808861831136886618B9831~0~YAAQG9/aF5mANAmEAQAATXe0EwgWiYqF0J5Y0l2FG1OJ06dIAs9K34ql9Ui/4UCP3ywCjKT7Iq4hQcKAVp2zOBku/M9GpbHxCUHR1iLob2xaQ20FsLgpG+uE/hatBkOhgD3XzS1bwIgiJqeIR5AOAJLHpwsLDRhzkJ9SVJ+nfdmwvGxXoAXBUXH7tGSZU8ibgs+3x/ngeHcv8dN3ZS69k9ZbhoBYcsmPFeb3aH0Ys8TFCpWOeT8Y+LVUgl9vdYp+G/zNHrJv6c6sXqtu36zVkLnX2CDoQoO4zIMmC1ySSw3u3/AHBWsGFb6ryWlbG4O0oDJtixt/XL+PDYynUwjVEpzquNkvd5PYy0t83X5Gx3wbfBiwp1QGFQpxEzBxSq9KEwjF+PZDUmDF4hJuxKGGQTVgEp7+nFS/00pw~-1~-1~-1",
        ];
        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9276691.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400770,5567905,1536,871,1536,960,1536,375,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8968,0.462044931231,814417783952,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,0,0,0,-1,-1,0;0,0,0,0,1434,520,0;1,0,0,0,1797,883,0;0,0,0,0,1102,520,0;1,0,0,0,1465,883,0;-1,2,-94,-102,-1,0,0,0,-1,-1,0;0,0,0,0,1434,520,0;1,0,0,0,1797,883,0;0,0,0,0,1102,520,0;1,0,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.overstock.com/myaccount-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1628835567904,-999999,17424,0,0,2904,0,0,5,0,0,4D6C52E9BE20315FA8421BCDBED879AA~-1~YAAQG9/aF3HeKDx7AQAA5VcqPgaxLZ1Vwk8TsfFysRhJpzh+yaPzGG5I20G5fkFxW9CByzgoYt/EhbaN4QdPQVboYdnT7NaP3mfqfjV2Htfnnoyf0Tl5zProanWZzMXh8lPVXl/UvsX5OH3bPgMZpiexc6JmgWTcfW9+kq9QF3rJimQQ+O/JDbZrRdNxjZsrZByr1YRsLZAsbET2dMRN+SzSTI1EYT+FJwscz9wCppDc1SVUgZPRiVT76wOCaCnZPPVCjB9Di0mKWuoH46oRPQ8vfcCq7fpqW5KxUqBY7dQaKxcGL2J6xFSaY/POnkOtMsB40rShKnrhNTPVxMvUj8bMi8xX8O6qWQR89J5ptfxxwVVy4qO5n2HrlcykZbfBKZe2Ix3EHOz3xuLJsA==~-1~-1~-1,37938,-1,-1,30261693,PiZtE,76046,79,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,16703718-1,2,-94,-118,94356-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9276691.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,400770,5567905,1536,871,1536,960,1536,375,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8968,0.594522238297,814417783952,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,0,0,0,-1,-1,0;0,0,0,0,1434,520,0;1,0,0,0,1797,883,0;0,0,0,0,1102,520,0;1,0,0,0,1465,883,0;-1,2,-94,-102,-1,0,0,0,-1,-1,0;0,0,0,0,1434,520,0;1,0,0,0,1797,883,0;0,0,0,0,1102,520,0;1,0,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.overstock.com/myaccount-1,2,-94,-115,1,32,32,0,0,0,0,1172,0,1628835567904,421,17424,0,0,2904,0,0,1174,0,0,4D6C52E9BE20315FA8421BCDBED879AA~-1~YAAQG9/aF5zeKDx7AQAAHFoqPgYAbkfPGccbsalLasR/KL9x8S/gytOAIhVUa0zAU7Z3Yz4JZfkGB+kvkPywAY+SkVES/brSslgQ3w4XBetTp35QZsQz3Ub0js8bRarvtqRINdxiKhuP58FVPWRTfw2t2JT0Mk7gbeIAUTJse1PdFT72x/dtKwH0J/uU3SzI41JajH0dpmfKwqr0QFROFPZrZKl+NBi6JL5SZV/O+hi5Ks1b3ppX3usAqDyTHNHiAW/O/clo6rUCLgWBFpJ9naE62fAJPxz6ZtmOWqQo5bqdfsINX0Z5sq3RT008M6AsCVmVG6HwhXjr0d0A3R3JOOc/ghrgnMB1uEKRRZ3yi6+GV+9xC4LzmmFLUNue7vTvBN1OJVlvGJ6bk7SQvw==~-1~-1~-1,36849,54,886358641,30261693,PiZtE,17608,58,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,20,20,20,20,20,20,20,0,20,0,0,1320,1280,220,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5575-1,2,-94,-116,16703718-1,2,-94,-118,96675-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;83;9;0",
        ];

        if (count($sensorData) !== count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;
        $this->http->setDefaultHeader("Referer", $referer);

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }
}
