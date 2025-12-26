<?php

/**
 * Class TAccountCheckerLastminute
 * Dysplay name: Lastminute
 * Database ID: 882
 * Author: VPetuhov
 * Created: 15.07.2013 11:58.
 */
class TAccountCheckerLastminute extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.lastminute.com/site/help/your-account/homepage.html");

        if (!$this->http->ParseForm("login_form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("userEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("userPassword", $this->AccountFields['Pass']);
        $this->http->SetInputValue("submit", "continue");

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindPreg("/An error occurred while processing your request\./ims")
            || $this->http->FindPreg("/Error rendering component/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.lastminute.com/site/help/your-account/homepage.html";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//p[@class='errorMessage']")) {
            if (strstr($message, 'we have encountered a technical problem')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } else {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($this->http->FindPreg("/sign me out/ims")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.lastminute.com/site/help/your-account/details.html?skin=engb.lastminute.com");
        $this->SetBalanceNA();
        // Name
        $name = CleanXMLValue($this->http->FindSingleNode("//td[contains(., 'first name')]/following::td[1]") . ' ' . $this->http->FindSingleNode("//td[contains(., 'last name')]/following::td[1]"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        if ($this->http->GetURL("https://www.lastminute.com/site/help/your-account/purchase-history.html?skin=engb.lastminute.com")) {
            $date = $this->http->FindNodes("//p[contains(@class, 'orderDate')]");
            $isFuture = false;

            foreach ($date as $item) {
                $this->http->Log("Date: $item / " . strtotime($item));

                if (strtotime($item) > time()) {
                    $isFuture = true;
                }
            }// foreach ($date as $item)

            if ($isFuture) {
                $this->sendNotification("lastminute have new future order.");
            }
        }
    }
}
