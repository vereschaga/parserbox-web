<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerUltraone extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.ultraonerewards.com/");

        if (!$this->http->ParseForm("loyalty_login")) {
            return false;
        }
        $this->http->SetInputValue("account_id", $this->AccountFields['Login']);
        $this->http->SetInputValue("pincode", $this->AccountFields['Pass']);
        $this->http->SetInputValue("button_login", "Login");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // Success login
        if ($this->http->FindSingleNode('//p[@id="member_name"]/strong')) {
            return true;
        }
        // Failed to login
        $error = $this->http->FindSingleNode('//div[@id="subpage_login"]/p[@class="instructions"]');
        $this->logger->error($error);
        // Wrong login/pass
        if (strpos($error, 'Please check your member information and try again') !== false) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // exception
//        throw new CheckException($errorMsg, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Parse()
    {
        // Page to parse
        $this->http->GetURL('http://www.ultraonerewards.com/ultraone-account-summary');
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//p[@id="member_name"]/strong'));
        // Points balance - Points Balance
        $this->SetBalance($this->http->FindSingleNode('//table[@class="account_summary"]//td[contains(text(), "Points Balance")]/following-sibling::td'));
        // Current tier level
        $this->SetProperty('CurrentTierLevel', $this->http->FindSingleNode('//table[@class="account_summary"]//td[contains(text(), "Current Tier Status")]/following-sibling::td'));
        // UltraCredits Balance
        $this->SetProperty('UltraCreditsBalance', $this->http->FindSingleNode('//table[@class="account_summary"]//td[contains(text(), "UltraCredits Balance")]/following-sibling::td'));
        // Current Gear Level
        $this->SetProperty('CurrentGearLevel', $this->http->FindSingleNode('//table[@class="account_summary"]//td[contains(text(), "Current Gear Level")]/following-sibling::td'));
        // Next Month Gear Level
        $this->SetProperty('NextMonthGearLevel', $this->http->FindSingleNode('//table[@class="account_summary"]//td[contains(text(), "Next Month Gear Level")]/following-sibling::td'));
        // Fuel Gallons to Next Gear
        $this->SetProperty('FuelGallonsToNextGear', $this->http->FindSingleNode('//table[@class="account_summary"]//td[contains(text(), "Fuel Gallons to Next Gear")]/following-sibling::td'));
        // YTD Points Earned
        $this->SetProperty('YTDPointsEarned', $this->http->FindSingleNode('//table[@class="account_summary"]//td[contains(text(), "YTD Points Earned")]/following-sibling::td'));

        // Ultra extras - as subaccounts
        // How many extras?
        $ultraExtrasTbx = '//table[@class="ultra_extras"]';
        $ultraExtrasNum = count($this->http->FindNodes($ultraExtrasTbx . '/tr')) - 1; // THEAD + tbody
        // Extras found
        if ($ultraExtrasNum > 0) {
            $this->SetProperty("CombineSubAccounts", false);
            // foreach extras
            for ($n = 2; $n <= $ultraExtrasNum + 1; $n++) {
                // UltraExtras
                $displayName = $this->http->FindSingleNode($ultraExtrasTbx . '/tr[' . $n . ']/td[1]');
                // Available
                $balance = $this->http->FindSingleNode($ultraExtrasTbx . '/tr[' . $n . ']/td[2]');
                $this->AddSubAccount([
                    'Code'              => str_replace(' ', '', Html::cleanXMLValue($displayName)),
                    'DisplayName'       => $displayName,
                    'Balance'           => $balance,
                ]);
            }
        }
    }
}
