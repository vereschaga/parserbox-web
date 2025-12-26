<?php

class TAccountCheckerSurveyspot extends TAccountChecker
{
    private $retry = 0;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.surveyspot.com/");

        // Survey Spot is now closed.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Survey Spot is now closed.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->parseForm('form1')) {
            return false;
        }
        $currentItem = $this->http->FindPreg('/var currentItem =\s*[\'\"]([^\'\"]+)/ims');
        $this->http->Form['requestStr'] = json_encode(
            [
                'user'              => $this->AccountFields['Login'],
                'password'          => $this->AccountFields['Pass'],
                'guid'              => $currentItem,
                'redirectUrl'       => "",
                "recaptchaResponse" => "",
            ]
        );
        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');

        return true;
    }

    public function Login()
    {
        $this->http->PostURL("https://www.surveyspot.com/app_services/aps/public/publicservices.asmx/Login", $this->http->Form['requestStr']);
        $authResponse = json_decode($this->http->Response['body']);

        if (!isset($authResponse->d)) {
            return false;
        }
        $authResponse->d = json_decode($authResponse->d);

        $this->http->Log('POST Response: ' . var_export($authResponse->d, true));

        if (isset($authResponse->d->ErrorMessage) && $authResponse->d->ErrorMessage != null) {
            throw new CheckException($authResponse->d->ErrorMessage, ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($authResponse->d->Success) && $authResponse->d->Success == true) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.surveyspot.com/Secured/My-Dashboard.aspx");

        //# Retry login
        if (($this->http->currentUrl() === 'http://www.surveyspot.com/'
                || $this->http->currentUrl() === 'https://www.surveyspot.com/')
            && $this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->retry < 3) {
            $this->http->Log("Retry login " . var_export($this->retry, true), true);
            $this->retry++;

            if ($this->LoadLoginForm()) {
                if ($this->Login()) {
                    $this->Parse();
                }
            }

            return;
        }
        //# You've Earned ... pts
        $this->SetBalance($this->http->FindSingleNode('//h5[@class = "earnedPoints"]'));
        //# chances to win
        $this->SetProperty("TotalPrizeDrawEntries", $this->http->FindSingleNode('//em[@id = "drawEntries"]'));
        //# next drawing
        $this->SetProperty("NextPrizeDrawDate", $this->http->FindSingleNode('//span[@id = "nextPrizeBlurb"]'));
        //# pts needed to redeem
        if ($this->http->FindSingleNode("//input[@id = 'minPointstoRedeem']/@value")
            && $this->http->FindSingleNode("//input[@id = 'userPoints']/@value")) {
            // set Property
            $remainingPoints = $this->http->FindSingleNode("//input[@id = 'minPointstoRedeem']/@value") - $this->http->FindSingleNode("//input[@id = 'userPoints']/@value");

            if ($remainingPoints > 0) {
                $this->SetProperty("NeededToRedeem", $remainingPoints . ' pts');
            }
        }

        $this->http->GetURL("https://www.surveyspot.com/secured/my-dashboard/survey-history");
        //# Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(text(), 'Member Since:')]/parent::li", null, true, '/Member\s*Since\s*:\s*([^<]+)/ims'));
        //# Completed
        $this->SetProperty("Completed", $this->http->FindSingleNode("//span[contains(text(), 'Completed:')]/parent::li", null, true, '/Completed\s*:\s*([^<]+)/ims'));
        //# Collected
        $this->SetProperty("Collected", $this->http->FindSingleNode("//span[contains(text(), 'Collected:')]/parent::li", null, true, '/Collected\s*:\s*([^<]+)/ims'));

        $this->http->GetURL("https://www.surveyspot.com/secured/my-dashboard/edit-profile?edit=true");
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("(//div[@id = 'personal-info']/h1)[1]"));
    }
}
