<?php

// this program is almost clone of citybank

class TAccountCheckerSearsclub extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->LogHeaders = true;
        $this->http->removeCookies();
        $this->http->GetURL('https://www.citibank.com/us/cards/srs/index.jsp');

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, 'https://www.accountonline.com/cards/svc/Login.do')]")) {
            return false;
        }
        $this->http->SetInputValue("USERNAME", $this->AccountFields['Login']);
        $this->http->SetInputValue("PASSWORD", $this->AccountFields['Pass']);
        $this->http->SetInputValue("deviceprint", 'version%3D2%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%2E9%3B%20rv%3A31%2E0%29%20gecko%2F20100101%20firefox%2F31%2E0%7C5%2E0%20%28Macintosh%29%7CMacIntel%26pm%5Ffpsc%3D24%7C1280%7C800%7C726%26pm%5Ffpsw%3D%26pm%5Ffptz%3D6%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D1%26pm%5Ffpco%3D1%26pm%5Ffpasw%3Dflash%20player%7Cjavaappletplugin%7Cdefault%20browser%7Cgoogletalkbrowserplugin%7Co1dbrowserplugin%7Cquicktime%20plugin%7Csharepointbrowserplugin%7Cskype%5Fc2c%5Fsafari%7Cflip4mac%20wmv%20plugin%26pm%5Ffpan%3DNetscape%26pm%5Ffpacn%3DMozilla%26pm%5Ffpol%3Dtrue%26pm%5Ffposp%3D%26pm%5Ffpup%3D%26pm%5Ffpsaw%3D1280%26pm%5Ffpspd%3D24%26pm%5Ffpsbd%3D%26pm%5Ffpsdx%3D%26pm%5Ffpsdy%3D%26pm%5Ffpslx%3D%26pm%5Ffpsly%3D%26pm%5Ffpsfse%3D%26pm%5Ffpsui%3D');
        unset($this->http->Form['RememberMe']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->ParseForm("login") && $this->http->FindPreg("/window\.onload\s*=\s*function\(\)\s*\{\s*document\.login\.action=\"\/cards\/svc\/GeneralLogin\.do\"\;/ims")) {
            $this->http->FormURL = 'https://www.accountonline.com/cards/svc/GeneralLogin.do';
            $this->http->PostForm();
        }

        if ($error = $this->http->FindSingleNode("//font[@class = 'err-new']", null, false, null, 0)) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // AccountOnline Temporarily Unavailable
        $this->CheckError($this->http->FindSingleNode("//h1[contains(text(), 'AccountOnline Temporarily Unavailable')]"), ACCOUNT_PROVIDER_ERROR);

        $this->checkErrors();

        if ($link = $this->http->FindSingleNode("//a[contains(text(), 'Remind me later')]/@href")) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }
        $this->CheckError($this->http->FindSingleNode("//font[@color = 'red' and contains(text(), 'This service is not available for this account.')]"), ACCOUNT_PROVIDER_ERROR);
        //# Go Paperless with Your Statements and Letters
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Go Paperless with Your Statements and Letters')]")
            && $this->http->ParseForm("paperlessForm")) {
            throw new CheckException("Sears Club website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/Security Question Checkpoint/ims")) {
            if ($this->ParseQuestion(true)) {
                return false;
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->http->Log("checkErrors");

        if ($error = $this->http->FindSingleNode("//div[@class = 'warning']")) {
            throw new CheckException($error, ACCOUNT_LOCKOUT);
        }
        // For your protection, we have blocked online access to your account for 24 hours.
        if ($error = $this->http->FindPreg("/For your protection, we have blocked online access to your account for 24 hours\./ims")) {
            throw new CheckException($error, ACCOUNT_LOCKOUT);
        }

        if ($error = $this->http->FindSingleNode("//p[@class = 'warning']")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function ParseQuestion($sendAnswers)
    {
        $this->http->Log("ParseQuestion: " . var_export($sendAnswers, true));

        if (!$this->http->ParseForm("addAccountForm")) {
            return false;
        }

        if ($this->http->FindPreg("/Security Question Checkpoint/ims")) {
            $needAnswer = false;

            for ($n = 0; $n < 2; $n++) {
                $question = $this->http->FindSingleNode('//label[@for="CYOTA_ANS_0"]', null, false, null, $n);
                $questions[] = $question;

                if (isset($question)) {
                    $this->http->Form["CYOTA_ANS_" . ($n + 1)] = $question;

                    if (!isset($this->Answers[$question])) {
                        $this->AskQuestion($question);
                        $needAnswer = true;
                    }
                }
            }// for ($n = 0; $n < 2; $n++)

            if (isset($questions)) {
                $this->http->Log("questions: " . var_export($questions, true));
            }

            if (!$needAnswer && $sendAnswers) {
                return !$this->ProcessStep('question');
            } else {
                return true;
            }
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->http->Log("sending answers");
        $questions = [];

        for ($n = 0; $n < 2; $n++) {
            $question = ArrayVal($this->http->Form, "CYOTA_ANS_" . ($n + 1));

            if ($question != '') {
                $questions[] = $question;

                if (!isset($this->Answers[$question])) {
                    $this->AskQuestion($question);

                    return false;
                }
                $this->http->Form["CYOTA_ANS_" . ($n + 1)] = $this->Answers[$question];
            }// if ($question != '')
        }// for ($n = 0; $n < 2; $n++)
        // user_page:homepageV2 ?
        $this->http->Log("questions: " . var_export($questions, true));

        if (count($questions) != 2) {
            return false;
        }
        $this->http->PostForm();
        // Your entry does not match the answer on record. Please try again.
        if ($error = $this->http->FindSingleNode("//div[@class = 'err-msg']", null, true, "/does not match the answer on record/ims")) {
            foreach ($questions as $question) {
                unset($this->Answers[$question]);
            }
            $this->ParseQuestion(false);
            $this->AskQuestion(array_shift($questions), $error);

            return false;
        }
        $this->checkErrors();

        return true;
    }

    public function Parse()
    {
        // Balance - Earned Points (Ending Balance)
        $balance = $this->http->FindSingleNode("//div[@class = 'reward_points']");

        //		$link = $this->http->FindSingleNode("//a[contains(@href, '/cards/svc/SummaryPromo.do?accountIndex=')]/@href");
        //		if(isset($link)){
        //			$this->http->GetURL("https://www.accountonline.com".$link);
//            $balance = $this->http->FindSingleNode('//td[contains(text(), "Ending Balance")]/following::td[1]');
//            if (isset($balance))
//                $this->SetBalance(str_replace(",", "", $balance));
        //		}
        //		$this->ShowLogs();
        $this->SetProperty("Name", $this->http->FindSingleNode('//span[@class="welcome_msg"]', null, true, '/(?:Welcome|Bienvenido\(a\)), (.*)/'));
        $this->http->GetURL('https://www.accountonline.com/cards/svc/SummaryPromo.do');
        $this->SetProperty("BeginningBalance", $this->http->FindSingleNode("//td[@class = 'desc' and contains(text(), 'Beginning Balance')]/following::td[1]"));
        $this->SetProperty("PointsEarned", $this->http->FindSingleNode("//td[@class = 'desc' and contains(text(), 'Points Earned')]/following::td[1]"));
        $this->SetProperty("AdjustmentsToPoints", $this->http->FindSingleNode("//td[@class = 'desc' and contains(text(), 'Adjustments to Points')]/following::td[1]"));
        $this->SetProperty("PointsRedeemed", $this->http->FindSingleNode("//td[@class = 'desc' and contains(text(), 'Points Redeemed')]/following::td[1]"));
        $this->SetProperty("PointsExpired", $this->http->FindSingleNode("//td[@class = 'desc' and contains(text(), 'Points Expired')]/following::td[1]"));
        $expires = $this->http->XPath->query("//h2[contains(text(), 'Future Point Expiration')]/following::table[1]//tr[td[2]]");
        $this->http->Log("expires found: " . $expires->length);

        for ($n = $expires->length - 1; $n >= 0; $n--) {
            $date = $this->http->FindSingleNode("td[1]", $expires->item($n));
            $date = preg_replace("/(^\d+\/)/ims", '${1}01/', $date);
            $balance = $this->http->FindSingleNode("td[2]", $expires->item($n));
            $this->http->Log("date: $date, balance: $balance");

            if (($balance != "0") && isset($date) && (strtotime($date) !== false)) {
                $this->SetExpirationDate(strtotime($date));
            }
        }

        // Balance - Earned Points (Ending Balance)
        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode('//td[contains(text(), "Ending Balance")]/following::td[1]');
        }

        if (isset($balance)) {
            $this->SetBalance(str_replace(",", "", $balance));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            //# Reward balance on the other site
            if ($this->http->FindPreg("/if you are a Shop Your Way Rewards member you can view your SHOP YOUR WAY REWARDS\s*<sup>SM<\/sup>\s*points balance at /ims")) {
                $this->SetBalanceNA();
            }
            // Este servicio no está disponible para esta cuenta
            if ($this->http->FindPreg("/(Este servicio no est\&\#225; disponible para esta cuenta)/ims")) {
                $this->SetBalanceNA();
            }
            //# This service is not available for this account.
            if ($message = $this->http->FindSingleNode("//font[contains(text(), 'This service is not available for this account.')]")) {
                $this->ErrorCode = ACCOUNT_WARNING;
                $this->ErrorMessage = $message;
            }
            // This page is temporarily unavailable
            if ($message = $this->http->FindPreg("/(This page is temporarily unavailable. Please try again later\.)/ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            //# AccountID: 590195
            $this->http->GetURL("https://www.accountonline.com/cards/svc/ToolsHome.do");

            if ($message = $this->http->FindSingleNode("//font[contains(text(), 'This service is not available for this account.')]")) {
                $this->ErrorCode = ACCOUNT_WARNING;
                $this->ErrorMessage = $message;
            }
            //# AccountID: 1391481
            if (!$this->http->FindPreg("/Rewards Summary/ims")
                && $this->http->FindPreg("/Sears MasterCard<sup><[^\>]+>\&reg;<\/span><\/sup>-5800/ims")) {
                $this->SetBalanceNA();
            }

            // multiple cards
            if ($card = $this->http->FindSingleNode("//select[@id = 'card_select']/option[not(@selected)]/@value")) {
                $this->http->Log("multiple cards");
                $this->sendNotification("searsclub - multiple cards");

                if ($this->http->ParseForm("SwitchAccount")) {
                    $this->http->SetInputValue("OBJ_SGN_FLD_DESIRED_RELATION", $card);
                    $this->http->SetInputValue("x", "16");
                    $this->http->SetInputValue("y", "10");
                    $this->http->PostForm();
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.citibank.com/us/cards/srs/index.jsp';

        return $arg;
    }
}
