<?php

//use AwardWallet\Common\OneTimeCode\OtcHelper;

use AwardWallet\Engine\ProxyList;

class TAccountCheckerRedcard extends TAccountChecker
{
    //use OtcHelper;
    use SeleniumCheckerHelper;
    use ProxyList;

    private $devicePrint;

    private $headers = [
        "X-Account-Type" => "TSYS",
        "X-Timezone-Id"  => "America/New_York",
        "Accept"         => "application/json, text/plain, */*",
        "Content-Type"   => "application/json;charset=utf-8",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br, zstd");
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://rcam.target.com/Secure", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/'X-Security-Token':\s*'([^']+)/")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
//        $this->http->removeCookies();
        $this->http->GetURL("https://rcam.target.com/?");

        $csrf = $this->http->FindPreg("/'X-Csrf-Token':\s*'([^']+)/");
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        $this->http->GetURL("https://rcam.target.com/Odyssey/Login");

        if (!$this->http->ParseForm("loginForm") || !$csrf) {
            return $this->checkErrors();
        }

        $this->sendSensorData($sensorPostUrl);

        $data = [
            "username"               => $this->AccountFields['Login'],
            "password"               => $this->AccountFields['Pass'],
            "cardNumber"             => "",
            "cvv"                    => "",
            "devicePrint"            => "version%3D1%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%5F15%5F7%29%20applewebkit%2F537%2E36%20%28khtml%2C%20like%20gecko%29%20chrome%2F98%2E0%2E4758%2E109%20safari%2F537%2E36%7C5%2E0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010%5F15%5F7%29%20AppleWebKit%2F537%2E36%20%28KHTML%2C%20like%20Gecko%29%20Chrome%2F98%2E0%2E4758%2E109%20Safari%2F537%2E36%7CMacIntel%26pm%5Ffpsc%3D30%7C1536%7C960%7C871%26pm%5Ffpsw%3D%26pm%5Ffptz%3D5%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D0%26pm%5Ffpco%3D1",
            "isSaveUsername"         => false,
            "isUseDifferentUsername" => true,
            "configPurchaseId"       => null,
        ];
        $this->http->RetryCount = 0;
        $headers = [
            "Referer"      => "https://rcam.target.com/?",
        ];
        $this->headers["X-Csrf-Token"] = $csrf;
        $this->http->PostURL("https://rcam.target.com/api/Login/ValidateUsername", json_encode($data), $this->headers + $headers, 40);
        $this->http->RetryCount = 2;

        return true;
    }

    /*
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://rcam.target.com/");

        /*
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");
        * /

        $this->http->GetURL("https://rcam.target.com/Odyssey/Login");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }

        $this->selenium();

        $csrf = $this->http->FindPreg("/'X-Csrf-Token':\s*'([^']+)/");
        $this->logger->debug('[CSRF]: ' . $csrf);

        if (!$csrf) {
            return $this->checkErrors();
        }

        /*
        $this->sendSensorData($sensorPostUrl);
        * /

        $data = [
            "username"               => $this->AccountFields['Login'],
            "password"               => $this->AccountFields['Pass'],
            "cardNumber"             => "",
            "cvv"                    => "",
            "devicePrint"            => $this->http->getCookieByName('DevicePrintCookie', 'rcam.target.com'),
            "isSaveUsername"         => false,
            "isUseDifferentUsername" => true,
            "configPurchaseId"       => null,
        ];
        $this->http->RetryCount = 0;
        $headers = [
            "Referer"      => "https://rcam.target.com/",
        ];
        $this->headers["X-Csrf-Token"] = $csrf;
        $this->http->PostURL("https://rcam.target.com/api/Login/ValidateUsername", json_encode($data), $this->headers + $headers, 40);
        $this->http->RetryCount = 2;

        return true;
    }
    */

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(., 'Card system is getting a new look with familiar features and settings.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->Token)) {
            $this->State["X-Token"] = $response->Token;
            $this->State["X-Csrf-Token"] = $this->headers["X-Csrf-Token"];
        }

        if (isset($response->State, $response->Token, $response->ChallengeQuestions) && $response->State == 'RsaAuthenticate') {
            // https://rcam.target.com/Odyssey/SecurityQuestion
            if (isset($response->ChallengeQuestions[0]->text)) {
                $question = $response->ChallengeQuestions[0]->text;
                $this->State["QuestionID"] = $response->ChallengeQuestions[0]->id;
                $this->AskQuestion($question, null, "Question");

                return false;
            }// if (isset($response->ChallengeQuestions[0]->text))
        }// if (isset($response->State, $response->Token, $response->ChallengeQuestions) && $response->State == 'RsaAuthenticate')

        if (isset($response->State, $response->Token, $response->ChallengeEmailAddress) && $response->State == 'MfaOtp') {
            // https://rcam.target.com/?#/Otp
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://rcam.target.com/api/Login/SendOtpToEmail", "{\"token\":\"{$response->Token}\"}", array_merge($this->headers, ["X-Token" => $response->Token]));
            $this->http->RetryCount = 2;
            $responseOTP = $this->http->JsonLog();

            if (!isset($responseOTP->Token) || !isset($responseOTP->State) || $responseOTP->State != 'MfaOtp') {
                if (is_array($responseOTP) && isset($responseOTP[0]->DisplayText)) {
                    $message = $responseOTP[0]->DisplayText;
                    $this->logger->error($message);

                    if (
                        stristr($message, 'You\'ve been locked out of Manage My RedCard, please contact us at')
                        || stristr($message, 'A passcode has not been sent because you\'ve been locked out of Manage my RedCard. Please contact us at ')
                        || stristr($message, 'A passcode has not been sent because you’ve been locked out of Manage My RedCard. Please contact us at ')
                        || $message == "A passcode has not been sent because you’ve been locked out of Manage My RedCard. Select 'Unlock Account' to continue."
                        || strstr($message, "A passcode has not been sent because you’ve been locked out of Manage ")
                    ) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    $this->DebugInfo = $message;
                }

                return false;
            }

            $this->State["X-Token"] = $responseOTP->Token;

            $question = "Please enter One Time Passcode which was sent to your email address: {$response->ChallengeEmailAddress}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            $this->AskQuestion($question, null, "MfaOtp");

            return false;
        }// if (isset($response->State, $response->Token, $response->ChallengeQuestions) && $response->State == 'MfaOtp')

//        if ($this->sendPassword())
//            return true;
        if (isset($response->State) && $response->State == 'Authorized') {
            return true;
        }

        if (isset($response->Message)) {
            $message = $response->Message;
            $this->logger->error($message);
            /**
             * We're unable to log you into Manage My REDcard.
             * Please contact us at 1-800-394-1829. We’re available 24 hours a day, 7 days a week.
             */
            if (strstr($message, "We're unable to log you into Manage My REDcard")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($response->Message))
        elseif (is_array($response) && isset($response[0]->DisplayText)) {
            $message = $response[0]->DisplayText;
            $this->logger->error("[Error]: " . $message);
            /**
             * We’re unable to log you into Manage My REDcard.
             * Try to reset your security questions using the button below, have your card ready to begin.
             * If you don’t have your card available, please contact us at 1-800-394-1829. We’re available 24 hours a day, 7 days a week.
             */
            if (
                stristr($message, 'We\'re unable to log you into Manage My REDcard.  Please try again later.')
                || stristr($message, 'We\'re unable to log you into Manage My RedCard. Please try again later.')
                || stristr($message, 'We\'re unable to log you into Manage my Target Circle Card. Please ')
            ) {
                throw new CheckException(strip_tags($message), ACCOUNT_PROVIDER_ERROR);
            }
            // The username and/or password is incorrect, please check your information and try again.
            if (stristr($message, 'The username and/or password is incorrect, please check your information and try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // The username and/or password is incorrect. Please check your information and try again or select the ‘Forgot your username or password’ link below.
            if (stristr($message, 'The username and/or password is incorrect. Please check your information and try again')) {
                throw new CheckException("The username and/or password is incorrect. Please check your information and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (stristr($message, 'We\'re unable to log you into Manage My RedCard. Please contact')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;
        }
        // Complete Security Questions
        if (isset($response->State) && $response->State == 'RsaUpdateUser') {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode('//title[contains(text(), "502 Proxy Error")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $this->headers["X-Csrf-Token"] = $this->State["X-Csrf-Token"];

        if ($step == 'MfaOtp') {
            $token = $this->State["X-Token"];
            $data = [
                "oneTimePasscode" => $this->Answers[$this->Question],
                "isRememberMe"    => true,
                "token"           => $token,
            ];
            unset($this->Answers[$this->Question]);
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://rcam.target.com/api/Login/ValidateOneTimePasscode", json_encode($data), array_merge($this->headers, ["X-Token" => $token]));
            $this->http->RetryCount = 2;
        } else {
            if (!isset($this->State["QuestionID"], $this->State["X-Token"])) {
                return false;
            }

            $token = $this->State["X-Token"];
            $data = [
                "challengeAnswers" => [
                    [
                        "id"     => $this->State["QuestionID"],
                        "answer" => $this->Answers[$this->Question],
                    ],
                ],
                "isRememberMe"     => true,
                "token"            => $token,
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://rcam.target.com/api/Login/ValidateSecurityQuestionAnswer", json_encode($data), array_merge($this->headers, ["X-Token" => $token]));
            $this->http->RetryCount = 2;
        }

        $response = $this->http->JsonLog();

        if (isset($response->Token)) {
            $this->State["X-Token"] = $response->Token;
        }
        // Invalid answer
        if (isset($response->Message, $response->Token)
            && (strstr($response->Message, "We're sorry, the security answer does not match the question.")
                || strstr($response->Message, "Your answer to the security question is incorrect. Please check your information and try again.")
                || strstr($response->Message, "We're sorry, the Username and/or answer to the security question is incorrect;")
                || strstr($response->Message, "The passcode you entered is not valid. Please try again.")
            )
        ) {
            if (strstr($response->Message, "The passcode you entered is not valid. Please try again.")) {
                throw new CheckException($response->Message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->AskQuestion($this->Question, $response->Message, "Question");

            return false;
        } elseif (is_array($response) && isset($response[0]->DisplayText)) {
            /**
             * We're unable to log you into Manage My REDcard. Please try again later.
             * If you are trying to make a payment or need immediate assistance please contact us at 1-800-394-1829.
             * We’re available 24 hours a day, 7 days a week.
             */
            if (stristr($response[0]->DisplayText, 'We\'re unable to log you into Manage My REDcard.  Please try again later.')) {
                throw new CheckException($response[0]->DisplayText, ACCOUNT_PROVIDER_ERROR);
            }
            // Could not validate Security Question Answer. Please refresh your screen or try back in a bit.
            if (stristr($response[0]->DisplayText, 'We’re unable to log you into Manage My REDcard. Try to reset your security questions using the button below')) {
                $this->AskQuestion($this->Question, $response[0]->DisplayText, "Question");

                return false;
            }
            /**
             * We’re unable to log you into Manage My REDcard.
             * Try to reset your security questions using the button below, have your card ready to begin.
             * If you don’t have your card available, please contact us at 1-800-394-1829. We’re available 24 hours a day, 7 days a week.
             */
            if (stristr($response[0]->DisplayText, 'We’re unable to log you into Manage My REDcard. Try to reset your security questions using the button below')) {
                throw new CheckException(str_replace('your security questions using the button below', 'your security questions', $response[0]->DisplayText), ACCOUNT_LOCKOUT);
            }

            if (
                $response[0]->DisplayText == "You've been locked out of Manage My RedCard, please contact us at 1-800-394-1829."
                || $response[0]->DisplayText == "A passcode has not been sent because you’ve been locked out of Manage My RedCard."
            ) {
                throw new CheckException($response[0]->DisplayText, ACCOUNT_LOCKOUT);
            }
            /**
             * You've been locked out of Manage My REDcard.
             * You can unlock your account by resetting your security questions using the button below, have your card ready to begin.
             * If you don't have your card, please contact us at 1-800-394-1829. We're available 24 hours a day, 7 days a week.
             */
            if (stristr($response[0]->DisplayText, "You've been locked out of Manage My REDcard. You can unlock your account by resetting your security questions using the button below, have your card ready to begin. If you don't have your card, please contact us at 1-800-394-1829.")) {
                throw new CheckException(str_replace('your security questions using the button below', 'your security questions', $response[0]->DisplayText), ACCOUNT_LOCKOUT);
            }
        }

//        if ($this->sendPassword())
//            return true;
        if (isset($response->State) && $response->State == 'Authorized') {
            return true;
        }
        /**
         * The following agreements are required for you to use the Manage My REDcard site.
         * Please read these carefully and select, "Agree and Continue" at the bottom of each agreement.
         */
        if (isset($response->State) && $response->State == 'EnrollmentConfirm') {
            $this->throwAcceptTermsMessageException();
        }

        return false;
    }

    /*
    function sendPassword() {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, false);
        if (!isset($response->State) || $response->State != 'MfaPassword')
            return false;
        $data = [
            "password" => $this->AccountFields['Pass'],
            "token"    => $this->State["X-Token"],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://rcam.target.com/api/Login/ValidatePassword", json_encode($data), array_merge($this->headers, ["X-Token" => $response->Token]));
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        if (isset($response->State) && $response->State == 'Authorized')
            return true;
        /**
         * The following agreements are required for you to use the Manage My REDcard site.
         * Please read these carefully and select, "Agree and Continue" at the bottom of each agreement.
         * /
        if (isset($response->State) && $response->State == 'EnrollmentConfirm')
            $this->throwAcceptTermsMessageException();
        if (is_array($response) && isset($response[0]->DisplayText)) {
            /**
             * We're unable to log you into Manage My REDcard. Please try again later.
             * If you are trying to make a payment or need immediate assistance please contact us at 1-800-394-1829.
             * We’re available 24 hours a day, 7 days a week.
             * /
            if (stristr($response[0]->DisplayText, 'We\'re unable to log you into Manage My REDcard. Please try again later. '))
                throw new CheckException($response[0]->DisplayText, ACCOUNT_PROVIDER_ERROR);
            /**
             * Oops, the information you entered is incorrect; please try again.
             * Please note, that passwords are case sensitive. If you’ve forgotten your password, please select the “Forgot Your Password?” link.
             * /
            if (stristr($response[0]->DisplayText, 'Oops, the information you entered is incorrect; please try again. Please note, that passwords are case sensitive.'))
                throw new CheckException('Oops, the information you entered is incorrect; please try again. Please note, that passwords are case sensitive.', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }
    */

    public function Parse()
    {
        $this->http->GetURL("https://rcam.target.com/Secure");
        $token = $this->http->FindPreg("/'X-Security-Token':\s*'([^']+)/");

        if (!$token) {
            return;
        }
        $this->http->setDefaultHeader("X-Security-Token", $token);
        $this->http->GetURL("https://rcam.target.com/api/User/current", $this->headers);
        $response = $this->http->JsonLog(null, 3, true);
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($response, 'FirstName') . " " . ArrayVal($response, 'LastName')));

        $accounts = ArrayVal($response, 'Accounts', []);

        foreach ($accounts as $account) {
            if (ArrayVal($account, 'AssociatedAccountTypeCode') != 'MANUAL') {
                continue;
            }
            // Account Identification Number
            $this->SetProperty("Number", ArrayVal($account, 'ProxyNumber'));
            $cardholderSince = ArrayVal($account, 'CardholderSince', null);
            // Cardholder Since ...
            if ($cardholderSince && strtotime($cardholderSince)) {
                $this->SetProperty("CardholderSince", date("F Y", strtotime($cardholderSince)));
            }
        }// foreach ($accounts as $account)

        // REDcard Savings This Year
        $this->http->GetURL("https://rcam.target.com/api/YouSave/", $this->headers);
        // Balance -  REDcard Savings This Year
        $this->SetBalance($this->http->FindPreg("/^([\d.\-,\s]+)$/"));
    }

    public function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $abck = [
            // 0
            "23E2DEE7D1F39B211E6317DAB8E47A25~0~YAAQmWvcF4nyIuSUAQAAvJ6p8w1gCJ1eMXs/qTCo3eyCU+TqotalW4TjwKomr/6y0FytXuo0njU5bsKdAirdG8N8S5ylUKiFxI4i4MercDBAF6mQ7oDUNKHm2FKI7IwH4z71FxSPlcs5/Ynybi/sJMRpST5TUQ0YcfZl14c2pY5GWD/v0wr0nncCGYCRzv+tGbY3EfjFBsYuKziDliascw9rSCXcc77+FunFk/vZqUUa/1Tg9c2vkhMKhQRRYjssatBory/V7id770NKzQ33I6jHLO1VOpXm7LTEmmSwGf51hoNBc4xdNI+tAl9ygCfM9qw60FTq437d6XiG1Gz/oTu+k/sCjC7Mq0oVqdN3pWvToxlHApRij6z839cEMp737AmxUB1g4ClUmOVWD0N78uARyvZx9YiTR9ttEtlQAMRSK3Ji6D08uJaLLrg1YDoII//acZSW2ZCkkY2P4lE1nTrXle73hZb4p8UpQepBPPNvKdFwbazjNxEq0Gj+YEcs7qaOeic0J3wIC23nDJVcC9KOodtRA7m9iF45eSZeqIYsJR1zRYx2QdF6UaLZBhzF5Xl2r4zS83kaVQEK5lI0hOdOboS6Nl+xmvwbGmTaSDdDnCJhaGSFpWBwRSFnTIcqc/WD/sMO5k5PrV89nJf4SOjXN1lPe/4G/pZ6DruZDoHVF6C7LfGYsmEupvEtpNsdDeqn/IcWg02ujHbfD3GF4qoSqXHno//+xUl2cjG2ASzxPZpTdOpnhV3JnBVVFAXo2ZsTjnIS8w==~-1~-1~-1",
            // 1
            "2231C2AF12871C464D986E0C988C4F15~0~YAAQmWvcF+fiIuSUAQAA9Fap8w2jFZ4EZHfMxNk1iaBKedP8MWHl0z5pCozhrFQ6Tbg8YoR8hsp22nDLxjdPy61sDS6ehTcD/vpJ14rrSeZE7QrD90ivICzaI9sQvdojBTu5k7TVe0S0cs+++bwhshEVIpUIhsqnISuzLEyUD+q9ZIhZTYRW06OmkFyVOIxiBA6NQtcH7WwCZeCFgZuo/uhQgrQrMWkHas+Jh7Bh+s1NqSd6CSPI3q9xpCzY9FYwYTz4aY0t2opgQxqd+Ugw1hpUlW8wHkRZ6kZymfZhK8dzMQEdqSxZV6dzR8yxjYvyBOx63FN/BYsNUfDhOtZe047XktgfMSWc6RzghksTzOHMuDz9hFHgU31Vb57aeXbVOYSMtYIsNP0pPetFWedH9iz3ezxZ0dWYuVQv33iha5d74rOLutKfVpU/PCaNUYIxQTfinQRBePSTjZ0h3ECYl8RbaaQJkl6oBAdyVS7dnPV4E/xJGHhKkwRCkLPEEeQTwgTG9pgzZj1P8rWujZmVnonKeIXapclRi2vc+KcdQVIUES77BMyyJMUb4z78StsnwHnEqaGTdkFXGEE8RfiZLSdpd7Uz7+FROADJMHAUxSrOMC8CC9ZiGnU2d4aAfkgpyGGHivrwZ6y3eC27SA9enNaZpEWbFLtTo0HFu5nzYZvAQh8VtqawShmC02mxQ4c2l/KK5Z2rvbT1YWf1TK9TqGSqGfzw+/Q=~-1~-1~-1",
            // 2
            "53F4ACB65C2CE8533DC1F675402AFCFA~0~YAAQmWvcF9TWIuSUAQAA9Bup8w0j9Mng+zcm/exvJE9fWz9imvwQ9B6CqwMfYCQ+Zg8ImHJ+xr5O/skuvyUYhZUGXm/WeGFmWZ67OCz2I+69dQb7XyBRzirIEd+iM9qJnsiGbemxzyE7YBB+EJCBAQ+/v7Cy2JF0GQxVDeYZHQTJNVJYbu7E5j+wWFqo164mMkhq0XSyiFfIT4jbZm13N9IflP5lnHb2cDbJXj2xO8sWRx6TQsvA/2SsO3huD6uCI9Pi3Ql8ToJDgY4Tg6PvObTnxGCb+Rcdn2g53Gixfuq702qs4uHHblH68lhE4H+aA1i02ikbfo6s4N3I2gwTjHdzdWQ3jw0DUHGit4HLc0Pq0RDRZsVbRoK1y2TuEkahfF6MdyqlV+1/4m1olXmICEcJ7vaz53p7/9X/r5dRJUI1fOSMsyoIUtUGM53lhj4fJWnVOjS+sj6DDQzplFuMAbfzwLORLqnoednUn6c6kqzYa5t5twvbccVXAZNIftGJ25lzKqQUeCdJTYwwp0oa9SqLMDJVohmCWry8hd2ZiEcU6ouUY2QWLn++9JAOyTJnTUhYdaP6JZXa0BMkac0V3y10B/rHU5RqstDaZJGOCom/M6og7Y7QW6Wqi2TTY9boFKiKUDI9zqGeM1Rauitwsg8wM6AmBQFZ2vDkG8+4xxkzl/gv7mVQn4LSgjxdONdr93PL6V7L6PdOcUF6KGA0Bc4SPPaWXcg=~-1~-1~-1",
        ];
        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";
        $this->http->setCookie("_abck", $abck[$key]);

        $sensorData = [
            // 0
            '3;0;1;0;3354950;6C5VNdq/Z4T8RZRJIhCTtfaPgiVQPm5ECbDkS/26/RU=;17,0,0,0,2,0;Z\"Vpv\"2:M\"b\"9,PJH\"^{T\"HcAbx\"f\"!yrDzV\"\"F\"R{M\"/n/#F\"%Dxr\"lE}QND8pKC`@qS53If<kWw >\"L+m\"*Y\"7kV\"t\"aili?4Vzrg CuW\"t{G\"NOf\"F\"\"|\".9=\"lxiZ<uz\"<CZC!oihA^p3,c|ljc`qookp#H!6y.zfc**yYhp[Oea-\"5\"GP+\"|ag?05IJC-u.R]D{=X\"o-_V^F,m?gK-eZjm!03h&LE<OE-c{tLHAN:S:H#{\"Pl;\"zh|\"/5Fio\"]6)\"oM~\"IG^\"3\"\"$\"v.\"X\"\"!MP\".Wr\"c\"|a? ^\"jls\"&o1\"T)u3U>q#$\"BxJ\"SwzgPk!AuGGo\"1\";iR\"=,:\" Yb}8\"p?#I\"HL1l.#hH4CW4Ff{xugU\"*:;\"p\"\"5\"rCp\"h_R[vF$D<dz6Hm\"lU\"J\"\"[\"(Gj\"S\"_8Oi`m>l\"Af^\":n+\"z\"\"jUw\"c*>\"uW%q;\"#>PG\"+wsyEt;]w9L^/+Vu06h)k=.kE T\"<NRv#\"1\"3u(j0(|G@C4 xpF \"]\"icI\"}@AU+\"k`8\"XtBuK\"*=a\"1:>y*([\">%r\"Iv.)2k&,Rg[v\"#_&L\"7HJ\"n?!u\"y_eI;aBU\"M+>\"Oy`+}`;m<AkOFdeL~\"HEE\"hU/s0QC~>E\"<{o\"b\"15VcMAeP6ogVauE0>{p/KCbrTT(gUhmvKJ-V~Z?tgxLE+OWAm)p9_DKz!y^oa^G;v[f<`O6]CsgPjlx.dl`<r|{iaU)(m6w~XHu;ABwB`` oC9|%8&}N*\")g}\"S;?\"a\"C\"MZ2\"Yb\"VcO!#\"1RFJ\"%0*H$l%zc~j\"UJ#\"-6m ![di@A\"bn\"J~`mxX*\"_Zb\"FI=g],E$cGKO]v7\"_-C\"t\"\"Q\" t[\"9\"LR\"BQ~\"o-kf\"<\"\"B\"t0f\"8xO 6\"UC\"62?JY\"J?\"l\"20>Y&\"+V%\"p !\"2Ji;u ,l]y7BDgd#]~?81O\"G\"*f;\"7W51V\"k?m]\"a\"\"X\"OME\"]M>ch\"_Oo\"E}%2(}xJFu?8\"c6j(\"t\"i\"W\">_s\"K\"e\"i]j\"v|N\"O\"\"g\"<r4\"8Q<It\"26t\";\"`-\"M\",dq\"zm<\">d3\"_\"\"[.t\"E>)\">\"\"p\";qg\"7\"\"q\"z&#\"2b~n)\"q-\"vW<IU2TK#+kc=xR{\">qW\"F\"SEkWV\"\"#\"~]T\"Hl=x[Ow_?oWW\"\"f\"GQ \"f\"pV\"V95\")o&R\"4#RgK\"@u\"m\"\"*\"v:_\"Vud~@n;Lekb-<}03P D\"/~[\":QC]BP8w~cY?1TGi)sn8^E5.+9@A?B$tCibY|7#bS6@!|D3Lv|xHxlN^Prt#p}pb3Vfj7a<~t`_VE)!;niwAxqdoj)(h*Q/#Y{Qj3bB*TTYM[I;g(iV`P_7Tq[$AcTGq+$;IPSV^C$2K{gY(Mo%J<Sc|.R]+;<AoD88Qp=vmtrC_(s$WK^9G.+0FxQg;<gemK9B-!b[cg5NOeO[<t?V:+\"1\"HvA\"YmwcJ=+6q]N\"_j~\"mZQG%\"UH6M\"BT2W)Io#<hh#\"*t%u\"g!-JI\")}@\"$n14hwg}\"|8D\"-\"Cj_\"b\"drI\"M\"\"9\"9l[\"Oe5Z/_7\"1?V\"+\"woit\":25\"TXx\"9] et,xLn_|z=\"#<CJvVN\"5\"c|VqAUqhjH&$%\"s\"mY2\"~\"zIRP*Wfl(2Kui_1sBIN)dgp;<0gdd\"1{\"s\"aEs\"ez&i \")^(E\"!\"=\"DeO',
            // 1
            '3;0;1;0;4469060;6C5VNdq/Z4T8RZRJIhCTtfaPgiVQPm5ECbDkS/26/RU=;16,0,0,0,2,0;w\".bM\"NXI\"r\"E%Pyq\"9}8\"i49[?\"1\"7ZI:zi\"\"{\"V}v\"SxGs*\"*Vt!\"^f:1y(Z/MCvH6[[3K5_N$g;o\"HI,\">+\"IF#\"Q\"6P!t~f?GN}H,]R\"|X3\"s1M\"%\"\"Q\"yI;\"bh>.6a!\"6t0Cv%w;?bUp>R5t|JU}}:y?:av 62Cj}s0DpG17n4g$\"M\"Ws*\"C Z~ul-PWWDe *X4~2\"P1O>tgo@$xbp#BKhrf+R^i2qI-+2DpFiCk<Sq#wg\"Dr|\"$zC\"W?ZBV\"]P)\"V75\"$j5\"K\"\";\"C:\"T\"\"E=%\"H&|\"#\"I,n h\"7`X\"YR;\".UcW/R.mW\"8t(\"~2[:NNrY2?5.\"9\"G6*\"$**\"N7fsX\"7=#&\"JHOh0eEXBhne]zih2wt\"D )\")\"\"r\"=Al\"|0g~rD^c,=f](k\"9~\"k\"\"j\"N;S\"5\"}L$HQ<q!\"5Gf\"NW!\"M\"\"l1e\"4*8\" xu}j\"w6c?\"WXo{f~Wg*x([+=J%#(!AD[i|jqL\"Lqc?y\"U\"|Bm7i*!vTvPP&rwL\":\">B[\"Vi&~b\"0O{\"Ft0in\"yjV\"p_qeu@Y\"#{G\"Cj4zyFY4X%Y1\"04lN\"M8]\"SS18\"Puu//kNl\"W|4\"vP1l=&1]^h3ci`Zy?\"w5|\"nbY:gvn-T1\"<mk\"A\"Ih L~&ug{P{Hi_9VciO)+1);HN@ijYuO|@~ajPxMgziWKYd+L&hCVR;d/6140ph$brrVdv)Vl(N%E)O8l=!g$M%s(!rZTic+m <)ny6LSEoeG&l@XvXLc\"I#m\"WAO\"T\"`\"-Xw\"1/\"L{U? \"l.H>\"w2s6-zR3.7)\"=T,\"-P]}GrG uO\"`b\"VO|2lVJ\"F4U\"{W=4}Z^OkU+vH`&\":;O\"K\"\"&\"@`=\"3\"5B\"k?Y\"Rx>7\"6\"\"Z\"x_-\"(z?uk\"YE\"@V9c;\"X3\"h\"k _dS\"[iB\"O(+\":aX=DDm9<6WD@(pVqO3:jz\"$\"]=M\"nxxb0\"}&Bi\"i\"\"{\"1$;\"NiaVU\"]Se\"x@}k.Xr{iu5]\"%4nZ\"v\"o\"Y\"#k#\"j\"k\"N8t\"/!X\"E\"\"F\"u3a\"0tDp&\"2<;\"|\"GS\"S\"V!J\"G#B\"u[r\"@\"\"laG\"nqA\".\"\"b\"rm0\"$\"\"X\"9M}\"Bd-`)\"<!\"!;2z/:Vo.C^r)OPk\".o?\"Z\"/hBio\"\"*\";ik\"2?`G134.Iovp\"\"&\"Q~L\";\"E*\"R`Q\"MH;]\"gyHc)\"J0\"@\"\"y\"3 L\"L0YzZ554RJKYg#%CVF2\"jb^\"HME0}aF>7%`ClDQX7kx|Kb;gQvNj-.F!bv Wz`BC0*hRtO[yX1DHvCuA*jEe~4E1r%;~UD{v^dH>tI^|`Dej9qM{d9wdX~$i[cG9b^4J_qd^=$9R:]e32gA,.77UT<^q=$QABxi9njLt}kx<!.DF>t0lXF2h=Lx**Pob$z[abb}BJ2d&A5x[P&kNv^L!o2NPQz<UUYQ uMLK:&^#z/Xw^\"/\"V=G\"?P#_a95*V}e\"}=?\"cJdGL\"x@,j\"{%onb?{NPA/N\"0OLe\"cbYaQ\"Qga\"4&vsli_V\"5]2\"&\"11(\"K\"f);\"t\"\"-\"$b7\"OVQ]?XC\"nMN\"!\"VXu~\"L_7\">D1\"Sa8X^D-%9#E3\"j8JuMBU\"J\"v4h(PG^F4BiI5\".\"3OO\"(\"kYz8{V}5U_ J4wP#G-(OqR29n?=f<\"X`\":\"V_:\":chOq\"e0RR\"k\"R\"&o<',
            // 2
            '3;0;1;0;4470838;6C5VNdq/Z4T8RZRJIhCTtfaPgiVQPm5ECbDkS/26/RU=;14,0,0,0,2,0;~\"~ L\"jb4\"]\"0tK5O\"hnl\"dniuV\"@\"oG]xy=\"\"E\"^r]\"J}_Cr\"UZVD\"NDWT9C+%NbZS|sHgm8hQ%YT5\"?gd\"-P\">1f\"%\"xC+Cv~-sL`kLog\".vb\"vc4\"~\"\"J\"b(p\"JwWf3&q\";cjo:A3Fa@A tUg{]fc!j$f17lFf7bHH9nnl7o__F|h@\"L\"khb\"R!QotmD5Jm!~jW`vTl\"$8,6,1`[p9(.Od8GtWW76xIlg!]EMR&KyM-V-W|1\"p o\"K%R\"oi-?b\"Sr,\")D4\"qV#\"Z\"\"D\"Hr\"C\"\"cXC\"vs}\"f\" ;Z^,\"0ZI\"cnm\"K8k>T^pO,\"YFj\"%TDI+jc4WF2$\"@\"_|$\"aGz\"f0T[/\"s%+0\"6d@25K |S{*x~OUoWr8\"$E6\"s\"\"]\"@_J\"V$=:e$}+KZ($hk\"su\"v\"\".\"O:D\"6\"A7:|Z|12\"ap4\"z4o\"m\"\"HH^\"Y==\"t{-;B\"Cvp.\"cQY[?VBW$4SPJ.~3[PBDEdt54^O\";5]u3\"L\"R/S,pGZo!{!b{RDj\"G\"[vL\"+t](M\"ABd\"7g)|2\")C`\"(bU?I`{\"Un<\",qn0zv 3U$_0\"Oj_p\"Y!v\"<:gd\"&MtHUhb]\"]Z^\"w[*w_R{l$IQ`6vhly\"Sui\".8YYlDwu$H\"Cz$\"x\"TD~r#i?xzAb(W`@xEn{ s>0}|hd-0%[C6~,=y9NsrolLE_*a;@Ra=t>LocaY`PZ.(g,G sip%1M|]BorgZg+u%5ptTO73MbH?x`{8-r,gR-rZDJ1`:Kra\"e5h\"B :\"`\"N\"]bM\",q\"n5w_C\"aA1=\"$o=K4wI(54@\"(fg\"}jdf%:kWC8\"i4\"r06&B!^\"I=X\"A44Yh{]}~RsR+,o\"=Hz\"c\"\"0\"n R\"@\"Jr\"nia\"MLM8\"t\"\"A\"w!u\"K%o5Y\"(_\"Qrs@0\")6\"o\"97bXN\"Nn^\"FAk\"v$d%lr7_ttBL[j27&<kd\"!\"bF|\";-qR*\"Jzpr\"Z\"\"D\"c7=\"@{5K/\"ZUj\"I6J}(Y{0F{tf\"&2x4\"J\"q\"[\"U`I\"h\"q\"/>A\"9qb\"p\"\"a\"v1k\"6Ifq)\"Do@\"8\"IY\"#\"45T\"P5|\"Ffx\"=\"\"b6X\"osd\"#\"\"p\"xw_\"_\"\"J\"Z*%\"]j/mB\"Bf\"#<3?b00+6>l{Oc61U9\"f|%\"~\"ByU5E\"\"h\"ehS\"G*D:s{)Ao^~J\"\"H\":Pj\"a\"si\"vDX\"i5kv\"`x0j2\"&+\"&\"\"1\"0^~\"v[>ige{GGbL8773NdHO\"JX \"^< KnK{venXN0pN+0A}g}#]nc(pD Alz&y_w#[-!loQZD/qAWr^Jff=hD~_B6Tv<U1.>[A~m`ZIO<BpVs3|q,AwP5sS9!mi1=4|7!rEQ07SupjQIg2oDD!+`A,6?Gw~e1T)oF5NtmM.*q;L:`$p{<NN(9&,gZ3 0d%{T|o91x0):^22*S(J=+|iO?!Q9@tZ/RT^9P{%9)VT=ESJ7f<>P#R1\"x\"JAC\"CfA{(nrg?fT\"(EO\"{s7xu\"]#(f\"23{5vlJ&)~lF\"0PTm\"%z65$\"=JT\"A+z2+176\"zD \"}\"1A@\"m\"1Q|\"a\"\"~\"1vL\"|.)<0Q+\"nEV\"3\" ,HZ\"@R3\"BI>\"x(e(6,js-,F4\"rX{@*s9\"6\"{1t,edzlX H1z\"|\";O`\"Q\">$V~sCy)Yk.`YFmX|jf7iBBA*P^1e\",<\"{\"RZ?\"F /&@\"Mu2R\"t\"c\"?2]',
        ];

        $secondSensorData = [
            // 0
            null,
            // 1
            null,
            // 2
            null,
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        /*
        if ($this->attempt > 0) {
            $key = 1;
        }
        */
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);

        if ($secondSensorData[$key]) {
            $sensorData = [
                'sensor_data' => $secondSensorData[$key],
            ];
            $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            $this->http->JsonLog();
            sleep(1);
        }

        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = $this->http->userAgent;
//            $selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL("https://rcam.target.com/?");
            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 15);
            $login->click();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
