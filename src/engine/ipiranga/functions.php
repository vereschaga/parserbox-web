<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerIpiranga extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "Accept"        => "application/json, text/plain, */*",
        "Referer"       => "https://www.kmdevantagens.com.br/",
        "Authorization" => "",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerIpirangaSelenium.php";

        return new TAccountCheckerIpirangaSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // checking via amazon return: Network error 28 - Connection timed out after 30001 milliseconds
        /*
        $this->http->SetProxy($this->proxyAustralia());
        */
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['_id_kmv'])) {
            return false;
        }

        $this->http->setDefaultHeader("_id_kmv", $this->State['_id_kmv']);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // stupid users gap
        if (!is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("CPF e/ou Senha Inválidos", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->setMaxRedirects(7);
        $loginURL = 'https://www.kmdevantagens.com.br/';
        $this->http->GetURL($loginURL);

        if (strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')) {
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL($loginURL);
        }

        $this->http->RetryCount = 2;
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->GetURL("https://www.kmdevantagens.com.br/api/auth/csrf");
        $response = $this->http->JsonLog();

        if (!isset($response->csrfToken)) {
            return $this->checkErrors();
        }

        $data = [
            "redirect"       => "false",
            "login"          => $this->AccountFields['Login'],
            "password"       => $this->AccountFields['Pass'],
            "recaptchaToken" => $captcha,
            "csrfToken"      => $response->csrfToken,
            "callbackUrl"    => "https://www.kmdevantagens.com.br/",
            "json"           => "true",
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/x-www-form-urlencoded",
            "Referer"      => "https://www.kmdevantagens.com.br/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.kmdevantagens.com.br/api/auth/callback/credentials", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $url = $response->url ?? null;

        if ($url == 'https://www.kmdevantagens.com.br/') {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        if ($url == 'https://kmdevantagens.com.br') {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        // CPF ou Senha incorreto(s)
        parse_str(parse_url($url, PHP_URL_QUERY), $output);
        $error = $output['error'] ?? null;

        if ($error) {
            $this->logger->error("[Error]: {$error}");
            // false/positive - captcha not passing through validation
            if (
                $error == 'Ocorreu um erro, tente novamente em alguns instantes.'
            ) {
//                $this->captchaReporting($this->recognizer, false);

                /*
                $this->http->GetURL("https://www.kmdevantagens.com.br/api/auth/session");
                $this->http->JsonLog();
                $this->http->GetURL("https://www.kmdevantagens.com.br/");
                $captcha = $this->parseReCaptcha();

                if ($captcha === false) {
                    return false;
                }

                $this->http->GetURL("https://www.kmdevantagens.com.br/api/auth/csrf");
                $response = $this->http->JsonLog();

                if (!isset($response->csrfToken)) {
                    return $this->checkErrors();
                }

                $data = [
                    "redirect"       => "false",
                    "login"          => $this->AccountFields['Login'],
                    "password"       => $this->AccountFields['Pass'],
                    "recaptchaToken" => $captcha,
                    "csrfToken"      => $response->csrfToken,
                    "callbackUrl"    => "https://www.kmdevantagens.com.br/",
                    "json"           => "true",
                ];
                $headers = [
                    "Accept"       => "*
                /*",
                    "Content-Type" => "application/x-www-form-urlencoded",
                    "Referer"      => "https://www.kmdevantagens.com.br/",
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://www.kmdevantagens.com.br/api/auth/callback/credentials", $data, $headers);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();
                $url = $response->url ?? null;

                if ($url == 'https://www.kmdevantagens.com.br/') {
                    $this->captchaReporting($this->recognizer);

                    return $this->loginSuccessful();
                }

                return false;
                */
                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                $error == 'Login ou senha incorreto'
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $error == 'Não foi possível realizar o login. Tente novamente em alguns instantes.'
            ) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;

            return false;
        }// if (isset($error))

        return $this->checkErrors();
    }

    public function Parse()
    {
        $responseSession = $this->http->JsonLog(null, 0);

        $this->http->GetURL("https://www.kmdevantagens.com.br/api/profile-data", $this->headers);
        $response = $this->http->JsonLog();
        // CPF
        $this->SetProperty("CPF", $response->document ?? null);
        // Name
        $this->SetProperty("Name", beautifulName($response->name . " " . $response->surname));

        if (!isset($response->category->description) && strstr($response->document, '***.***.***-')) {
            $response = $responseSession->user ?? null;
            // CPF
            $this->SetProperty("CPF", $responseSession->user->basicInformations->document ?? null);
            // Name
            $this->SetProperty("Name", beautifulName($responseSession->user->basicInformations->name . " " . $responseSession->user->basicInformations->surname));
        }

        // Balance
        $this->SetBalance($response->balance->value ?? $response->balance->balance ?? null);
        // Status
        $this->SetProperty('Status', $response->category->description ?? null);
        // Expirando em ... : ... km
        // Km to expire
        $kmToExpire = $response->balance->expires ?? $response->balance->expiresBalance ?? null;
        $this->SetProperty('KmExpire', $kmToExpire);
        // Expiration date
        if ($exp = $response->balance->expiresDate ?? null) {
            // Km due to expire em
            if (strtotime($exp) && $kmToExpire > 0) {
                $this->SetExpirationDate(strtotime($exp));
            } elseif ($kmToExpire === 0) {
                $this->ClearExpirationDate();
            }
        }// if ($exp = isset($response->balance->expiresDate))

        $this->http->GetURL("https://www.kmdevantagens.com.br/api/extract?page=1&quantity=10&period=7", $this->headers);
        $response = $this->http->JsonLog(null, 3, true);
        // Km earned during 120-day period
        $this->SetProperty('KmEarned', $response->earnedKmPeriod ?? null);

        $this->http->GetURL("https://www.kmdevantagens.com.br/api/voucher-extract?page=1&quantity=25&period=7", $this->headers);
        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response->vouchersTotal) && $response->vouchersTotal > 0) {
            $this->sendNotification("refs #23497 vouchers were found // RR");
        }
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.kmdevantagens.com.br/api/auth/session");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $this->headers['Authorization'] = ArrayVal($response, 'token');
        $email = ArrayVal($response, 'email');
        $document = ArrayVal(ArrayVal(ArrayVal($response, 'user'), 'basicInformations'), 'document');
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[Document]: {$document}");

        return $email !== '' || $document !== '';
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/RECAPTCHA_SITE_KEY\":\"([^\"]+)/") ?? "6LdOUUYoAAAAADXUfHJG8POqDQsOlk4yMxjfoXgN";

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"        => "RecaptchaV3TaskProxyless",
            "websiteURL"  => $this->http->currentUrl(),
            "websiteKey"  => $key,
            "isInvisible" => true,
            "minScore"    => 0.7,
            "pageAction"  => "login",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            /*
            "proxy"     => $this->http->GetProxy(),
            */
            "invisible" => 1,
            "version"   => "v3",
            "action"    => "login",
            "min_score" => 0.7,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
