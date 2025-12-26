<?php

class TAccountCheckerOutbackb extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->getURL('https://tablemates.outback.com/');
        // parsing form on the page
        if (!$this->http->ParseForm('sign-in-form')) {
            return $this->checkErrors();
        }
        // enter the login and password
        $this->http->SetInputValue("Email", $this->AccountFields["Login"]);
        $this->http->SetInputValue("PasswordVerify", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("Password", $this->AccountFields["Pass"]);
        $this->http->Form["Boomerang"] = "True";
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'This website is under construction')]/parent::p[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = json_decode($this->http->Response["body"], true);

        if (isset($response["is_success"])) {
            switch ($response["is_success"]) {
                case 1:
                    return true;

                    break;

                case 0:
                    throw new CheckException($response["data"]["errors"][''][0], ACCOUNT_INVALID_PASSWORD);

                    break;

                default:
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // name
        $this->SetProperty("Name", $this->getInfo("Name"));
        // card number
        $this->SetProperty("Number", $this->getInfo("PrintedCardNumber"));
        // awards number
        $this->SetProperty("Rewards", $this->getInfo("NumRewards"));
        // Are From My Table (Points from My Table)
        $this->SetProperty("PointsFromMyTable", $this->getInfo("MyBoomersFromTable"));
        // balance
        $this->SetBalance($this->getInfo("MyTotalBoomers"));

        //# subAccounts - Rewards    // refs #6653
        $this->http->GetURL("https://tablemates.outback.com/dashboard/my-rewards?RequestId=&_=" . time() . date('B'));
        $rewards = $this->http->XPath->query("//div[@class = 'reward']");
        $this->http->Log("Total rewards found: " . $rewards->length);

        if ($rewards->length > 0) {
            for ($i = 0; $i < $rewards->length; $i++) {
                $displayName = $this->http->FindSingleNode("div[@class = 'description']/span", $rewards->item($i));
                $exp = $this->http->FindSingleNode("div[@class = 'description']/div[@class = 'expiration']", $rewards->item($i), true, '/expires\s*([^<]+)/');

                $subAccounts[] = [
                    'Code'           => 'outbackbRewards' . $i,
                    'DisplayName'    => $displayName,
                    'Balance'        => null,
                    'ExpirationDate' => strtotime($exp),
                ];
            }// for ($i = 0; $i < $rewards->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }// if ($rewards->length > 0)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://tablemates.outback.com/dashboard";

        return $arg;
    }

    private function getInfo($scope)
    {
        $this->http->GetURL('https://tablemates.outback.com/dashboard/load-data?Scope=' . $scope);
        $response = json_decode($this->http->Response["body"], true);
        $result = array_values($response);

        if (count($result) == 1) {
            return $result[0];
        } else {
            return null;
        }
    }
}
