<?php
class TAccountCheckerGuestbook extends TAccountChecker
{
    public const QUESTION_TSC = 'Please enter a verification code which was sent to your phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
////            $this->http->SetProxy($this->proxyDOP());
//        }
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $result = Cache::getInstance()->get('guestbook_mail_list');

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Please choose your login type",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://theguestbook.com/users/sign_in");
            $nodes = $browser->XPath->query("//button[contains(@class, 'btn-hollow-transparent')]");

            for ($n = 0; $n < $nodes->length; $n++) {
                $mailType = CleanXMLValue($nodes->item($n)->nodeValue);

                if ($mailType) {
                    $arFields['Login2']['Options'][$mailType] = $mailType;
                }
            }

            if (count($arFields['Login2']['Options']) > 1) {
                Cache::getInstance()->set('guestbook_mail_list', $arFields['Login2']['Options'], 3600 * 24);
            } else {
                $this->sendNotification("guestbook - mail list not found", 'all', true, $browser->Response['body']);
            }
        }
    }

    public function LoadLoginForm()
    {
        $this->http->Log("[Login Type] -> {$this->AccountFields['Login2']}");
        $this->http->removeCookies();

        switch (strtolower($this->AccountFields['Login2'])) {
            case 'gmail':
                // debug
                $this->http->LogHeaders = true;
                $this->ArchiveLogs = true;

                $this->http->GetURL("https://theguestbook.com/users/auth/google_oauth2/profile_only?sign_in=true");
                // login form
                if (!$this->http->ParseForm("gaia_loginform")) {
                    return false;
                }
                $this->http->SetInputValue("Email", $this->AccountFields['Login']);
                $this->http->SetInputValue("Page", 'PasswordSeparationSignIn');

                $form = $this->http->Form;

                $this->http->FormURL = 'https://accounts.google.com/accountLoginInfoXhr';

                if (!$this->http->PostForm()) {
                    return false;
                }
                // Invalid credentials
                $response = $this->http->JsonLog();

                if (isset($response->error_msg)) {
                    throw new CheckException($response->error_msg, ACCOUNT_INVALID_PASSWORD);
                }

                // password form
                $this->http->FormURL = 'https://accounts.google.com/ServiceLoginAuth';
                $this->http->Form = $form;
                $this->http->SetInputValue("Passwd", $this->AccountFields['Pass']);

                break;

            case 'yahoo':
                $this->http->FilterHTML = false;
                $this->http->GetURL("https://theguestbook.com/users/auth/yahoo_openid_profile_only?sign_in=true");

                if (!$this->http->ParseForm("mbr-login-form")) {
                    return false;
                }
                $this->http->SetInputValue("username", $this->AccountFields['Login']);
                $this->http->SetInputValue("passwd", $this->AccountFields['Pass']);

                break;

            case 'outlook':
            case 'hotmail':
                $this->http->GetURL("https://theguestbook.com/users/auth/live_connect/profile_only?sign_in=true");
                $urlLogin = $this->http->FindPreg("/urlLogin:'([^\']+)/");

                if (!isset($urlLogin)) {
                    $this->http->Log("urlLogin not found");

                    return false;
                }
                $this->http->GetURL($urlLogin);
                $ppft = $this->http->FindPreg("/name=\"PPFT\" id=\"[^\"]+\" value=\"([^\"]+)/");
                $urlPost = $this->http->FindPreg("/urlPost:'([^\']+)/");

                if (!isset($ppft, $urlPost)) {
                    $this->http->Log("parameters are not found");

                    return false;
                }
                $this->http->FormURL = $urlPost;
                $this->http->SetFormText("loginfmt={$this->AccountFields['Login']}&passwd={$this->AccountFields['Pass']}&login=" . strtolower($this->AccountFields['Login']) . "&type=11&PPFT={$ppft}&PPSX=Passport&idsbho=1&sso=0&NewUser=1&LoginOptions=3&i1=0&i2=1&i3=112539&i4=0&i7=0&i12=1&i13=0&i14=240&i17=0&i18=__Login_Strings%7C1%2C__Login_Core%7C1%2C", "&");

                break;

            case 'other email':
                $this->http->GetURL("https://theguestbook.com/users/sign_in");

                if (!$this->http->ParseForm("new_user")) {
                    return false;
                }
                $this->http->SetInputValue("user[email]", $this->AccountFields['Login']);
                $this->http->SetInputValue("user[password]", $this->AccountFields['Pass']);
                $this->http->SetInputValue("commit", 'Sign In');

                break;

            default:
                $this->http->Log("Unknown email type");
                $this->sendNotification("guestbook - Unknown email type");

                break;
        }// switch ($this->AccountFields['Login2'])

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://theguestbook.com/";

        return $arg;
    }

    public function Login()
    {
        $this->http->Log("[Login Type] -> {$this->AccountFields['Login2']}");

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindNodes("//a[contains(@href, 'sign_out')]/@href")) {
            return true;
        }

        switch (strtolower($this->AccountFields['Login2'])) {
            case 'gmail':
                // The email and password you entered don't match.
                if ($message = $this->http->FindSingleNode('//span[contains(text(), "The email and password you entered don\'t match.")]')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->parseQuestion()) {
                    return false;
                }

                break;

            case 'yahoo':
                // This ID is not yet taken.
                if ($message = $this->http->FindPreg("/(This ID is not yet taken\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Invalid ID or password
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Invalid ID or password.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case 'outlook':
            case 'hotmail':
                // That password is incorrect.
                if ($message = $this->http->FindPreg("/sErrTxt:\'(That password is incorrect\. Be sure you..re using the password for your Microsoft account\.)/")) {
                    throw new CheckException(stripslashes($message), ACCOUNT_INVALID_PASSWORD);
                }
                // That Microsoft account doesn't exist.
                if ($message = $this->http->FindPreg("/sErrTxt:'(That Microsoft account doesn.'t exist\.)/")) {
                    throw new CheckException(stripslashes($message), ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case 'other email':
                // Invalid email or password
                if ($message = $this->http->FindPreg("/toastr\.warning\(\"(Invalid email or password\.)\"\)\;/")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;

            default:
                $this->http->Log("Unknown email type");

                break;
        }// switch ($this->AccountFields['Login2'])

        return false;
    }

    public function parseQuestion()
    {
        $this->http->Log("parseQuestion");
        // 2-Step Verification
        if ($this->http->FindSingleNode("//h1[contains(text(), '2-Step Verification')]")) {
            $this->http->Log("2-Step Verification");
            $question = $this->http->FindSingleNode("//div[contains(text(), 'Enter verification code')]");

            if (!$this->http->ParseForm("challenge")) {
                return false;
            }
        }
        // Verification via SMS by verification code
        elseif ($this->http->FindSingleNode('//div[contains(text(), "Verify it\'s you")]')) {
            $this->http->Log("Verification by verification code");
            $question = self::QUESTION_TSC;

            if (!$this->http->ParseForm("challengeform")) {
                return false;
            }
            $this->http->SetInputValue("VerifyPhoneType", "SMS_CHALLENGE");
            $this->http->SetInputValue("challengetype", "VerifySmsChallenge");

            if (!$this->http->PostForm()) {
                return false;
            }
        }

        if (!isset($question)) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->Log("ProcessStep. Security Question");

        if ($this->Question == self::QUESTION_TSC) {
            $this->http->Log("Verification by verification code");

            $this->sendNotification("guestbook - refs #11251. Verification code was entered");
//            $this->http->SetInputValue("Pin", $this->Answers[$this->Question]);
        } else {
            $this->http->Log("2-Step Verification");
            $this->http->SetInputValue("Pin", $this->Answers[$this->Question]);
            $this->http->SetInputValue("TrustDevice", "on");

            if (!$this->http->PostForm()) {
                return false;
            }
            // remove old code
            unset($this->Answers[$this->Question]);
            // Wrong code
            if ($this->http->FindSingleNode("//span[contains(text(), 'Wrong code. Try again.')]")) {
                $this->parseQuestion();

                return false;
            }
        }

        return true;
    }

    public function Parse()
    {
        // Balance - Redeemable Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Redeemable Balance')]/following-sibling::div[1]"));
        // Rewards Pending
        $this->SetProperty("RewardsPending", $this->http->FindSingleNode("//div[contains(text(), 'Rewards Pending')]/following-sibling::div[1]"));
        // Rewards Earned To Date
        $this->SetProperty("RewardsEarnedToDate", $this->http->FindSingleNode("//div[contains(text(), 'Rewards Earned To Date')]/following-sibling::div[1]"));

        // Name
        $this->http->GetURL("https://theguestbook.com/accounts/manage?section=profile");
        $name = CleanXMLValue(
            $this->http->FindSingleNode("//input[@id = 'firstname']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'middlename']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'lastname']/@value")
        );
        $this->SetProperty("Name", beautifulName($name));
    }
}
