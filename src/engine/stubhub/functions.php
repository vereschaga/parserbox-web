<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerStubhub extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->FilterHTML = false;
        $this->http->LogHeaders = true;

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
//            $proxy = $this->http->getLiveProxy("https://m.stubhub.com/mobile/auth/?t=account");
//            $proxy = $this->http->getLiveProxy("https://myaccount.stubhub.com/myaccount/rewards");
//            $this->http->SetProxy($proxy);
            $this->http->SetProxy($this->proxyDOP());
        }// if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
    }

//    public static function GetAccountChecker($accountInfo){
//        if ((ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG || ArrayVal($accountInfo, 'Partner') == 'awardwallet')) {
//            require_once __DIR__."/TAccountCheckerStubhubSelenium.php";
//            return new TAccountCheckerStubhubSelenium();
//        }
//        else
//            return new static();
//    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // Mobile version
        $this->http->setDefaultHeader("User-Agent", 'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0_2 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) CriOS/30.0.1599.12 Mobile/11A501 Safari/8536.25');
        $this->http->getURL("https://m.stubhub.com/mobile/auth/?t=account");

        if ($this->detectCaptcha() === false) {
            return false;
        }
        // parsing form on the page
        if (!$this->http->FindPreg('/form name="signinform" id="signinform"/ims')) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "https://m.stubhub.com/loginapi/1.0/login";
        $this->http->Form = [];
        $this->http->SetInputValue("username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);

        //		$this->http->GetURL("https://myaccount.stubhub.com/myaccount/rewards");
//        if ($this->detectCaptcha() === false)
//            return false;
        //		// parsing form on the page
        //		if (!$this->http->ParseForm(null, 1, true, '//form[contains(@action, "/login/signin.logincomponent_0.signinform")]'))
//            return $this->checkErrors();
//        $this->http->FormURL = "https://myaccount.stubhub.com/login/signin.logincomponent_0.signinform";
//        $this->http->SetInputValue("loginEmail", $this->AccountFields["Login"]);
        //		$this->http->SetInputValue("loginPassword", $this->AccountFields["Pass"]);
//        $this->http->SetInputValue("signIn", "signIn");

        return true;
    }

    public function detectCaptcha()
    {
        $this->http->Log("detectCaptcha");

        if ($url = $this->http->FindSingleNode("//meta[contains(@content, 'captcha.htm')]/@content")) {
            $url = explode(';', $url);

            foreach ($url as $u) {
                if (strpos($u, 'url=') !== false) {
                    $url = trim(str_replace('url=', '', $u));

                    break;
                }
            }
            $this->http->NormalizeURL($url);
            $this->http->Log("Captcha Url: {$url}");
            $this->http->GetURL($url);

            if ($captcha = $this->parseCaptcha()) {
                if (!$this->http->ParseForm(null, 1)) {
                    $this->http->Log("parseCaptcha Error", LOG_LEVEL_ERROR);
                }
                $this->http->SetInputValue('recaptcha_challenge_field', $captcha);

                if (!$this->http->PostForm()) {
                    return false;
                }
                $this->http->Log($this->http->Response['body']);

                return true;
            }
        }

        return true;
    }

    public function parseCaptcha()
    {
        $this->http->Log("parseCaptcha");
        $http2 = clone $this->http;

        $this->http->Log("Get Captcha Iframe");
        $iframeSrc = $http2->FindSingleNode("//iframe/@src");

        if (!$iframeSrc) {
            return false;
        }
        $http2->GetURL($iframeSrc);

        $this->http->Log("Parse Captcha Form");

        if (!$http2->ParseForm(null, 1, true, "//form")) {
            return false;
        }

        $formURL = $http2->FormURL;
        $form = $http2->Form;

        $this->http->Log("Get Captcha Image Url");

        if (!$imgurl = $http2->FindSingleNode("//img/@src")) {
            return false;
        }

        $imgurl = 'https://www.google.com/recaptcha/api/' . $imgurl;
        $this->http->Log("Captcha Image Url: {$imgurl}");

        $this->http->Log("Get Captcha Image");
        $file = $http2->DownloadFile($imgurl, "jpg");
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        try {
            $this->http->Log("Recognize Captcha");
            $captcha = trim($recognizer->recognizeFile($file));
        } catch (CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");

                throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // retries
            if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                || $e->getMessage() == 'timelimit (60) hit'
                || $e->getMessage() == 'slot not available') {
                $this->http->Log("parseCaptcha", LOG_LEVEL_ERROR);

                throw new CheckRetryNeededException(3, 7);
            }

            return false;
        }
        unlink($file);

        $this->http->Log("Post Captcha Form");
        $http2->FormURL = $formURL;
        $http2->Form = $form;
        // unset($http2->Form['reason']);
        $http2->SetInputValue('recaptcha_response_field', $captcha);
        $http2->PostForm();
        $this->http->Log("Response:\n" . $http2->Response['body']);

        if ($error = $http2->FindPreg("/>(Try again|Повторите попытку)/")) {
            $this->http->Log($error, LOG_LEVEL_ERROR);
        }
        $result = $http2->FindSingleNode("//textarea");

        return $result;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'performing scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, but the StubHub website is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@class='errorMsg']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // mobile version error
        if ($message = $this->http->FindPreg("/403 Forbidden/ims")) {
            throw new CheckException("We weren't able to process your information.We couldn't find an account with the email address and password you entered. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        // switch to desktop version
        $this->http->Log("switch to desktop version");
        $this->http->GetURL("http://www.stubhub.com/?ref=mweb");

        if ($this->detectCaptcha() === false) {
            return false;
        }
        // login successful
        if ($this->http->FindSingleNode("//a[text() = 'Sign out']")) {
            return true;
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Agree to our terms for StubHub U.S.')]")) {
            $this->throwAcceptTermsMessageException();
        }

        $this->http->GetURL("https://myaccount.stubhub.com/login/Signin");

        if ($message = $this->http->FindPreg("/You were so quiet, we signed you out \(just to be safe\)\./ims")) {
            $this->http->Log(">>>> Bug of provider: " . $message);
            $this->http->GetURL("https://myaccount.stubhub.com/myaccount/rewards");
            // login successful
            if ($this->http->FindSingleNode("//a[text() = 'Sign out']")) {
                return true;
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != 'https://myaccount.stubhub.com/myaccount/rewards') {
            $this->http->GetURL("https://myaccount.stubhub.com/myaccount/rewards");
        }
        // set Status
        $this->SetProperty("Status", trim($this->http->FindSingleNode("//div[span[contains(text(), 'Status') and contains(@class, 'statusHeading')]]/following-sibling::div[1]")));
        // set Earning
        $this->SetProperty("Earning", $this->http->FindSingleNode("//div[@class = 'summarySubImp' and contains(text(), 'reward')]", null, true, '/\s*([\d\.\,\%]+)/ims'));
        // set Balance
        $this->SetBalance($this->http->FindPreg("/var\s*rewardsEarned\s*=\s*([^\;]+)/ims"));
        // set Lifetime
        $this->SetProperty("Lifetime", $this->http->FindSingleNode("//div[contains(text(), 'Lifetime rewards earned')]", null, true, "/earned\s*:\s*([^<]+)/ims"));
        // set MemberSince
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[contains(text(), 'member since')]", null, true, "/since\s*:\s*([^<]+)/ims"));
        // set NextStatus
        $this->SetProperty("NextStatus", $this->http->FindSingleNode("//div[contains(text(), 'Become a')]", null, true, "/Become\s*a\s([^\s]+)\s/ims"));
        // set Available Rewards
        if (!$this->SetProperty("AvailableRewards", $this->http->FindSingleNode("//div[span[contains(text(), 'Available FanCodes')]]/following-sibling::div[1]"))) {
            if ($this->http->FindPreg("/(Join now and receive a FanCode for a free electronic delivery)/ims")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // Purchases
        $this->SetProperty('Purchases', $this->http->FindPreg('/var purchasedToNextTier\s*=\s*(\d+)\s*;/ims'));
        // Amount Spent
        $this->SetProperty('AmountSpent', $this->http->FindSingleNode('//td[@class="ordertotal"]'));

        // set Name
        $this->http->GetURL("https://myaccount.stubhub.com/myaccount/yourinfo");
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[contains(text(), 'Primary contact')]/following-sibling::div/text()[1]")));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://myaccount.stubhub.com/myaccount/rewards";

        return $arg;
    }
}
