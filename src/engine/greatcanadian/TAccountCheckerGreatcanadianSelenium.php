<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerGreatcanadianSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.greatcanadianrebates.ca/Balance/";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->useCache();
        $this->disableImages();
//        $this->http->SetProxy($this->proxyDOP(['tor1']));
        $this->setProxyBrightData(null, 'static', 'ca');

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $login = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout.php')]"), 0);
            $this->saveResponse();

            if ($login) {
                return true;
            }
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->increaseTimeLimit(120);
            $this->http->GetURL("https://www.greatcanadianrebates.ca");
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->increaseTimeLimit(120);
            $this->http->GetURL("https://www.greatcanadianrebates.ca");
        }
//        $this->http->GetURL("https://www.greatcanadianrebates.ca/login.php");

        $this->waitForElement(WebDriverBy::xpath("
            //input[normalize-space(@placeholder)='Email Address']
            | //input[@name = 'uid']
            | //form[@id = 'challenge-form']
            | //h1[contains(text(), 'This site can’t be reached') or contains(text(), 'There is no Internet connection')]
            | //a[contains(@href, 'logout.php')]
            | //img[@id = 'theButton']
        "), 15);
        $this->saveResponse();

        if ($signIn = $this->waitForElement(WebDriverBy::xpath("//img[@id = 'theButton']"), 0)) {
            $signIn->click();
        }

        // This site can’t be reached
        if ($message = $this->http->FindSingleNode('
                //*[self::h1 or self::span][
                    contains(text(), "This site can’t be reached")
                    or contains(text(), "There is no Internet connection")
                ]
                | //h2[contains(text(), "Access denied")]
            ')
        ) {
            $this->DebugInfo = $message;
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]"))

        $this->securityCheckWorkaround();
        $this->securityCheckWorkaround();

        $login = $this->waitForElement(WebDriverBy::xpath("//input[normalize-space(@placeholder)='Email Address'] | //input[@name = 'uid']"), 0);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[normalize-space(@placeholder)='Password'] | //input[@name = 'pw']"), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//input[normalize-space(@value)='Log In'] | //input[@name = 'Login']"), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout.php')]"), 0)) {
                return true;
            }

            return $this->checkErrors();
        }

        if ($key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey")) {
            $captcha = $this->parseReCaptcha($key);

            if ($captcha === false) {
                return false;
            }
            $this->driver->executeScript('document.getElementById("g-recaptcha-response").value = "' . $captcha . '";');
        }
        $login->sendKeys($this->AccountFields['Login']);
        $password = $this->AccountFields['Pass'];
        $pass->sendKeys($password);
        $this->saveResponse();
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The site database appears to be down
        if ($message = $this->http->FindPreg("/The site database appears to be down\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/:\s*syntax error, unexpected identifier \"UP\" in/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("
            //a[contains(@href, 'logout.php')]
            | //div[contains(@id, 'incorrectlogin')]/p
        "), 10);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout.php')]"), 0)) {
            return true;
        }

        // Login Incorrect
        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(@id, 'incorrectlogin')]/p"), 0)) {
            $message = $message->getText();
            $this->logger->error("[Error]: {$message}");

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $this->waitForElement(WebDriverBy::xpath("//p[contains(text(),'Total: ')] | //h1[contains(text(), 'Update account information for :')]"), 0);
        $this->saveResponse();

        // Payment Type
        $payment = $this->waitForElement(WebDriverBy::xpath("//td[b[contains(text(), 'Payment Type :')]]/following-sibling::td"), 0);
        $this->SetProperty("PaymentType", $payment->getText());
        // Total Eligible For Next Payment
        $cashBackRebatesPaid = $this->waitForElement(WebDriverBy::xpath("//td[b[contains(text(), 'Total Eligible For Next Payment')]]/following-sibling::td"), 0);
        $this->SetProperty("EligibleForNextPayment", $cashBackRebatesPaid->getText());
        // Next Pay Date Status
        $payoutOn = $this->waitForElement(WebDriverBy::xpath("//td[b[contains(text(), 'Next Pay Date Status')]]/following-sibling::td"), 0);

        if ($payoutOn) {
            $this->SetProperty("PayoutOn", $this->http->FindPreg("/will be paid on ([^\.]+)/", false, $payoutOn->getText()));
        }
        // Balance - Current Balance Total: $9.00
        $balance = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(),'Total: ')]"), 0);
        $regExp = "/([\d\.\,]+)/ims";

        // Update profile workaround
        if (!$balance && $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Update account information for :')]"), 0)) {
            $balance = $this->waitForElement(WebDriverBy::xpath('//b[contains(text(), "Current Balance:")]'), 0);
            $regExp = "/Current Balance:\s*([^|<]+)/";
        }

        $this->SetBalance($this->http->FindPreg($regExp, false, $balance->getText()));

        // AccountID: 3938815
        if ($this->AccountFields['Login'] == 'robichaud.andrejr@cegepvicto.ca') {
            return;
        }

        // Name
        try {
            $this->http->GetURL("https://www.greatcanadianrebates.ca/settings.php");
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }
        $this->saveResponse();

        if ($fname = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'fname']"), 0)) {
            $name = Html::cleanXMLValue(
                $fname->getAttribute('value')
                . ' ' . $this->waitForElement(WebDriverBy::xpath("//input[@name = 'lname']"), 0)->getAttribute('value')
            );
        }

        if (!empty($name) && strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'New Security Update Required')]"), 0)) {
                $this->throwProfileUpdateMessageException();
            }
        }
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function securityCheckWorkaround()
    {
        $this->logger->notice(__METHOD__);
        // Please complete the security check to access www.greatcanadianrebates.ca
        if (!$this->http->FindSingleNode("//form[@id = 'challenge-form']")) {
            return false;
        }
        $key = $this->http->FindSingleNode("//script[@data-sitekey]/@data-sitekey");
        $id = $this->http->FindSingleNode("//form[@id = 'challenge-form']//input[@name = 'id']/@value");

        if (!$id) {
            return false;
        }
        $captcha = $this->parseReCaptcha($key);

        if ($captcha === false) {
            return false;
        }
        $this->http->GetURL("http://www.greatcanadianrebates.ca/cdn-cgi/l/chk_captcha?id={$id}&g-recaptcha-response={$captcha}");

        return true;
    }
}
