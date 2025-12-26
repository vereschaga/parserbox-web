<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSas extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://cap.scandinavian.net/SASCreditsCustomer.aspx", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL("https://cap.scandinavian.net/Home/Login.aspx?ReturnUrl=%2fSASCreditsCustomer.aspx");
        $this->http->GetURL("https://cap.scandinavian.net/SASCreditsCustomer.aspx");

        if (!$this->http->ParseForm("Form1")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("log_in:txtUserName", $this->AccountFields['Login']);
        $this->http->SetInputValue("log_in:txtPassword", $this->AccountFields['Pass']);
        $this->http->SetInputValue("log_in:imgbtLogin.x", '13');
        $this->http->SetInputValue("log_in:imgbtLogin.y", '4');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://cap.scandinavian.net/Home/Login.aspx?ReturnUrl=%2fSASCreditsCustomer.aspx";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            ($this->http->Response['code'] == 500
                && empty($this->http->Response['body']))
            || $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 503. The service is unavailable.")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindPreg("/(Invalid username or password\.\s*Please try again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('#An undefined error occurred\.#i')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // https://cap.scandinavian.net/Home/ChangeExtPass.aspx
        if ($this->http->FindPreg('#OpenModalDialogNoReturn\(\'\/Home\/ChangeExtPass\.aspx\'#i')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $name = $this->http->FindSingleNode("//span[@id = 'header_lblName']", null, true, '/([A-Za-z\-\s]+)/ims');
        $this->SetProperty("Name", beautifulName($name));
        //# AccountNumber
        if (isset($name)) {
            $header = str_replace($this->Properties['Name'], '', $this->http->FindSingleNode("//span[@id = 'header_lblName']"));
            $this->SetProperty("AccountNumber", CleanXMLValue($header));
        }
        // Get data
        $this->http->GetURL("https://cap.scandinavian.net/Loading.aspx");

        if ($this->http->ParseForm("Form1")) {
            sleep(1);
            $this->http->GetURL("https://cap.scandinavian.net/SASCreditsHome.aspx");
        }
        $nodes = $this->http->XPath->query("//table[@id = 'dataGrid']/tr[not(contains(@class, 'Header'))]");
        $this->http->Log("Total nodes found: " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                // Company Code
                $companyCode = $this->http->FindSingleNode('td[2]/table/tr[2]/td/table/tr[1]/td[2]', $nodes->item($i));
                $displayName = $this->http->FindSingleNode('td[2]/table/tr[1]/td[1]', $nodes->item($i));
                $balance = $this->http->FindSingleNode('td[2]/table/tr[1]/td[3]', $nodes->item($i));
//                $exp = $this->http->FindSingleNode('td[3]', $nodes->item($i));

                if (isset($balance)) {
                    $subAccounts[] = [
                        'Code'        => 'sas' . $companyCode,
                        'DisplayName' => $displayName,
                        'CompanyCode' => $companyCode,
                        'Balance'     => $balance,
                        //                        'ExpirationDate' => strtotime($exp),
                    ];
                }// if (isset($balance))
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
//                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
                $this->SetBalanceNA();
            }// if(isset($subAccounts))
        }// if ($nodes->length > 0)
        // Your company information was not loaded correctly. Please press “Reload” to try again.
        elseif ($message = $this->http->FindPreg("/(Your company information was not loaded correctly\.)/ims")) {
            throw new CheckException($message . ' Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }
        // Please search for the company you would like to work with
        elseif ($this->http->FindPreg("/(Search For Specific company code)/ims") && !empty($this->Properties['AccountNumber']) && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[@id = 'header_btnLogout']/@id")) {
            return true;
        }

        return false;
    }
}
