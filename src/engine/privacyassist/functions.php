<?php

class TAccountCheckerPrivacyassist extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://privacyassist.bankofamerica.com");

        if (!$this->http->ParseForm("frmLogon")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("txtUserID", $this->AccountFields['Login']);
        $this->http->SetInputValue("txtPassword", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // We are upgrading our website to provide you the best online experience.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'We are upgrading our website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred on the server when processing the URL.
        if ($message = $this->http->FindPreg("/(An error occurred on the server when processing the URL\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->http->FindPreg("/Logout\.asp/")) {
            return true;
        }
        //# Invalid User ID or Password
        if ($message = $this->http->FindSingleNode("//div[@style = 'color:red']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Security Questions and Answers
        if ($message = $this->http->FindPreg("/(We have recently enhanced our security policy to better protect your personal information.[^<]+ Service Team\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // skip update
        if ($this->http->currentUrl() == 'https://privacyassist.bankofamerica.com/Pages/English/EmailLockMsg.asp'
            || $this->http->currentUrl() == 'https://privacyassist.bankofamerica.com/Pages/English/SpeedBumpTOU.asp') {
            $this->logger->notice("skip update");
            $this->http->GetURL("https://privacyassist.bankofamerica.com/Pages/English/IN_MemberSettings.asp");
        }

        // Set js Script
        $this->http->GetURL("https://privacyassist.bankofamerica.com/Pages/ASP/SetJScript.asp?");

        //# Message: No new changes have been reported
        $noCreditHistory = $this->http->FindPreg("/(No new changes have been reported)/ims");
        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(text(), 'Welcome')]", null, true, "/Welcome([^<\,]+)/ims"));

        $this->http->GetURL("https://privacyassist.bankofamerica.com/Pages/English/ScoreList.asp?Type=CU&Score=3B&mid=46");

        //# If a credit file only one
        if ($credit = $this->GetCreditProperties()) {
            $this->logger->notice(">>> Found only one credit file! <<<");
            //# Balance
            $this->SetBalance($credit['Balance']);
            //# Name
            $this->SetProperty("Name", $this->http->FindSingleNode("//table[contains(@summary, 'Score Bar Graph Name Info')]/tr[2]/td[2]", null, true, "/([^<\,]+)/ims"));
            //# Credit category
            $this->SetProperty("CreditCategory", $credit['Category']);
            //# Score is higher than
            $this->SetProperty("Score", $credit['Score']);
        }
        //# No credit history
        elseif (isset($noCreditHistory)) {
            $this->SetBalanceNA();
        }
        //# Sorry, your web account does not have credit information data yet
        elseif ($message = $this->http->FindSingleNode("//td[contains(text(), 'Sorry, your web account does not have credit information data yet')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Get links - if multiple Credit files
        elseif ($links = $this->http->FindNodes("//td[@class = 'SHeader']/a/@onclick", null, "/'([^\']+)'\)\;/")) {
            $link = $this->http->FindPreg("/wr\('\&nbsp;<a href=\"([^\']+)/");
            $this->logger->debug("Link -> {$link}");
            $link = str_replace(' ', '', $link);
            $this->logger->debug("Link -> {$link}");
            $reportID = $this->http->FindPreg("/ReportID=9(\d+)6\&/", false, $link);
            //# Get Name of Credit file
            $displayName = $this->http->FindNodes("//td[@class = 'SHeader']/a/@title");

//            if (empty($link)) {
            if (empty($reportID)) {
                $this->logger->notice("Link is nor found");

                return false;
            } else {
                $this->http->NormalizeURL($link);
            }

            //# Set Subaacounts - Credit files
            $this->logger->debug("Total nodes found: " . count($links));

            for ($i = 0; $i < count($links); $i++) {
                $this->http->GetURL("https://privacyassist.bankofamerica.com/Pages/English/WaitCreditScore.asp?ReportID={$reportID}&Type=C03&3BScore=Y&1B=Y&sSType=" . $links[$i]);

                if (($credit = $this->GetCreditProperties()) && isset($displayName[$i])) {
                    $subAccounts[] = [
                        'Code'        => 'Privacyassist' . str_replace(" ", '', $displayName[$i]),
                        'DisplayName' => $displayName[$i],
                        'Balance'     => $credit['Balance'],
                        //# Credit category
                        'CreditCategory' => $credit['Category'],
                        //# Score is higher than
                        'Score' => $credit['Score'],
                    ];
                }// if(($credit = $this->GetProperty()) && isset($displayName[$i]))
            }// for($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                $this->SetProperty("CombineSubAccounts", false);
                $this->SetBalanceNA();
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }
        }// elseif ($link = $this->http->FindSingleNode("//a[@title = 'Equifax']/@href"))
    }

    public function GetCreditProperties()
    {
        //# Credit category
        $credit['Category'] = $this->http->FindSingleNode("//table[contains(@summary, 'credit category')]/tr[3]/td[2]/img/@alt");
        //# Score is higher than
        $credit['Score'] = $this->http->FindSingleNode("//div[contains(text(), 'score is higher than')]", null, true, "/score is higher than ([\d\%]+)/ims");
        //# Balance
        $credit['Balance'] = $this->http->FindSingleNode("//table[contains(@summary, 'Score Bar Graph Name Info')]/tr[2]/td[3]/span");

        if (isset($credit['Balance'])) {
            return $credit;
        } else {
            $this->http->Log(">>> Credit Properties is not found! <<<");

            return false;
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://privacyassist.bankofamerica.com';
        $arg['SuccessURL'] = 'https://privacyassist.bankofamerica.com/Pages/English/ScoreList.asp?Type=CU&mid=103';

        return $arg;
    }
}
