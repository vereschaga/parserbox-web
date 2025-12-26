<?php

class TAccountCheckerPestana extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://secure.pestana.com/en/myaccount/points';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://secure.pestana.com/en/myaccount/dashboard');

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["NoCookieURL"] = true;

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "At the moment, we\'re improving the website")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Site Maintenance
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'Maintenance')]/@alt")) {
            throw new CheckException("We are currently updating petco.com & apologize for any inconvenience. Thank you for your patience!", ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if ($this->http->FindPreg("/Server Error in \'\/\' Application\./")
            // An unexpected error has occurred.
            || $this->http->FindPreg("/An unexpected error has occurred\./")
            // Service Unavailable
            || $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(0);

        if (!$this->http->PostForm() && $this->http->Response['code'] != 302) {
            return $this->checkErrors();
        }
        $this->http->setMaxRedirects(5);
        $redirect = $this->http->FindSingleNode('//h2[contains(text(), "Object moved to")]/a/@href');
        $this->logger->debug("Redirect -> '{$redirect}'");

        if (!$redirect) {
            return null;
        }
        $this->http->NormalizeURL($redirect);
        $this->http->GetURL($redirect);
        $this->http->RetryCount = 2;

        if (
            !in_array($redirect, [
                'https://www.pestana.com/en/myaccount/login?returnUrl=https%3a%2f%2fsecure.pestana.com%2fen%2fmyaccount%2fdashboard&r=401',
                'https://secure.pestana.com/en/myaccount/dashboard?r=401',
            ])
            && in_array($this->http->currentUrl(), [
                'https://www.pestana.com/en/notfound',
                'https://www.pestana.com/en/home/notfound?aspxerrorpath=/en/myaccount/login',
            ])
        ) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        } elseif (
            in_array($redirect, [
                'https://secure.pestana.com/en/myaccount/login?returnUrl=https%3a%2f%2fsecure.pestana.com%2fen%2fmyaccount%2fdashboard&r=401',
                'https://www.pestana.com/en/myaccount/login?returnUrl=https%3a%2f%2fsecure.pestana.com%2fen%2fmyaccount%2fdashboard&r=401',
                'https://secure.pestana.com/en/myaccount/dashboard?r=401',
            ])
        ) {
            /*
            $this->http->GetURL("https://secure.pestana.com/en/MyAccount/Login?r=401");
            */
            // Invalid data. Check your access data or try to recover your account password.
            if ($message = $this->http->FindSingleNode('//div[@class = "modal-login-container"]//p[contains(text(), "Invalid data. Check your access data or try to recover your account password.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        //# Access successful
        if ($this->loginSuccessful()) {
            return true;
        }

        // broken account, AccountID: 1485619
        if (in_array($this->AccountFields['Login'], [
            '700926488',
            '702663282',
            'ferreira@fgv.br',
            '703602280',
            'beatlekid@hotmail.com',
        ])
            && $this->http->ParseForm('login-form')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() !== self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class='name']/h2")));
        // Balance - Total Points
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'user-balance-points']", null, true, "/([^<]+)\s+Point/"));
        // Card Number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//div[@class = 'number']", null, true, "/NUMBER\s*([^<]+)/ims"));
        // Status
        $this->SetProperty("Status", beautifulName($this->http->FindPreg('#<p class="small"><b>(.+)</b> Member</p>#')));
        // Expiring Balance
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("//div[@class = 'user-balance-expiring']/b[1]"));
        // Expiration date
        if ($exp = $this->http->FindPreg('#<span>[\w ]+</span>\s+Member</small>\s+Valid until (\d{1,2}/\d{4})\s+</div>#')) {
            $exp = str_replace('/', '/01/', $exp); // adding days, so "12/2024" will be "1 Dec 2024"
            $this->logger->debug("Exp date: {$exp}");

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate($exp);
            }
        }// if ($exp = $this->http->FindSingleNode("//div[@class = 'user-balance-expiring']/b[2]"))
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logoffsso')]/@href")) {
            return true;
        }

        return false;
    }
}
