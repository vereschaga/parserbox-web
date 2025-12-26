<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMalina extends TAccountChecker
{
    use ProxyList;

    private $months = [
        "ЯНВАРЬ"   => 1,
        "ФЕВРАЛЬ"  => 2,
        "МАРТ"     => 3,
        "АПРЕЛЬ"   => 4,
        "МАЙ"      => 5,
        "ИЮНЬ"     => 6,
        "ИЮЛЬ"     => 7,
        "АВГУСТ"   => 8,
        "СЕНТЯБРЬ" => 9,
        "ОКТЯБРЬ"  => 10,
        "НОЯБРЬ"   => 11,
        "ДЕКАБРЬ"  => 12,
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://malina.ru");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, '/pp/login/')]")) {
            return false;
        }
        $this->http->setDefaultHeader("X-CSRFToken", $this->http->getCookieByName("csrftoken"));
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->AccountFields['Login'] = str_replace(' ', '', $this->AccountFields['Login']);
        $this->http->SetInputValue("contact", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        unset($this->http->Form['comment']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("captcha_1", str_replace(' ', '', $captcha));

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();
        // retries
        if (isset($response->errors[0])) {
            if (strstr($response->errors[0], 'Вы ввели неверные символы')) {
                throw new CheckRetryNeededException(3, 7);
            }
            // Неверный логин или пароль
            if (strstr($response->errors[0], 'Неверный логин или пароль')) {
                throw new CheckException($response->errors[0], ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($this->http->FindPreg('#"status": "success"#')) {
            $this->http->GetURL("https://malina.ru/msk/");
        }

        if ($this->http->FindSingleNode("//div[contains(@onclick, 'logout')]/@onclick")) {
            return true;
        }
        // Уважаемый участник, Ваши анкетные данные поступят в программу в течение 8 недель
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Уважаемый участник, Ваши анкетные данные поступят в программу в течение 8 недель')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Баланс баллов
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'acc-points' and img[contains(@src, 'points.png')]]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//li[@class = 'acc-holder']/a)[1]")));

        $this->http->GetURL("https://malina.ru/msk/pp/");
        // Balance - Баланс баллов
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Баланс баллов')]/following-sibling::div[1]"));
        // Name
        $name = $this->http->FindSingleNode("//div[contains(text(), 'Владелец счета')]/following-sibling::div[1]");

        if ($name) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Номер счета
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[contains(text(), 'Номер счета')]/following-sibling::div[1]"));
        // Дата регистрации
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[contains(text(), 'Дата регистрации')]/following-sibling::div[1]"));
        // Доступно к обмену на товары и услуги
        $this->SetProperty("AvailableToSpend", $this->http->FindSingleNode("//div[contains(text(), 'Доступно к обмену на')]/following-sibling::div[1]"));

        // Expiration Date
        $this->http->GetURL("https://malina.ru/msk/pp/forecast/");
        $exp = $this->http->XPath->query("//table[contains(@class , 'table-simple')]//tr");
        $this->http->Log("Total {$exp->length} expiration dates were found");

        for ($i = 0; $i < $exp->length; $i++) {
            $month = mb_convert_case($this->http->FindSingleNode("td[1]", $exp->item($i), true, "/([а-я]+)\s*\d{4}$/uims"), MB_CASE_UPPER, "UTF-8");
            $year = $this->http->FindSingleNode("td[1]", $exp->item($i), true, "/(\d{4})$/ims");
            $pointsToExpire = $this->http->FindSingleNode("td[2]", $exp->item($i));
            $this->http->Log("Month: $month $year / $pointsToExpire");

            if (!empty($month) && $pointsToExpire != 0) {
                // Expiration Date
                $this->SetExpirationDate(mktime(0, 0, 0, $this->months[$month], 1, $year));
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $pointsToExpire);

                break;
            }// if ($pointsToExpire > 0)
        }// for ($i = 0; $i < $exp->length; $i++)
    }

    protected function parseCaptcha()
    {
        $http2 = clone $this->http;
        $http2->GetURL("https://malina.ru/captcha/refresh/?_=" . time() . date('B'));
        $response = $http2->JsonLog();

        if (!isset($response->image_url)) {
            return false;
        }
        $http2->NormalizeURL($response->image_url);
        $file = $http2->DownloadFile($response->image_url, "png");

        $this->http->SetInputValue("captcha_0", $response->key);

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }
}
