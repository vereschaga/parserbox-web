<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPampers extends TAccountChecker
{
    use ProxyList;

    private $region;
    private $domain;

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'Canada') {
            $redirectURL = 'https://www.pampers.ca/login';
        } else {
            $redirectURL = 'https://www.pampers.com/login';
        }
        $arg["RedirectURL"] = $redirectURL;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            "USA"    => "USA",
            "Canada" => "Canada",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
        $this->getRegionSettings();

        $this->http->SetProxy($this->proxyReCaptcha(), false); //todo: temporarily gag -> error: Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to www.pampers.com:443
    }

    public function getRegionSettings()
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->AccountFields['Login2'])) {
            $this->AccountFields['Login2'] = 'USA';
        }
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        if ($this->AccountFields['Login2'] == 'USA') {
            $this->region = 'us';
            $this->domain = 'com';
        } elseif ($this->AccountFields['Login2'] == 'Canada') {
            $this->region = 'ca';
            $this->domain = 'ca';
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.pampers.{$this->domain}/edit-profile", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setDefaultHeader('Referer', 'http://www.pampers.' . $this->domain . '/home');
        $this->http->GetURL("https://www.pampers.{$this->domain}/en-{$this->region}/login");

        // Maintenance
        if (strstr($this->http->currentUrl(), 'maintenance')) {
            throw new CheckException("We are currently undergoing scheduled maintenance. Try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($message = $this->http->FindSingleNode('//div[@class = "post__content" and contains(., "This page is currently being improved. Try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm("mainform")) {
            return $this->checkErrors();
        }

        $data = [
            "Scenario"            => "login",
            "IsFullPage"          => true,
            "Target"              => "login",
            "Query"               => "",
            "registrationPageURL" => "https://www.pampers.{$this->domain}/en-{$this->region}/login",
            "RegisterPageUrl"     => "https://www.pampers.com/en-{$this->region}/login",
            "Data"                => [
                [
                    "key"   => "signInEmailAddress",
                    "value" => $this->AccountFields['Login'],
                ],
                [
                    "key"   => "currentPassword",
                    "value" => $this->AccountFields['Pass'],
                ],
                [
                    "key"   => "keepAlive",
                    "value" => true,
                ],
            ],
        ];
        $headers = [
            "__RequestVerificationToken" => $this->http->FindSingleNode("//input[@name='__RequestVerificationToken']/@value"),
            "Accept"                     => "text/html, */*; q=0.01",
            "X-Requested-With"           => "XMLHttpRequest",
            "Content-Type"               => "application/json",
        ];
        $this->http->PostURL("https://www.pampers.{$this->domain}/webservice/vortex/postlogin", json_encode($data, JSON_UNESCAPED_SLASHES), $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are working on a tiny technical problem, so please try again in a couple of minutes.
//        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are working on a tiny technical problem, so please try again in a couple of minutes.')]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Service unavailable/ims")) {
            throw new CheckException("Service unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // There's a tiny technical problem at the Pampers server.
        if ($message = $this->http->FindPreg("/There's a tiny technical problem at the Pampers server\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are currently undergoing scheduled maintenance')]", null, true, null, 0)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(., 'maintenance ')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->Log("[Current URL]: " . $this->http->currentUrl());

        // HTTP Status 500
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 500')]")
                // There's a tiny technical problem at the Pampers server.
                || $this->http->FindPreg("/There's a tiny technical problem at the Pampers server\./")
                // Server Error in '/' Application.
                || $this->http->FindPreg("/Server Error in \'\/\' Application\./")
                || $this->http->FindSingleNode("//p[contains(text(), 'This is the error description.')]")
                // provider error
                || ($this->http->currentUrl() == 'https://www.pampers.com/login' && $this->http->Response['code'] == 302)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/window\["bobcmn"\] = /')) {
            throw new CheckRetryNeededException(2);
        }

        return false;
    }

    public function Login()
    {
        //if ($this->AccountFields['Login2'] != 'USA')
        //    if (!$this->http->PostForm())
        //        return $this->checkErrors();

        // need to update profile
        if (($this->http->FindSingleNode("//h1[contains(text(), 'EDIT YOUR PROFILE')]") || $this->http->FindSingleNode("//h1[contains(text(), 'Edit Your Profile')]") || $this->http->FindSingleNode("//h1[contains(text(), 'Update Your Profile (required)')]")) && $this->http->FindSingleNode("//input[@value = 'Save changes']/@value")
            || $this->http->FindPreg("/<div data-webservice-status=\"OK\" data-webservice-redirect-url=\"https:\/\/www.pampers.{$this->domain}\/en-\w{2}\/edit-profile\" class=\"js-webservice-params\">/")) {
            throw new CheckException("Pampers (Gifts to Grow) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        // The email and password combination you entered is incorrect.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The email and password combination you entered is incorrect.')] | //p[contains(text(), 'The email and password combination you entered is incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The account has been blocked as a result of 20 failed login attempts.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The account has been blocked as a result of ')] | //p[contains(text(), 'The account has been blocked as a result of ')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // USA - new login form
        if ($message = $this->http->FindSingleNode("//p[@id = 'phmainbodyoverlay_0_ErrorMessageText']", null, true, "/(The email and password combination you entered is incorrect\..+)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // Thanks for visiting our new website. We know that some users are experiencing errors with login or viewing points, and we apologize.
        if ($message = $this->http->FindSingleNode("//i[contains(text(), 'Thanks for visiting our new website. We know that some users are experiencing errors with login or viewing points, and we apologize.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!stristr($this->http->currentUrl(), '/edit-profile')) {
            $this->http->GetURL("https://www.pampers.{$this->domain}/edit-profile");
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'profile-info__display-name']")));
        $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
        // Balance - Your Points
        $this->http->GetURL("https://www.pampers.{$this->domain}/header-profile-details");

        if ($this->http->FindSingleNode('//h2[@class="error-oasis__title" and contains(., "Something went wrong")]')) {
            throw new CheckRetryNeededException(2, 3);
        }

        if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(@class, 'js-code-points-total')]"))) {
            // server error
            if ($message = $this->http->FindSingleNode('//p[@class = "loy--error" and contains(text(), "server error")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // We're working on a new experience for you! Check back with us again soon!
            $this->http->GetURL("https://www.pampers.{$this->domain}/en-{$this->region}/rewards/catalog");

            if ($message = $this->http->FindSingleNode('(//p[contains(text(), "We\'re working on a new experience for you!")])[1]')) {
                $this->SetWarning($message);
            }
        }// if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(@class, 'js-code-points-total')]")))

        // Expiration Date
        $this->http->PostURL("https://www.pampers.{$this->domain}/loyalty/getpointhistoryfeed", '{"ReturnCount":"10","Filter":"all"}');
        $expNodes = $this->http->XPath->query("//header[@class='card__header']");
        $this->logger->debug("Total {$expNodes->length} exp date nodes were found");

        foreach ($expNodes as $expNode) {
            $date = $this->http->FindSingleNode(".//p[@class='card__description--paragraph' and contains(text(), 'Order date')]", $expNode, false, '#:\s+(\d+/\d+/\d+)#');
            //$points = $this->http->FindSingleNode(".//div[@class='card__reward--point']", $expNode);
            if (!isset($exp) || strtotime($date, false) > $exp) {
                $exp = strtotime($date, false);
                $this->SetProperty("LastActivity", $date);
                // refs #15834
                $expTotal = strtotime('+12 month', $exp);

                if ($expTotal > strtotime('12/31/2019')) {
                    $this->logger->debug("Original Expiration Date: " . date('m/d/Y', $expTotal));
                    $this->SetExpirationDate(strtotime('12/31/2019'));
                } else {
                    $this->SetExpirationDate($expTotal);
                }
                // Expiring Balance
                //$this->SetProperty("ExpiringBalance", $this->Balance);
            }
        }

        $now = strtotime('now');

        if ($now > strtotime('12/15') || $now < strtotime('01/15')) {
            $this->http->GetURL('https://www.pampers.com/en-us/rewards-terms-conditions');

            if (!$this->http->FindPreg('/The Loyalty Program and all Rewards Points expire no later than 11:50:50 p\.m\. EST\. on December 31, 2019/')) {
                $this->sendNotification('refs #15834, pampers - Update "Rewards Terms and Conditions"');
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(@href, "LogOut")]/@href') || $this->http->FindNodes('//a[contains(text(), "Log out")]')) {
            return true;
        }

        return false;
    }
}
