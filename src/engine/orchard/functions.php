<?php

/**
 * Class TAccountCheckerOrchard
 * Display name: Orchard Supply Hardware (Club Orchard)
 * Database ID: 1152
 * Author: AKolomiytsev
 * Created: 16.10.2014 5:53.
 */
class TAccountCheckerOrchard extends TAccountChecker
{
    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'orchardCertificates')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://cluborchard.osh.com/cluborchard/info");

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'member')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("emailAddress", $this->AccountFields['Login']);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $this->checkInvalidCredentials();

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'login')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // Club Orchard is unavailable at this time. Please check back in a little while.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Club Orchard is unavailable at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * We apologize: we encountered a problem while trying to process your request.
         * Please try again in a few minutes. Our apologies for the inconvenience.
         */
        if ($message = $this->http->FindPreg("/We apologize: we encountered a problem while trying to process your request\. Please try again in a few minutes\. Our apologies for the inconvenience\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Error 503. The service is unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkInvalidCredentials()
    {
        $this->logger->notice(__METHOD__);
        // Incorrect email address
        if ($this->http->FindPreg('#/cluborchard/registration#', false, $this->http->currentUrl())
            && $this->http->FindSingleNode("//h1[contains(text(), 'Sign-up for Club Orchard')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Incorrect email address or password.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect email address or password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We just sent you a password reset email. There will be a link to set your password, but it expires after 15 minutes.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We just sent you a password reset email.')]")) {
            throw new CheckException("Incorrect email address or password.", ACCOUNT_INVALID_PASSWORD);
        }
        // Please provide a valid email address.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please provide a valid email address.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]")) {
            return true;
        }
        $this->checkInvalidCredentials();

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current Reward Points
        $this->SetBalance($this->http->FindSingleNode("//p[strong[contains(text(), 'Current Reward Points')]]/text()[last()]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//div[contains(@class, 'co-user-info')]//strong)[1]")));
        //Account - Member ID
        $this->SetProperty("Account", $this->http->FindSingleNode("//p[strong[contains(text(), 'Member Id:')]]/text()[last()]"));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//h2[contains(text(), 'Member Since')]", null, true, "/Member Since ([0-9\/]+)/"));
        // Points to next certificate
        $this->SetProperty("PointsToCertificate", $this->http->FindSingleNode("//span[contains(text(), 'away from earning your next reward')]", null, true, "/([\d\,\.\s]+)\s+point/"));

        // My Rewards

        $rewards = $this->http->XPath->query("//table[contains(@class, 'co-coupon-table')]//tr[td]");
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            $balance = $this->http->FindSingleNode("td[1]", $reward, true, "/([\$\d\.]+)/");
            $barCode = $this->http->FindSingleNode("td[1]", $reward, true, "/\s*:\s*(\d+)/");
            $displayName = $this->http->FindSingleNode("td[1]", $reward);
            $expDate = $this->http->FindSingleNode("td[3]", $reward);

            $status = $this->http->FindSingleNode("td[4]", $reward);

            if (!stristr($status, 'Available') || stristr($this->http->FindSingleNode("th[1]", $reward), 'No Records Found')) {
                $this->logger->debug("skip certificate {$barCode}");

                continue;
            }

            $this->logger->debug("Exp Date: {$expDate}");
            $expDate = strtotime($expDate, false);

            if (isset($balance, $displayName, $barCode) && $expDate) {
                $this->AddSubAccount([
                    'Code'           => 'orchardCertificates' . $barCode,
                    'DisplayName'    => $displayName,
                    'Balance'        => $balance,
                    'ExpirationDate' => $expDate,
                    'Issued'         => $this->http->FindSingleNode("td[2]", $reward),
                    'BarCode'        => $barCode,
                    "BarCodeType"    => BAR_CODE_CODE_128,
                ]);
            }// if (isset($balance, $displayName, $barCode) && $expirationDate)
            else {
                $this->sendNotification("orchard - Rewards wasn't scrapped");
            }
        }// for ($rewards as $reward)

        if (isset($this->Properties['SubAccounts'])) {
            $this->SetProperty("CombineSubAccounts", false);
        }
    }
}
