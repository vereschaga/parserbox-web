<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerIsraelSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $bookingClasses;
    private $route;
    private $stepItinerary = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');

        $this->setProxyGoProxies();

        $this->disableImages();

        $this->useChromePuppeteer();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $login = preg_replace('/\D/', '', $this->AccountFields['Login']);

        if (empty($login)) {
            throw new CheckException("Member And/Or Password Incorrect", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->LogHeaders = true;

        try {
            $this->http->GetURL('https://www.elal-matmid.com/en/Login/Pages/Login.aspx');
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                throw new CheckRetryNeededException(3, 10);
            }
        }
        $loginInput = $this->waitForElement(WebDriverBy::xpath("//input[contains(@name, 'MembertxtID')]"), 30);
        $this->saveResponse();

        if (empty($loginInput)) {
            $this->checkErrors();

            throw new CheckRetryNeededException();
        }
        $loginInput->sendKeys($login);

        $passInput = $this->waitForElement(WebDriverBy::xpath("//input[contains(@name, 'PasswordtxtID')]"), 5);

        if (empty($loginInput)) {
            return $this->checkErrors();
        }
        $passInput->sendKeys($this->AccountFields['Pass']);

        $signIn = $this->waitForElement(WebDriverBy::xpath("//input[contains(@name, 'lnkSubmit')]"), 5);

        if (empty($signIn)) {
            return $this->checkErrors();
        }
        $signIn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // EL AL's website is being upgraded, and is not available at the moment. Please try again later.
        if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "EL AL\'s website is being upgraded,")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $sleep = 30;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $this->saveResponse();

            $providerError = ($this->waitForElement(WebDriverBy::xpath("//div[contains(normalize-space(text()), 'Log off was not carried out.')]"), 0)
                && $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Login/Pages/LogOff.aspx')]"), 0)) ? true : false;

            // success - account shown
            if (!$providerError && $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'LogOff')] | //button[contains(text(), 'Log Off') or contains(text(), 'log off') or contains(text(), 'Log out')]"), 0, true)) {
                return true;
            }

            if (!$providerError && $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'LogOff')] | //button[contains(text(), 'Log Off') or contains(text(), 'log off') or contains(text(), 'Log out')]"), 0, false)) {
                return true;
            }
            // invalid credentials
            if ($error = $this->waitForElement(WebDriverBy::xpath("
                    //label[contains(text(), 'Member Number Is Invalid')]
                    | //label[contains(text(), 'User or password are invalid!')]
                    | //span[contains(text(), 'Member And/Or Password Incorrect')]
                    | //span[contains(text(), 'Latin characters only')]
                    | //span[contains(text(), 'Member Number Is Invalid')]
                    | //span[contains(text(), 'At least 6 characters')]
                    | //span[contains(text(), 'English characters, Numbers and valid characters only')]
                    | //span[contains(text(), 'The data you entered do not match the details in our database')]
                "), 0)
            ) {
                throw new CheckException($error->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Error occurred, please try again later.
            if ($error = $this->waitForElement(WebDriverBy::xpath("
                    //label[contains(text(), 'Error occurred, please try again later.')]
                    | //span[contains(text(), 'Error occurred, please try again later.')]
                "), 0)
            ) {
                throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            // Dear Customer, your account has been blocked
            if ($error = $this->waitForElement(WebDriverBy::xpath("
                    //strong[contains(text(), 'Dear Customer, your account has been blocked')]
                    | //h2[contains(text(), 'Your account has been blocked')]
                "), 0)
            ) {
                throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
            }

            // Change Password
            // Enter the following details to create a new password
            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Enter the following details to create a new password')] | //b[contains(text(), 'In order to protect your account privacy, please replace your login password')] | //h2[text()='Change Password']"), 0, true)) {
                throw new CheckException('EL AL Israel Airlines website is asking you to change your password, until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);
            }

            if ($providerError) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// while ((time() - $startTime) < $sleep)

        return $this->checkErrors();
    }

    public function Parse()
    {
        try {
            $this->http->GetURL("https://www.elal.com/eng/frequentflyer/myffp/myaccount");
        } catch (ScriptTimeoutException|TimeOutException $e) {
            $this->logger->error("TimeoutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        $this->waitForElement(WebDriverBy::xpath("//em[@class = 'balance']"), 10);
        $this->saveResponse();
        // Status
        $this->SetProperty('CurrentClubStatus', $this->http->FindSingleNode("//em[@class = 'status']"));
        // Status valid until
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode("//span[contains(text(), 'Status valid until')]/following-sibling::span"));
        // Points In Your Account
        $this->SetBalance($this->http->FindSingleNode("//em[@class = 'balance']"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[contains(@class, 'inner-container')]//span[@class='name']")));
        // Member Number
        $this->SetProperty('MemberNo', $this->http->FindSingleNode("//div[contains(@class, 'inner-container')]//span[contains(text(), 'Member Number')]", null, true, "/:\s*(\d+)/i"));
        // Diamonds for next Tier
        $this->SetProperty('DiamondsForNextTier', $this->http->FindSingleNode("(//p[contains(text(), 'To upgrade to')])[1]/preceding-sibling::div//div/span/b"));
        // Flight segments for next Tier
        $this->SetProperty('FlightForNextTier', $this->http->FindSingleNode("(//p[contains(text(), 'To upgrade to')])[2]/preceding-sibling::div[2]//div/span/b"));
        // Diamonds to maintain Tier
        $this->SetProperty('DiamondsToMaintainTier', $this->http->FindSingleNode("(//p[contains(text(), 'To maintain')])[1]/preceding-sibling::div//div/span/b"));
        // Flight segments to maintain Tier
        $this->SetProperty('FlightToMaintainTier', $this->http->FindSingleNode("(//p[contains(text(), 'To maintain')])[2]/preceding-sibling::div[2]//div/span/b"));

        // Expiration date  // refs #6806
        /*
        if ($details = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'pointsAmount_time')]//a[contains(text(), 'Details')]"), 0)) {
            $details->click();
            $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Expired Points')]"), 7);
            sleep(2);
            $this->saveResponse();

            $expNodes = $this->http->XPath->query("//div[contains(text(), 'Point That Will Expire')]/following-sibling::div[1]//div[contains(@class, 'matmidPop_pointsTbody')]//tr[not(td[3]/span[contains(text(), '{RemainPoints}')])]");
            $this->logger->notice("Total {$expNodes->length} exp nodes were found");

            foreach ($expNodes as $node) {
                $date = strtotime($this->ModifyDateFormat($this->http->FindSingleNode("./td[4]/span", $node)));

                if (isset($date) && (!isset($exp) || $date < $exp)) {
                    $exp = $date;
                    // Expiring balance
                    $this->SetProperty('ExpiringBalance', $this->http->FindSingleNode("./td[3]/span", $node));
                    // Expiration date
                    $this->SetExpirationDate($exp);
                }
            }
        }// if ($details = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Details')]"), 0))
        else {
            $this->logger->notice("Exp date not found");
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->retries();
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        */
    }

    public function ParseItineraries()
    {
        $token = str_replace('"', '', $this->driver->executeScript("return sessionStorage.getItem('tokenElal')"));

        $futureFlights = $this->http->JsonLog($this->getApi('https://www.elal.com/api/MyFlights/futureFlights/lang/eng', $token))->futureFlights;
        $futureFlightsCounter = $futureFlights->futureFlightsCounter ?? 0;
        $this->logger->debug("Total {$futureFlightsCounter} futureFlights were found");
        $itineraries = [];
        $routes = $futureFlights->routes ?? [];
        $this->logger->debug("Total " . count($routes) . " routes were found");

        foreach ($routes as $route) {
            $itineraries[$route->pnr] = $route->departureDate;
        }

        $this->logger->debug(sprintf('Total %s itineraries were found', count($itineraries)));

        if (count($itineraries) > 1) {
            $this->sendNotification('check it // MI');
        }

        foreach ($itineraries as $pnr => $departureDate) {
            $this->logger->debug("========== Start #$pnr ==========");
            $url = $this->getApi("https://www.elal.com/api/MyFlights/manageOrderLink/lang/eng/pnr/{$pnr}", $token);
            $this->http->GetURL(str_replace('"', '', $url));
            sleep(random_int(1, 2));
            $enc = $this->http->FindPreg('/\?enc=(.+?)&/', false, $url);

            if ($enc) {
                $sessionId = str_replace('"', '', $this->driver->executeScript("return sessionStorage.getItem('sessionId')"));
                $data = $this->getApi("https://booking.elal.com/bfm/service/extly/retrievePnr/secured/manageMyBooking?enc=$enc", $sessionId);
                $this->parseItinerary($this->http->JsonLog($data));
            }

            try {
                $this->http->GetURL("https://www.elal.com/eng/frequentflyer/myffp/myaccount");
            } catch (ScriptTimeoutException|TimeOutException $e) {
                $this->logger->error("TimeoutException exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }
            $token = str_replace('"', '', $this->driver->executeScript("return sessionStorage.getItem('tokenElal')"));

        }

        return [];
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();
        $confNo = $data->data->bookingSummary->booking->reference;
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $confNo), ['Header' => 3]);
        $f->general()
            ->confirmation($confNo, "Booking code", true);

        foreach ($data->data->bookingSummary->booking->passengers as $passenger) {
            $f->general()->traveller(beautifulName("{$passenger->firstName} {$passenger->lastName}"));
            //$f->program()->account($passenger->matMidNumber, false);
            if (!empty($passenger->matMidNumber->state)) {
                $this->sendNotification('accountNumber  // MI');
            }
        }

        foreach ($data->data->bookingSummary->booking->tripNew ?? [] as $trip) {
            foreach ($trip->bound->segments ?? [] as $seg) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($seg->airline->name)
                    ->number($seg->flightNumber);

                $s->departure()
                    ->code($seg->departureAirport->code)
                    ->name($seg->departureAirport->label)
                    ->terminal($seg->departureTerminal->name, true)
                    ->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false, $seg->departureDate));

                $s->arrival()
                    ->code($seg->arrivalAirport->code)
                    ->name($seg->arrivalAirport->label)
                    ->terminal($seg->arrivalTerminal->name, true)
                    ->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false, $seg->arrivalDate));

                if (isset($seg->mealTypes)) {
                    $s->extra()
                        ->meals($seg->mealTypes);
                }
                $s->extra()
                    ->aircraft($seg->aircraftType);
                //->stops(array_sum($seg->stops));
                if (array_sum($seg->stops) > 0) {
                    $this->sendNotification('stops ' . array_sum($seg->stops) . ' // MI');
                }

                foreach ($seg->fares as $fare) {
                    $s->extra()->bookingCode($fare->rbd);
                    $s->extra()->cabin($fare->cabinTypeName);
                }

                $hours = floor($seg->duration / 60 / 60);
                $minutes = $seg->duration / 60 % 60;
                $s->extra()->duration($hours > 0 ? sprintf('%02dh %02dm', $hours, $minutes) : sprintf('%02dm', $minutes));
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return [];
    }

    private function getApi($url, $token)
    {
        $this->logger->notice(__METHOD__);
        $response = $this->sendApi($url, $token);

        if (empty($response) ||  preg_match('#"message":"Forbidden", "url":#', $response)
            || preg_match('#/elal/403.html"#', $response)) {
            sleep(random_int(1, 3));
            $response = $this->sendApi($url, $token);
        }

        return $response;
    }

    private function sendApi($url, $token)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[URL]: $url");

        try {
//                $headers = "
//                xhr.withCredentials = true;
//                ";
            $headers = "
                xhr.setRequestHeader('Accept', 'application/json, text/plain, */*');
                xhr.setRequestHeader('Adrum', 'isAjax:true');
                xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
                xhr.setRequestHeader('Authorization', 'Bearer $token');
                ";

            $this->driver->executeScript($script = "
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '$url');
                $headers
                xhr.onload = function() {
                    localStorage.setItem('responseText', xhr.responseText);
                }
                xhr.send();     
            ");
            $this->logger->info($script, ['pre' => true]);
            sleep(3);
            $response = $this->driver->executeScript("return localStorage.getItem('responseText')");

            if (empty($response)) {
                sleep(3);
                $response = $this->driver->executeScript("return localStorage.getItem('responseText')");

                if (empty($response)) {
                    sleep(3);
                    $response = $this->driver->executeScript("return localStorage.getItem('responseText')");

                    if (empty($response)) {
                        sleep(3);
                        $response = $this->driver->executeScript("return localStorage.getItem('responseText')");
                    }
                }
            }
            $this->driver->executeScript("localStorage.removeItem('responseText')");
            $this->logger->info("[Form response]: $response", ['pre' => true]);
            //$this->logger->info("[Form response]: $response");
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $response = null;
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        return $response;
    }

    private function retries()
    {
        $this->logger->notice(__METHOD__);
        // retries
        if ($this->http->FindPreg("/(?:The connection to the server was reset while the page was loading.|window\.rbzns = \{fiftyeightkb\:|The proxy server is refusing connections|Current session has been terminated\.|There is no Internet connection)/")
            || !$this->waitForElement(WebDriverBy::xpath("//input[contains(@name, 'MembertxtID')]"), 0)) {
            throw new CheckRetryNeededException(4, 10);
        }
    }

    private function correctSegment($segment, $node)
    {
        $this->logger->notice(__METHOD__);
        $text = "{$segment['DepName']} - {$segment['ArrName']}";
        $ancestor = $this->http->FindSingleNode('./ancestor::ul[1]', $node);

        if ($this->http->FindPreg("/$text/", false, $ancestor)) {
            return true;
        }

        return false;
    }
}
