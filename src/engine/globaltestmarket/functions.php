<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerGlobaltestmarket extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $loginToChina = false;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""          => "Please select your region",
            'Australia' => 'Australia',
            'France'    => 'France',
            'Germany'   => 'Germany',
            "Singapore" => "Singapore",
            "UK"        => "United Kingdom",
            "USA"       => "United States",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyUK());
        switch ($this->AccountFields['Login2']) {
            case 'Singapore':
                //$this->http->SetProxy($this->proxyDOP(['sgp1']));
                $this->setProxyBrightData(null, 'static', 'sg');

                break;

            case 'UK':
                // Needed UK proxy
                $this->http->SetProxy($this->proxyDOP(['lon1']));

                break;

            case 'Germany':
                $this->http->SetProxy($this->proxyDOP(['fra1']));
//                $this->setProxyBrightData(null, 'static', 'de');
                break;

            case 'France':
                $this->setProxyBrightData(null, 'static', 'fr');

                break;

            default:
                break;
        }
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->http->setDefaultHeader("Origin", "https://www.lifepointspanel.com");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.lifepointspanel.com/member/dashboard", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.lifepointspanel.com");

        if ($this->AccountFields['Login2'] == 'UK') {
            $this->http->GetURL("https://www.lifepointspanel.com/login");
        }

        if (!$this->http->ParseForm('lp-login-form')) {
//            $this->http->PostURL("https://www.lifepointspanel.com/en-US/webcamstatus", ["webcamstatus" => "1"]);
//            sleep(2);
            $this->http->PostURL("https://www.lifepointspanel.com/loginhtml", []);
            $str = $this->http->JsonLog($this->http->FindSingleNode("//textarea"), 0);
            $this->http->SetBody($str);
            $this->http->SaveResponse();
        }
        $form_build_id = $this->http->FindSingleNode("//input[@name = 'form_id' and @value = 'lp_login_form']/preceding-sibling::input[@name = 'form_build_id']/@value");

        if (!$this->http->ParseForm('lp-login-form') || !$form_build_id) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.lifepointspanel.com/en-us/login?ajax_form=1&_wrapper_format=drupal_ajax';
        $this->http->SetInputValue("login_email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("form_id", "lp_login_form");
        $this->http->SetInputValue("form_build_id", $form_build_id);

        //$key = $this->http->FindSingleNode("//form[@id='lp-login-form']//div[@class='g-recaptcha']/@data-sitekey");
        if ($key = $this->http->FindPreg("/api\.js\?render=([A-z\d]{27}-[A-z\d]{7}_[A-z\d]{4})/")) {
            $captcha = $this->parseReCaptcha($key, true);

            if ($captcha === null) {
                return false;
            }
            $this->http->SetInputValue('simple_recaptcha_token', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            // 502 Bad Gateway
            $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway') or contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindPreg("/The website encountered an unexpected error\. Please try again later\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Needed UK proxy
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Hi! It looks like LifePoints is not available in your country yet.')]")) {
            $this->DebugInfo = 'Bad proxy';
        }

        return false;
    }

    public function Login()
    {
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Referer"          => "https://www.lifepointspanel.com/",
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response[1]->url)) {
            $url = $response[1]->url;
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);

            // skip privacy update
            if ($skip = $this->http->FindSingleNode('//a[strong[contains(text(), "Skip")]]/@href')) {
                $this->http->GetURL($skip);
            }
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Consent to share your information below or click CONFIRM to continue to your survey dashboard
        if ($message = $this->http->FindSingleNode('//strong[contains(., "Consent to share your information below or click CONFIRM to continue to your survey dashboard")]')) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }
        // The answer entered for the CAPTCHA is not correct
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger") and (
                contains(., "The answer entered for the CAPTCHA is not correct")
                or contains(., "The answer you entered for the CAPTCHA was not correct.")
            )]', null, true, "/Error message\s*([^<]+)/")
            ?? $this->http->FindSingleNode('//div[contains(@class, "alert-danger") and (
                contains(., "Verification failed. Please try again later.")
                or contains(., "Verifizierung fehlgeschlagen. Bitte versuchen Sie es später erneut.")
            )]')
        ) {
            $this->logger->error("[Error]: {$message}");

            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException();
        }
        // Your email or password was entered incorrectly. Please try again or contact us.
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger") and (
                contains(., "Your email or password was entered incorrectly. Please try again")
                or contains(., "Email or password was entered incorrectly. Try again. If you have forgotten your password or email")
                or contains(., "Email or password was entered incorrectly. If you have forgotten your password or email address,")
                or contains(., "Your account was closed.")
                or contains(., "Your membership was canceled.")
                or contains(., "Your membership was cancelled.")
                or contains(., "L’adresse e-mail ou le mot de passe que vous avez indiqué(e) est incorrect. Veuillez essayer à nouveau ou nous")
                or contains(., "L\'adresse e-mail ou le mot de passe est incorrect. Veuillez réessayer. Si vous avez oublié votre mot de passe ou votre adresse e-mail")
                or (contains(., "The email address ") and contains(., " is not valid."))
                or contains(., "Sorry, we couldn\'t find an account with that username.")
                or contains(., "L\'adresse e-mail ou le mot de passe est incorrect.")
                or contains(., "Veuillez réessayer L\'adresse e-mail ou le mot de passe est incorrect.")
            )]', null, true, "/(?:^Error message\s*|)([^<]+)/")
        ) {
            $this->logger->error("[Error]: {$message}");

            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//li[contains(@class, "item--message") and (
                contains(., "La vérification a échoué. Veuillez réessayer ultérieurement.")
            )]')
        ) {
            $this->logger->error("[Error]: {$message}");

            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, your membership is temporarily unavailable. Please contact our Help Center for support.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-danger') and
                (contains(., 'Sorry, your membership is temporarily unavailable. Please contact our Help Center for support.')
                or contains(., 'Sorry, your membership is temporarily unavailable. Please contact our Help Centre for support.')
                or contains(., 'Membership not verified. When you first registered we sent an email containing a link to verify this email address.')
                or contains(., 'We are unable to process the request at this moment, please try again later.')
                or contains(., 'Désolé, votre compte n’est pas disponible pour le moment. ')
                or contains(., 'We are sorry to inform you that you have not qualified for our community.')
                )]", null, true, "/Error message\s*([^<]+)/")
        ) {
            // recaptcha v3 issue
            if (strstr($message, 'We are unable to process the request at this moment, please try again later.')) {
                $this->logger->debug("[Error]: {$message}");
                $this->captchaReporting($this->recognizer, false);

                if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                    $this->logger->error("wrong captcha answer");

                    return false;
                }

                throw new CheckRetryNeededException(3, 1);
            }

            $this->captchaReporting($this->recognizer);

            if (strstr($message, 'Membership not verified. When you first registered we sent an email containing a link to verify this email address.')) {
                $message = 'Membership not verified. When you first registered we sent an email containing a link to verify this email address.';
            }

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Email or password was entered incorrectly. If you have forgotten your password or email address/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/Sorry, your membership is temporarily unavailable. Please contact our Help Center for support\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - MarketPoints Balance
        $this->SetBalance($this->http->FindPreg("/\"pointsEarned\":(\d+),/"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'dash-header')]/h5", null, true, "/\w+\s*([^,]+)/")));

        $this->http->GetURL("https://www.lifepointspanel.com/member/account");
        // Name
        $name = Html::cleanXMLValue(
            $this->http->FindSingleNode("//input[@name = 'first_name']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@name = 'last_name']/@value")
        );

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        //# Last Transaction Date    // refs #17549
        $this->http->GetURL("https://www.lifepointspanel.com/member/activity");

        if ($lastActivity = $this->http->FindSingleNode("//table[@id = 'my-activity-list']//tr[last()]/td[3]")) {
            $lastActivity = strtotime($lastActivity);

            if ($lastActivity !== false) {
                $this->SetProperty("LastActivity", date(DATE_TIME_FORMAT, $lastActivity));
                $this->SetExpirationDate(strtotime("+1 year", $lastActivity));
            }
        }
    }

    protected function parseReCaptcha($key, $isV3 = false)
    {
        $this->logger->notice(__METHOD__);
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

        if ($isV3) {
            $postData = [
                "type"       => "RecaptchaV3TaskProxyless",
                "websiteURL" => $this->http->currentUrl(),
                "websiteKey" => $key,
                "minScore"   => 0.9,
                "pageAction" => "lp_login_form",
            ];

            if ($this->AccountFields['Login2'] != 'Germany') {
                $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
                $this->recognizer->RecognizeTimeout = 120;

                return $this->recognizeAntiCaptcha($this->recognizer, $postData);
            }
            $parameters += [
                "version"   => "v3",
                "action"    => "lp_login_form",
                "min_score" => 0.9,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//header[@id = 'navbar']//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
