<?php

use AwardWallet\Engine\ProxyList;
class TAccountCheckerUber extends TAccountChecker
{
    use ProxyList;

    private $name = '';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            $this->http->SetProxy('localhost:8000');
        }
    }

//    public function TuneFormFields(&$arFields, $values = NULL) {
//        parent::TuneFormFields($arFields);
//        // LOG IN
//        $regionOptions = array(
//            ""       => "Select region",
//            "Rider"  => "as a Rider",
//            "Driver" => "as a Driver",
//        );
//        $arFields["Login2"]["Options"] = $regionOptions;
//    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $arg["RedirectURL"] = 'https://riders.uber.com/';
    }

    public function LoadLoginForm()
    {
//        $this->http->Log("Region: ".$this->AccountFields["Login2"]);
        $this->http->removeCookies();
//        if ($this->AccountFields['Login'] == 'Rider') {
        $this->http->setCookie("_LOCALE_", "en_US", '.uber.com');
        $this->http->GetURL("https://riders.uber.com/");

        if (!$this->http->ParseForm(null, 1)) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
//        }
//        else {
//            $this->http->GetURL("https://partners.uber.com/#!/sign-in");
//            if (!$this->http->ParseForm("log-in"))
//                return $this->checkErrors();
//            $this->http->FormURL = 'https://partners.uber.com/api/auth/web_login/driver';
//            $this->http->SetInputValue("login", $this->AccountFields['Login']);
//            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
//        }

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
//        $this->http->Log("Region: ".$this->AccountFields["Login2"]);
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
//        if ($this->AccountFields['Login'] == 'Rider') {

        if ($this->http->ParseForm("login-form") && $this->http->InputExists("captcha-response-token")) {
            $this->logger->notice("reCaptcha");
            $this->http->SetInputValue("email", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('captcha-response-token', $captcha);

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        // Invalid email or password
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Invalid email or password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
//        }
//        else {
//            // Invalid email or password
//            if ($message = $this->http->FindSingleNode("//p[contains(@error, 'error')]"))
//                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
//        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('parseQuestion', ['Header' => 3]);

        if (!$this->http->FindSingleNode("//span[contains(text(), 'Two-Step Verification')]") || !$this->http->ParseForm("login-form")) {
            return false;
        }
        $email = $this->http->FindSingleNode("(//div[@id = 'input-container']//label[contains(text(), 'Email')])[1]", null, true, "/Email\s*-\s*([^<]+)/");
        $emailValue = $this->http->FindSingleNode("(//div[@id = 'input-container']//label[contains(text(), 'Email')])[1]/preceding-sibling::input/@value");
        $this->logger->debug("Email: {$email} / {$emailValue}");

        $phone = $this->http->FindSingleNode("(//div[@id = 'input-container']//label[contains(text(), 'SMS')])[1]", null, true, "/SMS\s*-\s*([^<]+)/");
        $phoneValue = $this->http->FindSingleNode("(//div[@id = 'input-container']//label[contains(text(), 'SMS')])[1]/preceding-sibling::input/@value");
        $this->logger->debug("Phone: {$phone} / {$phoneValue}");

        if ($email && $emailValue) {
            $question = "Please enter Identification Code which was sent to the following email address: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            $value = $emailValue;
        } elseif ($phone && $phoneValue) {
            $question = "Please enter Code which was sent to the phone number ending in {$phone}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            $value = $phoneValue;
        } else {
            return false;
        }
        $this->http->SetInputValue("mfa_method", $value);
        $this->http->PostForm();

        if (!isset($question) || !$this->http->ParseForm("login-form")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetInputValue("mfa_token", $this->Answers[$this->Question]);
        $this->http->PostForm();
        // The verification code you entered is invalid
        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'The verification code you entered is invalid')]")) {
            $this->logger->error($error);
            $this->AskQuestion($this->Question, $error);

            return false;
        }// if ($error = $this->http->FindSingleNode("//p[contains(text(), 'The verification code you entered is invalid')]"))
        unset($this->Answers[$this->Question]);

        return true;
    }

    public function Parse()
    {
//        $this->http->Log("Region: ".$this->AccountFields["Login2"]);
//        if ($this->AccountFields['Login'] == 'Rider') {
        $name = $this->http->FindSingleNode("//div[@class = 'grid']//h3[contains(@class, 'push-half--bottom')]");

        if (isset($name)) {
            $this->name = beautifulName($name);
            $this->SetProperty("Name", $this->name);
            $this->SetBalanceNA();
        }
//        }
//        else {
//
//        }
    }

    public function ParseItineraries()
    {
        $result = [];
//        $this->http->Log("Region: ".$this->AccountFields["Login2"]);
//        if ($this->AccountFields['Login'] == 'Rider') {
        $this->http->GetURL('https://riders.uber.com/en_US/trips');
        $page = 1;

        do {
            $this->logger->debug("Open page # $page");

            if ($page > 1 && isset($nextPage)) {
                $this->http->NormalizeURL($nextPage);
                $this->http->GetURL($nextPage);
            }
            $rentals = $this->http->XPath->query("//table[@id = 'trips-table']/tbody/tr[contains(@class, 'trip-expand__origin')]");
            $details = $this->http->XPath->query("//table[@id = 'trips-table']/tbody/tr[contains(@class, 'hard')]");
            $this->logger->debug("Total {$details->length} reservations were found ");

            for ($i = 0; $i < $details->length; $i++) {
                /** @var \AwardWallet\ItineraryArrays\CarRental $res */
                $res = [];
                $res['Kind'] = 'L';
                // Number   // refs #10700, 12220
                $res["Number"] = CONFNO_UNKNOWN;
                // TotalCharge
                $res['TotalCharge'] = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/h3", $details->item($i), true, "/([\d\.\,]+)/ims");
                // Currency
                $res["Currency"] = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/h3", $details->item($i), true, '/([A-Z]{3})/ims');

                if (empty($res["Currency"])) {
                    $currency = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/h3", $details->item($i), true, '/\$/ims');

                    if ($currency) {
                        $res["Currency"] = 'USD';
                    }
                }
                // PickupLocation
                $res['PickupLocation'] = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/div/div[1]//h6", $details->item($i));
                // PickupDatetime
                $time = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/h6", $details->item($i), true, "/\d{4}\s+([^<]+)$/ims");
                $date = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/h6", $details->item($i), true, "/\,\s+(.+\d{4})\s+/ims");
                $this->http->Log("Date: $date time: $time");
                $res['PickupDatetime'] = strtotime($date . ' ' . $time);
                // DropoffLocation
                $res['DropoffLocation'] = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/div/div[2]//h6", $details->item($i));
                // DropoffDatetime
                $time = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]/div[2]/div/div[2]//p", $details->item($i));
                $this->http->Log("Date: $date time: $time");
                $res['DropoffDatetime'] = strtotime($date . ' ' . $time);
                // CarModel
                $res['CarModel'] = $this->http->FindSingleNode("td[5]", $rentals->item($i));

                // refs #12155
                if ($res['CarModel'] == 'UberEATS') {
                    $this->logger->notice("Skip UberEATS category");

                    continue;
                }

                // Renter Name  // refs #10700
//                    $res['RenterName'] = beautifulName($this->http->FindSingleNode("td[3]", $rentals->item($i)));
                $res['RenterName'] = $this->name;
                // Cancelled
                if ($this->http->FindSingleNode("td[4]/div", $rentals->item($i)) == 'Canceled') {
                    $res['Cancelled'] = true;
                }

                // provider bug fix
                if (empty($res['PickupLocation']) && !empty($res['DropoffLocation']) && !isset($res['Cancelled'])) {
                    if ($detailsLink = $this->http->FindSingleNode(".//div[contains(@id, 'expand')]//a[contains(@href, '/trips/')]/@href", $details->item($i), true, null, 1)) {
                        $http2 = clone $this->http;
                        $this->logger->debug("Loading details...");
                        $http2->NormalizeURL($detailsLink);
                        $http2->GetURL($detailsLink);
                        // PickupLocation
                        $res['PickupLocation'] = $http2->FindSingleNode("(//div[contains(@class, 'trip-address')]//h6)[1]");
                    } else {
                        $this->logger->debug("Details link not found");
                    }
                }// if (empty($res['PickupLocation']) && !empty($res['DropoffLocation']) && !isset($res['Cancelled']))

                $result[] = $res;
            }// for ($i = 0; $i < $details->length; $i++)
            $page++;
        } while (($nextPage = $this->http->FindSingleNode("//a[contains(@href, '?page={$page}')]/@href")) && $page < 2);
//        }
//        else {
//
//        }

        return $result;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $key = $this->http->FindPreg("/'sitekey'\s*:\s*'([^\']+)/");
        $key = '6Lc8fSkTAAAAAAyJtkKcbUCNwBZ-5nbaVLczQEYh';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
