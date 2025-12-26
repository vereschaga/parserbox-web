<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSclub extends TAccountChecker
{
    use ProxyList;

    private $scriptURL = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://sclub.ru/");
        //$this->scriptURL = $this->http->FindPreg("/<script type=\"text\/javascript\" src=\"(\/?index\.[^\.]+.js)\"/");
        //if (!$this->scriptURL)
        //    return $this->checkErrors();
        $this->http->NormalizeURL($this->scriptURL);
//        $this->http->GetURL("https://www.sclub.ru/");
//        $antiForgeryToken = $this->http->FindPreg("/antiForgeryToken: '([^\']+)/ims");
//        if (!$this->http->ParseForm("mainLogin") || !isset($antiForgeryToken))
//            return $this->checkErrors();
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://oauth.sclub.ru/connect/token';
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue("client_id", "1");
        $this->http->SetInputValue("grant_type", "password");
        //$this->http->SetInputValue("scope", "read:cur_user write:cur_user write:cur_user_email cur_user_books offline_access payment:domru");

//        $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
//        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');
//        $this->http->setDefaultHeader('AntiForgeryToken', $antiForgeryToken);
//        $data = '{"UserName":"'.$this->AccountFields['Login'].'","Password":"'.$this->AccountFields['Pass'].'","StayAuthorized":true,"CollectEmailRequestId":""}';
//        $this->http->PostURL('https://www.sclub.ru/account/login', $data);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.sclub.ru/';
        $arg['SuccessURL'] = 'https://www.sclub.ru/';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'в данный момент на сервере ведутся профилактические работы')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Приносим свои извинения, в данный момент ведутся работы по обновлению сайта.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '403 Forbidden')]")) {
            throw new CheckException("Ошибка выполнения запроса: Not Found", ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if (
            $this->http->FindPreg("/Server Error in \'\/\' Application\./")
            || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindPreg("/<html><body><h1>504 Gateway Time-out<\/h1>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [400, 429, 500])) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        // reCaptcha
        $message = $response->message ?? null;

        if ($message == "Internal Error" && $this->http->Response['code'] = 500) {
            throw new CheckException("Уважаемый пользователь, на сайте произошла непредвиденная ошибка. Попробуйте выполнить действие повторно, либо обратитесь в службу технической поддержки.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message == "'recaptcha-code' header is required" && $this->scriptURL) {
            $this->logger->notice("reCaptcha");

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->setDefaultHeader("recaptcha-code", $captcha);
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;

            $this->http->RetryCount = 0;

            if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [400, 429])) {
                return $this->checkErrors();
            }
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
        }// if (isset($response->message) && $response->message == "'recaptcha-code' header is required" && $this->scriptURL)

        // Access is allowed
        if (isset($response->token_type, $response->access_token)) {
            $this->http->setDefaultHeader('authorization', $response->token_type . ' ' . $response->access_token);
            $this->http->GetURL("https://sclub.ru/api/user");

            return true;
        }
        // Invalid credentials
        if (isset($response->error, $response->error_description) && $response->error == 'invalid_grant') {
            if (strstr($response->error_description, 'Эта карта была заблокирована')) {
                throw new CheckException($response->error_description, ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($response->error_description, ACCOUNT_INVALID_PASSWORD);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://api.sclub.ru/identity/user');
        $response = $this->http->JsonLog();

        $message = $response->message ?? null;
        // Уважаемый пользователь, на сайте произошла непредвиденная ошибка. Попробуйте выполнить действие повторно, либо обратитесь в службу технической поддержки.
        if (
            ($this->http->Response['code'] == 503 && $this->http->FindPreg('/^Service Unavailable$/'))
            || ($this->http->Response['code'] == 500 && $message == 'Internal Error')
        ) {
            throw new CheckException("Уважаемый пользователь, на сайте произошла непредвиденная ошибка. Попробуйте выполнить действие повторно, либо обратитесь в службу технической поддержки.", ACCOUNT_PROVIDER_ERROR);
        }

        // Full Name
        if (isset($response->nickName)) {
            $this->SetProperty("Name", beautifulName($response->nickName));
        } else {
            $this->logger->notice("Name not found");
        }
        // all cards
        if (isset($response->cards)) {
            if (count($response->cards) > 1) {
                $this->sendNotification("sclub. Multiple cards were found. Check it!");
            }

            foreach ($response->cards as $card) {
                $this->http->GetURL("https://api.sclub.ru/cards/{$card}");
                $cardInfo = $this->http->JsonLog();
                // Balance - ... плюса
                if (isset($cardInfo->pluses)) {
                    $this->SetBalance((int) preg_replace('/[^\d\,\.]/ims', '', $cardInfo->pluses));
                }
                // Card Number
                if (isset($cardInfo->ean)) {
                    $this->SetProperty("CardNumber", $cardInfo->ean);
                } else {
                    $this->logger->notice("CardNumber not found");
                }
                // Card status
                if (isset($cardInfo->status)) {
                    $this->SetProperty("CardStatus", $cardInfo->status == 'active' ? 'Активна' : 'Неактивна');
                } else {
                    $this->logger->notice("CardStatus not found");
                }

                // refs #15934, Pending
                if (isset($cardInfo->delayedPluses)) {
                    $subAccounts[] = [
                        "Code"        => 'sclubPendingBalance' . $card,
                        "DisplayName" => "Pending Balance",
                        "Balance"     => (int) $cardInfo->delayedPluses,
                    ];
                } else {
                    $this->logger->notice("Pending not found");
                }
            }// foreach ($response->cards)

            if (!empty($subAccounts)) {
                $this->SetProperty("SubAccounts", $subAccounts);
                $this->SetProperty("CombineSubAccounts", false);
            }
        }// if (isset($response->cards))

        // TODO: It is necessary to check, the structure of json has changed
        // Personal actions ("Персональные акции")
        $this->http->GetURL('https://api.sclub.ru/content/user/personal-offers');
        $rewards = $this->http->JsonLog(null, 3, true);

        if (!$this->http->FindPreg("/^\[\]$/")) {
            $this->sendNotification('sclub: Personal offers were found');
        }

        /*foreach ($rewards as $reward) {
            $id = ArrayVal($reward, 'id');
            $displayName = ArrayVal($reward, 'title');
            $mechanicText = ArrayVal($reward, 'mechanicText');
            $status = ArrayVal($reward, 'status');
            if ($mechanicText)
                $displayName .= " ($mechanicText)";

            $subAccount = [
                "Code"        => 'sclubOffer'.$id,
                "DisplayName" => $displayName,
                "Balance"     => null,
            ];

            if (strtolower($status) == 'active') {
                $alias = ArrayVal($reward, 'alias', null);
                if (isset($alias, $response->cards[0]->ean)) {
                    $browser = clone $this;
                    $browser->http->GetURL("https://sclub.ru/api/offers/{$alias}?ean={$response->cards[0]->ean}");
                    $offerDetails = $browser->http->JsonLog(null, true, true);
                    $endsAt = ArrayVal($offerDetails, 'endsAt');
                    if ($endsAt = strtotime($endsAt))
                        $subAccount['ExpirationDate'] = $endsAt;
                }// if (isset($alias, $response->cards[0]->ean))

                $this->AddSubAccount($subAccount);
            }// if (strtolower($status) == 'active')
        }// foreach ($rewards as $reward)*/
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($this->scriptURL);
        $key = $this->http->FindPreg("/recaptchaKey:\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://www.sclub.ru/',
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
