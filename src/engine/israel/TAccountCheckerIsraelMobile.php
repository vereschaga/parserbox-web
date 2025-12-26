<?php

class TAccountCheckerIsraelMobile extends TAccountCheckerIsrael
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = true;
        $this->http->LogHeaders = true;
        $this->http->GetURL('http://m.elal.co.il/club?lang=en&country=Argentina#login');
        // getting login form from script
        $form = $this->http->FindPreg("/<script type=\"text\/html\" id=\"loginTemplate\">(.*)<\/script>/ims");
        $this->http->SetBody($form);

        if (!$this->http->ParseForm("memberLoginForm")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'http://m.elal.co.il/club/MemberLogin/Login?Lang=en';
        $this->AccountFields['Login'] = preg_replace('/\D/', '', $this->AccountFields['Login']);

        if (empty($this->AccountFields['Login'])) {
            throw new CheckException('Login Failed Try Again.', ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->SetInputValue('Number', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        if (($message = $this->http->FindPreg("/This page can't be displayed due to a security violation\./"))
            && $this->http->Response['code'] == 520) {
            $this->DebugInfo = $message;
            $this->http->Log(">>> $message");

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindPreg("/\"Authenticated\":true/ims")) {
            return true;
        }
        //# Invalid credentials
        if ($message = $this->http->FindPreg("/(Incorrect details[^\"]+)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Password date expired, You may enter the EL AL website to create a new password.
        if ($message = $this->http->FindPreg("/(Password date expired\,)/ims")) {
            throw new CheckException('EL AL Israel Airlines (Matmid) website is asking you to update your password, until you do so we would not be able to retrieve your account information.', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function checkPropertyValue($response, $value)
    {
        if (isset($response->$value)) {
            return $response->$value;
        }
        $this->http->Log("{$value} is not found");

        return false;
    }

    public function Parse()
    {
        $this->http->PostURL("http://m.elal.co.il/club/MyStatus/GetMyStatus?Lang=en", []);
        $response = json_decode($this->http->Response['body']);

        //# Balance - Total Points
        $this->SetBalance($this->checkPropertyValue($response, 'TotalPoints'));
        //# Member Number
        $this->SetProperty('MemberNo', $this->checkPropertyValue($response, 'CurrentClubCode') . preg_replace('/\D/', '', $this->AccountFields['Login']));
//        ## Status
        $this->SetProperty('CurrentClubStatus', $this->checkPropertyValue($response, 'CurrentClub'));
        //# Name
        $this->SetProperty('Name', beautifulName($this->checkPropertyValue($response, 'FullNameText')));
        // Status valid until
        if (isset($this->Properties['CurrentClubStatus']) && $this->Properties['CurrentClubStatus'] != 'Frequent') {
            $this->SetProperty('MembershipStatusEndDate', preg_replace("/([^\d\/]+)/ims", '', $this->checkPropertyValue($response, 'ClbExpiryDateTxt')));
        }
        // Additional points required to qualify for Next status
        $this->SetProperty('QualifyNextStatusPoints', $this->checkPropertyValue($response, 'PointsReqForNext'));
    }
}
