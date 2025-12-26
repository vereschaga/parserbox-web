<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPerekrestok extends TAccountChecker
{
    use ProxyList;

    private const USER_INFO_URL = 'https://api.perekrestok.ru/api/customer/1.4.0.0/user/profile';

    private $headers = [
        "Accept"       => "application/json, text/plain, */*",
        "Content-Type" => "application/json;charset=utf-8",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
//        $this->setProxyBrightData(null, 'static', 'ru');
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->KeepState = true;

        $this->AccountFields['Login'] = str_replace(' ', '', $this->AccountFields['Login']);

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(10);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        }

        $this->http->setUserAgent($this->State[$userAgentKey]);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State["Authorization"])) {
            return false;
        }

        $this->http->setDefaultHeader("Authorization", $this->State["Authorization"]);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (strlen($this->AccountFields['Login']) != 10) {
            throw new CheckException("Некорректный номер телефона. Проверьте корректность ввода и повторите попытку.", ACCOUNT_INVALID_PASSWORD);
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://my.perekrestok.ru/login");

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $this->http->RetryCount = 0;
        $data = [
            "device" => [
                "name"             => "Chrome 90",
                "os"               => "Mac OS 10.15.7",
                "platformIdentity" => "72daa9db8d1a4d8c8352f65e8b223082",
            ],
        ];
        $this->http->PostURL("https://api.perekrestok.ru/api/customer/1.4.0.0/token/anonymous", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();
        $accessToken = $response->content->accessToken ?? null;

        if (!$accessToken) {
            return false;
        }
        $this->http->setDefaultHeader("Authorization", "Bearer {$accessToken}");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $this->State["Authorization"] = $this->http->getDefaultHeader("Authorization");

        if ($this->parseQuestion()) {
            return false;
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        // phone # {"phone":"71112223344","isAdvertAgreed":true}
        $this->http->RetryCount = 0;
        $data = [
            "phone"          => '7' . $this->AccountFields['Login'],
            "isAdvertAgreed" => false,
        ];
        $this->http->PostURL("https://api.perekrestok.ru/api/customer/1.4.0.0/user/sign-in", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();

        // Recaptcha
//        if (!isset($response->content->uuid) && isset($response->captcha_url, $response->captcha_key)) {
//            $captcha = $this->parseReCaptcha($response->captcha_key);
//            if ($captcha === false)
//                return false;
//            $data = ["g_recaptcha_response" => $captcha];
//            $this->http->PostURL("https://my.perekrestok.ru{$response->captcha_url}", json_encode($data), array_merge(["Referer" => "https://my.perekrestok.ru/login"], $this->headers));
//            $response = $this->http->JsonLog();
//            $data = [
//                "number" => $this->AccountFields['Login'],
//            ];
//            $this->http->PostURL("https://my.perekrestok.ru/api/v5/2fa/loyalty/requests", json_encode($data), array_merge(["Referer" => "https://my.perekrestok.ru/login"], $this->headers));
//            $response = $this->http->JsonLog();
//        }
        $this->http->RetryCount = 2;

        $this->State['token'] = $response->content->uuid ?? null;

        if (!$this->State['token']) {
            return false;
        }
        $question = "Please enter Code which was sent to your phone +7{$this->AccountFields['Login']}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Authorization", $this->State["Authorization"]);
        $data = [
            "code"           => $this->Answers[$this->Question],
            "isAdvertAgreed" => false,
            "uuid"           => $this->State["token"],
        ];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.perekrestok.ru/api/customer/1.4.0.0/user/sign-in/confirm", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();
        $code = $response->error->code ?? null;
        $message = $response->error->message ?? null;

        if ($code) {
            if ($code == "INVALID_CONFIRMATION_CODE") {
                $this->AskQuestion($this->Question, "Что-то пошло не так", "Question");

                return false;
            }

            $this->logger->error($message);

            return false;
        }// if ($code)

        if (!$this->setTokens()) {
            return false;
        }

        return $this->loginSuccessful();
    }

    public function setTokens()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $accessToken = $response->content->accessToken ?? null;
        $refreshToken = $response->content->refreshToken ?? null;

        if (!$accessToken || !$refreshToken) {
            return false;
        }

        $authorization = "Bearer {$accessToken}";
        $this->State["Authorization"] = $authorization;
        $this->http->setDefaultHeader("Authorization", $authorization);

        return true;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Имя Фамилия
        $name = $response->content->basicInformation->firstName ?? null;
        $surname = $response->content->basicInformation->lastName ?? null;
        $this->SetProperty("Name", beautifulName($name . ' ' . $surname));

        $this->http->GetURL("https://api.perekrestok.ru/api/customer/1.4.0.0/user/loyalty/card", $this->headers);
        $response = $this->http->JsonLog();
        // Номер карты
        $this->SetProperty("CardNumber", $response->content->accountId ?? null);
        // Balance - Баллов
        $this->SetBalance($response->content->points ?? null);
        // Рублей
        $rub = floor($response->content->rubles / 100);
        $this->SetProperty("Discount", $rub . ' RUB');

        if (isset($response->content->nearestExpiration)) {
            $expDate = strtotime($response->content->nearestExpiration->date);
            $expBalance = $response->content->nearestExpiration->points;

            if ($expBalance > 0 && $expDate) {
                $this->SetExpirationDate($expDate);
                // Expiring balance
                $this->SetProperty("ExpiringBalance", $expBalance);
            }// if ($expBalance > 0 && $expDate)
        }// if (isset($response->content->nearestExpiration))

        $this->http->GetURL("https://api.perekrestok.ru/api/customer/1.4.0.0/coupon", $this->headers);
        $response = $this->http->JsonLog();

        if (!empty($response->content->items)) {
            $this->sendNotification("Coupons were found // RR");
        }

        // Expiration date
//        $this->http->GetURL("https://api.perekrestok.ru/api/customer/1.4.0.0/expiration_info");
//        $response = $this->http->JsonLog();
//        if (!isset($response->data->expiration_info_list))
//            return;
//        if (isset($response->data->expiration_info->date)) {
//            $expDate = $response->data->expiration_info->date;
//            $expBalance = $response->data->expiration_info->value;
//            if ($expBalance > 0 && $expDate) {
//                $this->SetExpirationDate($expDate);
//                // Expiring balance
//                $this->SetProperty("ExpiringBalance", $expBalance);
//            }// if ($expBalance > 0 && $expDate)
//        }// if (isset($response->data->expiration_info->date))
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::USER_INFO_URL, $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $firstName = $response->content->basicInformation->firstName ?? null;

        if ($firstName) {
            return true;
        }

        return false;
    }
}
