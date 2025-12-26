<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerMorrisons extends TAccountChecker
{
    use OtcHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private array $headers = [
        'Accept'              => '*/*',
        'Content-Type'        => 'application/json',
        'Referer'             => "https://more.morrisons.com/",
        'Origin'              => "https://more.morrisons.com",
    ];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], "ReadyToLoad")) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "£%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->disableOriginHeader();
        $this->http->RetryCount = 0;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'], $this->State['token'])) {
            return false;
        }

        $this->http->setDefaultHeader('Authorization', $this->State['Authorization']);

        if ($this->loginSuccessful()) {
            return true;
        }

        $this->http->unsetDefaultHeader('Authorization');

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://more.morrisons.com/login');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $token = $this->getAuthToken();
        $this->State['token'] = $token;
        $this->State['deviceId'] = $this->genUuid();

        if (empty($token)) {
            $this->DebugInfo = "X-Firebase-AppCheck not found";

            return false;
        }
        $headers = $this->headers;
        $headers['X-Firebase-Appcheck'] = $this->State['token'];
        $data = [
            "data" => [
                "deviceId" => $this->State['deviceId'],
                "username" => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
            ],
        ];
        $this->http->PostURL("https://europe-west2-mktg-mymorrisons-prd-fb-c0e64.cloudfunctions.net/generateOTPUnauthenticated", json_encode($data), $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->result->requiresValidation) && $response->result->requiresValidation === true) {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }
            $this->AskQuestion("We've sent a verification code to {$this->AccountFields['Login']}", null, 'Question');

            return false;
        }

        $message =
            $response->error->details->body
            ?? $response->error->message
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Looks like your log in details are incorrect. Please check and try again.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Something went wrong, if trying again doesn\'t work, please contact Customer services') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'Rate limit is exceeded') {
                $this->captchaReporting($this->recognizer);
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
            }

            if (
                $message == 'The function must be called from an App Check verified app.'
                || $message == 'Unauthenticated'
            ) {
                $this->DebugInfo = "block or x-firebase-appcheck header issue";

                throw new CheckRetryNeededException(2, 0);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $headers = $this->headers;
        $headers['X-Firebase-Appcheck'] = $this->State['token'];
        $data = [
            "data" => [
                "deviceId" => $this->State['deviceId'],
                "otp"      => $this->Answers[$this->Question],
                "username" => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
            ],
        ];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://europe-west2-mktg-mymorrisons-prd-fb-c0e64.cloudfunctions.net/loginCustomerOTP", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $message =
            $response->error->details->body
            ?? $response->error->message
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Something went wrong, if trying again doesn\'t work, please contact Customer services') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->AskQuestion($this->Question, $message, 'Question');
        }

        if (isset($response->result->firebaseToken)) {
            $this->State['Authorization'] = 'Bearer ' . $this->State['token'];

            return $this->authorization($response);
        }

        return false;
    }

    public function genUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName(trim($response->result->firstName)));
        // CardNumber
        $cardNumber = $response->result->cardNumber;
        $this->SetProperty('CardNumber', $cardNumber);

        $data = [
            'data' => [
                'deviceId'      => $this->State['deviceId'],
                'mock'          => false,
                'mockBehaviour' => null,
            ],
        ];
        $this->http->PostURL('https://europe-west2-mktg-mymorrisons-prd-fb-c0e64.cloudfunctions.net/getCashpotBalance', json_encode($data), $this->headers);
        $response = $this->http->JsonLog();

        // More Points
        $this->SetBalance($response->result->totalPointsBalance);
        // Until next Fiver
        $this->SetProperty('PointsUntilNextVoucher', $response->result->pointsUntilNextVoucher . ' points');
        // Ready to load
        $this->AddSubAccount([
            'Code' => 'morrisonsReadyToLoad',
            'DisplayName' => 'Ready to load',
            'Balance' => $response->result->redeemablePoundsBalance,
        ]);


        // My Morrisons Offers
        $data = [
            'data' => [
                'deviceId'      => $this->State['deviceId'],
                'mock'          => false,
                'mockBehaviour' => null,
                'rewardTypes'   => ['Product', 'Pound'],
            ],
        ];
        $this->http->PostURL('https://europe-west2-mktg-mymorrisons-prd-fb-c0e64.cloudfunctions.net/getPersonalOffers', json_encode($data), $this->headers);
        $response = $this->http->JsonLog();
        $offers = $response->result->offers ?? [];

        foreach ($offers as $offer) {
            $this->AddSubAccount([
                'Code'             => md5($offer->expiryDate . $offer->header),
                'DisplayName'      => $offer->header,
                'Balance'          => null,
                'VoucherCode'      => $offer->voucherCode,
                'ExpirationDate'   => $offer->expiryDate / 1000,
            ]);
        }// foreach ($offers as $offer)
    }

    // fire_app_check
    protected function parseCaptchaV3($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => 'https://more.morrisons.com/login',
            "websiteKey" => $key,
            "minScore"   => 0.9,
            "pageAction" => "fire_app_check",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => "https://www.mymorrisons.com/login", //$this->http->currentUrl(),
            "websiteKey"   => $key,
            "isInvisible"  => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.mymorrisons.com/login", //$this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function getAuthToken(): ?string
    {
        $this->logger->notice(__METHOD__);
        $result = Cache::getInstance()->get('morrisons_token');

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set token from cache: {$result}");

            return $result;
        }

        $this->logger->notice("get new token from site");
        $this->http->GetURL("https://firebase.googleapis.com/v1alpha/projects/-/apps/1:210892075174:web:47108e5e58f70225814467/webConfig", ["x-goog-api-key" => "AIzaSyBLgPcV9UFV7rVew_wVQi3t7IduqtqDD_A", "content-type" => "application/json"]);
        $config = $this->http->JsonLog();

        if (!isset($config->appId)) {
            return false;
        }

        $appId = $config->appId;

        $this->http->PostURL("https://firebaseinstallations.googleapis.com/v1/projects/mktg-mymorrisons-prd-fb-c0e64/installations", '{"fid":"eqNakthSkACHQVoMt29VwV","authVersion":"FIS_v2","appId":"' . $appId . '","sdkVersion":"w:0.5.8"}', ["x-goog-api-key" => "AIzaSyBLgPcV9UFV7rVew_wVQi3t7IduqtqDD_A", "content-type" => "application/json"]);
        $response = $this->http->JsonLog();

        if (!isset($response->authToken->token)) {
            return $this->checkErrors();
        }

        $captcha = $this->parseCaptchaV3("6LfMeecfAAAAAFXgqPa7KUyyhsgN7c-WXf6MSQMv");

        if (!$captcha) {
            return $this->checkErrors();
        }

        $data = [
            'recaptcha_v3_token' => $captcha,
        ];
        $this->http->PostURL("https://content-firebaseappcheck.googleapis.com/v1/projects/mktg-mymorrisons-prd-fb-c0e64/apps/{$appId}:exchangeRecaptchaV3Token?key=AIzaSyBLgPcV9UFV7rVew_wVQi3t7IduqtqDD_A", json_encode($data), $this->headers);

        $response = $this->http->JsonLog();

        if (!isset($response->token)) {
            // recaptcha issue
            if (isset($response->message) && $response->message == ' App attestation failed.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            return $this->checkErrors();
        }

        $this->captchaReporting($this->recognizer);
        $result = $response->token;
        $this->logger->debug("set new token: {$result}");
        Cache::getInstance()->set('morrisons_token', $result, 60 * 60 * 20);

        return $result;
    }

    private function authorization($response): bool
    {
        $this->logger->notice(__METHOD__);

        if (isset($response->result->firebaseToken)) {
            $data = [
                "token"             => $response->result->firebaseToken,
                "returnSecureToken" => true,
            ];

            $this->http->PostURL("https://identitytoolkit.googleapis.com/v1/accounts:signInWithCustomToken?key=AIzaSyBLgPcV9UFV7rVew_wVQi3t7IduqtqDD_A", json_encode($data), $this->headers);
            $authResponse = $this->http->JsonLog();

            if (!isset($authResponse->idToken)) {
                return false;
            }

            $data = [
                "grant_type"    => "refresh_token",
                "refresh_token" => $authResponse->refreshToken,
            ];
            $this->http->PostURL("https://securetoken.googleapis.com/v1/token?key=AIzaSyBLgPcV9UFV7rVew_wVQi3t7IduqtqDD_A", $data);
            $response = $this->http->JsonLog();

            if (!isset($response->id_token)) {
                return false;
            }

            $this->State['Authorization'] = ucfirst($response->token_type) . " " . $response->id_token;
            $this->http->setDefaultHeader('Authorization', $this->State['Authorization']);

            return $this->loginSuccessful();
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = $this->headers;
        $headers['X-Firebase-Appcheck'] = $this->State['token'];
        $headers['Authorization'] = $this->State['Authorization'];
        $data = [
            'data' => [
                'deviceId'      => $this->State['deviceId'],
                'mock'          => false,
                'mockBehaviour' => null,
            ],
        ];
        $this->http->PostURL('https://europe-west2-mktg-mymorrisons-prd-fb-c0e64.cloudfunctions.net/getCustomerLite', json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!empty($response->result->customerNumber)) {
            return true;
        }

        return false;
    }
}
