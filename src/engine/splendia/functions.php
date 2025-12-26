<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSplendia extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.splendia.com/");
        $this->http->GetURL("https://www.splendia.com/en/club/profile/");

        if (!$this->http->ParseForm()) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue('email', $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Content-Type" => "application/json;charset=utf-8",
            "Accept"       => "application/json, text/plain, */*",
            "X-XSRF-TOKEN" => urldecode($this->http->getCookieByName("XSRF-TOKEN")),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.splendia.com/auth/signin.json', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        //# We are very sorry, our website is currently undergoing maintenance.
        if ($message = $this->http->FindPreg("/(We are very sorry, our website is currently undergoing maintenance\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The service is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'The service is temporarily unavailable.')
                or contains(text(), 'Welcome to the official website of Splendia, specialist in carefully selected luxury hotels. The English site is currently under construction. In the meantime, we invite you to discover the site in the language of Molière.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
//        if (!$this->http->PostForm())
//            return $this->checkErrors();

        if (isset($response->id)) {
            // Name
            if (isset($response->firstName, $response->lastName)) {
                $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
            }
            // Preferred currency
            if (isset($response->currency)) {
                $currencyNumber = $response->currency;
                $this->http->GetURL("https://www.splendia.com/en/club/profile");
                $currencyOption = $this->http->XPath->query("//select[@id = 'currency']/option");
                $this->logger->notice("currency option: {$currencyOption->length}");
                $currencies = [];

                if ($response->currency == 'Euro') {
                    $response->currency = 'Euros';
                }
                $this->logger->notice(">>> Preferred currency: " . $currencyNumber);

                for ($n = 0; $n < $currencyOption->length; $n++) {
                    $name = CleanXMLValue($currencyOption->item($n)->nodeValue);
                    $code = CleanXMLValue($currencyOption->item($n)->getAttribute("value"));

                    if ($name != "" && $code != "") {
                        $currencies[$code] = $name;
                    }
                }// for ($n = 0; $n < $currencyOption->length; $n++)

                if (isset($currencies[$currencyNumber])) {
                    $this->http->GetURL("https://www.splendia.com/en/currencies.json");
                    $response = $this->http->JsonLog();

                    if (isset($response->other)) {
                        foreach ($response->other as $currencyData) {
                            if ($currencyData->name == $currencies[$currencyNumber]) {
                                $currency = $currencyData->code;
                                $this->logger->notice(">>> Preferred currency: " . $currency);

                                break;
                            }
                        }
                    }
                }// if (isset($currencies[$response->currency]))

                if (isset($currency)) {
                    // Switching to preferred currency
                    $this->logger->notice(">>> Switching to preferred currency");
                    $this->http->GetURL("https://www.splendia.com/en/collections?changed_currency=" . $currency);
                }// if (isset($currency))
            }// if (isset($response->currency))

            return true;
        }
        // Please enter a valid Email
        if (isset($response->message) && strstr($response->message, 'Invalid request parameters')
            && $this->AccountFields['Login'] == 'cambrogi@dalkia.com.br') {
            throw new CheckException("Please enter a valid Email", ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect credentials
        if (isset($response->message) && strstr($response->message, 'Incorrect credentials')) {
            throw new CheckException("Incorrect credentials", ACCOUNT_INVALID_PASSWORD);
        }

        // AccountID: 2025524
        if ($this->http->Response['body'] == '{"message":"","error":{}}') {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.splendia.com/en/club/my-splendia");
        // Balance - TOTAL
        $this->SetBalance($this->http->FindSingleNode("//p[@class = 'keyfigure keyfigure--huge']", null, true, "/([\d\.\,]+)/ims"));
        // Status
        $this->SetProperty('Status', beautifulName($this->http->FindSingleNode('//img[contains(@src, "member_card_")]/@src', null, true, '/member_card_([^.]+)\.[^\/]+$/ims')));

        if (empty($this->Properties['Status'])) {
            $this->SetProperty('Status', beautifulName($this->http->FindSingleNode('//img[contains(@src, "card-")]/@src', null, true, '/card-([^.]+)\.[^\/]+$/ims')));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.splendia.com/en/club/my-splendia';

        return $arg;
    }
}
