<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPyaterochka extends TAccountChecker
{
    use ProxyList;

    private $headers = [
        "Content-Type" => "application/json;charset=utf-8",
        "Accept"       => "application/json, text/plain, */*",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""      => "Select your login type",
            "card"  => "Card #",
            "phone" => "Mobile #",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://my.5ka.ru/login");

        if ($this->http->Response['code'] != 200) {
            return false;
        }
        $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");
        $this->http->RetryCount = 0;
        $data = [
            "version" => "1",
            "app"     => [
                "version"    => "1",
                "platform"   => "web",
                "user_agent" => HttpBrowser::PROXY_USER_AGENT,
            ],
        ];
        $this->http->PostURL("https://my.5ka.ru/api/v1/startup/handshake", json_encode($data), $this->headers);
        $this->http->JsonLog();
        $token = $this->http->FindPreg("/\"token\"\s*:\s*\{\"value\"\s*:\s*\"([^\"]+)/");

        $login = str_replace('-', '', str_replace(' ', '', $this->AccountFields['Login']));

        switch ($this->AccountFields['Login2']) {
            case "card":
                $schema = "by-card";

                if (!is_numeric($login) && is_string($login)) {
                    throw new CheckException('Неверный номер карты', ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case "phone":
                $schema = "by-phone";
                $login = "+7" . substr($login, -10);

                break;

            default:
                if (strlen($login) > 14) {
                    $schema = "by-card";
                } else {
                    $schema = "by-phone";
                    $login = "+7" . substr($login, -10);
                }

                break;
        }

        if (isset($schema)) {
            $data = [
                "login"    => $login,
                "password" => $this->AccountFields['Pass'],
                "schema"   => $schema,
            ];
        } else {
            $this->logger->error("not defined type of Login2");

            return false;
        }

        if (!$token) {
            return false;
        }
        $this->http->setCookie("token", "Token{$token}");
        $this->http->setCookie("header_name", "X-Authorization");
        $xAuthorization = "Token{$token}";
        $this->State["X-Authorization"] = $xAuthorization;
        $this->http->setDefaultHeader("X-Authorization", $xAuthorization);

        $this->http->PostURL("https://my.5ka.ru/api/v1/auth/signin", json_encode($data), $this->headers);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, true);

        if (isset($response->token)) {
            $this->State['token'] = $response->token;

            $this->http->GetURL("https://my.5ka.ru/api/v1/users/me", ["Referer" => "https://my.5ka.ru/login"]);
            $response = $this->http->JsonLog();

            if (isset($response->favorite_segment)) {
                return true;
            }
            // 2 factor
            sleep(3);

            if ($this->parseQuestion()) {
                return false;
            }

            return true;
        }// if (isset($response->token)) {

        if (isset($response->code)) {
            // Invalid credentials
            if ($response->code == 1) {
                throw new CheckException('Неправильный пароль', ACCOUNT_INVALID_PASSWORD);
            }
            // В целях обеспечения безопасности Ваш личный кабинет был заблокирован на 1 час. Привязанные к личному кабинету карты будут активны в это время. Если у Вас возникли вопросы, то Вы можете связаться с нашими сотрудниками по бесплатному телефону горячей линии: 8-800-555-55-05
            if ($response->code == 4004 && isset($response->message)) {
                throw new CheckException($response->message, ACCOUNT_LOCKOUT);
            }
        }// if ($response->code))

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        if (isset($this->State['token'])) {
            $response = $this->http->JsonLog();

            if (isset($response->attempts) && $response->attempts == 0) {//????
                throw new CheckException("Превышено максимальное количество попыток ввода нового номера. Пользователь заблокирован. Попробуйте через час.", ACCOUNT_PROVIDER_ERROR);
            }
            //if (isset($response->code) && $response->code == 1) {
            $question = "Please enter Code which was sent to your phone number. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        //}
        }// if (isset($this->State['token']))
        else {
            $this->logger->error("Something went wrong");
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("X-Authorization", $this->State["X-Authorization"]);
        $data = [
            "code"  => $this->Answers[$this->Question],
            "token" => $this->State['token'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://my.5ka.ru/api/v1/auth/2fa", json_encode($data), array_merge($this->headers, ["Referer" => "https://my.5ka.ru/login"]));

        unset($this->Answers[$this->Question]);
        $response = $this->http->JsonLog();

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL("https://my.5ka.ru/api/v1/users/me?format=json", ["Referer" => "https://my.5ka.ru/login"]);
        $response = $this->http->JsonLog();

        if (isset($response->favorite_segment)) {
            // Name
            if (isset($response->person->first_name)) {
                $this->SetProperty("Name", beautifulName($response->person->first_name . ' ' . $response->person->last_name));
            }

            if (empty($mainCard = $this->http->FindPreg("#\"cards\":\{\"main\":\"(.+?)\"#"))) {
                $this->logger->error("can't find cardNumber");

                return false;
            }

            $this->http->GetURL("https://my.5ka.ru/api/v1/cards/" . $mainCard . "?format=json", ["Referer" => "https://my.5ka.ru/login"]);
            $response = $this->http->JsonLog();

            if (
                $this->http->Response['code'] == 500
                && $this->http->FindSingleNode('//p[contains(text(), "Internal Server Error")]')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // CardNumber
            if (isset($response->number)) {
                $this->SetProperty("CardNumber", $response->number);
            }
            // Balance
            if (isset($response->balance) && isset($response->balance->points)) {
                $this->SetBalance($response->balance->points);
            }

            // Discount
            $this->SetProperty("Discount", (string) (int) ($response->balance->points / 10) . ' p');

            // EarnedThisMonth
            $this->SetProperty("EarnedThisMonth", $response->balance->incoming_monthly_points);
        }

        return true;
    }
}
