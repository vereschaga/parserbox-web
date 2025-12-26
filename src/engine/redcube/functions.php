<?php

class TAccountCheckerRedcube extends TAccountChecker
{
    public function LoadLoginForm()
    {
//        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false || !is_numeric($this->AccountFields['Login']))
//            throw new CheckException('Invalid credentials', ACCOUNT_INVALID_PASSWORD);
        if (!strstr($this->AccountFields['Login'], '+7')) {
            throw new CheckException('Invalid credentials', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.redcube.ru');

        if (!$this->http->ParseForm("CheckPhone")) {
            return false;
        }
        $this->http->SetInputValue('phone', $this->AccountFields['Login']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        $this->http->JsonLog();

        if (!$this->http->FindPreg('#\{"result":true#i')) {
            $this->logger->error('Unexpected site page, there should be input field for birth date');

            return false;
        }// if (!$this->http->FindPreg('#\{"result":truei'))

        /*if (!$this->http->ParseForm(null, 1, false, '//form[@class = "i-cube_enter-form"]')) {
            $this->logger->error('Failed to parse second login form');
            return false;
        }// if (!$this->http->ParseForm(null, 1, false, '//form[@class = "i-cube_enter-form"]'))

        if (preg_match('#(\d{1,2})\.(\d{1,2})\.(\d{4})#i', $this->AccountFields['Pass'], $m)) {
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }// if (preg_match('#(\d{1,2})\.(\d{1,2})\.(\d{4})#i', $this->AccountFields['Pass'], $m))
        else
            throw new CheckException('Invalid birth date, it should be in format DD.MM.YYYY', ACCOUNT_INVALID_PASSWORD);*/

        $this->http->FormURL = 'https://www.redcube.ru/users/login';
        $this->http->SetInputValue('phone', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        if (!$this->http->PostForm()) {
            $this->logger->error('Failed to post second login form');

            return false;
        }
        $response = $this->http->JsonLog();

        if (!$this->http->FindPreg("/\{\"result\":true,\"errors\":false,\"data\":\{\"needConfirm\":false\}\}/")) {
            $this->logger->error('Unexpected site page, there should be input field for birth date');
            // Неверные данные авторизации!
            if (isset($response->errors->main) && $response->errors->main == 'Неверные данные авторизации!') {
                throw new CheckException("Неверные данные авторизации!", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if (!$this->http->FindPreg('#Дата рождения:#i'))

        if ($error = $this->http->FindSingleNode('//*[@class="error-message"]')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.redcube.ru/users/edit");

        if ($this->http->FindSingleNode("//h2[contains(text(), 'Пользователь №')]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        //		$properties = [
        //			'CardType' => 'Тип карты:',
        //			'Balance' => 'Остаток бонусов на Вашей карте:',
        //			'TotalSpent' => 'Сумма всех покупок по карте:',
        //			'LastTransaction' => 'Дата последней операции:',
        //			'CardStatus' => 'Статус карты:'
        //		];

        $this->SetProperty('Name', beautifulName(CleanXMLValue(
            $this->http->FindSingleNode('//input[@name = "data[User][name]"]/@value')
            . ' ' . $this->http->FindSingleNode('//input[@name = "data[User][father]"]/@value')
            . ' ' . $this->http->FindSingleNode('//input[@name = "data[User][family]"]/@value')
        )));
        // Карта №
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("//h2[contains(text(), 'Пользователь №')]/text()[1]", null, true, "/№\s*([\d]+)/"));
        // Остаток на карте: ... бонусов
        if ($balance = $this->http->FindSingleNode("//h2[contains(text(), 'Пользователь №')]/i/span", null, true, self::BALANCE_REGEXP)) {
            $this->sendNotification('Check balance // MI');
            $this->SetBalance($balance);
        } elseif (!empty($this->Properties['CardNumber']) && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        //		foreach ($properties as $key => $label) {
        //			$value = $this->http->FindSingleNode('//td[contains(., "'.$label.'")]/following-sibling::td[1]');
        //			if ($key == 'Balance')
        //				$this->SetBalance($value);
        //			else
        //				$this->SetProperty($key, $value);
        //		}

        return true;
    }
}
