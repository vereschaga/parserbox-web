<?php

class TAccountCheckerIprestige extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // https://www.sino-hotels.com/file/media/share/201811_important_notice_faq_pdf.pdf
        throw new CheckException("The member portal closed on 31 October 2018.", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->GetURL('https://www.iprestigerewards.com/en/');

        if (!$this->http->ParseForm('login_form')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.iprestigerewards.com/en/';
        $arg['SuccessURL'] = 'https://www.iprestigerewards.com/en/?op=transaction_summary';

        return $arg;
    }

    public function checkErrors()
    {
        // In order to maintain our superior service quality, we are currently performing scheduled system maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'In order to maintain our superior service quality, we are currently performing scheduled system maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->result) && $response->result == 'success' && !empty($response->redirect)) {
            $response->redirect = stripcslashes($response->redirect);
            $this->http->NormalizeURL($response->redirect);
            $this->http->GetURL($response->redirect);
        }// if (isset($response->result) && $response->result == 'success' && !empty($response->redirect))
        // successful login
        if ($this->http->FindSingleNode('(//a[contains(@href, "logout")]/@href)[1]')) {
            return true;
        }
        // Membership no. or E-mail address and/or password is invalid. Please re-enter.
        if (isset($response->errors->errorStack[0]) && strstr($response->errors->errorStack[0], 'password is invalid')) {
            throw new CheckException($response->errors->errorStack[0], ACCOUNT_INVALID_PASSWORD);
        }
        // Set a password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Thank you for visiting. For security reason, our system has been upgraded, password reset is recommended')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.iprestigerewards.com/en/?op=profile_index");
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode("//div[@class = 'member-name']"));
        // Your Membership No.
        $this->SetProperty('MembershipNumber', $this->http->FindSingleNode('//span[@class = "num"]'));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//span[@class = "member-status"]'));
        // Year End
        $this->SetProperty('YearEnd', $this->http->FindSingleNode('//span[@class = "date"]'));

        $this->http->GetURL("https://www.iprestigerewards.com/en/?op=transaction_summary");
        // Needed to Next Level
        $this->SetProperty('NeededToNextLevel', $this->http->FindPreg("/You need to spend\s*<b>([^<]+)<\/b>\s*more to upgrade to/ims"));
        // Next Elite Level
        $this->SetProperty('NextEliteLevel', $this->http->FindPreg("/more to upgrade to\s*<b>([^<]+)/ims"));
        // Calculation period
        $this->SetProperty('CalculationPeriod', $this->http->FindSingleNode("//td[contains(text(), 'Calculation Period')]/following-sibling::td"));
        // Rooms
        $this->SetProperty('Rooms', $this->http->FindSingleNode("//td[contains(text(), 'Rooms')]/following-sibling::td"));
        // Food & Beverage
        $this->SetProperty('FoodAndBeverage', $this->http->FindSingleNode("//td[contains(text(), 'Food & Beverage')]/following-sibling::td"));
        // Others
        $this->SetProperty('Others', $this->http->FindSingleNode("//td[contains(text(), 'Others')]/following-sibling::td"));
        // Bonus
        $this->SetProperty('Bonus', $this->http->FindSingleNode("//td[contains(text(), 'Bonus')]/following-sibling::td"));
        // Total - Balance
        $this->SetBalance($this->http->FindSingleNode("//td[b[contains(text(), 'Total')]]/following-sibling::td", null, true, '/^USD(.*)$/'));
    }
}
