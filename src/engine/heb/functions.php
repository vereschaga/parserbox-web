<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerHeb extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.heb.com/loyalty/manage-pcr-account/account-detail';

//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerHebSelenium.php";
//        return new TAccountCheckerHebSelenium();
//    }
    public function InitBrowser()
    {
        parent::InitBrowser();
        //$this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.51 Safari/537.36');
    }

    public static function DisplayName($fields)
    {
        if (isset($fields['Properties']['NextPayout'])) {
            $currentPayoutDate = $fields['Properties']['NextPayout']['Val'];

            return $fields["DisplayName"] . "<br> (Payout on {$currentPayoutDate})";
        }

        return $fields["DisplayName"];
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $data = '[{"operationName":"loginForm","variables":{},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"1b37fc03ca9ed4c2db8e60f02582efc8c82681690f837d12e5bbad4dfdd5ebfd"}}}]';
        $headers = [
            "Accept"                       => "*/*",
            "Accept-Language"              => "en-US,en;q=0.5",
            "Accept-Encoding"              => "gzip, deflate, br",
            "content-type"                 => "application/json",
            "apollographql-client-name"    => "WebPlatform-EXO (Production)",
            "apollographql-client-version" => "31eba4750ee2826ad92b021140f3b33f13afc0e6",
        ];
        $this->http->PostURL("https://www.heb.com/graphql", $data, $headers);
        $response = $this->http->JsonLog(null, 3, false, 'versionToken');
        $versionToken = $response[0]->data->termsOfService->versionToken ?? null;

        if (empty($versionToken)) {
            return false;
        }

        $this->http->GetURL('https://www.heb.com/my-account/login');
//        if (!$this->http->ParseForm('loginForm')) {
//            return $this->checkErrors();
//        }
        /*

        if ($this->http->FindPreg("/window\.recaptchaEnabled = true;/")) {
        */
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        /*
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.value.googleRecaptchaToken', $captcha);
        }
        */
        $reese84 = '3:togDc+VFnnEfm1fWh0dOzQ==:eSfwrTUWEAenFy/g+11T9rTYZ9sTLzxDWc7bSuWw5VA7dcc/0gonNjcNj2SD6+QOdAZXMwcpNu4OkeLWUzqc/JKLa7bVtoeteXtd+KxYOynRrbSovnEbUvDF6Ap2D/XmjrBmoje2pUNWuIvF9Lojs6wGyQsq72RvPXDCqiMP+odqtwrAmgH1WpjkIpVWrBDMDMRILUHktNyzv4cErZu6t76XJGj2r2KeMNWwsTH/8/ADo+mKIRdlc0tGygtideOAhrMmrWDJP9jB24Ww3nFakCUxstiTl/HLphhZYUcjw77EDRJBKijFxYBJ53tSej/N//D4UTIvhKiN+B3W0gx8+5ZR4zSZkvC2yzROGaG245gZPa3elGljijoEbzmPJTukTMtD8+hk/DePf0f5LUliWeNLivgOIzSVyCSRBz6N6srXBIYsrelTRRbDmcRDHVCZToeGtZFOPLIZVkAO3Trqcc9kw23z0pkayAs9vnAl+K4=:AFUpewM5BtlQ20+cC73bxNvTCFGRw/GLNmOgZDroLIY=';
        $this->http->setCookie('reese84', $reese84, ".heb.com");

        $data = [
            "username"       => $this->AccountFields['Login'],
            "password"       => $this->AccountFields['Pass'],
            "recaptchaToken" => $captcha,
            "tosToken"       => $versionToken,
        ];
//        $microtime = round(microtime(true) * 1000);
//        $newrelic = base64_encode('{"v":[0,1],"d":{"ty":"Browser","ac":"1681014","ap":"745594861","id":"beac48aa908a9d04","tr":"b429c93dcbe10196285c352326429480","ti":'.$microtime.'}}');
        $headers = [
            "Accept"          => "*/*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "content-type"    => "application/json",
            //            "traceparent" => "00-b429c93dcbe10196285c352326429480-beac48aa908a9d04-01",
            //            "tracestate" => "1681014@nr=0-1-1681014-745594861-beac48aa908a9d04----".$microtime,
            //            "x-newrelic-id" => "VQAPUFZSDBADU1FTDwACXlQ=",
            //            "newrelic" => $newrelic,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.heb.com/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->bid)) {
            return true;
        }
        // The Email address or password you entered do not match our records
        if (
            $this->http->Response['code'] == 401
            && $this->http->Response['body'] == '{}'
        ) {
            throw new CheckException("The Email address or password you entered do not match our records.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if (strstr($this->http->currentUrl(), 'https://www.heb.com:30143/static/site-maintenance')) {
            throw new CheckException("This site is temporarily down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        // heb.com is currently down for site maintenance to bring you a better experience.
        if ($message = $this->http->FindPreg("/heb.com is currently down for site maintenance to bring you a better experience\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
//        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->seleniumPage();

        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'whitebox']/div[contains(., 'Current Points Balance')]/following-sibling::div[contains(@class, 'pointsTotal')]"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//p[contains(., 'Member Name:')]/following-sibling::p[1]")));
        // set Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//p[contains(., 'Member since')]", null, true, '/Member since\s+(.*)/ims'));
        // set Next Payout
        $this->SetProperty('NextPayout', $this->http->FindSingleNode("//div[contains(@class, 'nextConvert') and contains(., 'Next Payout')]", null, true, '/Next\s*payout:?\s*(.*)/ims'));
        // set To be converted to Dollars on
        $this->SetProperty('ToBeConvertedToDollarsOn', $this->http->FindSingleNode("//div[contains(@class, 'nextConvert') and contains(., 'Points will be converted to Dollars')]", null, true, '/converted to Dollars on\s+(.*)/ims'));

        // SubAccount: H-E-B Reward Dollars
        $rewardDollars = $this->http->FindSingleNode("//div[@class = 'whitebox']/div[contains(., 'H‑E‑B Reward Dollars')]/following-sibling::div[contains(@class, 'pointsTotal')]");

        if ($rewardDollars && $rewardDollars > 0) {
            $this->sendNotification("heb. H-E-B Reward Dollars were detected");
        }
        //# Use your dollars by ...
        $exp = $this->http->FindSingleNode("//div[@class = 'whitebox' and div[contains(., 'H‑E‑B Reward Dollars')]]", null, true, '/Use\s*your\s*dollars\s*by\s*(.*)/ims');

        if (isset($rewardDollars, $exp) && strtotime($exp)) {
            $subAccounts[] = [
                'Code'           => 'hebRewardDollars',
                'DisplayName'    => "H-E-B Reward Dollars",
                'Balance'        => $rewardDollars,
                'ExpirationDate' => strtotime($exp),
            ];
            //# Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }

        $idNodes = $this->http->XPath->query("//div[@class = 'id-card-content']/div[@id]");

        if (isset($idNodes)) {
            $i = 1;

            foreach ($idNodes as $node) {
                if ($i <= 5) {
                    $value = Html::cleanXMLValue($node->nodeValue);

                    if ($value != '') {
                        $this->SetProperty("MemberNo" . $i++, $value);
                    }
                }// if ($i <= 5)
            }// foreach($idNodes as $node)
        }// if (isset($idNodes))

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//h1[contains(., 'link your points club rewards account') or contains(., 'Link Your Points Club Rewards Account')]")) {
                throw new CheckException("Heb (Points Club Rewards) website is asking you to create a one-time link to your Points Club Rewards account, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'hebRewardDollars')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return parent::FormatBalance($fields, $properties);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'loginForm']//div[@class = 'g-recaptcha']/@data-sitekey") ?? '6LefKvEUAAAAAILfHUZFcKKuUirG4qD0_aBnnFUL';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        /*$postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "isInvisible" => true
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);*/

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://www.heb.com/my-account/login?DPSLogout=true',
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function seleniumPage()
    {
        $this->logger->notice(__METHOD__);
        // get cookies from curl
        $allCookies = array_merge($this->http->GetCookies(".heb.com"), $this->http->GetCookies(".heb.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.heb.com"), $this->http->GetCookies("www.heb.com", "/", true));

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $this->logger->info('chosenResolution:');
            $this->logger->info(var_export($chosenResolution, true));
            $selenium->setScreenResolution($chosenResolution);

            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_53);
//        $selenium->disableImages();
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.heb.com/loyalty/manage-pcr-accounts");
            sleep(1);

            foreach ($allCookies as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".heb.com"]);
            }

            $selenium->http->GetURL(self::REWARDS_PAGE_URL);
            // save page to logs
            $selenium->http->SaveResponse();
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }
    }
}
