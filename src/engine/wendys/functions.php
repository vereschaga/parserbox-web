<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerWendys extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 10;
    private const XPATH_QUESTION = '//p[contains(text(), "Please enter the security code sent to:")]';
    private const XPATH_ERROR = '//div[contains(@class, "alert-danger")] 
    | //span[contains(text(),"An account could not be created. Please contact Customer ")]
    | //span[contains(text(),"having trouble logging in, try resetting your password, or if that")]';


    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->useFirefoxPlaywright();
        $this->setProxyMount();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
    }

    public function LoadLoginForm()
    {
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('There is not a user that matches this email/password.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://my.wendys.com/login');
        $openAuthFormButton = $this->waitForElement(WebDriverBy::xpath('//button[@class="account-info"]'), self::WAIT_TIMEOUT * 2);
        $this->driver->executeScript('setInterval(function() {$(`button#onetrust-accept-btn-handler`).click();}, 500);');
        $this->saveResponse();
        $this->driver->executeScript('try { document.querySelector(\'div.ab-show\').style.display = "none" } catch (e) {}');
        sleep(1);

        if (!$openAuthFormButton) {
            $openAuthFormButton = $this->waitForElement(WebDriverBy::xpath('//button[@class="account-info"]'), self::WAIT_TIMEOUT);
        }

        if (!$openAuthFormButton) {
            $this->saveResponse();
            $this->DebugInfo = "'Sign in' btn not found";

            if ($this->waitForElement(WebDriverBy::xpath('//img[@id = "dancing-frosty"]'), 0)) {
                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }

        $openAuthFormButton->click();
        $this->saveResponse();

        if ($openLoginFormButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "LOG IN WITH EMAIL")]'), self::WAIT_TIMEOUT)) {
            $openLoginFormButton->click();
        }

        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//img[contains(@src,"spinner.gif")]'), 0));
        }, self::WAIT_TIMEOUT);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="email"]'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);
        $this->driver->executeScript('let remMe = document.querySelector(\'[name="rememberMe"]\'); if (remMe) remMe.checked = true;');
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            $this->DebugInfo = "login fields not found";
            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Log In') and not(@disabled)]"), 3);

        if (!$button) {
            $this->saveResponse();
            return false;
        }

        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(implode(" | ", [
            self::XPATH_QUESTION,
            self::XPATH_ERROR,
        ])), self::WAIT_TIMEOUT * 3);
        $this->saveResponse();

        if ($this->parseQuestion()) {
            return false;
        }

//        if ($this->loginSuccessful()) {
//            return true;
//        }

        $message = $this->http->FindSingleNode(self::XPATH_ERROR);

        if ($message) {
            $message = Html::cleanXMLValue($message);
            $message = preg_replace("/×$/", "", $message);
            $this->logger->error("[Error]: {$message}");

            if ($message === "That didn't seem to work. If you're having trouble logging in, try resetting your password, or if that doesn't work, call customer care @ (888) 624-8140 or customercare@wendys.com") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "An account could not be created. Please contact Customer ")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message === "Your account was previously locked for your security. To unlock it, please reset your password.") {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                $message === "For full access to My Wendy's™, we need you to first create a profile."
                || strstr($message, 'That didn\'t seem to work. If you\'re having trouble logging in, try resetting your password')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message === "We're sorry, an error was detected.") {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $rewardsPageLink = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Rewards"]'), 0);
        $rewardsPageLink->click();

        $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "points-value")]'), 5);
        $this->saveResponse();
        // Balance - Your Balance, pts
        $this->SetBalance($this->http->FindSingleNode('//p[contains(@class, "points-value")]/span'));
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//div[contains(@class, "user-details")]/span', null, true, "/,\s*([^<]+)/"));

        $this->driver->executeScript('try { document.querySelector(\'.reward-history-tab\').click() } catch (e) {}');
        sleep(5);
        $this->saveResponse();
        /*
        $headers = [
            'Authorization' => "Bearer {$this->State['accessJwt']}",
        ];

        $data = '{"lang":"en","cntry":"US","sourceCode":"ORDER.WENDYS","version":"23.8.3","showRewardHistory":true,"limit":10,"isGuest":false}';

        $this->http->PostURL("https://api.app.prd.wendys.digital/web-client-gateway/webmobileaggregator/recent?lang=en&cntry=US&sourceCode=ORDER.WENDYS&version=23.8.3", $data, $this->headers + $headers);
        $response = $this->http->JsonLog();

        $groupExpiringSoon = [];

        foreach ($response->expiringRewards->expiringSoon ?? [] as $expiringSoon) {
            $expDate = $this->http->FindPreg('/^(.+?)T/', false, $expiringSoon->date);

            if (isset($groupExpiringSoon[$expDate])) {
                $groupExpiringSoon[$expDate]['points'] += $expiringSoon->points;
            } else {
                $groupExpiringSoon[$expDate] = [
                    'points' => $expiringSoon->points,
                    'date'   => $expDate,
                ];
            }
        }

        if (!empty($groupExpiringSoon)) {
            ksort($groupExpiringSoon);
            $this->logger->debug(json_encode($groupExpiringSoon));

            $groupExpiringSoon = current($groupExpiringSoon);

            if ($exp = strtotime($groupExpiringSoon['date'])) {
                $this->SetExpirationDate($exp);
                $this->SetProperty('ExpiringBalance', $groupExpiringSoon['points']);
            }
        }
        */
    }

    public function parseQuestion(): bool
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode(self::XPATH_QUESTION);
        $email = $this->http->FindSingleNode(self::XPATH_QUESTION . '/following-sibling::p[1]');

        if (!$question || !$email) {
            return false;
        }

        $question .= " ". $email;
        $this->holdSession();
        $this->AskQuestion($question, null, "Question");

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        // $answerInputs = $this->driver->findElements(WebDriverBy::xpath('//input[@aria-describedby="otp-helper"]'));
        $answerInputs = $this->driver->findElements(WebDriverBy::xpath('//input[contains(@aria-label, "Digit") and contains(@aria-label, "of 6")]'));
        $this->saveResponse();

        if (!$answerInputs) {
            return false;
        }

        $this->logger->debug("entering code...");

        foreach ($answerInputs as $i => $element) {
            $this->logger->debug("#{$i}: {$answer[$i]}");
            $answerInputs[$i]->clear();
            $answerInputs[$i]->sendKeys($answer[$i]);
            $this->saveResponse();
        }

        $button = $this->waitForElement(WebDriverBy::xpath("//button[@data-wendys-button=\"true\" and contains(., 'Confirm') and not(@disabled)]"), 3);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        // TODO
        sleep(5);
        $loadingOverlay = $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//div[@id = "loading-overlay-portal"]'), 0));
        }, 30);
        if (!$loadingOverlay) {
            $this->logger->error('Loading overlay challenge went wrong');
        }
        $this->saveResponse();

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
