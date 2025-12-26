<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerKukuruza extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
    }

    public function LoadLoginForm()
    {
        if (strlen($this->AccountFields["Login"]) != 13) {
            throw new CheckException('The bar code of your card should consist of 13 digits', ACCOUNT_INVALID_PASSWORD); /*checked*/
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://oplata.kykyryza.ru/personal/pub/Entrance");
        // get cookies
        $this->http->GetURL("https://oplata.kykyryza.ru/api/v0001/ping/session?rid=a36dcce30a2b98");
        $this->http->JsonLog();

        $data = [
            "principal" => str_replace(" ", "", $this->AccountFields['Login']),
            "secret"    => "",
            "type"      => "AUTO",
        ];
        // send login and password
        $data['secret'] = $this->AccountFields["Pass"];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json;charset=utf-8",
            "channel"      => "web",
            "X-Request-Id" => "e8eb83d58b4938",
            "X-XSRF-TOKEN" => $this->http->getCookieByName("XSRF-TOKEN", "mybank.oplata.kykyryza.ru"),
        ];
        $this->State['headers'] = $headers;
        $this->http->PostURL("https://oplata.kykyryza.ru/api/v0001/authentication/auth-by-secret?rid=2ab922b2f1f70", json_encode($data), $headers);
        $this->http->JsonLog();

//        $this->http->SetInputValue("ean", $this->AccountFields["Login"]);
//        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        // Maintenance
        if ($message = $this->http->FindPreg("/Прямо сейчас мы\&nbsp;работаем над тем, чтобы сделать наш сервис лучше, быстрее и&nbsp;удобнее\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindPreg("/\"status\":\"OTP_REQUIRED\"/")) {
            return false;
        }
        $this->Question = "Введите код из SMS, отправленный на привязанный к карте номер";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $data = [
            'principal'     => str_replace(" ", "", $this->AccountFields['Login']),
            'otp'           => $this->Answers[$this->Question],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://oplata.kykyryza.ru/api/v0001/authentication/confirm?rid=24e8236056639', json_encode($data), $this->State['headers'], 40);
        $this->sendNotification("answer was entered // RR");
        $this->http->RetryCount = 2;
        unset($this->Answers[$this->Question]);
        $response = $this->http->JsonLog();
        // check errors
        if ($this->http->FindPreg("/\"status\":\"AUTH_WRONG\"/")) {
            $this->AskQuestion($this->Question, "Проверьте правильность введенных данных и попробуйте войти повторно.");

            return false;
        }

        return true;
    }

    public function Login()
    {
        // form submission
//        if (!$this->http->PostForm())
//            return $this->checkErrors();

        if ($this->parseQuestion()) {
            return false;
        }

        // login successful
//        if ($this->http->FindSingleNode("//a[@class = 'b-user-info__exit']/@class"))
        if ($this->http->FindPreg("/(\{\"status\":\"OK\"\})/")) {
            return true;
        }
        // Invalid credentials
        if ($this->http->FindPreg("/\"status\":\"AUTH_WRONG\"/")) {
            throw new CheckException("Введен неверный пароль или номер штрих-кода карты. Проверьте правильность написания и повторите попытку. Обратите внимание, не нажата ли клавиша Caps Lock, проверьте язык ввода.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/\"status\":\"CARD_IS_ARCHIVED\"/")) {
            throw new CheckException("Вход в интернет-банк невозможен, так как договор по данной карте расторгнут. Пожалуйста, обратитесь в Информационный центр программы «Кукуруза», если у вас есть какие-либо вопросы.", ACCOUNT_INVALID_PASSWORD);
        }
        // account lockout
        if ($this->http->FindPreg("/\"status\":\"DP_LOCKED_PERMANENT\"/")) {
            throw new CheckException("Вход в интернет-банк невозможен, так как ваша карта заблокирована. Возможно, она была заменена на новую карту. Пожалуйста, обратитесь в Информационный центр программы «Кукуруза», если у вас есть какие-либо вопросы.", ACCOUNT_LOCKOUT);
        }

//        // check for invalid password
//        if ($message = $this->http->FindSingleNode("//div[@id='errorList']"))
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
//        // hard code
//        $this->http->Log("Current URL: ".$this->http->currentUrl());
        // Вход в Платежный кабинет невозможен, так как ваша карта заблокирована
//        if ($this->http->FindPreg("/(\{\"status\":\"DP_LOCKED_PERMANENT\"\})/"))
//            throw new CheckException("Вход в Платежный кабинет невозможен, так как ваша карта заблокирована. Возможно, она была заменена на новую карту. Пожалуйста, обратитесь в Информационный центр программы «Кукуруза» по телефону 8 800 700-77-10, если у вас есть какие-либо вопросы.", ACCOUNT_LOCKOUT);

        return $this->checkErrors();
    }

    public function Parse()
    {
//        // set balance
//        $this->SetBalance($this->http->FindSingleNode("(//a[contains(@href, 'bonus-statement')]/span[@class = 'b-user-info__balance'])[2]"));
//        ## Name
//        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'b-user-info__name']")));
//        ## Account Number
//        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//th[contains(text(), '№')]/span"));
//        ## Cash Balance
//        $cashBalance = str_replace('руб', ' руб', $this->http->FindSingleNode("//a[@href = '../personal/history']/span/parent::a"));
//        $this->SetProperty("CashBalance", Html::cleanXMLValue($cashBalance));

        $this->http->setDefaultHeader("X-Request-Id", "a94701ac7b3aa8");
        $this->http->setDefaultHeader("X-XSRF-TOKEN", $this->http->getCookieByName("XSRF-TOKEN", "mybank.oplata.kykyryza.ru"));
        $this->http->GetURL("https://oplata.kykyryza.ru/api/v0001/consumer?rid=a94701ac7b3aa8");
        $response = $this->http->JsonLog();
        // Name
        if (isset($response->data->fio)) {
            $this->SetProperty("Name", beautifulName($response->data->fio));
        }

        $this->http->setDefaultHeader("X-Request-Id", "9dd480a262cfd8");
        $this->http->setDefaultHeader("X-XSRF-TOKEN", $this->http->getCookieByName("XSRF-TOKEN", "mybank.oplata.kykyryza.ru"));
        $this->http->GetURL("https://oplata.kykyryza.ru/api/v0001/cards?rid=9dd480a262cfd8");
        $cardInfo = $this->http->JsonLog();

        if (isset($cardInfo->data)) {
            foreach ($cardInfo->data as $card) {
                if ($card->isBlocked != false || $card->isExpired != false) {
                    $this->http->Log("Skip blocked/expired card *{$card->panTail}");
                    $this->SetBalanceNA();

                    continue;
                }

                $subAccount = [
                    // Code
                    "Code"          => 'kukuruza' . $card->panTail,
                    // DisplayName
                    'DisplayName'   => $card->name . " *" . $card->panTail,
                    // Account Number
                    "AccountNumber" => $card->panTail,
                ];

                foreach ($card->equities as $equity) {
                    switch ($equity->type) {
                        case 'OWN_AMOUNT_REMAINING':
                            // Cash Balance
                            $subAccount["CashBalance"] = $equity->amount . ' руб';

                            break;

                        case 'BNS':
                            // Accrued points
                            $subAccount["AccruedPoints"] = floor($equity->amount);

                            break;

                        case 'BNS_DELAY':
                            // Pending points
                            $subAccount["PendingPoints"] = floor($equity->amount);

                            break;

                        case 'BNS_AVAILABLE':
                            // Balance
                            $subAccount["Balance"] = floor($equity->amount);

                            break;
                    }
                }// foreach ($card->equities as $equity)

                $subAccounts[] = $subAccount;
            }
        }// foreach ($cardInfo->data as $card)

        // SubAccounts
        if (!empty($subAccounts)) {
            $this->SetBalanceNA();
            //# Set Sub Accounts if them > 1
            if (count($subAccounts) > 1) {
                $this->SetProperty("CombineSubAccounts", false);
            }
            $this->http->Log("Total subAccounts: " . count($subAccounts));
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if (!empty($subAccounts))
    }
}
