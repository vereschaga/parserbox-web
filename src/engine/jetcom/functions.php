<?php

class TAccountCheckerJetcom extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://my.jet2.com/bookings');
        $login = $this->waitForElement(WebDriverBy::id('email'), 7);
        $btn = $this->waitForElement(WebDriverBy::id('continue'), 0);

        if (!isset($login, $btn)) {
            $this->saveResponse();

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $btn->click();

        return true;

        $this->http->GetURL('https://login.jet2.com/03390016-3d96-41a8-8b21-03f1259fb9cf/b2c_1a_signup-signin-sspr/oauth2/v2.0/authorize?client_id=cf287b73-24d7-4b84-a219-89b8388c79e3&scope=openid%20email%20offline_access%20https%3A%2F%2Flogin.jet2.com%2Fmyjet2-edge%2FmyJet2.Access&response_type=code&redirect_uri=https%3A%2F%2Fmy.jet2.com%2Fapi%2Fauth%2Fcallback%2Fazure-ad-b2c&state=QZOXgs-71WPu2gMq6Eii2nASGwmJ4tfO9-jE4MJNRcA');

        if (!$this->http->ParseForm('attributeVerification')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('email', $this->AccountFields["Login"]);
        $this->http->SetInputValue('request_type', 'RESPONSE');
        $this->http->FormURL = 'https://login.jet2.com/03390016-3d96-41a8-8b21-03f1259fb9cf/B2C_1A_SignUp-SignIn-SSPR/SelfAsserted?tx=StateProperties=eyJUSUQiOiIwNmM0YTk0ZC0zOTNlLTQ0M2UtYTNmMy1jYzc2NTU3OThjYTIifQ&p=B2C_1A_SignUp-SignIn-SSPR';

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->GetURL("https://reservations.jet2.com/jet2.reservations.web.portal/myjet2jsonpproxy.aspx?action=login&callback=jQuery171030530628511328806_" . time() . date('B') . "&un=" . urlencode($this->AccountFields["Login"]) . "&pw=" . urlencode($this->AccountFields["Pass"]) . "&_=" . time() . date('B'));
        $this->http->SetInputValue("TxtBox_Email", $this->AccountFields["Login"]);
        $this->http->SetInputValue("TxtBox_Password", $this->AccountFields["Pass"]);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['RequestMethod'] = 'GET';
        $arg['CookieURL'] = "https://reservations.jet2.com/jet2.reservations.web.portal/myjet2jsonpproxy.aspx?action=login&callback=jQuery171030530628511328806_" . time() . date('B') . "&un=" . urlencode($this->AccountFields["Login"]) . "&pw=" . urlencode($this->AccountFields["Pass"]) . "&_=" . time() . date('B');
        $arg['SuccessURL'] = 'https://reservations.jet2.com/jet2.Reservations.web.portal/start.aspx?action=7&lang=en';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're updating Jet2.com
        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "We\'re updating Jet2.com")]
                | //p[contains(text(), "Our website is currently unavailable as we\'re making some great new updates and improvements.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if ($this->http->FindSingleNode('
                //h1[contains(text(), "Internal Server Error - Read")]
                | //p[contains(text(), "A server-side 500 error occurred.")]
        ')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $el = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"] | //button[@aria-label="Send verification code" and @aria-hidden="false"] | //div[@class="error pageLevel" and @aria-hidden="false"] | //label[contains(text(), "We send our flight and package holiday offers out to myJet2 account holders by e-mail")]'), 8, false);
        $this->saveResponse();

        // password
        if ($el && $el->getAttribute('id') == 'password' && $btn = $this->waitForElement(WebDriverBy::id('next'), 0)) {
            $el->sendKeys($this->AccountFields['Pass']);
            $btn->click();
            $el = $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Bookings")] | //label[contains(text(), "Don\'t miss out on offers")] | //div[@class="error pageLevel" and @aria-hidden="false"]'), 7);
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Don\'t miss out on offers")]'), 0)
                && $continueBtn = $this->waitForElement(WebDriverBy::id('continue'), 0)
            ) {
                $continueBtn->click();
                sleep(2);
                $this->saveResponse();
            }

            if ($error = $this->waitForElement(WebDriverBy::xpath('//div[@class="error pageLevel" and @aria-hidden="false"]'), 0, false)) {
                $error = $error->getText();
                $this->logger->error("[Error]: {$error}");

                if (str_contains($error, 'Invalid username or password')) {
                    throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                }

                if (str_contains($error, 'Oops! An error occurred on our side, please try again later.')) {
                    throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $error;

                return false;
            }

            if ($el) {
                return true;
            }
        }

        // 2fa
        if ($el && $el->getAttribute('id') == 'readOnlyEmail_ver_but_send') {
            $this->throwProfileUpdateMessageException(); // Temporary. Zero updated accounts by far.

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }
            $el->click();

            if ($this->waitForElement(WebDriverBy::xpath('//input[@aria-label="Verification code" and @aria-hidden="false"]'), 5, false)) {
                $this->holdSession();
                $this->AskQuestion('We’ve sent a verification code to your email inbox.', null, 'Question');

                return false;
            }
            $this->saveResponse();

            return $this->checkErrors();
        }

        // validation error
        if ($el && $el->getAttribute('class') == 'error pageLevel') {
            $error = $el->getText();
            $this->logger->error($error);

            if (str_contains($error, 'One or more fields are filled out incorrectly')) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = $error;

            return false;
        }

        if ($this->http->FindSingleNode('//label[contains(text(), "We send our flight and package holiday offers out to myJet2 account holders by e-mail") or contains(text(), "Fancy exclusive discounts, destination updates and inspiration in your inbox")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://my.jet2.com/personal-details');
        $this->waitForElement(WebDriverBy::xpath('//p[@data-testid="accordion-header-value-Name"]/span[not(contains(text(), "Not specified"))]'), 10);
        $this->saveResponse();
        $name = $this->http->FindSingleNode('//div[@data-testid="accordion-header-value-Name"]/div');
        $this->logger->debug("[Name]: {$name}");
        $email = $this->http->FindSingleNode('//div[@data-testid="accordion-header-value-Email address"]/div');
        $this->logger->debug("[Email]: {$email}");

        if ($name != 'Not specified') {
            $this->SetProperty('Name', beautifulName($name));
        }

        if ($name || $email) {
            $this->SetBalanceNA();
        }
    }

    public function xpathQuery($query)
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    public function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];

        // RecordLocator
        $result['RecordLocator'] = $this->http->FindPreg('/Reference no: (\w+)/');
        $this->logger->info(sprintf('Parse Itinerary #%s', $result['RecordLocator']), ['Header' => 3]);
        // Passengers
        $passengers = [];
        $labels = $this->http->FindNodes('//label[contains(@class, "passenger-summary__label")]');

        foreach ($labels as $label) {
            $passengers[] = trim(beautifulName($label));
        }
        $result['Passengers'] = $passengers;
        // TotalCharge
        $total = $this->http->FindSingleNode('//div[contains(@class, "price-breakdown__footer")]');
        $result['TotalCharge'] = $this->cost($total);
        // Currency
        $result['Currency'] = $this->currency($total);
        // TripSegments
        $tripSegments = [];
        $segments = $this->xpathQuery('//section[contains(@class, "flight-detail")]');

        foreach ($segments as $node) {
            $ts = [];
            // FlightNumber
            $flight = trim($this->http->FindSingleNode('.//span[contains(text(), "Flight:")]/following-sibling::span[1]', $node));
            $ts['FlightNumber'] = $this->http->FindPreg('/\w{2}(\d+)/', false, $flight);
            // AirlineName
            $ts['AirlineName'] = $this->http->FindPreg('/^\w{2}/', false, $flight);
            // DepCode
            $fromTo = $this->http->FindSingleNode('.//h2[contains(@class, "flight-detail__fromto")]', $node);
            $depText = $this->http->FindPreg('/(.+?) to/', false, $fromTo);
            $ts['DepCode'] = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $depText);
            // ArrCode
            $arrText = $this->http->FindPreg('/to (.+)/', false, $fromTo);
            $ts['ArrCode'] = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $arrText);
            // DepartureTerminal
            $ts['DepartureTerminal'] = $this->http->FindPreg('/Terminal\s+(\d+)/i', false, $depText);
            // ArrivalTerminal
            $ts['ArrivalTerminal'] = $this->http->FindPreg('/Terminal\s+(\d+)/i', false, $arrText);
            //DepDate
            $depDateText = $this->http->FindSingleNode('.//span[contains(text(), "Depart:") or contains(text(), "Departs:")]/following-sibling::span[1]', $node);
            $depDateText = preg_replace('/\bat\s+/', '', $depDateText);
            $ts['DepDate'] = strtotime($depDateText);
            // ArrDate
            $arrDateText = $this->http->FindSingleNode('.//span[contains(text(), "Arrive:") or contains(text(), "Arrives:")]/following-sibling::span[1]', $node);
            $arrDateText = preg_replace('/\bat\s+/', '', $arrDateText);
            $ts['ArrDate'] = strtotime($arrDateText);
            // Duration
            $ts['Duration'] = $this->http->FindSingleNode('.//span[contains(text(), "In the air:")]/following-sibling::span[1]', $node);
            $tripSegments[] = $ts;
        }
        $result['TripSegments'] = $tripSegments;

        return $result;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->GetURL('https://reservations.jet2.com/Jet2.Reservations.Web.Portal/secure/loggedin/MyJet2HomePage.aspx');
        $this->waitForElement(WebDriverBy::xpath('//h1[contains(@class, "section__title")]'), 3);
        $this->saveResponse();

        if ($this->http->FindNodes('//div[@style="display:block;" and contains(text(), "You don\'t have any active bookings")]')) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        $pnrList = $this->http->FindNodes('//h1[contains(@class, "section__title")]/@data-pnr');

        foreach ($pnrList as $pnr) {
            $this->http->GetURL(sprintf('https://www.jet2.com/api/myjet2/myjet2bookings/setpnrcontext?pnr=%s&scid=INITIAL_IDENTIFIER', $pnr));
            $contextId = $this->http->FindPreg('/"contextId":"(\w+)"/');
            $this->http->GetURL(sprintf('https://www.jet2.com/en/manage-my-booking?scid=%s', $contextId));
            $result[] = $this->ParseItinerary();
        }

        return $result;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $input = $this->waitForElement(WebDriverBy::xpath('//input[@aria-label="Verification code" and @aria-hidden="false"]'), 5, false);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Verify" and @aria-hidden="false"]'), 0, false);

        if (!isset($input, $btn)) {
            $this->saveResponse();

            return $this->checkErrors();
        }
        $input->clear();
        $input->sendKeys($answer);
        $this->saveResponse();
        $btn->click();

        if ($error = $this->waitForElement(WebDriverBy::xpath('//div[@class="verificationErrorText error" and @aria-hidden="false"]'), 6, false)) {
            $this->saveResponse();
            $error = $error->getText();
            $this->logger->error($error);

            if (str_contains($error, 'Your verification code is incorrect')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error, 'Question');

                return false;
            }

            if (str_contains($error, 'Incorrect format')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error, 'Question');

                return false;
            }

            if (str_contains($error, 'There have been too many requests to verify this email address')
                || str_contains($error, "You've made too many incorrect attempts")
            ) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            }

            if (str_contains($error, 'That code is expired')
                && $resendCodeBtn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Resend code" and @aria-hidden="false"]'), 0, false)
            ) {
                $resendCodeBtn->click();
                $this->holdSession();
                $this->AskQuestion($this->Question, 'That code is expired. We’ve sent a new verification code to your email inbox.', 'Question');

                return false;
            }

            $this->DebugInfo = $error;

            return false;
        }

        return true;
    }
}
