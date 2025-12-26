<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerVolotea extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://booking.volotea.com/AjaxCustomerSessionCheck.aspx', [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->loggedin) && $response->loggedin === true) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://booking.volotea.com/Login.aspx');
//        if (!$this->http->ParseForm("loginFormPage")) {
//            if ($this->http->FindSingleNode("//script[contains(@src,'/_Incapsula_Resource')]/@src"))
//                $this->selenium();
//            else
//                return $this->checkErrors();
//        }
        $this->selenium();

        $query = http_build_query([
            'email'    => $this->AccountFields["Login"],
            'password' => $this->AccountFields["Pass"],
            'remember' => 'true',
        ]);
        $this->http->GetURL('https://booking.volotea.com/AjaxCustomerLogin.aspx?' . $query);

        return true;
    }

    public function checkErrors()
    {
//        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'We are currently undergoing system maintenance')]"))
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // The email or password that you entered is incorrect
        if ($message = $this->http->FindPreg('/"errormessage":"(The email or password that you entered is incorrect)"/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // login successful
        if (isset($response->loggedin) && $response->loggedin === true) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, false);
        // Name
        $this->SetProperty('Name', beautifulName("{$response->user->firstname} {$response->user->lastname}"));

        $this->http->GetURL('https://booking.volotea.com/CustomerDashboard.aspx');
        // VOLOTEA CREDIT:
        $this->SetProperty('Credit', $this->http->FindSingleNode("//h1[contains(text(),'VOLOTEA CREDIT:')]", null, false, '/:\s*(.+)/'));
        // Type of user
        $this->SetProperty('Status', $this->http->FindSingleNode("//p[contains(text(),'Type of user:')]", null, false, '/:\s*(.+)/'));

        $this->http->GetURL('https://booking.volotea.com/PortalBookings.aspx?culture=en-GB');

        if (!$this->http->FindSingleNode("//td[contains(text(),'No reservations were found matching your search')]")) {
            $this->sendNotification('refs #16966, volotea - reservations found');
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) && !empty($this->Properties['Credit'])) {
                $this->SetBalanceNA();
            }
        }
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->http->FindPreg('#Chrome|Safari|WebKit#ims', false, $this->http->getDefaultHeader("User-Agent"))) {
                if (rand(0, 1) == 1) {
                    $selenium->useGoogleChrome();
                } else {
                    $selenium->useChromium();
                }
            } else {
                $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            }

            $selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            $selenium->http->GetURL('https://booking.volotea.com/Login.aspx');
            //$login = $selenium->waitForElement(WebDriverBy::id('emailLoginForm'), 5);

            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }
}
