<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMalaysiaSelenium extends TAccountCheckerMalaysia
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.malaysiaairlines.com/my/en/enrich-portal/home/summary.html';
    private const QUESTION_MESSAGE = "Please check your email to get the verification code.";

    protected $endHistory = false;
    private $tenant = null;
    private $param = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyStaticIpDOP()); // 2fa: block workaround

        $this->UseSelenium();

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.malaysiaairlines.com/my/en/enrich-portal/home/summary.html");
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 10);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 10);
            $this->saveResponse();
        }

        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "preloader")]'), 0));
        }, 10);

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "next"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error("something went wrong");

            if (parent::loginSuccessful()) {
                return true;
            }

            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //label[@id = "sms_option"]
            | //button[@id = "sendCode"]
            | //div[contains(@class, "error") and @style="display: block;"]/p
            | //div[@id = "email_error"]
        '), 15);
        $this->saveResponse();

        $this->adittionalCaptcha();

        if ($sendCodeBySMS = $this->waitForElement(WebDriverBy::xpath('//label[@id = "sms_option"] | //label[input[@id = "extension_preferredChannel_sms"]]'), 0)) {
            $sendCodeBySMS->click();
            $this->saveResponse();

            if ($continueBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "continue"]'), 0)) {
                $continueBtn->click();
            }

            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Type of captcha (visual / audio) is required.")]'), 5)) {
                $captchaInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "captchaControlChallengeCode"]'), 0);
                $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "continue"]'), 0);
                $this->saveResponse();

                if (!$captchaInput || !$contBtn) {
                    return false;
                }

                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    return false;
                }

                $captchaInput->sendKeys($captcha);
                $this->saveResponse();
                $contBtn->click();
                sleep(5);

                $this->adittionalCaptcha();
            }

            $this->saveResponse();
        }

        if ($sendCode = $this->waitForElement(WebDriverBy::xpath('//button[@id = "sendCode"]'), 0)) {
            $sendCode->click();
            $this->waitForElement(WebDriverBy::xpath('//input[@id = "verificationCode"]'), 10);
            $this->saveResponse();
        }

        if ($maskedPhoneNumber = $this->http->FindSingleNode('//div[contains(@class, "number")] | //input[@id = "strongAuthenticationPhoneNumber"]/@value')) {
            $this->holdSession();
            $this->AskQuestion("Enter your verification code which was sent to your phone number: {$maskedPhoneNumber}", null, "QuestionPhone");

            return false;
        }

        $this->saveResponse();

        if (parent::loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "error") and @style="display: block;"]/p | //div[@id = "email_error"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Please enter a valid email address'
                || $message == 'Please enter valid email address.'
                || $message == 'Your email ID / password is incorrect. Please try again.'
                || $message == 'We can\'t seem to find your account. Create one now?'
                || $message == 'Invalid email address. Please enter a valid email address.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'There is an error on your mobile number. Please call our Contact Center')
                || strstr($message, 'Unable to process your request(EC007).')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'Your account is temporarily locked to prevent unauthorized use')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "verificationCode"]'), 0);
        if (!$codeInput) {
            $this->saveResponse();

            return false;
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;

        $codeInput->clear();
        $mover->sendKeys($codeInput, $answer, 5);
        $this->saveResponse();
        /*
        $codeInput->sendKeys($answer);
        */

        sleep(2);
        $verifyBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "phoneSmsVerificationControl_but_verify_code" and not(@disabled)]'), 10);
        $this->saveResponse();

        if (!$verifyBtn) {
            $this->saveResponse();

            return false;
        }

        $verifyBtn->click();

        $this->waitForElement(WebDriverBy::xpath('//button[@id = "sadsada"]'), 10); // todo
        $this->saveResponse();

        return true;
    }

    public function Parse()
    {
        parent::Parse();
    }

    public function GetHistoryColumns()
    {
        parent::GetHistoryColumns();
    }

    public function ParseHistory($startDate = null)
    {
        return parent::ParseHistory($startDate = null);
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $imageData = $this->http->FindSingleNode("//img[(@id = 'captchaControlChallengeCode-img')]/@src", null, true, "/jpeg;base64\,\s*([^<]+)/ims");
        $this->logger->debug("jpeg;base64: {$imageData}");

        if (empty($imageData)) {
            return false;
        }

        $this->logger->debug("decode image data and save image in file");
        // decode image data and save image in file
        $imageData = base64_decode($imageData);
        $image = imagecreatefromstring($imageData);
        $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".jpeg";
        imagejpeg($image, $file);

        if (!isset($file)) {
            return false;
        }

        $this->logger->debug("file: " . $file);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function adittionalCaptcha()
    {
        $this->logger->notice(__METHOD__);

        if ($captchaInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "captchaControlChallengeCode"]'), 0)) {
            $this->logger->notice("one more captcha");
            $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "continue"]'), 0);
            $this->saveResponse();

            if (!$captchaInput || !$contBtn) {
                return false;
            }

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $captchaInput->sendKeys($captcha);
            $this->saveResponse();
            $contBtn->click();
            sleep(5);
        }

        $this->saveResponse();

        return true;
    }
}
