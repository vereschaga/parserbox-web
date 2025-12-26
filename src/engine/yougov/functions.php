<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerYougov extends TAccountChecker
{
    use ProxyList;
    use OtcHelper;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""        => "Select a region",
        "Canada"  => "Canada", // refs #19778
        "Germany" => "Germany", // refs #12250
        'ID'      => 'Indonesia',
        "Lebanon" => "Lebanon", // refs #20519
        //        "MENA"    => "Middle East & North Africa", // refs #20519
        "UK"      => "United Kingdom",
        "USA"     => "USA",
    ];

    private $url;
    private $key = null;
    private $language;

    private $authViaCode = [
        'Canada',
        "USA",
        'UK',
        'ID',
        'Lebanon',
        'MENA',
        'Germany',
    ];

    public function UpdateGetRedirectParams(&$arg)
    {
        $this->getDomain();
        $arg["RedirectURL"] = "https://{$this->url}/account/";
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());

        if ($this->AccountFields['Login'] == 'awtestaw111@gmail.com') {
            $this->AccountFields['Login2'] = 'MENA';
        }
        $this->getDomain();

        $this->logger->debug("[url]: {$this->url}");
        $this->logger->debug("[language]: {$this->language}");
    }

    public function getDomain()
    {
        switch ($this->AccountFields['Login2']) {
            case 'Germany':
                $this->url = 'yougov.de';
                $this->language = 'de-de';

                break;

            case 'Canada':
                $this->url = 'ca.yougov.com/ca-en/account';
                $this->language = 'ca-en';

                break;

            case 'ID':
                $this->url = 'account.yougov.com/id-en/account';
                $this->language = 'id-en';

                break;

            case 'Lebanon':
                $this->url = 'ca.yougov.com/lb-en/account';
                $this->language = 'lb-en';

                break;

            case 'MENA':
                $this->url = 'account.yougov.com/ae-en/account';
                $this->language = 'ae-en';

                break;

            case 'UK':
                $this->url = 'account.yougov.com/gb-en/account';
                $this->language = 'gb-en';

                break;

            case 'USA':
            default:
                $this->url = 'account.yougov.com/us-en/account';
                $this->language = 'us-en';

                break;
        }
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://account.yougov.com/api/pubapis/v5/{$this->language}/users/personal_details/settings/", [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->email) && strtolower($response->email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Oops: your email format isn’t right and you need to enter a password.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        $this->http->GetURL("https://account.yougov.com/{$this->language}/login/email");

        if (!$this->http->FindPreg("/window.bootstrapJSON /")) {
            return $this->checkErrors();
        }
        $data = [
            "email" => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://account.yougov.com/api/auth/v1/{$this->language}/auth/login/send_code/", json_encode($data));
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;
        // Please enter the code we sent to veresch80@yahoo.com to log in to your account.
        if ($message == 'OK') {
            $this->AskQuestion("Please enter the code we sent to {$this->AccountFields['Login']} to log in to your account.", null, "2fa");

            return false;
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The service is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The service is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // This system is currently undergoing some planned maintenance
        if ($message = $this->http->FindPreg("/(This system is currently undergoing some planned maintenance\.\s*Service will be resumed no later than([\d\/\:\s*])GMT)/ims")) {
            throw new CheckException(Html::cleanXMLValue($message), ACCOUNT_PROVIDER_ERROR);
        }
        // The service is temporarily unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            // hard code
            || strlen($this->http->Response['body']) == 1) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->getWaitForOtc()) {
            $this->sendNotification("mailbox, 2fa - refs #20703 // RR");
        }
        /**
         * Check your email (...0@... .com) to complete your login. No email?
         * Make sure you entered your email and password correctly and try again.
         * If the problem persists, please email us at help@yougov.com.
         */
        $question = "Please copy-paste an authorization link which was sent to your email to continue the authentication process."; /*review*/

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->getDomain();

        if ($step == "2fa") {
            $headers = [
                'Accept'          => 'application/json, text/plain, */*',
                'Content-Type'    => 'application/json',
                'Accept-Encoding' => 'gzip, deflate, br',
            ];
            $data = [
                "code"  => $this->Answers[$this->Question],
                "email" => $this->AccountFields['Login'],
            ];
            unset($this->Answers[$this->Question]);
            $this->http->PostURL("https://account.yougov.com/api/auth/v1/{$this->language}/auth/login/", json_encode($data), $headers);
            $response = $this->http->JsonLog();
            $message = $response->message ?? null;
            // The code you entered is invalid.
            if ($message == 'The code you entered is invalid.') {
                $this->AskQuestion($this->Question, "The code you entered is invalid.", "2fa");

                return false;
            }

            if (isset($response->token)) {
                // fix for accounts with other regions
                if (
                    isset($response->membership->country_code, $response->membership->language_code)
                    && $response->membership->country_code . '-' . $response->membership->language_code != $this->language
                ) {
                    $this->url = $response->url;
                    $this->language = $response->membership->country_code . '-' . $response->membership->language_code;
                }

                $this->http->GetURL("https://account.yougov.com/{$this->language}/account/");

                return true;
            } elseif (isset($response->url) && strstr($response->url, '/join/new/?coreg=eyJ')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }// if ($step == "2fa")

        // wrong link
        if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL)) {
            $this->AskQuestion($this->Question, "The link you entered seems to be incorrect", "Question"); /*review*/

            return false;
        }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))
        $this->http->GetURL($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        sleep(5);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->url}/api/yguser_data/?cacheBuster=" . date("UB"), [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->points, $response->panelistid)) {
            return true;
        }

        return false;
    }

    public function Login()
    {
        // for reCaptcha
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        if (!$this->http->PostForm() && ($this->http->Response['code'] != 500)) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();
        // reCaptcha
        $attempt = 0;

        while (isset($response->error_code) && strstr($response->error_code, 'captcha_required') && $attempt < 3) {
            $this->logger->debug("[Attempt]: {$attempt}");
            $attempt++;
            sleep(3);
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;
            $captcha = $this->parseCaptcha($this->key);

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->PostForm();
            $response = $this->http->JsonLog();
        }// while (isset($response->error_code) && strstr($response->error_code, 'captcha_required') && $attempt < 3)

        if (isset($response->error_code) && $response->error_code == 'bad_email_or_password') {
            throw new CheckException("We don't recognise your email address or your password isn't right", ACCOUNT_INVALID_PASSWORD);
        }
        // Please complete the captcha to continue.
        if (isset($response->error_code) && strstr($response->error_code, 'captcha_required')) {
            throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // You unsubscribed - please contact help@yougov.com to reactivate your account
        if (isset($response->message)
            && ($response->message == 'You unsubscribed - please contact help@yougov.com to reactivate your account'
                || strstr($response->message, 'You unsubscribed - please contact supportme@yougov.com to reactivate your account')
                || strstr($response->message, 'You unsubscribed - please contact supportuk@yougov.com to reactivate your account'))
            ) {
            throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
        }

        // refs #17029
        if (isset($response->message)
            && (strstr($response->message, 'to complete your login. No email? Make sure you entered your email and password')
                || strstr($response->message, 'um den Login abzuschließen. Sie haben keine Email erhalten? Prüfen Sie bitte, ob  Sie Ihre Email-Adresse und Ihr Passwort'))) {
            $this->parseQuestion();

            return false;
        }

        $providerError = $this->http->FindSingleNode("//title[contains(text(), '500 Server Error')]");

        if ($this->http->Response['code'] == 500) {
            $this->http->GetURL("https://{$this->url}/opi/myfeed#/all");
        }
        // Oops: we don't recognise your email address or your password isn't right.
        if ($message = $this->http->FindPreg("/Oops: we don't recognise your email address or your password isn't right\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been deactivated
        if ($this->http->FindPreg("/(Your account has been deactivated)/ims")
            || $this->http->FindPreg('/"message": "Reactivation required"/')) {
            throw new CheckException("Your account has been deactivated", ACCOUNT_PROVIDER_ERROR);
        }

        // Access is allowed
        if ($this->http->FindPreg("/Login successful|Login erfolgreich/ims")) {
            return true;
        }

        if ($providerError) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://account.yougov.com/api/pubapis/v5/{$this->language}/users/personal_details/identity/", $headers);
        $response = $this->http->JsonLog();

        if (isset($response->reason, $response->human_check) && $response->reason == 'inactive' && $response->human_check === false) {
            $this->throwProfileUpdateMessageException();
        }
        // Name
        $this->SetProperty("Name", beautifulName(($response->firstName ?? null) . " " . ($response->lastName ?? null)));

        $this->http->GetURL("https://account.yougov.com/api/pubapis/v5/{$this->language}/users/points/", $headers);
        $response = $this->http->JsonLog();
        // Balance - Points
        $this->SetBalance($response->points ?? null);

        // AccountID: 5176709
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($response->reason, $response->human_check)
            && $response->reason == "inactive"
            && $response->human_check == true
        ) {
            $this->throwProfileUpdateMessageException();
        }

        $this->http->GetURL("https://account.yougov.com/api/pubapis/v5/{$this->language}/users/", $headers);
        $response = $this->http->JsonLog();
        // Member
        $this->SetProperty("Number", $response->id ?? null);
        $this->http->RetryCount = 2;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) && $region != 'MENA') {
            $region = 'UK';
        }

        return $region;
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 100;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }
}
