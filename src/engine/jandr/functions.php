<?php

class TAccountCheckerJandr extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.jr.com/myAccount/myAccountLogin.jsp');
        // parse form
        if (!$this->http->ParseForm('login')) {
            return $this->checkErrors();
        }
        // headers
        $this->http->SetDefaultHeader('Referrer', 'https://www.jr.com/myAccount/myAccountLogin.jsp');
        // fill fields
        $this->http->SetInputValue('LOGIN<>userid', $this->AccountFields['Login']);
        $this->http->SetInputValue('LOGIN<>password', $this->AccountFields['Pass']);
        // fix
        $this->http->MultiValuedForms = true;
        $this->http->Form['login'] = '';

        return true;
    }

    public function checkErrors()
    {
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently updating our site to serve you better.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Error 500--Internal Server Error
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Error 500--Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // success login?
        if ($this->http->FindNodes('//a[contains(@href, "bmForm=logout")]')) {
            return true;
        }
        // failed to login
        $message = $this->http->FindSingleNode('//div[contains(@class, "errorMsg")]/ul/li[1]/span');
        // wrong card num
        if (strpos($message, 'This e-mail address/password combination was not found') !== false
            || strpos($message, 'Valid e-mail address required') !== false
            || strpos($message, 'Password required') !== false) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (strpos($message, 'The maximum number of login attempts for this account has been exceeded') !== false) {
            throw new CheckException("The maximum number of login attempts for this account has been exceeded. In order to access this account you must reset your password", ACCOUNT_LOCKOUT);
        }

        $this->http->Log("Error: {$message}");

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Available Rewards
        $this->SetProperty('AvailableRewards', $this->http->FindSingleNode('//span[@class="loyaltyPoints"]'));
        // Pending Rewards - Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@class="loyaltyPointsX"]', null, true, '/^\$(.*)$/'));
        // Lifetime Accrued
        $this->SetProperty('LifetimeAccrued', $this->http->FindSingleNode('//td[contains(text(), "Lifetime Accrued")]/following-sibling::td[1]'));
        // Lifetime Redeemed
        $this->SetProperty('LifetimeRedeemed', $this->http->FindSingleNode('//td[contains(text(), "Lifetime Redeemed")]/following-sibling::td[1]'));

        // Profile url
        $this->http->GetURL('https://www.jr.com/myAccount/updateProfile.jsp');
        // Name
        $this->SetProperty('Name', beautifulName(
            $this->http->FindSingleNode('//input[@name="USER_ACCOUNT<>firstName"]/@value')
            . ' ' . $this->http->FindSingleNode('//input[@name="USER_ACCOUNT<>lastName"]/@value')));
    }
}
