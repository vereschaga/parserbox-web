<?php

// Feature #4433

class TAccountCheckerLetoile extends TAccountChecker
{
    use AwardWallet\Engine\ProxyList;

    public $cardTypes = [
        ""      => "Select your card type",
        "2"     => "Ruby card", // "рубиновая",
        "3"     => "Sapphire card", // "сапфировая",
        "4"     => "Amethyst card", // "аметистовая",
        "5"     => "Diamond card", // "бриллиантовая"
        "1_old" => "Green card", // "зеленая"
        "2_old" => "Red card", // "красная"
        "3_old" => "Blue card", // "синяя"
        "4_old" => "Silver card", // "серебряная"
        "5_old" => "Gold card", // "золотая"
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->cardTypes;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $cardTypesValues = [
            "2"     => "rub", // "рубиновая",
            "3"     => "sap", // "сапфировая",
            "4"     => "ame", // "аметистовая",
            "5"     => "bri", // "бриллиантовая"
            "1_old" => "green", // "зеленая"
            "2_old" => "red", // "красная"
            "3_old" => "blue", // "синяя"
            "4_old" => "silver", // "серебряная"
            "5_old" => "gold", // "золотая"
        ];

        if (!isset($cardTypesValues[$this->AccountFields['Login2']])) {
            $this->logger->error("CardType not found");

            return false;
        }

        $this->http->GetURL("https://www.letu.ru/help/payment/dcBalance.jsp");

        if ($this->http->currentUrl() == 'https://www.letu.ru/balance') {
            $data = [
                "cardType"     => $cardTypesValues[$this->AccountFields['Login2']],
                "cardNumber"   => $this->AccountFields['Login'],
                "pushSite"     => "storeMobileRU",
                "_dynSessConf" => "1741904436651548639",
            ];
            $headers = [
                "X-Requested-With" => "XMLHttpRequest",
                "Content-Type"     => "application/json; charset=utf-8",
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
            ];
            $this->http->PostURL("https://www.letu.ru/rest/model/atg/rest/DiscountCardActor/dcBalance?pushSite=storeMobileRU&locale=ru_RU", json_encode($data), $headers);

            return true;
        }

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, '/global/json/ndkBalanceJSON.jsp')]")) {
            return false;
        }
        $this->http->SetInputValue("cardNumber", $this->AccountFields['Login']);
        $this->http->SetInputValue("cartType", $cardTypesValues[$this->AccountFields['Login2']]);

        return true;
    }

    public function Login()
    {
        if ($this->http->currentUrl() == 'https://www.letu.ru/help/payment/dcBalance.jsp' && !$this->http->PostForm()) {
            return false;
        }
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('#"balance#ims')) {
            return true;
        }
        // Некорректный номер карты - пожалуйста, проверьте правильность ввода номера или выбор цвета.
        $message = $response->message[0] ?? null;

        if (strstr($message, 'Некорректный номер карты')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Баланс карты
        $this->SetBalance($this->http->FindPreg('#"balance":([^\"\,]+)#ims'));
        // Скидка по карте
        $this->SetProperty("Discount", $this->http->FindPreg('#"sale":\s*\"([^\"\,]+)#ims'));
    }
}
