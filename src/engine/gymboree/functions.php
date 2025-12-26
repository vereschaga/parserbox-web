<?php

class TAccountCheckerGymboree extends TAccountChecker
{
    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "gymboreeCertificate"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.gymboree.com/sign-in?original=%2Faccount");

        if (!$this->http->ParseForm("dwfrm_login")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('dwfrm_login_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('dwfrm_login_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('dwfrm_login_login', 'Login');
        $this->http->SetInputValue('dwfrm_login_rememberme', 'true');

        return true;
    }

    public function checkErrors()
    {
        // Our site is temporary offline for maintenance.
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Our site is temporary offline for maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is currently unavailable.
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'Gymboree logo. Our site is currently unavailable.')]/@alt")) {
            throw new CheckException("Our site is currently unavailable right now, but we'll be ready for you soon.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Sorry, this does not match our records...
        if ($message = $this->http->FindSingleNode("//div[@class='error-form' and contains(normalize-space(.), 'Sorry, this does not match our records.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // For security reasons, your account has been locked.
        if ($message = $this->http->FindPreg("/(For security reasons, your account has been locked)/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // login successful
        if ($this->http->FindNodes("//a[contains(@href, 'Logout')]")) {
            return true;
        }

        // Reset Password
        if ($this->http->FindPreg('#/on/demandware\.store/Sites-Gymboree-Site/[^\/]+/Login-LoginForm\?scope=#', false, $this->http->currentUrl())) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'user-name']")));
        // Rewards Member Number
        $this->SetProperty("MemberID", $this->http->FindSingleNode("//div[@class = 'rewards-number']", null, true, "/\:\s*([^<]+)/"));
        // Points needed for next Rewards Certificate
        $this->SetProperty("ToNextCertificate", $this->http->FindSingleNode("//div[contains(@class, 'pointsnext')]", null, true, "/([\d\,\.]+)\s*points?\s*away\s*from/"));
        // Balance - You have ... points
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'reward-amount']", null, true, "/You have ([\d\,\.]+) point/"));
        // Rewards Certificate Value
        $this->SetProperty("RewardsCertificateValue", $this->http->FindSingleNode("//span[contains(text(), 'Rewards Certificate Value')]/following-sibling::span[1]"));

        if ($this->ErrorCode === ACCOUNT_ENGINE_ERROR) {
            // We're sorry, but the Gymboree Rewards system is currently unavailable. Please try again later.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, but the Gymboree Rewards system is currently unavailable")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/(Enter your Rewards number to access your account)/ims")) {
                throw new CheckException("Gymboree (Rewards) website is asking you to enter your Rewards number, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }/*checked*/

            // no Balance (AccountID: 1793316)
            if ($this->http->FindSingleNode("//div[@class = 'noRewards']")) {
                $this->SetBalanceNA();
            }
        }
        // GymBucks
        $this->http->GetURL("https://www.gymboree.com/gymbucks");
        $this->SetProperty("GymBucks", $this->http->FindSingleNode("//div[@class = 'gymbucks-amount']", null, true, "/You have ([$\,\.\d]+) in/"));

        // Rewards Certificates
        if (isset($this->Properties['RewardsCertificateValue']) && trim($this->Properties['RewardsCertificateValue']) != '$0.00') {
            $this->http->GetURL("https://www.gymboree.com/rewards");
            $certificates = $this->http->XPath->query("//div[contains(@class, 'tabletitle')]/div[contains(@class, 'row')]");
            $this->logger->debug("Total {$certificates->length} rewards were found");

            foreach ($certificates as $certificate) {
                $balance = $this->http->FindSingleNode("div[2]", $certificate);
                $code = $this->http->FindSingleNode("div[1]/div[@class = 'certcode']", $certificate);
                $exp = $this->http->FindSingleNode("div[1]/div[@class = 'info']", $certificate, true, "/Expires\s*([^<]+)/");

                if (isset($balance, $code, $exp)) {
                    $this->AddSubAccount([
                        'Code'           => 'gymboreeCertificate' . $code,
                        'DisplayName'    => " Certificate #{$code}",
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($exp, null),
                    ]);
                }// if (isset($balance, $code, $exp))
            }// foreach ($certificates as $certificate)
        }// if (isset($this->Properties['RewardsCertificateValue']) && trim($this->Properties['RewardsCertificateValue']) != '$0.00')
    }
}
