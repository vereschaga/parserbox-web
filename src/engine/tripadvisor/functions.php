<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Event;

class TAccountCheckerTripadvisor extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    use PriceTools;
    /** @var CaptchaRecognizer */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
        $this->setProxyGoProxies();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.tripadvisor.com/Bookings", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // invalid email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("E-mail address  is either invalid or starts with a generic alias to which we cannot send.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        /*$this->http->setCookie("SetCurrency", "USD", "www.tripadvisor.com");

        if (!$this->http->GetURL("https://www.tripadvisor.com/")) {
            return $this->checkErrors();
        }

        $this->http->GetURL("https://www.tripadvisor.com/RegistrationController?flow=core_combined&pid=39778&returnTo=%2FBookings&fullscreen=true&requireSecure=true");

        if (!$this->http->FindSingleNode("//div[@id = 'regSignIn']")) {
            if (
                $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
                || $this->http->Response['code'] == 403
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3);
            }

            return $this->checkErrors();
        }

        $this->markProxySuccessful();*/

        $this->getCookiesFromSelenium();

        return true;

        $this->http->FormURL = 'https://www.tripadvisor.com/RegistrationController';
        $this->http->Form = [];
        $this->http->SetInputValue("altsessid", $this->http->FindSingleNode("//input[@id = 'altsessid']/@value"));
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("formContext", "taSignIn");
        $this->http->SetInputValue("flow", $this->http->FindPreg("/signin_options\s*=\s*\{flow:\s*'([^\']+)',csrfToken:\s*'/"));
        $this->http->SetInputValue("stateToken", $this->http->FindSingleNode("//input[@id = 'regState']/@value"));
        $this->http->SetInputValue("csrfToken", $this->http->FindPreg("/signin_options\s*=\s*\{flow:\s*'[^\']+',csrfToken:\s*'([^\']+)/"));
        $this->http->SetInputValue("noHeadFoot", "true");
        $this->http->SetInputValue("forceDesktop", "true");
        $this->http->SetInputValue("g-recaptcha-response", '');
        $this->http->SetInputValue("recaptchaSiteKey", '');
        // header
        $this->http->setDefaultHeader("X-Puid", $this->http->FindPreg("/'X-Puid'\s*,\s*'([^\']+)/"));

        if ($key = $this->http->FindSingleNode("//div[@id = 'recaptcha_context_data']/@data-site-key-enterprise")) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->SetInputValue("recaptchaSiteKey", $key);
        }

        return true;
    }

    public function Login()
    {
        /*
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Accept"           => "*
        /*",
//            'x-puid'           => 'Wtc0RgoQKmEAAq8OJ00AAADa',
            "Content-Type"     => "application/x-www-form-urlencoded; charset=utf-8",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        */

        if ($this->http->FindPreg("/ta.registration.RegController.closeOverlay/")) {
            $this->http->PostURL("https://www.tripadvisor.com/MultiPartAjax", [
                "ifb"        => "true",
                "parts"      => "IP_HEADER",
                "requestUrl" => "__2F__",
            ]);
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Either your email or password was incorrect\. Please try again
        if ($message = $this->http->FindPreg("/(?:\"error\":\"|)(Either your email or password was incorrect\. Please try again)/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // E-mail address is either invalid or starts with a generic alias to which we cannot send.
        if ($message = $this->http->FindPreg("/(?:\"error\":\"|)(E-mail address\s*is either invalid or starts with a generic alias to which we cannot send.)/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/(?:\"error\":\"|)(Password must be at least six characters long)\"/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // For security purposes, we are asking TripAdvisor members to update their passwords. Your old password has expired.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'For security purposes, we are asking TripAdvisor members to update their passwords. Your old password has expired.')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * Your TripAdvisor password has expired. To reset your password, open the email we just sent to
         * ...
         * Follow the instructions in the email to sign in. Thanks!
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your TripAdvisor password has expired.')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Your TripAdvisor password has expired.", ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if (
            $this->http->FindPreg("/\"error\":\"(Please verify that you are not a robot\.)\"/")
            || $this->http->FindPreg("/\"error\":\"(We.u0027re sorry, an unexpected error has occurred. Please try again.)\"/")
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(4, 1, self::CAPTCHA_ERROR_MSG);
        }
        // Sign-in Error
        if ($message = $this->http->FindPreg("/\"error\":\"(Sign-in Error)\"/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false && strstr($this->AccountFields['Login'], '@gmail.com')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('Unfortunately, we are currently do not support Login with Social Media', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.tripadvisor.com/members-badgecollection');
        // Balance - Total Points
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Total Points')]/following-sibling::div[@class = 'points']"));
        // Current Level
        $this->SetProperty("Level", $this->http->FindSingleNode("//div[@class='progress_info tripcollectiveinfo']//div[@class='current_badge badge']"));
        // Total Points refs #16882
        $this->SetProperty("ToNextLevel", $this->http->FindSingleNode("//div[@class='points_to_go']/span[@class='points']/b"));
        // You've earned 31 badges
        $this->SetProperty("BadgesEarned", $this->http->FindPreg("#You've earned <span>(\d+)</span> badges#"));

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->currentUrl() == 'https://www.tripadvisor.com/Articles-l1N6VWYZIZlY-Achievements_overview.html'
        ) {
            $this->SetBalanceNA();
        }

        $this->http->GetURL('https://www.tripadvisor.com/Settings-cp');
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id='firstname']/@value") . ' ' . $this->http->FindSingleNode("//input[@id='lastname']/@value")));

        $this->http->GetURL('https://www.tripadvisor.com/Profile');
        // Since
        $this->SetProperty("Since", $this->http->FindSingleNode("//span[contains(text(),'Joined in ')]", null, true, "/Joined in\s*([^<]+)/"));
        // Name
        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[contains(@class, "ui_social_avatar")]/following-sibling::span[1]//h1/span')));
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL("https://www.tripadvisor.com/Bookings");

        // parse all pages with itineraries
        $past = "";

        if ($this->ParsePastIts) {
            $past = " or contains(@class, 'past')";
        }
        $reservations = $this->http->FindNodes("//a[contains(.,'View Details')]/@href");
        $this->logger->debug("Total " . count($reservations) . " reservations were found");

        if ($this->http->FindSingleNode("//div[contains(text(), 'No bookings yet—let') and contains(text(), 's change that')]")) {
            return $this->noItinerariesArr();
        }

        /*if (!$this->ParsePastIts
            && $this->http->FindPreg('/<div class=\"bookingsHeader\">\s*Upcoming Bookings\s*\(0\)\s*<\/div>\s*<table class=\"allBookings future\">\s*<\/table>/")
        ) {
            return $this->noItinerariesArr();
        }*/

        foreach ($reservations as $url) {
            // AttractionBookingDetails-a1006351103-a_reservationId.
            $bookingId = $this->http->FindPreg('/AttractionBookingDetails-a(\d+)-a_reservationId/', false, $url);

            if (!$bookingId) {
                return [];
            }
            $this->http->GetURL("https://www.tripadvisor.com/nova/rest/booking/{$bookingId}");
            $response = $this->http->JsonLog(null, 2);

            if (empty($response->tourGrade->title)) {
                return [];
            }
            $itType = '';

            if (mb_strlen($response->tourGrade->title) > 5) {
                $searchText = $response->tourGrade->title;
            } else {
                $searchText = $response->product->name;
            }

            if (stristr($response->tourGrade->title, 'Show')) {
                $itType = 'EventShow';
            } else {
                $itType = 'Event';
            }
            //$this->logger->debug("Itinerary type: {$itType}");

            switch ($itType) {
                case 'Event':
                    $this->parseEvent($response, Event::TYPE_EVENT);

                    break;

                case 'EventShow':
                    $this->parseEvent($response, Event::TYPE_SHOW);

                    break;

                default:
                    $this->sendNotification("New itinerary type // MI");

                    break;
            }// switch (strtolower($itType))
        }// foreach ($reservations as $reservation)

//        $this->http->Log("Parse Cancelled Reservations...");
//        $result = array_merge($result, $this->ParseCancelledReservations());

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//span[@id = 'SIGNOUT_LINK'] | //a[contains(@href, 'SignOut') and not(contains(@href, 'RegistrationController'))] | //a[@aria-label=\"Profile\"]")) {
            return true;
        }

        return false;
    }

    private function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        /*
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "enterprise",
            "action"    => "LOGIN",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
        */

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "LOGIN",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // An error occurred while processing your request.
        if ($this->http->FindPreg("/An error occurred while processing your request\./")
            && $this->http->Response['code'] == 504) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // This service is temporarily unavailable.
        if ($msg =
                $this->http->FindSingleNode('//div[@id = "regMessage" and contains(text(), "This service is temporarily unavailable.")]')
                ?? $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, the server encountered an internal error or misconfiguration and was unable to complete your request.")]')
        ) {
            throw new CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseEvent($data, $type)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Parsing Event #{$data->itineraryId}", ['Header' => 3]);

        $e = $this->itinerariesMaster->add()->event();
        $e->place()->type($type);
        $e->ota()->confirmation($data->itemId, 'Confirmation #');
        $e->general()->confirmation($data->itineraryId, 'Booking Reference #');
        $e->general()->status(beautifulName($data->bookingState->status));
        $e->place()->name($data->product->name);
        $address = $data->departurePoint->address ?? $data->departurePoint->name ??
            $data->departurePoints[0]->address ?? null;

        if ($address) {
            $e->place()->address(strip_tags($address));
        }
        $e->place()->phone($data->customerServiceDetails->phoneNumber);

        $e->booked()->start2($data->travelDate->dateIso);
        $e->booked()->noEnd();
        $e->booked()->guests($this->http->FindPreg('/^(\d+) Adult/', false, $data->travellerMixInfo->travelerMix));

        // $215.86 USD
        if (preg_match('/\.([\d.,\s])\s*([A-Z]{3})/', $data->price, $m)) {
            $e->price()->total($m[1]);
            $e->price()->currency($m[2]);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($e->toArray(), true), ['pre' => true]);
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $key = rand(0, 3);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
            //$selenium->disableImages();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.tripadvisor.com/RegistrationController?flow=core_combined&pid=39778&returnTo=%2FBookings&fullscreen=true&requireSecure=true");
                $this->solveDatadomeCaptcha($selenium);
                $selenium->http->GetURL("https://www.tripadvisor.com/RegistrationController?flow=core_combined&pid=39778&returnTo=%2FBookings&fullscreen=true&requireSecure=true");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
                sleep(2);
                $this->savePageToLogs($selenium);
            }

            $accept = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 5);

            if ($accept) {
                $selenium->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
                $this->savePageToLogs($selenium);
//                $accept->click();
            }

            $contEmail = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Continue with email")]'), 5);
            $this->savePageToLogs($selenium);

            if (!$contEmail) {
                if ($this->http->FindSingleNode('//span[contains(text(), "This site can") and contains(text(), "t be reached")]')) {
                    $retry = true;
                }

                return false;
            }

            $contEmail->click();
            sleep(1);
            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@onclick, "FORM_SWITCH_SIGN_IN")] | //button[contains(., "Sign in") and contains(., "using your Tripadvisor account")]'), 1);
            $this->savePageToLogs($selenium);

            if ($signIn) {
                $signIn->click();
            }

            sleep(1);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "regSignIn.email" or @id = "modernRegSignIn.email"]'), 1);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "regSignIn.password" or @id = "modernRegSignIn.password"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign in")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$btn) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            sleep(2);

            try {
                $btn->click();
            } catch (Exception $e) {
                $selenium->driver->executeScript('document.querySelector("button[onclick*=\'regSignIn_submit_button_click\']").click()');
            }
            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/RegistrationController/g.exec(url)) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');
//            $selenium->driver->executeScript('
//                const constantMock = window.fetch;
//                window.fetch = function() {
//                    console.log(arguments);
//                    return new Promise((resolve, reject) => {
//                        constantMock.apply(this, arguments)
//                        .then((response) => {
//                            if (response.url.indexOf("RegistrationController") > -1) {
//                                response
//                                .clone()
//                                .json()
//                                .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
//                        }
//                            resolve(response);
//                        })
//                    .catch((error) => {
//                            reject(response);
//                        })
//                    });
//                }
//            ');
            $selenium->waitFor(function () use ($selenium) {
                // | //div[@id = "regErrors"] - todo:
                return $selenium->waitForElement(WebDriverBy::xpath("
                    //div[contains(@class,'antigate_solver recaptcha solved')]/a[contains(text(),'Solved')]
                    | //h1[contains(text(), 'Bookings')]
                    | //a[contains(text(), 'Bookings')]
                "), 0);
            }, 40);
            sleep(5);
            $this->savePageToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | WebDriverCurlException
            | TimeOutException
            | NoSuchWindowException
            | NoSuchDriverException
            $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return $key;
    }

    private function solveDatadomeCaptcha(TAccountCheckerTripadvisor $selenium): bool
    {
        $this->logger->notice(__METHOD__);
        $captchaFrame = $selenium->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha") and @width="100%" and @height="100%"]'), 5);

        if (!$captchaFrame) {
            $this->logger->info('captcha not found');
            $this->savePageToLogs($selenium);

            return true;
        }
        $selenium->driver->switchTo()->frame($captchaFrame);
        $slider = $selenium->waitForElement(WebDriverBy::cssSelector('.slider'), 5);
        $this->savePageToLogs($selenium);

        if (!$slider) {
            $this->logger->error('captcha not found');
            $selenium->driver->switchTo()->defaultContent();
            $this->savePageToLogs($selenium);

            return false;
        }

        // loading images to Imagick
        [$puzzleEncoded, $imgEncoded] = $selenium->driver->executeScript('
            const baseImageCanvas = document.querySelector("#captcha__puzzle > canvas:first-child");
            const puzzleCanvas = document.querySelector("#captcha__puzzle > canvas:nth-child(2)");
            if (!baseImageCanvas || !puzzleCanvas) return [false, false];
            return [puzzleCanvas.toDataURL(), baseImageCanvas.toDataURL()];
        ');

        if (!$puzzleEncoded || !$imgEncoded) {
            $this->logger->error('captcha image not found');

            return false;
        }

        if (!extension_loaded('imagick')) {
            $this->DebugInfo = "imagick not loaded";
            $this->logger->error("imagick not loaded");

            return false;
        }

        // getting puzzle size and initial location on image
        $puzzle = new Imagick();
        $puzzle->setBackgroundColor(new ImagickPixel('transparent'));
        $puzzle->readImageBlob(base64_decode(substr($puzzleEncoded, 22))); // trimming "data:image/png;base64," part
        $puzzle->trimImage(0);
        $puzzleInitialLocationAndSize = $puzzle->getImagePage();
        $puzzle->clear();
        $puzzle->destroy();

        // saving captcha image
        $img = new Imagick();
        $img->setBackgroundColor(new ImagickPixel('transparent'));
        $img->readImageBlob(base64_decode(substr($imgEncoded, 22)));
        $path = '/tmp/seleniumPageScreenshot-' . getmypid() . '-' . microtime(true) . '.jpeg';
        $img->writeImage($path);
        $img->clear();
        $img->destroy();

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 60;
        $params = [
            'coordinatescaptcha' => '1',
            'textinstructions'   => 'Click on the most left edge of the dark puzzle / Кликните по самому левому краю темного паззла',
        ];
        $targetCoordsText = '';

        try {
            $targetCoordsText = $this->recognizer->recognizeFile($path, $params);
        } catch (CaptchaException $e) {
            $this->logger->error("CaptchaException: {$e->getMessage()}");

            if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                $this->captchaReporting($this->recognizer, false); // it is solvable

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            if ($e->getMessage() === 'timelimit (60) hit') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }
        } finally {
            unlink($path);
        }

        $targetCoords = $this->parseCoordinates($targetCoordsText);
        $targetCoords = end($targetCoords);

        if (!is_numeric($targetCoords['x'] ?? null)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $m = new MouseMover($selenium->driver);
        $m->duration = rand(300, 1000);
        $m->steps = rand(200, 300);
        $distance = $targetCoords['x'] /* - $puzzleInitialLocationAndSize['x'] */;
        $stepLength = floor($distance / $m->steps);
        $pauseBetweenSteps = $m->duration / $m->steps;
        $m->enableCursor();
        $this->savePageToLogs($selenium);
//        $m->moveToElement($slider);
        $m = $selenium->driver->getMouse()->mouseDown($slider->getCoordinates());
        $distanceTraveled = 0;

        for ($stepsLeft = 50; $stepsLeft > 0; $stepsLeft--) {
            $m->mouseMove(null, $stepLength, 0);
            $distanceTraveled += $stepLength;
            usleep(round($pauseBetweenSteps * rand(10, 50) / 100));
        }
        $lastStep = round($distance - $distanceTraveled);

        if ($lastStep > 0) {
            $m->mouseMove(null, $lastStep, 0);
        }
        $this->savePageToLogs($selenium);
        $m->mouseUp();

        $this->logger->debug('switch to defaultContent');
        $selenium->driver->switchTo()->defaultContent();
        $this->savePageToLogs($selenium);
        $this->logger->debug('waiting for page loading captcha result');

        return true;
    }
}
