<?php

//  ProviderID: 1048

class TAccountCheckerMalmo extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.flygbra.se/mina-sidor/';

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.flygbra.se';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('x-umb-culture', 'en-US');
//        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
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
        $this->http->GetURL('https://www.flygbra.se/en/');

        if (!$this->http->FindPreg("/\/umbraco\/Surface\/SessionApi\/Login/")) {
            return $this->checkErrors();
        }
        /*
        $this->http->FormURL = 'https://www.flygbra.se/umbraco/Surface/SessionApi/Login';
        $this->http->SetInputValue('userName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('__RequestVerificationToken', $this->http->FindPreg("/__RequestVerificationToken&quot;,&quot;value&quot;:&quot;([^&]+)/"));
        $this->http->SetInputValue('memberStartPage', "/mina-sidor/");
        $this->http->SetInputValue('keepMeSignedIn', "true");
        */

        $data = '-----------------------------144539349023203693022869786361
Content-Disposition: form-data; name="__RequestVerificationToken"

' . $this->http->FindPreg("/__RequestVerificationToken&quot;,&quot;value&quot;:&quot;([^&]+)/") . '
-----------------------------144539349023203693022869786361
Content-Disposition: form-data; name="userName"

' . $this->AccountFields['Login'] . '
-----------------------------144539349023203693022869786361
Content-Disposition: form-data; name="password"

' . $this->AccountFields['Pass'] . '
-----------------------------144539349023203693022869786361
Content-Disposition: form-data; name="keepMeSignedIn"

true
-----------------------------144539349023203693022869786361
Content-Disposition: form-data; name="memberStartPage"

/mina-sidor/
-----------------------------144539349023203693022869786361--
';
        $headers = [
            "Accept"          => "*/*",
            "Content-Type"    => "multipart/form-data; boundary=---------------------------144539349023203693022869786361",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.flygbra.se/umbraco/Surface/SessionApi/Login", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        /*
        $this->http->RetryCount = 0;
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        */
        $response = $this->http->JsonLog();

        if (isset($response->userRedirectUrl) && $response->userRedirectUrl == '/mina-sidor/') {
            return true;
        }
        // Catch errors
        if (isset($response->message)) {
            $this->logger->error($response->message);
            // Invalid username or password
            if (in_array($response->message, ['Invalid username or password', 'Invalid member number or password'])) {
                throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
            }
            // Something went wrong. If this error occure again pleas contact BRA customer support.
            if ($response->message == 'Something went wrong. If this error occure again pleas contact BRA customer support.') {
                throw new CheckException($response->message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (isset($response->Message))
        // Teknisk fel, kontakta FlygBRA kundservice.
        if ($this->http->FindPreg("/The page cannot be displayed because an internal server error has occurred\./")) {
            throw new CheckException("Teknisk fel, kontakta FlygBRA kundservice.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg('/firstName&quot;:&quot;([^&]+)/i') . " " . $this->http->FindPreg('/surName&quot;:&quot;([^&]+)/i')));
        // MemberNumber
        $this->SetProperty('MemberNumber', $this->http->FindPreg('/frequentFlyerId&quot;:&quot;([^&]+)/i'));
        // Medlemsnivå
        $this->SetProperty('PointLevel', $this->http->FindPreg('/bonusLevel&quot;:&quot;([^&]+)/i'));
        // Balance - Bra bonus
        $this->SetBalance($this->http->FindPreg('/awardPoints&quot;:([^,]+)/i'));

        if ($this->Balance <= 0) {
            return;
        }
        $this->logger->info("Expiration date", ['Header' => 3]);
        // Expiration Date refs # 18094
        $expiringPoints = $this->http->FindPreg('/,&quot;expiringPoints&quot;:(\[.*?\]),&quot;/');
        $expiringPoints = $this->http->JsonLog(htmlspecialchars_decode($expiringPoints));
        $minDate = strtotime('01/01/3018');
//        $this->logger->info(var_export($expiringPoints, true), ['pre' => true]);
        foreach ($expiringPoints as $item) {
            if (isset($item->expireDate, $item->points) && $item->points > 0) {
                $this->logger->debug("ExpirationDate: {$item->expireDate}");
                $item->expireDate = $this->http->FindPreg('/^(.+?)T\d+/', false, $item->expireDate);
                $expDate = strtotime($item->expireDate, false);

                if ($expDate && $expDate < $minDate) {
                    $minDate = $expDate;
                    // Expiration Date
                    $this->SetExpirationDate($expDate);
                    // Expiring balance
                    $this->SetProperty('ExpiringBalance', $item->points);
                }
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg('/frequentFlyerId&quot;:&quot;([^&]+)/i')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//p[contains(text(), "Internal Server Error")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

//        $this->http->GetURL('https://www.flygbra.se/');
        if ($this->http->FindSingleNode('
                //p[contains(normalize-space(text()), "Nu lyfter vi snart igen!") or contains(text(), "Braathens Regional Airlines. We are almost ready for take-off!")]
                | //h1[contains(text(), "Welcome to BRA,")]/following-sibling::p[contains(text(), "We will fly again as soon as the Corona restrictions ease!")]
            ')
        ) {
            throw new CheckException("Welcome to Braathens Regional Airlines (BRA)! We have temporary paused all our traffic but we hope to see you again this autumn!", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "Vi upplever just nu störningar på vår hemsida och är ledsna att du drabbas av detta")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
