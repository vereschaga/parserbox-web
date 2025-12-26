<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerHebSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
//        $this->disableImages();
        $this->useChromium();
        $this->useCache();
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL('https://www.heb.com/my-account/login');

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 15);
        $this->saveResponse();
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Log in')]"), 0);

        if (!$login || !$pass || !$btn) {
            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);

        if ($this->http->FindPreg("/window\.recaptchaEnabled = true;/")) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->driver->executeScript("$('div.g-recaptcha iframe').remove();");
            $this->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
            $this->driver->executeScript("$('#googleRecaptchaToken').val(\"" . $captcha . "\");");
        }

        $btn->click();

        return true;
    }

    public function Login()
    {
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'log out')]"), 7);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (!$logout) {
            $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(., 'Log Out')] | //button[contains(., 'Log Out')]"), 0, false);
        }
        $this->saveResponse();

        if ($logout || strstr($this->http->currentUrl(), 'https://www.heb.com/?_requestid=')) {
            return true;
        }
        // Please enter a valid email address.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'formError']//span[contains(., 'Please enter a valid email address.')]"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, please re-submit the form.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'formError']//span[contains(., 'Sorry, please re-submit the form.')]"), 0)) {
            throw new CheckRetryNeededException(2, 1, $message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // The email address or password you entered does not match our records.
        if ($message = $this->http->FindPreg('/(The email address or password you entered does not match our records\.)/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The account has been locked.
        if ($message = $this->http->FindPreg("/(The account has been locked\. After 24 hours your account should be automatically unlocked\.)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // The Email address or password you entered do not match our records
        if (strstr($this->http->currentUrl(), 'errorLogin=true')) {
            throw new CheckException("The Email address or password you entered do not match our records.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function checkErrors()
    {
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
        $this->http->GetURL("https://www.heb.com/loyalty/manage-pcr-account/account-detail");
        $this->saveResponse();
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
            // Oops. Our system is currently down Please try again soon.
            if ($message = $this->http->FindSingleNode("//div[@id = 'formError']/span[contains(text(), 'Oops. Our system is currently down Please try again soon.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/googleRecaptchaSiteKey\":\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }
}
