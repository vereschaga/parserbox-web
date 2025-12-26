<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerScorecard extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper; // for method getWaitForOtc
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.scorecardrewards.com/");
        $applicationKey = $this->http->FindPreg("/window.HINDA_APPLICATION_KEY = '([^\']+)/");

        if (!$applicationKey) {
            return $this->checkErrors();
        }
        $this->http->setDefaultHeader("X-ApplicationKey", $applicationKey);
        $this->State['X-ApplicationKey'] = $applicationKey;
        /*
        $this->http->GetURL("https://services.scorecardrewards.com/site/configuration");
        */

        $this->http->GetURL("https://login.awardcenter.com/{$applicationKey}/login?returnUrl=https%3A%2F%2Fwww.scorecardrewards.com%2F%23%2Fhome");

        if (!$this->http->ParseForm(null, '//div[contains(@class, "login-container")]//form')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('Username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        $this->http->RetryCount = 0;
        /*
        // Authorization
        $this->http->setDefaultHeader('Accept', "text/plain");
        $this->http->setDefaultHeader("Authorization", "Basic ".base64_encode($this->AccountFields['Login'].":".$this->AccountFields['Pass']));
        $this->http->setDefaultHeader("X-ReCaptchaResponse", $captcha);
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're sorry, ScoreCardRewards.com is temporarily unavailable.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, ScoreCardRewards.com is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->Response['code'] == 503
            && $this->http->FindPreg('/^The service is unavailable\.$/')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        /**
         * We're sorry, but our site is currently unavailable while we actively work to apply system enhancements.
         * We apologize for any inconvenience and will be back shortly.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, but our site is currently unavailable while we actively work to apply")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function getAuthHeaders()
    {
        $this->logger->notice(__METHOD__);
        $code = $this->http->FindPreg("/\?code=([^\&]+)/", false, $this->http->currentUrl());

        if (!$code) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://services.scorecardrewards.com/participants/login?code={$code}");
        $this->http->RetryCount = 2;
        $authenticationInfo = $this->http->Response['headers']['authentication-info'] ?? null;

        if (!$authenticationInfo) {
            return false;
        }

        $this->http->setDefaultHeader("Authorization", $authenticationInfo);
        $headers = [
            "Accept"  => "application/json, text/plain, */*",
            "Referer" => "https://www.scorecardrewards.com/",
        ];
        $this->http->GetURL("https://services.scorecardrewards.com/participants/me", $headers, 120); // increase delay for AccountID: 4874232
        $response = $this->http->JsonLog();

        if (isset($response->success) && $response->success == 'true') {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return false;
    }

    public function Login()
    {
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->getAuthHeaders()) {
            return true;
        }

        // Account Authentication
        if ($deliveryAddress = $this->http->FindPreg("/\"deliveryType\"\s*:\s*\"(?:Email|Call)\".toLowerCase\(\),\s*\"address\"\s*:\s*\"((?:\(?\d+\)?\s*[\d\-]+|[\w\.\-]+\@\w+\.\w+))\s*\"/")) {
            $this->captchaReporting($this->recognizer);
            $deliveryType = $this->http->FindPreg("/\"deliveryType\"\s*:\s*\"((?:Email|Call))\".toLowerCase\(\),\s*\"address\"\s*:\s*\"(?:\(?\d+\)?\s*[\d\-]+|[\w\.\-]+\@\w+\.\w+)\s*\"/");
            $jwtToken = $this->http->FindPreg('/jwtToken: \'([^\']+)/');

            if (!$jwtToken || !$deliveryType) {
                return false;
            }
            $data = [
                "deliveryType"    => strtolower($deliveryType),
                "deliveryAddress" => $deliveryAddress,
            ];
            $headers = [
                "Accept"        => "*/*",
                "Content-Type"  => "application/json",
                "Authorization" => "Bearer {$jwtToken}",
            ];

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $this->http->PostURL("https://login.awardcenter.com/mfa/delivery", json_encode($data), $headers);
            // We sent a one-time code via the method you selected, enter the code below. Please allow a few minutes for the code to arrive.
            $response = $this->http->JsonLog();

            if (!isset($response->key)) {
                // AccountID: 3769254
                if (isset($response->errorMessage) && $response->errorMessage == 'Error sending code to participant') {
                    throw new CheckException("We are unable to generate an authentication code at this time, please try again.", ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            $this->State['key'] = $response->key;
            $this->State['headers'] = $headers;
            $this->State['formURL'] = $formURL;
            $this->State['form'] = $form;
            $question = "We sent a one-time code via the method you selected: {$deliveryAddress}, enter the code below. Please allow a few minutes for the code to arrive.";
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        // check errors
        $message = $response->error->userMessage ?? $this->http->FindSingleNode('//p[contains(@class, "error-text")]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");
            // Unable to validate reCAPTCHA response, please try again.
            if (stristr($message, 'Unable to validate reCAPTCHA response, please try again.')) {
                $this->captchaReporting($this->recognizer, false);
                $this->http->setDefaultHeader("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");
                $this->http->unsetDefaultHeader('Authorization');
                $this->http->unsetDefaultHeader('X-ReCaptchaResponse');

                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }// if (stristr($message, 'Unable to validate reCAPTCHA response, please try again.'))

            $this->captchaReporting($this->recognizer);

            /*
             * Incorrect username or password provided.
             * If this problem continues, please contact customer support at 1(800) 854-0790.
             */
            if (stristr($message, 'Incorrect username or password provided')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // We're sorry, but this account is not allowed to login.
            if (stristr($message, 'We\'re sorry, but this account is not allowed to login.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Your account has been disabled for your security. Please contact customer support at 1(800) 854-0790 for assistance.
            if (stristr($message, 'Your account has been disabled for your security')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            /*
             * The information entered does not match our records, or your account is ineligible or no longer participating in the program.
             * Please try again or contact us at 1(800) 854-0790 if you need assistance.
             */
            if (stristr($message, 'The information entered does not match our records, or your account is ineligible or no longer participating in the program.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                // We were unable to retrieve your information. Please try again.
                stristr($message, 'We were unable to retrieve your information.')
                // The rewards website is currently unavailable due to system updates.
                || stristr($message, 'The rewards website is currently unavailable due to system updates')
                // There was an error processing your request. If this problem continues, please contact customer support at 1(800) 854-0790.
                || stristr($message, 'There was an error processing your request. If this problem continues, please contact customer support at ')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Your account is locked. Please contact customer service for assistance.
            if (stristr($message, 'Your account is locked.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            return false;
        }// if ($message)
        elseif ($this->AccountFields['Login'] == 'mandawool' && $this->http->Response['code'] == 500) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        } elseif ($this->AccountFields['Login'] == '999340308' && !$this->http->FindPreg('/deliveryMethods.push/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("We are unable to complete your login at this time as we do not have the required profile information. Please contact us with any questions at 1-800-854-0790.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/let deliveryMethods = \[\];/")) {
            throw new CheckException("We are unable to complete your login at this time as we do not have the required profile information. Please contact us with any questions at 1-800-854-0790.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $data = [
            "code" => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://login.awardcenter.com/mfa/delivery/' . $this->State['key'], json_encode($data), $this->State['headers']);
        unset($this->State['key']);
        unset($this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Entered OTP is wrong.Please enter OTP again
        if (isset($response->validated, $response->attemptsRemaining) && $response->validated == false && $response->attemptsRemaining > 0) {
            $this->AskQuestion($this->Question, "The code entered is invalid. {$response->attemptsRemaining} attempts remaining", "Question");

            return false;
        }

        $this->http->FormURL = $this->State['formURL'];
        $this->http->Form = $this->State['form'];
        $this->http->SetInputValue('AuthenticationToken', $response->successToken);
        $this->http->SetInputValue('password', '');
        $this->http->SetInputValue('g-recaptcha-response', '');
        $this->http->PostForm();

        $this->http->setDefaultHeader("Authorization", $response->successToken); // for https://services.scorecardrewards.com/participants/login?code=
        $this->http->setDefaultHeader("X-ApplicationKey", $this->State['X-ApplicationKey']);

        return $this->getAuthHeaders();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0, true);
        $data = ArrayVal($response, 'data', []);
        $items = ArrayVal($data, 'items', []);

        if (!isset($items[0])) {
            return;
        }
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($items[0], 'displayName')));

        if (isset($this->http->Response['headers'])) {
            $this->logger->debug(var_export($this->http->Response['headers'], true));
        }
        $id = ArrayVal($items[0], 'id');
        $authenticationInfo = $this->http->Response['headers']['authentication-info'] ?? null;

        if ($id && $authenticationInfo) {
            // set Token
            $this->http->unsetDefaultHeader("X-ReCaptchaResponse");
            $this->http->setDefaultHeader('Authorization', $authenticationInfo);

            $this->http->GetURL("https://services.scorecardrewards.com/participants/{$id}/points");
            $response = $this->http->JsonLog(null, 3, true);
            $data = ArrayVal($response, 'data', []);
            // Balance - Available Points
            $this->SetBalance(ArrayVal($data, 'userBalance'));

            // Update Security Questions
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ArrayVal(ArrayVal($response, 'error'), 'userMessage') == 'Please complete security questions.'
            ) {
                $this->throwProfileUpdateMessageException();
            }
        }// if ($id && $authenticationInfo)
        // strange error (AccountID: 1666080)
        elseif ($this->AccountFields['Login'] == 'indra81' && $this->Properties['Name'] == 'View Only') {
            throw new CheckException("You are currently viewing our reward assortment as a guest. Please login or register to experience your full personal program benefits.", ACCOUNT_PROVIDER_ERROR);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[contains(@class, "login-container")]//form//div[contains(@class, "g-recaptcha")]/@data-sitekey');

        if (!$key) {
            $response = $this->http->JsonLog(null, 3, true);
            $key = ArrayVal($response, 'reCaptchaSiteKey', false);
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
}
