<?php

class TAccountCheckerPureprofile extends TAccountChecker
{
    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://account.pureprofile.com/');

        if (!$this->http->ParseForm("sign-me-in-form") && !$this->http->FindPreg("/<noscript>You need to enable JavaScript to run this app\.<\/noscript>/")) {
            return $this->checkErrors();
        }

        // enter the login and password
        $this->http->SetInputValue("email", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);

        $data = [
            'username'    => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            'platformKey' => '1efdfeb8-a14a-4eac-b66d-6c4929e4376c',
        ];
        $headers = [
            "Content-Type" => "application/json",
            "Accept"       => "*/*",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://pp-auth-api.pureprofile.com/api/v1/user/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function parseQuestion($message)
    {
        $this->logger->notice(__METHOD__);
        // You need to verify your device first, email has been sent to: ...
        $email = $this->http->FindPreg("/email has been sent to:? (.+)/", false, $message);

        if (!$email) {
            $this->logger->error("email not found");

            return false;
        }

        $question = "Please enter the authorization link which was sent to your email: {$email} (link from 'AUTHORISE THIS LOGIN' button)"; /*review*/

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        // wrong link
        if (!filter_var($answer, FILTER_VALIDATE_URL) || $answer == 'https://my.pureprofile.com/settings') {
            $this->AskQuestion($this->Question, "The link you entered seems to be incorrect"); /*review*/

            return false;
        }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))
        $this->http->RetryCount = 0;
        $this->http->GetURL($answer);
        $this->http->RetryCount = 2;
        /*
         * link from email
         * https://access-email.pureprofile.com/api/v1/email/clicks/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXNzYWdlIjoiODg5OGIwYzMtZDFlNy00M2YzLThhOTgtYTJiMGI5MWU2M2NjIiwibGluayI6Imh0dHBzOi8vcHAtYXV0aC1hcGkucHVyZXByb2ZpbGUuY29tL2FwaS92MS91c2VyL3ZlcmlmeS1kZXZpY2U_dG9rZW49M2Q4ZWYwYjctNmY4Yi00MzRlLTljOGEtMmMyMTM1ZjIyMzZmXHUwMDI2ZW1haWxUb2tlbj02MmQ4MmU0NC0wOGFiLTRhYmMtOTVkYi01ZDQ1MzhlYzFhYzNcdTAwMjZyZWRpcmVjdD1odHRwcyUzQSUyRiUyRmFjY291bnQucHVyZXByb2ZpbGUuY29tJTJGbG9naW4tYXV0aG9yaXNhdGlvbiUyRiUzRnRva2VuJTNEM2Q4ZWYwYjctNmY4Yi00MzRlLTljOGEtMmMyMTM1ZjIyMzZmIiwiZ3VpZCI6IjIzM2I3NTRmLTJiMDgtNDQ5OS1iZWZlLWQ1YzdmNzQ2ZjMxNiIsImlzcyI6ImFjY2Vzcy1lbWFpbCJ9.QDsRKI6VSiZHSmtaXluc2Y8d6SKbds_0iknMAs1vYFM
         *
         * redirect to
         * https://account.pureprofile.com/login-authorisation/?token=3d8ef0b7-6f8b-434e-9c8a-2c2135f2236f
         */
        /*
        // if wrong/old link was entered
        if ($this->http->FindSingleNode() {
            throw new CheckException("Something went wrong, perhaps you entered incorrect or expired link. Please try update your account one more time.", ACCOUNT_PROVIDER_ERROR);/*review* /
        }
        */
        $token = $this->http->FindPreg("/\/\?token=([^&]+)/", false, $this->http->currentUrl());

        if (!$token) {
            $this->logger->error("something went wrong, token not found");

            return false;
        }

        $this->setAuthData($token);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Sorry, an error occurred while processing your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our tech stars are working on something awesome you\'ll get to see very soon.")]')) {
            throw new CheckException("Maintenance Mode. " . $message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->ppToken)) {
            $this->setAuthData($response->ppToken);

            return true;
        }

        if (isset($response->error, $response->message)) {
            $this->logger->debug("error: {$response->error}");
            $this->logger->debug("message: {$response->message}");

            // ReCaptcha
            if (($response->error == 'Bad Request' && strstr($response->message, 'recaptchaResponse has not been submitted in request body!'))) {
                $captcha = $this->parseReCaptcha('6Lfr45kUAAAAAOOMLIpPtwnley0vHYsFxWyfHu5W');

                if ($captcha !== false) {
                    $data = [
                        'username'          => $this->AccountFields['Login'],
                        'password'          => $this->AccountFields['Pass'],
                        'platformKey'       => '1efdfeb8-a14a-4eac-b66d-6c4929e4376c',
                        'recaptchaResponse' => $captcha,
                    ];
                    $headers = [
                        "Content-Type" => "application/json",
                        "Accept"       => "*/*",
                    ];

                    $this->http->RetryCount = 0;
                    $this->http->PostURL("https://pp-auth-api.pureprofile.com/api/v1/user/login", json_encode($data), $headers);
                    $this->http->RetryCount = 2;
                    $response = $this->http->JsonLog();

                    if (isset($response->ppToken)) {
                        $this->setAuthData($response->ppToken);

                        return true;
                    }
                }
            }

            // The provided email and password do not match
            if (($response->error == 'Unauthorized' && strstr($response->message, 'Invalid username/password'))
                || ($response->error == 'Forbidden' && strstr($response->message, 'Account has been closed.'))
                || ($response->error == 'Unauthorized' && strstr($response->message, 'Account has been closed.'))
                || ($response->error == 'Forbidden' && strstr($response->message, 'Account has been suspended.'))
                || ($response->error == 'Unauthorized' && strstr($response->message, 'This login and password combination doesn\'t match our records, try resetting your password.'))
                || ($response->error == 'Bad Request' && strstr($response->message, 'Request body validation failed.'))
                || ($response->error == 'Bad Request' && strstr($response->message, 'This login and password combination doesn\'t match our records, try resetting your password.'))
            ) {
                throw new CheckException("The provided email and password do not match", ACCOUNT_INVALID_PASSWORD);
            }
            // Oh no! Your password has expired
            if (
                $response->error == 'Bad Request'
                && strstr($response->message, 'Password expired, please set up a new one.')
            ) {
                throw new CheckException("Oh no! Your password has expired. We just sent you an email to create a new password with at least 6 characters, one upper case letter and a number.", ACCOUNT_INVALID_PASSWORD);
            }

            // Oops! You’ve entered your password incorrectly 3 times.
            if ($response->error == 'Forbidden' && strstr($response->message, 'Invalid status of user')) {
                throw new CheckException("Oops! You’ve entered your password incorrectly 3 times.", ACCOUNT_LOCKOUT);
            }
            // Your account has been locked, please reset your password
            if ($response->error == 'Unauthorized' && strstr($response->message, 'Account has been locked.')
                || $response->error == 'Bad Request' && strstr($response->message, 'is locked. Reset your password to unlock it.')) {
                throw new CheckException("Your account has been locked, please reset your password", ACCOUNT_LOCKOUT);
            }

            if (
                $response->error == 'Bad Request'
                && strstr($response->message, "Sorry, but your account {$this->AccountFields['Login']} is suspended. Please contact our support team.")
            ) {
                throw new CheckException($response->message, ACCOUNT_LOCKOUT);
            }

            /*
             * Oh no! Your password has expired
             * We just sent you an email to create a new password with at least 6 characters, one upper case letter and a number.
            */
            if (($response->error == 'Unauthorized'
                && strstr($response->message, 'Password expired, please set up a new one. Email has been sent to'))) {
                throw new CheckException("We just sent you an email to create a new password with at least 6 characters, one upper case letter and a number.", ACCOUNT_INVALID_PASSWORD);
            }
            // You need to verify your device first, email has been sent to: [EMAIL]
            if (($response->error == 'Forbidden' && strstr($response->message, 'You need to verify your device first, email has been sent to'))) {
                $this->parseQuestion($response->message);
            }
        }// if (isset($response->error) && $response->error == '$response->error')

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->http->setDefaultHeader("pp-token", $this->http->getCookieByName("pp-token-pnl-2"));
        $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");
        $this->http->GetURL("https://pp-auth-api.pureprofile.com/api/v1/user/profile");
        $response = $this->http->JsonLog(null, 3, true);
        $this->SetProperty("Name", ArrayVal($response, 'firstName') . " " . ArrayVal($response, 'lastName'));

        $domain = ArrayVal($response, 'instanceUrl');
        $instanceCode = ArrayVal($response, 'instanceCode');

        if (!$domain || !$instanceCode) {
            $this->logger->error("instanceUrl or instanceCode not found");

            return;
        }

        // Balance - Account Balance
        $this->http->GetURL("https://{$domain}/api/v1/{$instanceCode}/user/balance");
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');
        $this->SetBalance(ArrayVal($data, 'balance'));

        $this->http->GetURL("https://{$domain}/api/v1/{$instanceCode}/info");
        $response = $this->http->JsonLog(null, 3, true);
        // Currency
        $data = ArrayVal($response, 'data');
        $this->SetProperty("Currency", ArrayVal($data, 'currency_code'));
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

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

    private function setAuthData($ppToken)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setCookie("pp-token-pnl-2", $ppToken, ".pureprofile.com");
        $this->http->setDefaultHeader("pp-token", $ppToken);
        $this->http->GetURL("https://my.pureprofile.com/");
    }
}
