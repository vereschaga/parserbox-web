<?php

class TAccountCheckerMad extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.ethicalsuperstore.com/account/account.php");

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email_address", $this->AccountFields['Login']);
        $this->http->SetInputValue("new_password", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("customer_type", "returning_customer");
        unset($this->http->Form['marketing_3rd_party']);

        return true;
    }

    public function checkErrors()
    {
        // You've caught us in the middle of upgrading the Ethical Superstore website
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "You\'ve caught us in the middle of upgrading the Ethical Superstore website")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // success
        if ($this->http->FindNodes("//a[contains(@href, 'logoff.php')]")) {
            return true;
        }
        // invalid credentials
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'Incorrect password, please try again')
                or contains(text(), 'Incorrect email address and password combination, please try again.')
            ]
        ")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // That e-mail address does not appear to exist in our system
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'That e-mail address does not appear to exist in our system')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() == 'http://www.ethicalsuperstore.com/index.php') {
            $this->http->GetURL("https://www.ethicalsuperstore.com/account/account.php");
        }
        // Worth
        $this->SetProperty("BalanceWorth", $this->http->FindSingleNode("//h4[contains(text(), 'Your current balance')]/following-sibling::p[1]/b"));
        // Balance - Your current balance
        $this->SetBalance($this->http->FindSingleNode("//h4[contains(text(), 'Your current balance')]/following-sibling::p[1]", null, true, "/(\d+)\s*\-/"));

        // Vouchers
        $nodes = $this->http->XPath->query("//div[@class = 'voucher']");
        $this->http->Log("Total nodes found: " . $nodes->length);

        for ($i = 0; $i < $nodes->length; $i++) {
            $vouchers = $this->http->FindSingleNode(".//div[contains(@class, 'voucher__code')]/p/text()[last()]", $nodes->item($i));
            $issued = $this->http->FindSingleNode(".//div/p[b[contains(text(), 'Issued')]]/text()[last()]", $nodes->item($i));
            $expires = $this->http->FindSingleNode(".//div/p[b[contains(text(), 'Expires')]]/text()[last()]", $nodes->item($i));
            $balance = $this->http->FindSingleNode(".//div/p[b[contains(text(), 'Value')]]/text()[last()]", $nodes->item($i), true, "/[\d\.\,]+/");
            $available = $this->http->FindSingleNode(".//div[contains(@class, 'show-desk')]/p[b[contains(text(), 'Available')]]/span", $nodes->item($i));

            if (!$available) {
                $subAccounts[] = [
                    'Code'           => 'Voucher#' . str_replace(' ', '', $vouchers),
                    'DisplayName'    => "Voucher: " . $vouchers,
                    'Balance'        => $balance,
                    'Issued'         => $issued,
                    'ExpirationDate' => strtotime($expires),
                ];
            }
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($subAccounts)) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->http->Log("Total subAccounts: " . count($subAccounts));
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if (isset($subAccounts))

        // Name
        $name = CleanXMLValue($this->http->FindSingleNode("//b[contains(text(), 'First name')]/following-sibling::span")
            . " " . $this->http->FindSingleNode("//b[contains(text(), 'Last name')]/following-sibling::span"));

        if (strlen($name) > 3) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'Voucher')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
        } else {
            return $fields['Balance'];
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.ethicalsuperstore.com/account/login.php';
        $arg['SuccessURL'] = 'https://www.ethicalsuperstore.com/account/account.php';

        return $arg;
    }
}
