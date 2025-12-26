<?php

class TAccountCheckerRedspottedhanky extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://tickets.redspottedhanky.com/rsh/en/account/login.aspx");

        if (!$this->http->ParseForm("aspnetForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$mainContentPlaceHolder$loginControl$EmailAddress', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$mainContentPlaceHolder$loginControl$Password', $this->AccountFields['Pass']);
        $this->http->Form['__EVENTTARGET'] = 'ctl00$mainContentPlaceHolder$loginControl$SignInButton';
        $this->http->Form['ctl00$mainContentPlaceHolder$loginControl$fwdu'] = 'https://loyalty.redspottedhanky.com/default.aspx';
        $this->http->Form['ctl00$mainContentPlaceHolder$loginControl$ssoatreq'] = '1';
        $this->http->Form['JavaScriptEnabled'] = 'true';

        return true;
    }

    public function checkErrors()
    {
        //# The Loyalty Portal is experiencing technical difficulties
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are working to get the portal back up and running')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologise for the difficulties you're experiencing.
        if ($message = $this->http->FindSingleNode('//b[contains(text(), "We apologise for the difficulties you\'re experiencing.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry, but there has been an error with the application.
        if ($message = $this->http->FindSingleNode('//b[contains(text(), "We\'re sorry, but there has been an error with the application.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently carrying out essential system maintenance.
        if ($message = $this->http->FindSingleNode('//b[contains(text(), "We are currently carrying out essential system maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
                // HTTP Error 404. The requested resource is not found.
            || $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 404. The requested resource is not found.")]')
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Service Unavailable
        if ($this->http->Response['code'] == 503 && $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }
        // SSL error
        if ($this->http->Response['code'] === 0
                && $this->http->FindPreg('/OpenSSL SSL_read/', false, $this->http->Response['errorMessage'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        //# Access is allowed
        $balance = $this->http->FindSingleNode("//span[contains(@class,'userPointsBox-points')]/text()[1]");

        if (isset($balance)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[@class = 'ulc_messages Label']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        //# Please wait whilst we activate your loyalty account
        if ($this->http->currentUrl() == 'https://loyalty.redspottedhanky.com/UserMessage.aspx?p=1'
            && ($message = $this->http->FindSingleNode("//h1[contains(text(), 'If you have just registered with redspottedhanky please wait whilst we activate your loyalty account')]"))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
//        $this->http->GetURL("https://loyalty.redspottedhanky.com/default.aspx");
        // "Sorry we were unable to verify your account details..."
        if ($message = $this->http->FindSingleNode("//span[@id = 'lblInfo']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//span[contains(@class,'userPointsBox-points')]/text()[1]"));
        // Expiration Date  // refs #8705
        $this->SetExpirationDate(strtotime("31 dec"));

        $this->http->GetURL("https://tickets.redspottedhanky.com/rsh/en/account/userdetails");
        $this->SetProperty("Name", $this->http->FindSingleNode("//input[contains(@name,'FirstName')]/@value") . " " . $this->http->FindSingleNode("//input[contains(@name,'Surname')]/@value"));

        //# E-vouchers     // refs #6215
        $this->http->GetURL("https://tickets.redspottedhanky.com/rsh/en/account/UserEVouchers");
        $nodes = $this->http->XPath->query("//table[contains(@id, 'VoucherList')]//tr");
        $this->http->Log("Total E-vouchers found: " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $code = $this->http->FindSingleNode('td[1]', $nodes->item($i));
                $exp = $this->http->FindSingleNode('td[2]', $nodes->item($i), true, '/Expiry\s*([^<]+)/ims');

                if (empty($exp)) {
                    $exp = $this->http->FindSingleNode('td[3]', $nodes->item($i), true, '/until\s*([^<]+)/ims');
                }
                $balance = $this->http->FindSingleNode('td[3]', $nodes->item($i), true, "/[\d\.\,]+/ims");

                if ($balance > 0) {
                    $subAccounts[] = [
                        'Code'           => 'redspottedhankyVoucher' . $i,
                        'DisplayName'    => "E-voucher # " . $code,
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($exp),
                    ];
                }
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }// if ($nodes->length > 0)
        elseif ($message = $this->http->FindSingleNode("//div[contains(text(), 'There are currently no loyalty points available in your account')]")) {
            $this->http->Log(">>> " . $message);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://tickets.redspottedhanky.com/rsh/en/account/login.aspx';
        $arg['SuccessURL'] = 'https://loyalty.redspottedhanky.com/default.aspx';

        return $arg;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'redspottedhankyVoucher')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
        } else {
            return parent::FormatBalance($fields, $properties);
        }
    }
}
