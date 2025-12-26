<?php

class TAccountCheckerMnogo extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        if (
            !isset($this->State['auth_token'])
            || !isset($this->State['id'])
        ) {
            return false;
        }

        if ($this->loginSuccessful($this->State['auth_token'], $this->State['id'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.mnogo.ru/enterl.html");
        //$this->http->SetBody(mb_convert_encoding($this->http->Response['body'], 'UTF-8', 'windows-1251'));

        $this->logger->debug($this->http->FindPreg('#<title>(.+?)</title>#'));
        $this->logger->debug($this->http->FindSingleNode("//head/title"));
        $this->logger->debug(mb_convert_encoding($this->http->FindSingleNode("//head/title"), 'CP1251'));

        if (!$this->http->FindPreg('#<button class="[^\"]+js-siginWrapMob_btn">Войти</button>#')) {
            return $this->checkErrors();
        }

        if (!preg_match('/^(\d{2})(\d{2})(\d{4})$/', $this->AccountFields['Login2'], $matches)) {
            throw new CheckException('Please enter your birthday as 8 numbers, no spaces "ddmmyyyy"', ACCOUNT_INVALID_PASSWORD);
        }

        $logintype = 'card_number';
        $loginlink = 'card';

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            $logintype = 'email';
            $loginlink = 'email';
        }

        $data = [
            $logintype      => $this->AccountFields['Login'],
            'birthday_date' => "{$matches[3]}-{$matches[2]}-{$matches[1]}",
        ];

        $headers = [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.mnogo.ru/userapi/login/{$loginlink}/birthday", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Внимание: технические работы
        if ($message = $this->http->FindSingleNode("
                //div[contains(text(), 'Внимание: технические работы')]
                | //*[self::h1 or self::title][contains(text(), 'Упс, Вы застали нас во время ремонта!')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error 404
        if (
            $this->http->FindSingleNode("
                //body[contains(text(), 'File not found')]
                | //p[contains(text(), 'Fuel\Core\RedisException [ Error ]: Unknown response:')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $auth_token = $response->result->auth_token ?? null;
        $id = $response->result->id ?? null;

        if ($this->http->Response['code'] == 502 && empty($this->http->Response['body'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($auth_token && $id && $this->loginSuccessful($auth_token, $id)) {
            $this->State['auth_token'] = $auth_token;
            $this->State['id'] = $id;

            return true;
        }

        $message = $response->errors->global[0] ?? $response->errors->birthday_date[0] ?? null;

        if ($message) {
            if ($message == 'format_incorrect') {
                throw new CheckException("Введите правильный день рождения", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'not_found') {
                throw new CheckException("Такого сочетания номера карты и дня рождения не существует", ACCOUNT_INVALID_PASSWORD);
            }

            // no any message on the site (AccountID: 1985492)
            if ($message == 'deleted_without_restore') {
                throw new CheckException("Ваш аккаунт был удален без возможности восстановления", ACCOUNT_INVALID_PASSWORD);
            }

            /*
             * Чтобы восстановить аккаунт согласитесь с необходимыми условиями и подтвердите восстановление из письма, которое мы отправим Вам на почту.
            */
            if ($message == 'deleted') {
                $this->throwAcceptTermsMessageException();
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", $response->result->users[0]->first_name);
        // Card Number
        $this->SetProperty("CardNumber", $response->result->users[0]->cards[0]->number);
        // Balance - бонусов
        $this->SetBalance($response->result->users[0]->points_balance_main);
    }

    private function loginSuccessful($auth_token, $id)
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Content-Type"  => "application/x-www-form-urlencoded",
            "Authorization" => "Bearer {$auth_token}",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.mnogo.ru/userapi/api/users/get", "{\"filter\":{\"user_id\":{\"eq\":\"{$id}\"}},\"schema\":[\"cards\",\"points_balance_main\",\"first_name\",\"contacts\",\"is_password_set\",\"external_accounts\"],\"limit\":1,\"offset\":0}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 5, false, 'number');

        if (
            (
                isset($response->result->users[0]->cards[0]->number)
                && $response->result->users[0]->cards[0]->number == $this->AccountFields['Login']
            )
            || strstr($this->http->Response['body'], $this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }
}
