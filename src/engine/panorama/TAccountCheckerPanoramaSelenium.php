<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPanoramaSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->UseSelenium();
        $this->useChromium();
        //engine/pac.php -> affiliate.flyuia.com
        //$this->useCache();
        //$this->disableImages();
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://new.flyuia.com/us/en/panorama-club/");

        $login = $this->waitForElement(WebDriverBy::id('login-card-number'), 10);
//        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Login"]'), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Password"]'), 0);
        $sbm = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Enter"]'), 0);

        if (!$login || !$pass || !$sbm) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $sbm->click();

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        sleep(4);
        $sleep = 30;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

            $logout = $this->waitForElement(WebDriverBy::id('logout'), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }
            // Card number should contain 10 digits.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Card number should contain 10 digits.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Invalid card number or PIN code
            if ($message = $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Invalid card number or PIN code")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Invalid Card number or Password
            if ($message = $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Invalid Card number or Password")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // The password must be a 6-digit number
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "The password must be a 6-digit number")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Password consists of 6 digits
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Password consists of 6 digits")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // This account is locked
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "This account is locked")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
            }
            // Error connecting to server. Please, try again.
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Error connecting to server.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            sleep(1);
            $this->saveResponse();
        }
        // Please match the requested format
        if ($this->http->FindPreg('/[\*a-z]+/ims', false, $this->AccountFields['Pass'])) {
            throw new CheckException('Password consists of 6 digits', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->AccountFields['Login'] == '‎1007769744' || strlen($this->AccountFields['Pass']) < 6) {
            throw new CheckException('Please match the requested format', ACCOUNT_INVALID_PASSWORD);
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->saveResponse();
//        if ($this->http->FindPreg("/SOAP-ERROR: Parsing WSDL: Couldn't load from/"))
//            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

//        if (isset($response->status, $response->message) && in_array($response->status, ['Error', 'NoAuth'])) {
//            // Invalid card number or PIN code
//            if (strstr($response->message, 'Authentication failed: message not found'))
//                throw new CheckException("Invalid card number or PIN code", ACCOUNT_INVALID_PASSWORD);
//            // Invalid credentials
//            if (strstr($response->message, 'Authentication failed'))
//                throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
//            if (strstr($response->message, 'The process cannot access the file'))
//                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
//            // Server was unable to process request.
//            if (strstr($response->message, 'Server was unable to process request.')
//                /*
//                 * There was no endpoint listening at http://lmsapp.flyuia.com/axis2/services/WebServicesService.WebServicesServiceHttpSoap11Endpoint/ that could accept the message.
//                 * This is often caused by an incorrect address or SOAP action. See InnerException, if present, for more details.
//                 */
//                || strstr($response->message, 'There was no endpoint listening at http'))
//                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
//            else
//                $this->logger->error("Unknown error -> {$response->message}");
//        }// if (isset($response->status, $response->message) && in_array($response->status, ['Error', 'NoAuth']))

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.flyuia.com/us/en/panorama-club/AccountSummary");
        // provider error
        if ($this->http->Response['code'] == 500 && $this->http->Response['body'] == 'Error') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindPreg("/<td[^>]+>Name[^<]*<\/td>\s*<td>([^<]+)/")
            . ' ' . $this->http->FindPreg("/<td[^\>]*>Surname[^<]*<\/td>\s*<td>([^<]+)/")));
        // Panorama Club Card Number
        $this->SetProperty("CardNumber", $this->http->FindPreg("/<td[^\>]*>Panorama Club Card Number[^<]*<\/td>\s*<td>([^<]+)/"));
        // Balance - Award miles
        $this->SetBalance($this->http->FindPreg("/<td[^\>]*>Award miles[^<]*<\/td>\s*<td>([^<]+)/ims"));
        // Status miles accumulated in [this_year]
        $this->SetProperty("StatusMilesThisYear", $this->http->FindPreg("/<td[^\>]*>Status miles accumulated in[^<]+<\/td>\s*<td>([^<]+)/"));
        // Status segments accumulated in [this_year]
        $this->SetProperty("StatusSegmentsThisYear", $this->http->FindPreg("/<td[^\>]*>Status segments accumulated in[^<]+<\/td>\s*<td>([^<]+)/"));
        // Card level
        $this->SetProperty("CardLevel", $this->http->FindPreg("/<td[^\>]*>Card level\/Card Validity[^<]*<\/td>\s*<td>([^\/<]+)/"));
        // Card Validity
        $this->SetProperty("CardValidity", $this->http->FindPreg("/<td[^\>]*>Card level\/Card Validity[^<]*<\/td>\s*<td>[^\/]+\/\s*([^<]+)/"));
        // Status miles required toward Premium Level
        $this->SetProperty("StatusMilesRequired", $this->http->FindPreg("/<td[^\>]*>Status miles required toward[^<]+<\/td>\s*<td>([^<]+)/"));
        // Status segments required toward Premium Level
        $this->SetProperty("StatusSegmentsRequired", $this->http->FindPreg("/<td[^\>]*>Status segments required toward[^<]+<\/td>\s*<td>([^<]+)/"));

        // Expiry date
        $matches = $this->http->FindPregAll('/<tr>\s*<td>(?<date>[\d\/]+)<\/td>\s*<td>(?<miles>[^<]+)<\/td>\s*<\/tr>/ims', $this->http->Response['body'], PREG_SET_ORDER);

        foreach ($matches as $match) {
            $date = $this->ModifyDateFormat($match['date'], "/", true);
            $miles = $match['miles'];
            $this->logger->debug("$date / " . strtotime($date) . " -> {$miles}");

            if (strtotime($date)) {
                $this->SetExpirationDate(strtotime($date));
                // Expiry miles
                $this->SetProperty("ExpiryMiles", $miles);

                break;
            }// if (strtotime($date))
        }// foreach ($matches as $match)
    }
}
