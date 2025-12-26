<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerMarriottgift extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] != 'USD') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
//        $this->http->setUserAgent(\HttpBrowser::PROXY_USER_AGENT);
    }

    public function LoadLoginForm()
    {
        if (!is_numeric($this->AccountFields['Login']) || strlen($this->AccountFields['Login']) < 10) {
            throw new CheckException("Invalid card number or PIN.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://gifts.marriott.com/check-balance/");

        if (!$this->http->ParseForm("cws_form_chkBal")) {
            return false;
        }

        $this->selenium();

        return true;
//        $this->sendSensorData();

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "params" => [
                "en",
                "99",
                "mqid",
                "mqpass",
                $this->AccountFields['Login'],
                $this->AccountFields['Pass'],
                "",
            ],
            "id"                   => "88",
            "g-recaptcha-response" => $captcha,
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://gifts.marriott.com/cws40_svc/marriottcons/consumer/dc_994.rpc", json_encode($data), $headers);
        /*
        $this->http->SetInputValue("cws_txt_gcNum", $this->AccountFields['Login']);
        $this->http->SetInputValue("cws_txt_gcPin", $this->AccountFields['Pass']);
        $this->http->SetInputValue("balance_check_currency_id", $this->AccountFields['Login2']);

        // captcha
        if ($captcha = $this->parseCaptcha()) {
            if ($captcha === false)
                return false;
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }
        */

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
//        if (!$this->http->PostForm())
//            return false;

        if (
            $this->http->FindPreg("/\{\"status\": 0/")
            || $this->http->FindSingleNode('//span[@id = "cws_output_gcBalance"]/span[@class="monetary"]')
        ) {
            return true;
        }

        $message =
            $response->result->message
            ?? $this->http->FindSingleNode('//div[@id = "cws_api_errors" and @style="display: block;"]')
            ?? null
        ;
        $this->logger->error("[Error]: {$message}");

        if ($message) {
            /*
            // retries
            if ($this->http->FindSingleNode("//span[contains(text(), '*Invalid code entered.')]")) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }
            */
            // Invalid. Please check the card number and re-enter
            if (
                $message == 'Cert not exist'
                || $message == 'Invalid. Please check the card number and re-enter'
            ) {
                throw new CheckException('Invalid. Please check the card number and re-enter', ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Cert on hold' || $message == 'Card is on hold') {
                throw new CheckException('Card is on hold', ACCOUNT_LOCKOUT);
            }
            // Certificate cancelled
            if (in_array($message, [
                'Certificate cancelled',
                'Invalid security code',
            ])) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Operation not permitted') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance
        $this->SetBalance(
            $response->result->I2
            ?? $this->http->FindSingleNode('//span[@id = "cws_output_gcBalance"]/span[@class="monetary"]')
            ?? null
        );
        // Currency
        $this->SetProperty('Currency',
            $response->result->I5
            ?? $this->http->FindSingleNode('//span[@id = "cws_output_gcCurrency"]')
            ?? null
        );
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9221031.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,405682,5829280,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8939,0.447395854223,824397914640,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1381,1381,0;0,-1,0,0,1372,1372,0;-1,2,-94,-102,0,-1,0,0,1381,1381,0;0,-1,0,0,1372,1372,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://gifts.marriott.com/check-balance/-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1648795829280,-999999,17638,0,0,2939,0,0,2,0,0,F33C1AED06E057F4768ED643E0C63480~-1~YAAQm2vcF+HVVtJ/AQAA0MPj4wegiWm/JoBInvPJm+N63bWAfvRSkvmz+nlHyXrdwWOoOv+peIEaU6MH7smQ6hfDRB7zreKiYi5Oj+4OnzzE0keCrHB/08lRJMOmeJWLuMyvUAAigCeNh379/Kr2S9WmOe7zTXlhlsT1Xn6SMSZzmEM1yJ3Jt4MzhKZaUdWuASgFFWw3qu5Gfz/KvOyvfMD2+Q7wNOih4XXYVi2Xz2bUWMIc4Y7SSKu1yR2ckfOE0Otz5BbV44T84GfZegTRxNRl1Wtw1RFZCnoYmtfQuTxco9iDywx8gpE7gwrBa/PiU7/1qYDCizk4uw2XvWDX5X5j6feekcsLQdix93dWcxpuTgmtehHRuWyD7w==~-1~-1~-1,35695,-1,-1,30261693,PiZtE,104751,121,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,157390572-1,2,-94,-118,90272-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9221031.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,405682,5829280,1536,871,1536,960,1536,456,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8939,0.271658659135,824397914640,0,loc:-1,2,-94,-131,Mozilla/5.0 (macOS;12.3.0;x86;64;) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Safari/537.36-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1381,1381,0;0,-1,0,0,1372,1372,0;-1,2,-94,-102,0,-1,0,0,1381,1381,0;0,-1,0,0,1372,1372,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://gifts.marriott.com/check-balance/-1,2,-94,-115,1,32,32,0,0,0,0,539,0,1648795829280,15,17638,0,0,2939,0,0,539,0,0,F33C1AED06E057F4768ED643E0C63480~-1~YAAQm2vcFzDWVtJ/AQAAMcXj4wfDKNBz1OEdAcEGudjy+vZkoZkaqhgOylrWBvAip7Kxyx1bVUXY/NMIyhsUIEemPtqqBmxSuIXb3WiDFgycCJLRfl29NNwesVl26oHcIodWwta3JwwyYd5tIm5RGvS3hq86uECPX2pFJ2yvMGMNZSm37k+K9gPQWNyVjpDDu5r8qFxy3ZXWwHAQAjxx1X3N2l6AMdl20H2rs5KvIvfuuXcfD8rCO8ch8p5kL8dUcnrFQAQEIJkoQICMfP8POF5LlmognBpQKp8vRT8CFBcY3hu37cRqviaB53Ux0SY+0VUDeIk9J3JGXek0uCDfKIXLeRPQOePnEplQeSULT52t9ZR0lzqKRIJSNODC8Fmym3bsxwzGLJO3~-1~-1~-1,36906,55,-979498305,30261693,PiZtE,36536,56,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,157390572-1,2,-94,-118,100399-1,2,-94,-129,,,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,,,,0-1,2,-94,-121,;3;5;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = "6LdUCxYTAAAAANMjMuPFMrC1GyTHmem5M1llJ8Id";

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => "https://gifts.marriott.com/check-balance/", //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;

        try {
            $selenium->UseSelenium();
//            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
//            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://gifts.marriott.com/check-balance/");

            if ($selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button--accept-cookies"]'), 5)) {
                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript("
                    document.querySelector('#button--accept-cookies').click()
                ");
                sleep(3);
            }

            $this->savePageToLogs($selenium);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "cws_txt_gcNum"]'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "cws_txt_gcPin"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Get Balance")]'), 0);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $selenium->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");

            $this->savePageToLogs($selenium);

            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//strong[contains(text(), "Card Number:")]'), 10);
            $this->savePageToLogs($selenium);
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
//            $retry = true;
        } catch (UnexpectedJavascriptException | NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }
}
