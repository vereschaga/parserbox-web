<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerPanorama extends TAccountChecker
{
    use ProxyList;

//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerPanoramaSelenium.php";
//        return new TAccountCheckerPanoramaSelenium();
//    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        // Card number should contain 10 digits.
        if (strlen($this->AccountFields['Login']) < 10) {
            throw new CheckException("Card number should contain 10 digits.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->LogHeaders = true;
        $this->http->removeCookies();
        $this->http->GetURL("https://new.flyuia.com/ua/en/panorama-club/");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
//        $this->http->FormURL = 'https://www.flyuia.com/UDS/ajax.php?c=Account&m=RequestAuth';
        $this->http->Inputs = [
            'Login' => [
                'maxlength' => 10,
            ],
            'txtPass' => [
                'Password' => 6,
            ],
        ];
        $this->http->SetInputValue('Login', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://new.flyuia.com/ua/en/panorama-club/';
        $arg['SuccessURL'] = 'https://www.flyuia.com/ua/en/panorama-club/AccountSummary';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Currently on the website flyuia.com is undergoing technical work.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("
                //h1[contains(text(), '502 Bad Gateway')]
                | //h1[contains(text(), '522 Origin Connection Time-out')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//div[contains(text(), 'we have some technical problems.')]")) {
            throw new CheckException("Sorry, we have some technical problems.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm([], 80)) {
            // {"readyState":4,"responseText":"","status":500,"statusText":"Internal Server Error"}
            if ($this->http->Response['code'] == 500 && empty($this->http->Response['body'])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        if ($this->http->FindPreg("/SOAP-ERROR: Parsing WSDL: Couldn't load from/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/"status":"Error","message":"Object reference not set to an instance of an object\."/')) {
            throw new CheckException('Error connecting to server. Please, try again.', ACCOUNT_PROVIDER_ERROR);
        }

        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->status, $response->message->USERID) && $response->status == 'Success') {
            $this->http->GetURL("https://www.flyuia.com/ua/en/panorama-club/AccountSummary");
            // provider error
            if ($this->http->Response['code'] == 500 && $this->http->Response['body'] == 'Error') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }// if (isset($response->status, $response->message->USERID) && $response->status == 'Success')

        if (isset($response->status, $response->message) && in_array($response->status, ['Error', 'NoAuth', 'AccountLocked'])) {
            // Invalid card number or PIN code
            if (strstr($response->message, 'Authentication failed: message not found')) {
                throw new CheckException("Invalid card number or PIN code", ACCOUNT_INVALID_PASSWORD);
            }
            // Invalid credentials
            if (strstr($response->message, 'Authentication failed')) {
                throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($response->message, 'The process cannot access the file')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // This account is locked
            if (strstr($response->message, 'account_locked:[No translate]')) {
                throw new CheckException("This account is locked", ACCOUNT_LOCKOUT);
            }
            // Server was unable to process request.
            if (strstr($response->message, 'Server was unable to process request.')
                /*
                 * There was no endpoint listening at http://lmsapp.flyuia.com/axis2/services/WebServicesService.WebServicesServiceHttpSoap11Endpoint/ that could accept the message.
                 * This is often caused by an incorrect address or SOAP action. See InnerException, if present, for more details.
                 */
                || strstr($response->message, 'There was no endpoint listening at http')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            } else {
                $this->logger->error("Unknown error -> {$response->message}");
            }
        }// if (isset($response->status, $response->message) && in_array($response->status, ['Error', 'NoAuth', 'AccountLocked']))

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindPreg("/<td[^>]+>Name[^<]*<\/td>\s*<td[^\>]*>([^<]+)/")
            . ' ' . $this->http->FindPreg("/<td[^\>]*>Surname[^<]*<\/td>\s*<td[^\>]*>([^<]+)/")));
        // Panorama Club Card Number
        $this->SetProperty("CardNumber", $this->http->FindPreg("/<td[^\>]*>Panorama Club Card Number[^<]*<\/td>\s*<td[^\>]*>([^<]+)/"));
        // Balance - Award miles
        $this->SetBalance($this->http->FindPreg("/<td[^\>]*>Miles balance[^<]*<\/td>\s*<td[^\>]*>([^<]+)/ims"));
        // Status miles accumulated in [this_year]
        $this->SetProperty("StatusMilesThisYear", $this->http->FindPreg("/<td[^\>]*>Status miles accumulated in[^<]+<\/td>\s*<td[^\>]*>([^<]+)/"));
        // Status segments accumulated in [this_year]
        $this->SetProperty("StatusSegmentsThisYear", $this->http->FindPreg("/<td[^\>]*>Status segments accumulated in[^<]+<\/td>\s*<td[^\>]*>([^<]+)/"));
        // Card level
        $this->SetProperty("CardLevel", $this->http->FindPreg("/<td[^\>]*>Card level\/Card Validity[^<]*<\/td>\s*<td[^\>]*>([^\/<]+)/"));
        // Card Validity
        $this->SetProperty("CardValidity", $this->http->FindPreg("/<td[^\>]*>Card level\/Card Validity[^<]*<\/td>\s*<td[^\>]*>[^\/]+\/\s*([^<]+)/"));
        // Status miles required toward Premium Level
        $this->SetProperty("StatusMilesRequired", $this->http->FindPreg("/<td[^\>]*>Status miles required toward[^<]+<\/td>\s*<td[^\>]*>([^<]+)/"));
        // Status segments required toward Premium Level
        $this->SetProperty("StatusSegmentsRequired", $this->http->FindPreg("/<td[^\>]*>Status segments required toward[^<]+<\/td>\s*<td[^\>]*>([^<]+)/"));

        // Expiry date
        $matches = $this->http->FindPregAll('/<tr>\s*<td>(?<date>[\d\/]+)<\/td>\s*<td[^\>]*>(?<miles>[^<]+)<\/td>\s*<\/tr>/ims', $this->http->Response['body'], PREG_SET_ORDER);

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
