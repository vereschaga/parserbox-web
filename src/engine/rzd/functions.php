<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRzd extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://rzd-bonus.ru/cabinet/';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
        $this->setProxyGoProxies(null, 'ru');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://rzd-bonus.ru/cabinet/");

        $hash = $this->getHash();

        if ($hash) {
            $this->http->setCookie("__jhash_", (string) $this->generateJhash($hash));
            $this->http->setCookie("__jua_", $this->fixedEncodeURIComponent());
            sleep(2);
            $headers = [
                "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
            ];
            $this->http->GetURL("https://rzd-bonus.ru/cabinet/", $headers);
        }

        if (!$this->http->ParseForm("form_auth")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('USER_LOGIN', $this->AccountFields['Login']);
        $this->http->SetInputValue('USER_PASSWORD', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Maintenance
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'сайт по техническим причинам временно недоступен')]
                | //div[@class='page_404']/p[contains(normalize-space(), 'На сайте ведутся технические работы')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/SOAP-ERROR: Parsing WSDL: Couldn't load from/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // Логин или пароль указаны неверно
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'Логин или пароль указаны неверно')
                or contains(text(), 'Пользователь не может быть авторизован.')
            ]")
        ) {
            $message = rtrim($message, ".");

            throw new CheckException($message . '. Обратите внимание, что ОАО "Российские Железные Дороги" имеет 2 основных сайта: <a href=\'http://rzd.ru/\' target=\'_blank\'>http://rzd.ru/</a> (справочная информация, бронирование билетов, грузоперевозки и т.д.) и <a href=\'https://rzd-bonus.ru\' target=\'_blank\'>https://rzd-bonus.ru</a> (бонусная информация по программе РЖД Бонус).AwardWallet агрегирует бонусную информацию с сайта <a href=\'https://rzd-bonus.ru\' target=\'_blank\'>https://rzd-bonus.ru</a>, поэтому, пожалуйста, убедитесь, что ваши данные валидны именно для этого сайта либо зарегистрируйтесь по указанной ссылке в бонусной программе РЖД .', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Восстановление пароля')]")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "message_error")]/p')) {
            $this->logger->error("[Error]: {$message}");

            // Необходимо сменить пароль! Пожалуйста, воспользуйтесь функционалом восстановления пароля для установки нового пароля.
            if (
                strstr($message, 'Необходимо сменить пароль!')
                || $message == 'Авторизация временно недоступна.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[contains(@class, 'user-info_name')]/strong"));
        // Balance - Общая сумма баллов
        $this->SetBalance($this->http->FindSingleNode("//p[contains(@class, 'user-info_points') and not(contains(@class, 'disabled')) and contains(text(), 'Премиальных')]/strong"));
        // ... статус
        $this->SetProperty("Level", beautifulName($this->http->FindSingleNode("//div[@class = 'user_cart']/p/strong", null, true, "/(.+)\s+статус/")));
        // This year Qualification Points
        $this->SetProperty("ThisYearQualificationPoints", $this->http->FindSingleNode('//div[contains(text(), "Квалификационные баллы за")]/following-sibling::div/div[contains(@class, "number-inner")]'));

        $this->http->GetURL("https://rzd-bonus.ru/cabinet/profile/");
        // Номер счета
        $this->SetProperty("Number", $this->http->FindSingleNode("//label[contains(text(), 'Номер счета')]/following-sibling::input/@value"));
        // Name
        $this->SetProperty("Name", Html::cleanXMLValue(
            $this->http->FindSingleNode("//label[contains(text(), 'Фамилия')]/following-sibling::input/@value") . ' ' .
            $this->http->FindSingleNode("//label[contains(text(), 'Имя')]/following-sibling::input/@value") . ' ' .
            $this->http->FindSingleNode("//label[contains(text(), 'Отчество')]/following-sibling::input/@value")
        ));
        // Уровень
        $this->SetProperty("Level", $this->http->FindSingleNode("//label[contains(text(), 'Уровень')]/following-sibling::input/@value"));

        // Expiration Date  // refs #6725
        $this->http->GetURL("https://rzd-bonus.ru/cabinet/my-trips/");
        $clients = $this->http->FindPreg("/var clients = \[([^\]]+)\]/");
        $clients = "[" . preg_replace('/,$/', '', $clients) . "]";
//        $this->logger->debug(var_export($clients, true), ['pre' => true]);
        $transactions = $this->http->JsonLog($clients, 0, true);

        if (is_array($transactions)) {
            foreach ($transactions as $transaction) {
                $value = ArrayVal($transaction, 'Дата');
                $operation = ArrayVal($transaction, 'Операция');

                if (stristr($operation, 'Номер поезда')) {
                    $dates[] = [
                        "ExpirationDate" => strtotime($value),
                        "LastActivity"   => $value,
                        "Operation"      => $operation,
                    ];
                }
            }
        }// foreach ($transactions as $transaction)

        if (isset($dates[0]["ExpirationDate"], $dates[0]["LastActivity"]) && $dates[0]["ExpirationDate"] != false) {
            rsort($dates);
            $this->logger->debug("Filtered transactions:");
            $this->logger->debug(var_export($dates, true), ['pre' => true]);

            if ($dates[0]["ExpirationDate"] != false) {
                $this->SetProperty("LastActivity", date("d.m.Y", $dates[0]["ExpirationDate"]));
                $this->SetExpirationDate(strtotime("+ 24 months", $dates[0]["ExpirationDate"]));
            }// if ($dates[0]["ExpirationDate"] != false)
        }// if (isset($dates[0]["ExpirationDate"], $dates[0]["LastActivity"]) && $dates[0]["ExpirationDate"] != false)
    }

//    works only 24 min
//    function IsLoggedIn()
//    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
//        $this->http->RetryCount = 2;
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//
//        return false;
//    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }

        return false;
    }

    private function getHash()
    {
        $hash = $this->http->FindPreg("/get_jhash\((\d+)/");

        if (!$hash && $this->http->FindPreg("/var jhash = get_jhash\(code\)\;/")) {
            $hash = $this->http->getCookieByName("__js_p_", "rzd-bonus.ru", "/", true);
        }

        $this->logger->debug("get_jhash: {$hash}");

        return $hash;
    }

    private function generateJhash($hash)
    {
        $script = /** @lang JavaScript */
            "    
            function get_jhash(b)
            {
                var x = 123456789;
                var i = 0;
                var k = 0;
                
                for(i=0; i < 1677696; i++)
                    {
                        x = (x + b ^ x + x % 3 + x % 17 + b ^ i) % (16776960);
                        if(x % 117 == 0)
                        {
                            k = (k + 1) % 1111;
                        }
                    }
                
                return k;
            }
            sendResponseToPhp(get_jhash({$hash}));
        ";
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);

        return $jsExecutor->executeString($script);
    }

    private function fixedEncodeURIComponent()
    {
        $script = /** @lang JavaScript */
            "
            function fixedEncodeURIComponent (str) {
              return encodeURIComponent(str).replace(/[!'()*]/g, function(c) {
                return '%' + c.charCodeAt(0).toString(16);
              });
            }
            sendResponseToPhp(fixedEncodeURIComponent('{$this->http->userAgent}'));
        ";
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);

        return $jsExecutor->executeString($script);
    }
}
