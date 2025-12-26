<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerRostelecom extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->setHttp2(true);
        */

        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
//        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_EU));
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->postUrl('https://lk.rt.ru/client-api/getProfile', null, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindPreg("/\"login\":\"[^\"]+\"/")) {
            return true;
        }

        return false;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && isset($properties['CashBalance'])) {
            return $properties['CashBalance'] . " rub.";
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        /*
        $this->http->GetURL("https://lk.rt.ru/");
        */
        /*
        $headers = [
            "Accept"          => "*
        /*",
            "Accept-Encoding" => "gzip, deflate, br",
            "x-cfids"         => "-",
        ];
        $this->http->GetURL("https://af.rt.ru/api/fl/id119", $headers);
        $cfids = $this->http->getCookieByName("cfids119");

        if (!$cfids) {
            return false;
        }
        $this->http->RetryCount = 2;
        $this->http->setCookie("cfids119", $cfids, ".rt.ru");
        $this->checkConnectionErrors();
        */
        $this->selenium("https://b2c.passport.rt.ru/auth/realms/b2c/protocol/openid-connect/auth?response_type=code&scope=openid&client_id=lk_b2c&redirect_uri=https%3A%2F%2Flk.rt.ru%2Fsso-auth%2F%3Fredirect%3Dhttps%253A%252F%252Flk.rt.ru%252F&state=%7B%22uuid%22%3A%224FFD0547-33CA-45D7-B795-E0D6C65DE0C5%22%7D");
        /*
        $this->http->GetURL("https://b2c.passport.rt.ru/auth/realms/b2c/protocol/openid-connect/auth?response_type=code&scope=openid&client_id=lk_b2c&redirect_uri=https%3A%2F%2Flk.rt.ru%2Fsso-auth%2F%3Fredirect%3Dhttps%253A%252F%252Flk.rt.ru%252F&state=%7B%22uuid%22%3A%224FFD0547-33CA-45D7-B795-E0D6C65DE0C5%22%7D");
        */
        $this->checkConnectionErrors();

        if (!$this->http->ParseForm(null, '//form[@class = "login-form"]')) {
            return $this->checkErrors();
        }

        $tab_type = "EMAIL";

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $tab_type = "LOGIN";
        }

        $this->http->SetInputValue("tab_type", $tab_type);
        $this->http->SetInputValue("username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("rememberMe", "on");

        return true;
    }

    public function checkConnectionErrors()
    {
        $this->logger->notice(__METHOD__);
        // retries
        if (
            $this->http->FindPreg('/Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to /', false, $this->http->Error)
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            || $this->http->FindSingleNode('//h2[contains(text(), "Ваш запрос был отклонен из соображений безопасности.")]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3);
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function postUrl(
        $url, $params = [
            'client_uuid'  => '',
            'current_page' => '',
        ],
        $headers = [], $timeout = 120
    ) {
        $header = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json;charset=UTF-8',
        ];
        $params = $params ?? ['client_uuid'  => '', 'current_page' => ''];

        $this->http->PostURL($url, json_encode($params), array_merge($header, $headers), $timeout);

        return $this->http->JsonLog(null, 3, true);
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($redirect = $this->http->FindPreg("/lk.rt.ru\?redirect=(.+)/", false, $this->http->currentUrl())) {
            $this->http->GetURL(urldecode($redirect));
        }

        if (
            $this->http->currentUrl() == 'https://lk.rt.ru/'
            || strstr($this->http->currentUrl(), 'https://lk.rt.ru/#account_attach_auto')
            || strstr($this->http->currentUrl(), 'https://lk.rt.ru/#auto-attach')
            || strstr($this->http->currentUrl(), 'https://start.rt.ru/')
            || strstr($this->http->currentUrl(), 'https://lk.rt.ru')
        ) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@class = 'server-alert-container alert-error']") ?? $this->http->FindPreg("/wrongUsernameOrPasswordMessage:\s*\"([^\"]+)/")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Неверный логин или пароль')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->info('Name', ['Header' => 3]);
        $response = $this->postUrl('https://lk.rt.ru/client-api/getProfile');
        // Name
        $this->SetProperty("Name", beautifulName(Html::cleanXMLValue(sprintf('%s %s %s',
            $response['lastName'] ?? null,
            $response['name'] ?? null,
            $response['middleName'] ?? null
        ))));

        $this->logger->info('Balance', ['Header' => 3]);
        $response = $this->postUrl("https://lk.rt.ru/client-api/getFplStatus");
        $balance = ArrayVal($response, 'balance');
        // Balance
        $this->SetBalance($balance);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Данные по бонусной программе временно недоступны
            $errorMessage = ArrayVal($response, 'errorMessage');

            if (in_array($errorMessage, [
                "Произошла ошибка. Пожалуйста, повторите попытку позже. Сообщить об ошибке Вы можете в <a href='#feedback'>службу поддержки.</a>",
                "Программа лояльности «Бонус» временно недоступна. Скоро мы вернемся в обновленном дизайне. Вас ждет новая система начисления бонусов и каталог подарков.",
                "Программа «Бонус» временно недоступна. Скоро мы вернемся с обновленной системой начисления бонусов, и вы сможете обменивать их на подарки от партнеров и сервисы Ростелекома по новому курсу -  1 бонус = 1 рубль.",
                "Пожалуйста, напишите нам в чат. Мы с удовольствием поможем Вам",
                "В целях улучшения качества работы бонусной программы проводятся технические работы. Приносим извинения за доставленные неудобства",
            ])
            ) {
                $this->SetWarning("Данные по бонусной программе временно недоступны");
            }
            // Вы пока не подключены к бонусной программе
            elseif (ArrayVal($response, 'status') === 'MISSING' || $errorMessage == "Пожалуйста, повторите попытку позже или напишите нам в чат. Мы с удовольствием поможем Вам") {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Status Balance
        $this->SetProperty('StatusBalance', ArrayVal($response, 'statusBalance'));
        $levelInfo = ArrayVal($response, 'levelInfo');
        $level = ArrayVal($levelInfo, 'level');

        switch ($level) {
            case 'BASIC':
                $this->SetProperty('Status', 'Базовый');

                break;

            case 'STANDARD':
                $this->SetProperty('Status', 'Стандартный');

                break;

            case 'PRIVILEGE':
                $this->SetProperty('Status', 'Привилегия');

                break;
//            case '':
//                $this->SetProperty('Status', 'VIP');
//                break;
            default:
                if ($this->ErrorCode == ACCOUNT_CHECKED && !empty($level)) {
                    $this->sendNotification("rostelecom. New status was found: {$level}");
                }

                break;
        }// switch ($level)
        // Status expiration
        $this->SetProperty('StatusExpiration', ArrayVal($levelInfo, 'expireDate', null));

        // Sub Accounts begin
        $response = $this->postUrl("https://lk.rt.ru/client-api/getAccounts");
        $this->SetProperty("CombineSubAccounts", false);
        $accounts = ArrayVal($response, 'accounts', []);
        $this->logger->debug("Total " . count($accounts) . " accounts were found");

        foreach ($accounts as $account) {
            $services = ArrayVal($account, 'services', []);
            $accountId = ArrayVal($account, 'accountId');
            $number = ArrayVal($account, 'number');

            $this->logger->info('Account info #' . $number, ['Header' => 3]);

            $serviceIds = $types = [];

            foreach ($services as $i => $service) {
                $serviceIds[$i] = $service['serviceId'];
                $types[$i] = $service['type'];
            }
            // Cash Balance
//            $responseCashAccountBalance = $this->postUrl("https://lk.rt.ru/client-api/getAccountBalance", ['accountId' => $accountId]);
            $responseCashAccountBalance = $this->postUrl("https://lk.rt.ru/client-api/getAccountBalanceV2", ['accountId' => $accountId]);
            $cash = ArrayVal($responseCashAccountBalance, 'balance');
            $cash = preg_replace('/(\d{2})$/', ',\1', $cash);
            // Tariff Name
            $headers = [
                "Referer"          => "https://lk.rt.ru/",
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "X-Requested-With" => "XMLHttpRequest",
                "CSRF-TOKEN"       => $this->http->getCookieByName("CSRF-TOKEN"),
            ];
            $responseTariffName = $this->postUrl("https://lk.rt.ru/client-api/getServiceTariffName", ['servicesId' => $serviceIds], $headers);
            $tariffs = ArrayVal($responseTariffName, 'tariffNames', []);

            foreach ($services as $i => $service) {
                $servicesId = $service['serviceId'];
                $servicesNumber = $service['number'];
                $type = $types[$i];

                if ($type === 2) {
                    $displayName = 'Home Phone';

                    if (strpos($servicesNumber, '7') == 0) {
                        $servicesNumber = "+" . $servicesNumber;
                    } else {
                        $servicesNumber = "+7" . $servicesNumber;
                    }
                } elseif ($type === 1) {
                    $displayName = 'Home Internet';
                } elseif ($type === 8) {
                    $displayName = 'IP TV';
                } elseif ($type === 6) {
                    // TODO: аренда оборудования похоже, нужно ли учитывать?
                    continue;
                } elseif ($type === 15) {
                    $displayName = 'Service "АЛЛЁ"';
                } elseif ($type === 17) {
                    $displayName = 'Smart home';
                } elseif ($type === 18) {
                    $displayName = 'Rostelecom Lyceum';
                } elseif ($type === 19) {
                    $displayName = 'Wink – TV-Online';
                } elseif ($type === 16) {
                    $displayName = 'Mobile Phone';

                    if (strpos($servicesNumber, '7') == 0) {
                        $servicesNumber = "+" . $servicesNumber;
                    } else {
                        $servicesNumber = "+7" . $servicesNumber;
                    }
                } else {
                    $this->sendNotification("rostelecom - new service type: {$type}");
                }

                $tariff = $tariffs[$servicesId] ?? 'данные недоступны';

                if (strstr($tariff, '((')) {
                    $tariff = null;
                }

                if (isset($displayName)) {
                    $this->AddSubAccount([
                        'Code'          => sprintf('rostelecomId%s', $servicesId),
                        'Balance'       => null,
                        'DisplayName'   => $displayName . " (#{$number})",
                        'AccountNumber' => $number,
                        'Number'        => $servicesNumber,
                        'CashBalance'   => $cash,
                        'Tariff'        => $tariff,
                    ]);
                }
            }// foreach ($services as $i => $service)
        }
    }

    public function selenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL($url);

            // login
            if ($standardAuth = $selenium->waitForElement(WebDriverBy::id('standard_auth_btn'), 10)) {
                $standardAuth->click();
            }

            $selenium->waitForElement(WebDriverBy::id('username'), 5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // TODO
        }

        return $result;
    }
}
