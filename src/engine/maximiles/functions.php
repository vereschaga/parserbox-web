<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMaximiles extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected $regionOptions = [
        ""       => "Select your country",
        "UK"     => "UK",
        "France" => "France",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->UseSelenium();
        $this->useGoogleChrome();
        /*
        $this->setScreenResolution([1920, 1080]);
        */
        $this->http->saveScreenshots = true;

        if ($this->AccountFields['Login2'] != 'France') {
            /*
            $this->http->SetProxy($this->proxyUK());
            */
            $this->http->SetProxy($this->proxyDOP());
        } else {
//            $this->http->SetProxy($this->proxyReCaptcha());
            $this->setProxyBrightData();
        }

        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        if ($this->AccountFields['Login2'] == 'France') {
            $this->http->GetURL('https://www.maximiles.com/account/personal-information', [], 20);
        } else {
            $this->http->GetURL('https://www.maximiles.co.uk/account/personal-information', [], 20);
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);
        // reset cookie
        $this->http->removeCookies();
        $loginURL = "https://www.maximiles.co.uk/account/personal-information";

        if ($this->AccountFields['Login2'] == 'France') {
            $loginURL = "https://www.maximiles.com/account/personal-information";
        }

        $this->http->GetURL($loginURL);

        $acceptCookiesBtn = $this->waitForElement(WebDriverBy::id('cookiesModalSubmit'), 5);

        if (isset($acceptCookiesBtn)) {
            $acceptCookiesBtn->click();
        }

        $this->logger->debug('looking for form elements');
        $this->saveResponse();
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username" or @id = "username_header" or @id="_username"]'), 5);
        $pwdInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password" or @id = "password_header" or @id="_password"]'), 5);
        /*
        $btn = $this->waitForElement(WebDriverBy::xpath('//form[@id = "login-page-form" or @id="login-header-form"]//button[@name = "_submit"]'), 0);
        */

        /*
        if (!isset($loginInput, $pwdInput, $btn)) {
        */
        if (!isset($loginInput, $pwdInput)) {
            $this->logger->error('form elements not found');
            $this->saveResponse();

            return false;
        }

        $this->logger->debug('inserting credentials');
        $this->saveResponse();
        $loginInput->sendKeys($this->AccountFields['Login']);
        sleep(1);
        $pwdInput->sendKeys($this->AccountFields['Pass']);
        sleep(1);
        $this->logger->debug('clicking submit');
        $this->saveResponse();
        /*
        $btn->click();
        */
        $pwdInput->sendKeys(WebDriverKeys::ENTER);

        /*
        $this->http->GetURL($loginURL);

        if (!$this->http->ParseForm(null, "//form[@action='/login-check']")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("_username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("_password", $this->AccountFields["Pass"]);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The Maximiles website is currently under maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'The Maximiles website is currently')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Notre site Maximiles est en cours de maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Notre site Maximiles est en cours de maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        */
        $xpathAfterLoading = '//a[contains(@href, "/logout")] | //div[contains(@class, "alert")] | //input[@id = "safe_connection_safeCode"] | //h1[text() = "Mes informations personnelles" or text() = "Account information"]';
        $el =
            $this->waitForElement(WebDriverBy::xpath($xpathAfterLoading), 15)
            ?? $this->waitForElement(WebDriverBy::xpath($xpathAfterLoading), 0, false)
        ;
        $this->saveResponse();

        if (!$el) {
            $btn = $this->waitForElement(WebDriverBy::xpath('//form[@id = "login-header-form"]//button[@name = "_submit"]'), 0);

            if (!$btn) {
                return $this->checkErrors();
            }
            $btn->click();
            $el =
                $this->waitForElement(WebDriverBy::xpath($xpathAfterLoading), 15)
                ?? $this->waitForElement(WebDriverBy::xpath($xpathAfterLoading), 0, false)
            ;
            $this->saveResponse();
        }

        if (!$el) {
            return $this->checkErrors();
        }
        $message = str_replace('×', '', $el->getText());

        if (str_contains($message, 'There was an error with your e-mail/password combination. Please try again.')
            || str_contains($message, "La combinaison Email / Mot de passe n'est pas bonne. Veuillez réessayer.")
            || str_contains($message, 'This account is closed, thanks to contact us')
            || str_contains($message, 'Ce compte est fermé. Merci de contacter le service clients')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }
        // There was an error with your e-mail/password combination
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert") and 
                (contains(., "There was an error with your e-mail/password combination. Please try again.")
                or contains(., "La combinaison Email / Mot de passe n\'est pas bonne. Veuillez réessayer.")
                or contains(., "This account is closed, thanks to contact us")
                or contains(., "Ce compte est fermé. Merci de contacter le service clients")
            )]', null, true, "/^(?:x|×)?(.+)/")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The captcha is invalid or can not be resolved
        if ($message = $this->http->FindSingleNode("//div[contains(@class,'alert') and contains(.,'The captcha is invalid or can not be resolved')]")) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }
        // Your account state doesn't allow us to send to you emails. Please contact us with the following form.
        if ($message = $this->http->FindSingleNode("//div[contains(@class,'alert') and contains(.,'t allow us to send to you emails. Please contact us with the following form.')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // You may have heard there are some changes in personal data protection law,
        // so we wanted you to know that we've updated our Terms and Conditions and Privacy Policy to make things clearer for you.
//        if ($this->http->FindSingleNode("//form[@actio/*n='/accept-terms-and-conditions']/@method")
//            && stripos($this->http->currentUrl(), '/accept-terms-and-conditions') !== false)
//            $this->throwAcceptTermsMessageException();*/

        // no errors, no auth // AccountID: 4531893
        if ($this->AccountFields['Login'] == 'sant.cas@gmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 4338030
        if ($this->AccountFields['Login'] == 'paulgirod@hotmail.fr') {
            throw new CheckException("There was an error with your e-mail/password combination. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//p[
            contains(text(), "Veuillez entrer le code qui vient de vous être envoyé par email")
            or contains(text(), "Please enter the code that has just been emailed to you")
        ]');

        if (!isset($question) || !$this->http->ParseForm("safe_connection")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";
        $this->holdSession();

        return true;
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $codeInput = $this->waitForElement(WebDriverBy::id('safe_connection_safeCode'), 0);
        $btn = $this->waitForElement(WebDriverBy::id('safe_connection_validation'), 0);

        if (!isset($codeInput, $btn)) {
            $this->logger->error('form elements not found');

            return false;
        }

        $codeInput->clear();
        $codeInput->sendKeys($code);
        $btn->click();
        $error = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, \"alert-danger\") and contains(., "Your code is not valid")]'), 5);

        if ($error) {
            $this->AskQuestion($this->Question, 'Your code is not valid', 'Question');

            return false;
        }

        return true;

        $this->http->SetInputValue("safe_connection[safeCode]", $this->Answers[$this->Question]);
        $this->http->SetInputValue("safe_connection[validation]", "");
        // remove old code
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }
        // Your code is not valid
        if ($error = $this->http->FindSingleNode("//div[contains(@class, \"alert-danger\") and contains(., 'Your code is not valid')]/text()[last()]")) {
            $this->AskQuestion($this->Question, $error);

            return false;
        }

        return true;
    }

    public function Parse()
    {
//        ## Collected Points (Points Gagnés)
//        $this->SetProperty("PointsCollected", $this->http->FindSingleNode("//table[@class='quick_statement01']/tr/th[2]", null, true, "/\+\s([\d]+)\s*(?:points|Maximiles)/ims"));
//        ## Spent Points (Points Echangés)
//        $this->SetProperty("PointsSpent", $this->http->FindSingleNode("//table[@class='quick_statement02']/tr/th[2]", null, true, "/\-\s([\d]+)\s*(?:points|Maximiles)/ims"));

        $this->saveResponse();
        // Name
        $this->SetProperty('Name', trim(beautifulName($this->http->FindSingleNode("//input[@id='bilendi_member_account_profile_firstName']/@value") . " " . $this->http->FindSingleNode("//input[@id='bilendi_member_account_profile_lastName']/@value"))));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("(//span[contains(@class,'firstname')])[1]")));
        }

        // Balance - points
        $this->SetBalance($this->http->FindSingleNode("(//a[@class='miles']/span[@class='points'])[1]"));
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'UK';
        }

        return $region;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//button[@class='g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href,'/logout')])[1]")) {
            return true;
        }

        return false;
    }
}
