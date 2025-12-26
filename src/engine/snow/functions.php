<?php

class TAccountCheckerSnow extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.snow.com/My-Account/Login.aspx");
        $token = $this->http->FindSingleNode("//input[@id = 'csrfToken']/@value");

        if (!$this->http->ParseForm("form1") || !isset($token)) {
            return $this->checkErrors();
        }

        $this->http->setDefaultHeader("RequestVerificationToken", $token);
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->PostURL("https://www.snow.com/_/account/existing-account-login",
            [
                "Password" => $this->AccountFields['Pass'],
                "UserName" => $this->AccountFields['Login'],
            ]);
//        $this->http->SetInputValue('globalmaincontent_0$5widecolumn905685bbc4d846269387d23df5038e96_0$txtExistingUsername', $this->AccountFields['Login']);
//        $this->http->SetInputValue('globalmaincontent_0$5widecolumn905685bbc4d846269387d23df5038e96_0$txtExistingPassword', $this->AccountFields['Pass']);
//        $this->http->Form['globalmaincontent_0$5widecolumn905685bbc4d846269387d23df5038e96_0$btnExistingSubmit'] = 'Sign In';

        return true;
    }

    public function checkErrors()
    {
        //# Our site is currently experiencing technical difficulties.
        if ($message = $this->http->FindPreg("/(Our site is currently experiencing technical difficulties\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently undergoing scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'currently undergoing scheduled maintenance.')]")) {
            throw new CheckException("We are currently undergoing scheduled maintenance.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        //		if (!$this->http->PostForm())
//            return $this->checkErrors();
        $response = $this->http->JsonLog();

        if (isset($response->isSuccess, $response->message->RedirectUrl) && $response->isSuccess == true) {
            $redirect = $response->message->RedirectUrl;
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'SignOut')]/@href")
            || $this->http->FindSingleNode("//a[contains(text(), 'Sign out')]")) {
            return true;
        }
        // Invalid Username or Password
        if (isset($response->message->errors[0])) {
            throw new CheckException($response->message->errors[0], ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != 'https://www.snow.com/My-Account/View-My-Profile.aspx') {
            $this->http->GetURL("https://www.snow.com/my-account/view-my-profile.aspx");
        }
        // Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h2[contains(text(), 'My Pass Information')]/following-sibling::ul/li//span[contains(@id, 'passNumber')]"));
        // Pass Type
        $this->SetProperty("PassType", $this->http->FindSingleNode("//h2[contains(text(), 'My Pass Information')]/following-sibling::ul/li//span[contains(@id, 'passDescription')]"));
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//h2[contains(text(), 'Personal Information')]/following-sibling::ul/li[1]/span[@class = 'value']"));

        $this->http->GetURL("https://www.snow.com/my-account/my-peaks-rewards.aspx");
        //# Balance - Current Peaks points per household
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'accountSummary']/dl/dd[1]/text()"));
        //# Total Certificates available per household
        $this->SetProperty("TotalCertificatesAvailable", $this->http->FindSingleNode("//div[@class = 'accountSummary']/dl/dd[2]/text()"));

        // Sub Accounts - Active/Available Certificates

        $nodes = $this->http->XPath->query("//div[contains(@id, 'activeCertificates')]/table/tbody/tr");
        $subAccounts = [];

        if (isset($nodes)) {
            $this->http->Log("Total nodes found: " . $nodes->length);

            for ($i = 0; $i < $nodes->length; $i++) {
                $expiarationDate = $this->http->FindSingleNode('td[3]', $nodes->item($i), true);
                $certificate = $this->http->FindSingleNode('td[1]', $nodes->item($i), true);
                $subAccounts[] = [
                    'Code'           => 'Certificate#' . $certificate,
                    'DisplayName'    => "Certificate #" . $certificate,
                    'Balance'        => null,
                    'ExpirationDate' => strtotime($expiarationDate),
                ];
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (count($subAccounts) > 0) {
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }
        }

        // Expiration date   // refs #10203
        $familyMemberIDs = $this->http->FindNodes("//select[@id = 'columnCenter_ctl00_ddlFamily']/option/@value");
        unset($exp);

        foreach ($familyMemberIDs as $id) {
            $data = ["IPCode" => $id];
            $this->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');
            $this->http->PostURL("https://www.snow.com/vailresorts/sites/PlanningAndBooking/WebServices/UserWebServices.svc/GetPeakEarningDetails", json_encode($data));
            $response = $this->http->JsonLog(null, false);

            if (isset($response->d)) {
                $this->http->Response['body'] = $response->d;
            }
            $rows = $this->http->XPath->query("//table[@class = 'tabularData']//tr[td]");
            $this->http->Log("Total {$rows->length} rows were found");

            if (isset($response->d)) {
                $this->http->SetBody($response->d, true);
            }
            $rows = $this->http->XPath->query("//table[@class = 'tabularData']//tr[td]");
            $this->http->Log("Total {$rows->length} rows were found");

            if ($rows->length > 0) {
                $lastActivity = $this->http->FindSingleNode('td[4]', $rows->item(0));
                $this->http->Log("Last Activity: $lastActivity");

                if (!isset($exp) || strtotime($lastActivity) > $exp) {
                    if ($exp = strtotime($lastActivity)) {
                        $exp = strtotime("+3 year", $exp);
                        $month = date("n", $exp);
                        $year = date("Y", $exp);
                        $this->http->Log("Date: $month / $year");

                        if ($month <= 4) {
                            $this->http->Log("Exp Date: in this Year");
                            $exp = mktime(0, 0, 0, 5, 0, $year);
                        } else {
                            $this->http->Log("Exp Date: in Next Year");
                            $exp = mktime(0, 0, 0, 5, 0, $year + 1);
                        }
                        $this->SetExpirationDate($exp);
                    }// if ($exp = strtotime($lastActivity))
                    $this->SetProperty("LastActivity", $lastActivity);
                }// if (!isset($exp) || strtotime($lastActivity) > $exp)
                elseif ($message = $this->http->FindSingleNode("//span[contains(text(), 'No Peaks information is available for this account')]")) {
                    $this->http->Log($message);
                }
            }// if ($rows->length > 0)
            else {
                $this->http->Log("No information is available");
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.snow.com/My-Account/Login.aspx';
        $arg['SuccessURL'] = 'https://www.snow.com/my-account/my-peaks-rewards.aspx';

        return $arg;
    }
}
