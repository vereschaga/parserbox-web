<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerTajSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    protected const XPATH_SUCCESS = '
        //a[contains(., "VIEW PROFILE") or @id = "nav-profile-tab"]
        | //div[contains(text(), "Welcome to Taj InnerCircle")]
        | //p[contains(text(), "Welcome to NeuPass")]
        | //span[contains(text(), "LOG OUT")]
    ';

    protected const XPATH_QUESTION = '//span[contains(text(), "Please enter the OTP sent to")]';

    protected const XPATH_ERROR = '
        //span[@class="error-message" and @style="display: block;" and normalize-space(text()) != "undefined"]
        | ' . self::XPATH_QUESTION . '
        | //div[@id = "toast-message"]
        | //p[contains(@class, "css-1hno5b6") or contains(@class, "css-fxrfmg")]
    ';

    protected const XPATH_RESULT = [self::XPATH_SUCCESS, self::XPATH_ERROR];
    private HttpBrowser $browser;
    private int $stepItinerary = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt == 0) {
            $this->setProxyGoProxies(null, 'es');
        } elseif ($this->attempt == 1) {
            $this->setProxyMount();
        } elseif ($this->attempt == 2) {
            $this->setProxyNetNut(null, 'uk');
        }

        $this->UseSelenium();

        /*if ($this->attempt == 0) {
            $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        } elseif ($this->attempt == 1) {*/
            $this->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
        //}

        $request = FingerprintRequest::firefox();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->seleniumOptions->setResolution([
                $fingerprint->getScreenWidth(),
                $fingerprint->getScreenHeight()
            ]);
            $this->http->setUserAgent($fingerprint->getUseragent());
        }


        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        try {
            $this->http->GetURL('https://www.tajhotels.com/en-in/');
            sleep(5);
            $this->http->GetURL('https://www.tajhotels.com/en-in/homepage?redirectUrl=%2Fmy-account&pathUrl=%2Fneupass-login&redirectionType=dialog');
        } catch (ScriptTimeoutException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->debug("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $switchFormXpath = '//button[contains(text(), "EMAIL ADDRESS")]';

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $switchFormXpath = '//button[contains(text(), "MEMBERSHIP NUMBER")]';

            if ($this->http->FindPreg("/^[a-zA-Z]+$/", null, $this->AccountFields['Login'])) {
                throw new CheckException("Invalid Login and/or Password", ACCOUNT_INVALID_PASSWORD);
            }
        }

        $switchForm = $this->waitForElement(WebDriverBy::xpath($switchFormXpath), 35);
        $this->saveResponse();

        if (!$switchForm) {
            return false;
        }

        $switchForm->click();

        $selectedOption = '//div[contains(@id, "simple-tabpanel-") and not(@hidden)]';
        $loginInput = $this->waitForElement(WebDriverBy::xpath($selectedOption . '//input[@name = "senderEmail" or @name = "senderMobile"]'), 5);
        $this->saveResponse();

        if (!$loginInput) {
            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->driver->executeScript("document.querySelector('[aria-label=\"Checkbox demo\"]').click();");
        sleep(1);
        $button = $this->waitForElement(WebDriverBy::xpath($selectedOption . '//button[contains(text(), "CONTINUE")]'), 5);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "Password"]'), 15);
        $this->saveResponse();

        if (!$passwordInput) {
            if ($this->parseQuestion()) {
                return false;
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "We\'d like to know you better")]')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($error = $this->http->FindSingleNode(self::XPATH_ERROR)) {
                $this->logger->error("[Error]: {$error}");

                switch ($error) {
                    case 'Uh-oh! Unable to process verification request now. Try again later':
                        throw new CheckRetryNeededException(3, 0, $error, ACCOUNT_PROVIDER_ERROR);
                    case 'Something Went Wrong, Please Try Again Network Error':
                    case 'Please enter a valid membership number.':
                        throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);

                    default:
                        $this->DebugInfo = $error;
                }
            }

            return false;
        }

        $this->driver->executeScript("document.querySelector('[aria-label=\"Checkbox demo\"]').checked = true;");
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        sleep(1);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "LOGIN")]'), 5);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Application error: a client-side exception has occurred (see the browser console for more information).
        if ($error = $this->http->FindSingleNode('//h2[contains(text(),"Application error: a client-side exception has occurred (see the browser console for more information).")]')) {
            $this->DebugInfo = $error;
            $this->markProxyAsInvalid();
            throw new CheckRetryNeededException(3, 0, $error, ACCOUNT_PROVIDER_ERROR);
        }
        return false;
    }

    public function Login()
    {
        sleep(3);
        $this->waitForElement(WebDriverBy::xpath(implode(" | ", self::XPATH_RESULT)), 12);
        $this->saveResponse();

        if ($question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Please enter your mobile number')]"), 0)) {
            $questionPhone = $question->getText() . ", e.g. (+86) 18528625051";

            if (!isset($this->Answers[$questionPhone])) {
                $this->AskQuestion($questionPhone, null, "QuestionPhone");

                return false;
            }

            $codePhone = $this->http->FindPreg("/\(([^\)]+)/", false, $this->Answers[$questionPhone]);
            $numberPhone = $this->http->FindPreg("/\)\s*(.+)/", false, $this->Answers[$questionPhone]);
            $this->logger->debug("[codePhone]: {$codePhone}");
            $this->logger->debug("[numberPhone]: {$numberPhone}");

            if (!isset($codePhone) || !isset($numberPhone)) {
                $this->AskQuestion($questionPhone, null, "QuestionPhone");

                return false;
            }

            $this->waitForElement(WebDriverBy::xpath("//img[@id = 'down-error-phone']"), 0)->click();
            $this->saveResponse();
            $this->driver->executeScript('document.evaluate(\'//div[@class = "modal-screen"]//div[@class = "list"]//div[contains(@class, "country-code") and text() = "' . $codePhone . '"]\', document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue.click()');
            $this->saveResponse();

            $numberFiled = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'welcomeNewUserPhoneInput-phone']"), 0);
            $this->saveResponse();

            if (!$numberFiled) {
                return false;
            }

            $numberFiled->click();
            $numberFiled->sendKeys($numberPhone);
//            $contBtn = $this->waitForElement(\WebDriverBy::xpath("//button[@value = 'Submit']/parent::node()"), 0);
            $this->saveResponse();

//            if (!$contBtn) {
//                return false;
//            }

//            $contBtn->click();
            $this->driver->executeScript("document.querySelector('[confirmbuttonid=\"confirmButtonWelcome\"] button').click()");

            if ($this->parseQuestion(5)) {
                return false;
            }

            return false;
        }

        if (
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS), 0)
            || $this->http->FindNodes(self::XPATH_SUCCESS)
        ) {
            $this->markProxySuccessful();
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode(self::XPATH_ERROR)) {
            $this->logger->error("[Error]: {$error}");

            switch ($error) {
                case 'Unable to locate user with provided login id.':
                case 'We have upgraded our website/app for a seamless browsing experience. Please reset your password to access your account.':
                case 'Invalid Login and/or Password':
                case 'TIC Number is not registered':
                case 'Invalid credentials':
                case 'Incorrect Password':
                case 'Password is not set, please login using OTP':
                case 'Your email is not verified.':
                case 'User is not found':
                case 'You have entered incorrect password 5 times, please try again after 5 minutes.':
                case 'Invalid Auth Code or Client Id':
                case 'Incorrect password. Please try again.':
                    throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);

                    break;
                /**
                 * Activate Account
                 * Your profile has been created successfully
                 * One Time Password (OTP) has been sent to your registered email and phone number
                 * Not received OTP?Resend OTP.
                 */
                case 'User verification is pending, dynamic access code is send to your email registered with us.':
                    $this->throwProfileUpdateMessageException();
//                    https://www.tajinnercircle.com/bin/validate-code?username=james.rourke%40hotmail.co.uk&otp=123245&action=new-online-user&_=1595841330313
                    break;

                case 'Error during sign in, please try again':
                case 'User registration not complete. Please complete registration to login.':
                case 'User registration not complete. Please complete registration with phone number to login.':
                case 'You have exhausted all email verification attempts.':
                case 'Something went wrong please try again after sometime':
                case strstr($error, 'Email is not verified. Email verification code is sent to '):
                case strstr($error, 'Email is not verified. Email verification code is resent to'):
                case 'Please contact Customer support to activate your account':
                case 'Something Went Wrong, Please Try Again Network Error':
                case '[object Object]':
                    throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);

                    break;

                case 'Login failed - Your account has been locked':
                    throw new CheckException($error, ACCOUNT_LOCKOUT);

                    break;

//                case 'TIC in GR. User not enrolled and OTP sent':
//                    $this->State['data'] = [
//                        "refId"                  => $response->refId,
//                        "countryCode"            => str_replace('+', '', $response->countryCode),
//                        "mobileNumber"           => $response->mobileNumber,
//                        "otp"                    => "",
//                        "phoneVerificationToken" => null,
//                    ];
//                    $this->AskQuestion("Enter the OTP we’ve sent you", null, "Question");
//
//                    break;

                case 'Something went wrong please try again after sometime.':
                    $code = $response->code ?? null;

                    if (stripos($code, 'E11000 duplicate key error collection') !== false) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (stripos($code, ' returned non unique result.') !== false) {// AccountID: 2939855
                        throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    }

                    break;

                default:
                    $this->DebugInfo = $error;
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function parseQuestion($delay = 0)
    {
        $this->logger->notice(__METHOD__);
        $question = $this->waitForElement(WebDriverBy::xpath(self::XPATH_QUESTION), $delay);
        $this->saveResponse();

        if (!$question) {
            if ($error = $this->http->FindSingleNode('//div[@id = "toast-message"]')) {
                $this->logger->error("[Error]: {$error}");

                if ($error == 'Uh-oh! Unable to process verification request now. Try again later') {
//                    throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    throw new CheckRetryNeededException(3, 0, $error, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $error;
            }

            return false;
        }

        $this->holdSession();
        $this->AskQuestion($question->getText(), null, "Question");
        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($step == 'QuestionPhone') {
            $phone = $this->waitForElement(WebDriverBy::xpath('//input[@id = "welcomeNewUserPhoneInput-phone"]'), 0);

            if (!$phone) {
                $this->saveResponse();
                $this->logger->error("something went wrong");

                return false;
            }

            if (!strstr($this->Answers[$this->Question], '(+')) {
                $this->AskQuestion($this->Question, "Please enter date in appropriate format: (+86) 18528625051", "QuestionPhone");

                return false;
            }

            if (!strstr($this->Answers[$this->Question], '(+61')) {
                $this->sendNotification("phone number was entered, need to choose Area Code // RR");
            }

            $phone->sendKeys($this->Answers[$this->Question]);
            // todo: need to select rigth country code

            $button = $this->waitForElement(WebDriverBy::xpath('//button[span[contains(text(), "Continue")] and not(contains(@class, "disabled"))]'), 0);
            $this->saveResponse();

            if (!$button) {
                $this->logger->error("something went wrong");

                return false;
            }

            $button->click();
            sleep(3);
            $this->parseQuestion();

            return false;
        }

        if (!isset($this->Answers[$this->Question])) {
            $this->holdSession();
            $this->AskQuestion($this->Question, null, "Question");

            return false;
        }

        $this->saveResponse();
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);


        $answerInputs = $this->driver->findElements(WebDriverBy::xpath('//form//input[contains(@class,"-input")]'));
        $this->logger->debug("entering answer...");
        foreach ($answerInputs as $i => $element) {
            $this->logger->debug("#{$i}: {$answer[$i]}");
            $element->clear();
            $element->sendKeys($answer[$i]);
        }
        $this->saveResponse();
        sleep(3);

        $result = $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_SUCCESS . " 
            | //span[contains(text(),'Uh-oh! Incorrect OTP.')]
            | //span[contains(text(),'OTP has expired, Please generate a new OTP and try again.')]"), 12);
        $this->saveResponse();

        if ($result && strstr($result->getText(), 'Incorrect OTP.')) {
            $this->holdSession();
            $this->AskQuestion($this->Question, $result->getText(), "Question");

            return false;
        }

        if ($result) {
            $this->markProxySuccessful();
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.tajhotels.com/en-in/my-account");
        $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Neucoin')]"), 10);
        $this->saveResponse();
        // Name
        $this->SetProperty("Name", beautifulName(($this->driver->executeScript("return localStorage.getItem('userFirstName');") ?? null) . " " . $this->driver->executeScript("return localStorage.getItem('userLastName');") ?? null));
        // Membership Number
        $this->SetProperty("Number", $this->http->FindSingleNode('(//span[contains(text(), "Membership Number")])[1]', null, true, "/:\s*(.+)/"));
        // Balance - Neucoins
        $this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Neucoin')]", null, true, "/(.+) Neucoin/"));
        // Current tier
        $this->SetProperty("Tier", $this->driver->executeScript("return localStorage.getItem('userTier');"));
        // Your membership is valid till
        $this->SetProperty("TierExpiration", $this->http->FindSingleNode('//div[@class = "membership-date"]')); // TODO


        // refs#24000
        $epicure =$this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'EPICURE')]"), 0);
        if ($epicure) {
            $epicure->click();
            usleep(500);
            // Epicure Level - Privileged
            $this->SetProperty("EpicureLevel", beautifulName($this->http->FindSingleNode('//span[contains(text(),"Epicure No :")]/../preceding-sibling::h3', null, false, '/^(\w+)$/')));
        }
    }


    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        $this->logger->debug($this->http->currentUrl());
    }

    public function ParseItineraries()
    {
        $noIt = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'There are no bookings under your account.')]"), 0);

        if ($noIt) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $this->parseWithCurl();
        $token = $this->driver->executeScript('return localStorage.getItem("accessToken")');
        $headers = [
            "Authorization" => "Bearer {$token}",
        ];
        $this->browser->GetURL('https://api-cug1-825v2.tajhotels.com/orderService/v1/my-accounts/overview?limit=2',  $headers);
        if ($this->browser->FindPreg('/"data":"Your booking could not be found. Please check the information you have entered"\}/')) {
            $this->itinerariesMaster->setNoItineraries(true);
            return [];
        }
        $response = $this->browser->JsonLog();
        foreach ($response->hotelBookings->upComingBookings as $upComingBooking) {
            if ($upComingBooking->orderType == 'HOTEL_BOOKING') {
                foreach ($upComingBooking->orderLineItems as $orderLineItem) {
                    $this->parseItinerary($orderLineItem->hotel);
                }
            } else {
                $this->sendNotification("new it $upComingBooking->orderType // MI");
            }
        }
        return [];
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();
        $paramHotelName = strtolower(preg_replace('/\s+/', '-', $data->name));
        $this->browser->GetURL("https://www.tajhotels.com/en-in/hotels/$paramHotelName");

        $address = $this->browser->FindSingleNode('//div[normalize-space(text())="CONTACT"]/../following-sibling::div//p');
        $checkInTime = $this->browser->FindSingleNode('//p[contains(text(),"Check-in from")]', null ,false, '/from (.+)/');
        $checkOutTime = $this->browser->FindSingleNode('//p[contains(text(),"Check-out until")]', null ,false, '/until (.+)/');
        //$checkOut = preg_replace('/noon/', '', $checkOut);

        $this->logger->info("[{$this->stepItinerary}] Parse Hotel #{$data->bookingNumber}", ['Header' => 3]);
        $this->stepItinerary++;
        $h->general()->confirmation($data->bookingNumber);
        $h->hotel()
            ->name($data->name)
            ->address($address);

        $data->checkIn = $this->http->FindPreg('/(^\d+.+?)T/', false, $data->checkIn);
        $data->checkOut = $this->http->FindPreg('/(^\d+.+?)T/', false, $data->checkOut);

        $h->booked()
            ->checkIn2("$data->checkIn $checkInTime")
            ->checkOut2("$data->checkOut $checkOutTime")
        ;

        $travellers = [];
        $total = [];
        foreach ($data->rooms as $room) {
            $r = $h->addRoom();
            $r->setConfirmation($room->confirmationId);
            $r->setType($room->roomName);
            $r->setDescription($room->detailedDescription);

            foreach ($room->guestCount as $item) {
                if ($item->ageQualifyingCode == 'Adult') {
                    $h->booked()->guests($item->numGuests);
                }
                if ($item->ageQualifyingCode == 'Child') {
                    $h->booked()->kids($item->numGuests);
                }
            }

            foreach ($room->travellerDetails as $item) {
                $travellers[] = beautifulName("{$item->firstName} {$item->lastName}");
            }


            $h->general()
                ->cancellation($room->cancelPolicyDescription);
            $total[] = $room->price;
        }
        $h->general()->travellers(array_unique($travellers));


        if (preg_match("/Free cancellation by (?<hours>[\d:]+[AP]M)-(?<days>\d+) days prior to arrival to avoid a penalty of 1 night charge plus/u", $h->getCancellation(), $m)) {
            $h->booked()
                ->deadlineRelative($m['days'] . 'days', $m['hours']);
        }

        $h->price()
            ->total(array_sum($total))
            ->currency($room->currency)
        ;
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }
}
