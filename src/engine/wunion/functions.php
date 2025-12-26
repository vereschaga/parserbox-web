<?php

class TAccountCheckerWunion extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = [
            ''   => 'Select your country',
            'us' => 'United States',
            'ca' => 'Canada',
            'de' => 'Germany',
        ];
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && strstr($properties['SubAccountCode'], 'Cashback')
        ) {
            if (isset($properties['Currency']) && $properties['Currency'] == '€') {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
            }

            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->saveScreenshots = true;

        if (empty($this->AccountFields['Login2'])) {
            $this->AccountFields['Login2'] = 'us';
        }
    }

    public function LoadLoginForm(): bool
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        // GET params needed for redirecting to rewards page after login
        $this->http->GetURL("https://www.westernunion.com/{$this->AccountFields['Login2']}/en/home.html");
        $btnToForm = $this->waitForElement(WebDriverBy::xpath('//*[@id = "wu-mobile-login-button"] | //a[@amplitude-id="button-login_rodeo_popup"]'), 7);

        if (!$btnToForm) {
            $this->saveResponse();
            $this->driver->executeScript("let login = document.querySelector('[id = \"wu-mobile-login-button\"]'); if (login) login.style.zIndex = '100003';");
            $btnToForm = $this->waitForElement(WebDriverBy::xpath('//*[@id = "wu-mobile-login-button"] | //a[@amplitude-id="button-login_rodeo_popup"]'), 7);
            $this->saveResponse();
        }

        if (!$btnToForm) {
            $this->saveResponse();

            return false;
        }
        $btnToForm->click();
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "txtEmailAddr" or @id = "input_login_email"]'), 5);

        if (!$login) {
            $this->http->GetURL("https://www.westernunion.com/{$this->AccountFields['Login2']}/en/web/user/login");
        }

        if (!$login && $btnToForm = $this->waitForElement(WebDriverBy::xpath('//*[@id = "wu-mobile-login-button"] | //a[@amplitude-id="button-login_rodeo_popup"]'), 0)) {
            $this->saveResponse();
            $btnToForm->click();
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "txtEmailAddr" or @id = "input_login_email"]'), 5);
        $this->saveResponse();
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@id = "txtKey" or @id = "input_login_pwd"]'), 2);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "button-continue" or @id = "btn_login_log_in"]'), 2);

        if (!isset($login, $pwd, $btn)) {
            $this->saveResponse();
            $this->logger->debug('Unable to locate form fields!');

            return false;
        }

        if ($acceptCookiesBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id="onetrust-accept-btn-handler"]'), 0)) {
            $acceptCookiesBtn->click();
        }
        sleep(2);

        $this->saveResponse();
        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);

        if (strlen($login->getAttribute('value')) < strlen($this->AccountFields['Login'])) {
            $login->click();
            $this->driver->executeScript("let login = document.getElementById('txtEmailAddr') ?? document.getElementById('input_login_email'); login.value = '{$this->AccountFields['Login']}';");
        }

        if (strlen($pwd->getAttribute('value')) < strlen($this->AccountFields['Pass'])) {
            $pwd->click();
            $this->driver->executeScript("let pwd = document.getElementById('txtKey') ?? document.getElementById('input_login_pwd'); pwd.value = '{$this->AccountFields['Pass']}';");
        }

        $captchaInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "input_firstName"]'), 0);

        if ($captchaInput && $image = $this->waitForElement(WebDriverBy::id('image-captcha'), 0)) {
            $this->saveResponse();
            $captcha = $this->parseCaptcha($image);

            if (!$captcha) {
                return false;
            }
            $captchaInput->sendKeys($captcha);
        }

        $this->saveResponse();
        $btn->click();

        return true;
    }

    public function Login(): bool
    {
        $el = $this->waitForElement(WebDriverBy::xpath('//li[contains(@class, "wu-user-loggedin")] | //a[@id="user-logout-link" or @id="link_btn-logout" or @id="link_menu_mywuReward" or contains(text(), "Log out")] | //button[@id="goToRegister"] | //div[@id="crossCountryErr" or @id="error_firstName"] | //small[@id="notification-code"]'), 7);
        $this->saveResponse();

        if ($el && $elText = $el->getText()) {
            if ($elText == 'Re-enter the code exactly as it appears in the box.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if ($elText == 'Go to the Canada website') {
                throw new CheckException('Please click the "Edit" button and choose your country in the dropdown list.', ACCOUNT_INVALID_PASSWORD);
            }

            if ($elText == '403') {
                $this->DebugInfo = 'request blocked';

                return false;
            }
        }

        if ($register = $this->waitForElement(WebDriverBy::xpath('//button[@id = "goToRegister"]'), 0)) {
            $register->click();
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Please check email and try again.")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        $errorCode = $this->waitForElement(WebDriverBy::id('notification-code'), 3);
        $errorText = $this->waitForElement(WebDriverBy::id('notification-message'), 0);

        if (!$errorCode || !$errorText) {
            return false;
        }
        $errorCode = $errorCode->getText();
        $errorText = $errorText->getText();
        $this->logger->error("ErrorCode = $errorCode, ErrorText = $errorText");

        if ($errorCode === 'C1131') {
            throw new CheckException($errorText, ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($errorCode, ['C1121', 'C1122'])) {
            throw new CheckException($errorText, ACCOUNT_LOCKOUT);
        }

        if (str_contains($errorText, "We're sorry, but there's been a technical problem")
            || str_contains($errorText, 'Please try again later')
            || $errorCode == 502) {
            throw new CheckException($errorText, ACCOUNT_PROVIDER_ERROR);
        }

        $this->DebugInfo = $errorText;

        return false;
    }

    public function Parse(): void
    {
        $currentURL = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentURL}");

        switch ($this->AccountFields['Login2']) {
            case 'ca':
                if ($currentURL != 'https://www.westernunion.com/ca/en/web/mywu') {
                    $this->http->GetURL('https://www.westernunion.com/ca/en/web/mywu');
                    $this->waitForElement(WebDriverBy::id('label_mywu_available_points'), 5);
                }

                break;

            case 'de':
            case 'us':
                if ($currentURL != "https://www.westernunion.com/{$this->AccountFields['Login2']}/en/mywu/rewards.html") {
                    $this->http->GetURL("https://www.westernunion.com/{$this->AccountFields['Login2']}/en/mywu/rewards.html");
                    $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "points-balance-card") and string-length(normalize-space()) > 0]'), 5);
                }

                break;
        }

        $this->saveResponse();
        // Balance - Points available
        $this->SetBalance($this->http->FindSingleNode('//p[contains(@class, "points-balance-card")] | //div[@id = "label_mywu_available_points"]'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//*[contains(text(), "We need to verify your identity before you can send money.")]')) {
                $this->throwProfileUpdateMessageException();
            }

            return;
        }

        // Cash back balance
        $cashbackBalance = $this->http->FindSingleNode('//p[contains(@class, "cash-balance-card")]');

        if ($cashbackBalance) {
            $this->AddSubAccount([
                'Code'                    => 'Cashback',
                'DisplayName'             => 'Сash back',
                'Balance'                 => $cashbackBalance,
                'Currency'                => $this->http->FindPreg("/([^\d]+)/", false, $cashbackBalance),
                // Cash back needed for withdrawal
                'DollarsToNextWithdrawal' => $this->http->FindSingleNode('//p[contains(@class, "cash-away")]'),
                // Lifetime earnings
                'LifetimeEarnings'        => $this->http->FindSingleNode('//p[contains(@class, "life-time-txns")]'),
            ]);
        }

        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//p[@id = "mywuNameField"] | //a[@id = "link_btn_menu_myprofile"]/div/div')));
        // My WU number
        $this->SetProperty('Number', $this->http->FindSingleNode('//p[@id = "mywuNumberField"] | //div[@id = "label_mywu_card_number"]'));
        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//p[contains(@class, "member-since")] | //div[@id = "label_mywu_member_since"]'));
        // Points until your next reward
        $this->SetProperty('PointsToNextReward', $this->http->FindSingleNode('//p[contains(@class, "points-away")]'));

        // You don't have any active discounts on your account
        if (!$this->http->FindSingleNode('//div[@id = "mywu-rewards-no-pending-discounts"]')) {
            $this->sendNotification('refs #10619 wunion - found discounts');
        }
    }

    protected function parseCaptcha($imageElement)
    {
        $this->logger->notice(__METHOD__);
        $file = $this->takeScreenshotOfElement($imageElement);

        if (!$file) {
            return false;
        }
        $this->logger->debug("file: $file");
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, [
            'numeric'  => 4,
            'regsense' => 1,
            'language' => 2,
        ]);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $logout =
            $this->waitForElement(WebDriverBy::xpath('//a[@id = "user-logout-link" or @id = "link_btn-logout" or contains(text(), "Log out")]'), 10)
            ?? $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log out")] | //span[contains(text(), "Available points:")]'), 0, false)
        ;
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }
}
