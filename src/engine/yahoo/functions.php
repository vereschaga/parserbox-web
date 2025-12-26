<?php

class TAccountCheckerYahoo extends TAccountChecker
{
    private $z = null;
    private $response = null;
    private $form = [];
    private $formURL = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        // https://help.yahoo.com/kb/SLN35642.html
        throw new CheckException("Yahoo Answers has shut down as of May 4, 2021. Yahoo Answers was once a key part of Yahoo's products and services, but it has declined in popularity over the years as the needs of our members have changed. We decided to shift our resources away from Yahoo Answers to focus on products that better serve our members and deliver on Yahoo's promise of providing premium trusted content.", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://login.yahoo.com/config/login;_ylt=AwrB2.XxlStUKFwAuSvj1KIX?.src=knowsrch&.intl=us&.lang=en-US&.done=https://answers.yahoo.com/activity");

        if ($this->http->ParseForm("mbr-login-form")) {
            $this->logger->notice("old login form");
            $this->http->SetInputValue("username", $this->AccountFields['Login']);
            $this->http->SetInputValue("passwd", $this->AccountFields['Pass']);
        } else {
            $this->logger->notice("new login form");

            if (!$this->http->ParseForm("login-username-form")) {
                return false;
            }
            $this->http->SetInputValue("username", $this->AccountFields['Login']);
            $this->http->SetInputValue("passwd", '');
            $this->http->SetInputValue("signin", "Next");
            $this->http->unsetInputValue("countryCodeIntl");
            $this->http->setMaxRedirects(10);
            $this->http->PostForm();

            // reCaptcha v.2
            if ($frame = $this->http->FindSingleNode("//iframe[@id = 'recaptcha-iframe']/@src")) {
                $this->http->NormalizeURL($frame);
                $this->http->GetURL($frame);

                if (!$this->http->ParseForm("recaptchaForm")) {
                    return false;
                }
                $captcha = $this->parseReCaptcha();

                if ($captcha === false) {
                    return false;
                }
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
                $this->http->PostForm();
            }

            // password form
            if (!$this->http->ParseForm(null, "//form[contains(@class, 'challenge-form')]")) {
                // Sorry, we don't recognize this email.
                if ($message = $this->http->FindPreg('/Sorry,[^<]+we[^<]+don\&\#x27;t[^<]+recognize[^<]+this[^<]+email\./')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, we don't recognize this account.
                if ($message = $this->http->FindPreg('/Sorry,[^<]+we[^<]+don\&\#x27;t[^<]+recognize[^<]+this[^<]+account\./')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, we were unable to proceed with your request. Please try again.
                if ($message = $this->http->FindPreg('/Sorry, we were unable to proceed with your request\. Please try again\./')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // strange new form https://home.bt.com/login/loginform?
                if ($this->http->ParseForm("frm_login")) {
                    $parts = parse_url($this->http->currentUrl());
                    parse_str($parts['query'], $query);
                    $target = $query['TARGET'] ?? null;
                    $this->logger->debug("Target: {$target}");

                    if (empty($target)) {
                        return false;
                    }
                    $target = str_replace('$SM$', '', $target);
                    $this->logger->debug("Target: {$target}");

                    $this->http->SetInputValue("USER", $this->AccountFields['Login']);
                    $this->http->SetInputValue("PASSWORD", $this->AccountFields['Pass']);
                    $this->http->SetInputValue("rememberMe", "on");

                    $this->form = $this->http->Form;
                    $this->formURL = $this->http->FormURL;

                    if (!$this->http->PostForm()) {
                        return false;
                    }
                    $this->http->GetURL($target);

                    return true;
                }

                return true;
            }
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("verifyPassword", "Next");

            $this->form = $this->http->Form;
            $this->formURL = $this->http->FormURL;
        }

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();

        // reCaptcha v.2
        if ($frame = $this->http->FindSingleNode("//iframe[@id = 'recaptcha-iframe']/@src")) {
            $this->http->NormalizeURL($frame);
            $this->http->GetURL($frame);

            if (!$this->http->ParseForm("recaptchaForm")) {
                return false;
            }
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->PostForm();
        }

        // AccountID: 4718317
        if (
            $this->http->Response['code'] == 404
            && $redirect = $this->http->FindPreg('/<META http-equiv="refresh" content="0;URL=\'([^\']+)/')
        ) {
            $this->http->GetURL($redirect);

            if ($this->http->FindSingleNode("//a[contains(@href, 'https://login.yahoo.com/config/login/?logout_all=')]/@href")) {
                $this->http->GetURL("https://answers.yahoo.com");
            }
        }

        $response = $this->http->JsonLog();

        if (isset($response->status, $response->url) && $response->status == 'redirect') {
            $this->logger->debug("Redirect: " . $response->url);
            $this->http->GetURL($response->url);
        } elseif (isset($response->status, $response->code) && $response->status == 'error') {
            switch ($response->code) {
                case '1212': case '1213':
                    throw new CheckException('Invalid ID or password. Please try again using your full Yahoo! ID.', ACCOUNT_INVALID_PASSWORD);

                case '1235':
                    throw new CheckException('This ID is not yet taken.', ACCOUNT_INVALID_PASSWORD);

                case '9999':
                    if (isset($response->challenge_data)) {
                        $rep = json_decode($response->challenge_data);
                        $this->logger->debug(var_export($rep->challenge_data, true), ["pre" => true]);

                        if (isset($response->challenge_data->z)) {
                            $this->z = $response->challenge_data->z;
                            $this->logger->debug("z -> " . var_export($rep->z, true), ["pre" => true]);
                        } elseif ($this->z = $this->http->FindPreg('/\"z.\":\s*.\"([^\"]+).\"/ims')) {
                            $this->logger->debug("z -> " . $this->z);
                        }
                        $this->logger->debug(var_export($rep->challenges, true), ["pre" => true]);

                        if (isset($rep->challenges[2]->data[0])) {
//                            $this->logger->debug(var_export($rep->challenges[2]->data[0], true), ["pre" => true]);
                            $this->response = $rep->challenges[2];
                            $this->parseQuestion($this->response->data[0]);
                        } elseif (isset($rep->challenges[0]->data[0])) {
//                            $this->logger->debug(var_export($rep->challenges[02]->data[0], true), ["pre" => true]);
                            $this->response = $rep->challenges[0];

                            foreach ($this->response->data[0] as $key => $value) {
                                $this->logger->debug(var_export($key, true), ["pre" => true]);
                                $this->logger->debug(var_export($value, true), ["pre" => true]);
                                $question = $value;

                                break;
                            }

                            if (isset($question)) {
                                $this->parseQuestion($question);

                                return false;
                            }
                        }
                    }

                break;

                default:
                    $this->logger->notice("Code: " . $response->code);
            }
        }

        // Change your password
        $scrumb = $this->http->FindSingleNode("//input[@name = '.scrumb']/@value");
        $done = $this->http->FindSingleNode("//input[@name = '.done']/@value");
        $src = $this->http->FindSingleNode("//input[@name = '.src']/@value");
        $st = $this->http->FindSingleNode("//input[@name = '.st']/@value");

        if ($this->http->ParseForm("enter-information-form")
            && isset($scrumb, $done, $src, $st)) {
            $this->http->GetURL("https://edit.yahoo.com/config/change_pw?.scrumb=" .
                $scrumb . "&.done=" . $done . "&.src=" . $src . "&.st=" . $st);
        }
        // Don’t get locked out of your account
        if ($this->http->FindPreg("/Don\’t get locked out of your(?:\s*|\&nbsp;)account/ims")
            && $this->http->ParseForm("send-sms-form")) {
            $this->logger->debug("Skip sending SMS");
            $this->http->SetInputValue("skipbtn", "Skip for now");
            $this->http->PostForm();
        }
        // I'll secure my account later
        // Don't get locked out!
        if ($later = $this->http->FindSingleNode('//a[
                @id = "update-upsell-tertiary-cta"
                or (contains(., "ll secure my account") and contains(., "later"))
                or (contains(text(), "Remind me") and contains(., "later"))
                or (contains(text(), "Skip for now"))
            ]/@href')
        ) {
            $this->logger->debug("Skip password update");
            $this->http->NormalizeURL($later);
            $this->http->GetURL($later);
        }
        // New Privacy and Terms
        if ($this->http->FindSingleNode("//h1[contains(text(), 'New Privacy and Terms') or contains(text(), 'Novos Termos e Privacidade da Oath')]") && $this->http->ParseForm(null, "//form[@action = '/consent']")) {
            $this->logger->debug("Skip New Privacy and Terms");
            $this->http->SetInputValue("disagree", "I'll do this later");
            $this->http->PostForm();
        }
        //# Update your password recovery information
        if ($this->http->FindSingleNode("//h1[contains(text(),'Update your password recovery information')]")
            || $this->http->FindSingleNode("//h1[contains(text(),'What if you lost access to your Yahoo! account?')]")
            || $this->http->FindSingleNode("//h1[contains(text(),'Never lose access to your account.')]")
            || $this->http->FindSingleNode("//*[contains(text(),'Change your password')]")
            || $this->http->FindSingleNode("//*[contains(text(),'Create a new password')]")
            || $this->http->FindPreg("/You may unlock your account immediately by changing your password/ims")) {
            $this->throwProfileUpdateMessageException();
        }
        // We’re asking users to change their passwords due to recent security incidents online.
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'We’re asking users to change their passwords due to recent security incidents online.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, we were unable to proceed with your request. Please try again.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, we were unable to proceed with your request. Please try again.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, your Yahoo Answers account is currently suspended
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, your Yahoo Answers account is currently suspended')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // You no longer have access to your Yahoo!
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'You no longer have access to your Yahoo! service because your')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // If you ever have trouble signing in, your up-to-date recovery email
        if ($this->http->FindSingleNode("//p[contains(text(), 'If you ever have trouble signing in, your up-to-date recovery email')]")
            // Click continue below to change your password and update your mobile number
            || (strstr($this->http->currentUrl(), 'https://login.yahoo.com/account/update?')
                && strstr($this->http->currentUrl(), 'tn=change_password&context=spreg_cpw&display=login&'))) {
            $this->throwProfileUpdateMessageException();
        }

        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Seus dados, sua experiência')]")
            && strstr($this->http->currentUrl(), 'https://consent.yahoo.com/br/collectConsent?sessionId')
        ) {
            $this->throwAcceptTermsMessageException();
        }

        if (
            $this->http->FindSingleNode("(//a[contains(@href, 'logout=1')]/@href)[1]")
            || $this->http->FindSingleNode("//a[contains(@class, 'UserProfileBanner__userName')]")
            || $this->http->FindPreg('/nickname:\s*\'([^\']+)/ims')
        ) {
            return true;
        }
        // Invalid password. Please try again.
        if ($message = $this->http->FindPreg("/>(Invalid[^<]+password\.[^<]+Please[^<]+try[^<]+again)\s*<\/p>/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        $message = $this->http->FindSingleNode("//p[contains(@class, 'error-msg')]");

        if ($message) {
            $this->logger->error($message);

            if ($this->http->FindPreg('/(Invalid password\. Please try again)/', false, $message)) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (!in_array($message, [
                'Oops, Something went wrong. Please try again.',
                'Please provide an Account Key.',
            ])
            ) {
                return false;
            }
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'yregertxt')]/strong")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // This ID is not yet taken.
        if ($message = $this->http->FindPreg("/(This ID is not yet taken\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid ID or password
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Invalid ID or password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, we don't recognize this email.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Sorry, we don\'t recognize this email.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The email and password you entered don't match.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "The email and password you entered don\'t match.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Account Locked Temporarily
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Account Locked Temporarily")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Join Yahoo Answers
        if ($this->http->FindSingleNode('//h1[contains(text(), "Join Yahoo Answers")]')) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (($this->http->ParseForm("push-polling-form") || $this->http->FindPreg("/Tap[^<]+on[^<]+the[^<]+Account[^<]+Key[^<]+push[^<]+notification[^<]+to[^<]+sign[^<]+inё./ims"))
            && !$this->http->FindPreg("/Get[^<]+Account[^<]+Key[^<]+code[^<]+and[^<]+enter[^<]+here/ims")) {
            $this->logger->notice("skip 'Account Key push'");
            /**
             * Tap on the Account Key push notification to sign in.
            verificationContext:ACCOUNT_ACCESS_ODP
             */
            $this->http->SetInputValue("skip", "Use text or email to sign in");

            if (!$this->http->PostForm()) {
                return false;
            }
        }

        // Questions v.3
        if ($this->http->FindPreg("/<p>Get the 8 character code and enter it/ims")) {
            $this->parseQuestion("Questions v.3");

            return false;
        }
        // Questions v.4
        // Get Account Key code and enter here
        if ($this->http->FindPreg("/Get[^<]+Account[^<]+Key[^<]+code[^<]+and[^<]+enter[^<]+here/ims")) {
            $this->parseQuestion("Questions v.4");

            return false;
        }
        // Questions v.5
        if ($this->http->FindSingleNode("//*[contains(text(), 'For security purposes, please verify the missing')] | //*[contains(text(), 'If you have access to this phone, please verify the missing')]")) {
            $this->parseQuestion("Questions v.5");

            return false;
        }

        // TODO: Weak filter
        // Questions v.2
        // First time signing in with this device?
        if (($this->http->ParseForm("f") || $this->http->FindPreg("/First[^<]+time[^<]+signing[^<]+in[^<]+with[^<]+this[^<]+device/ims"))
            && !$this->http->FindPreg("/Get[^<]+Account[^<]+Key[^<]+code[^<]+and[^<]+enter[^<]+here/ims")) {
            $this->http->PostForm();
            $this->parseQuestion("Questions v.2");

            return false;
        }

        if (($this->http->ParseForm(null, "//form[contains(@class, 'pure-form')]") || $this->http->FindPreg("/For[^<]your[^<]safety,[^<]choose[^<]a[^<]method[^<]below[^<]to[^<]verify[^<]that[^<]it\'s[^<]really[^<]you[^<]signing[^<]in[^<]to[^<]this[^<]account\./ims"))
            && !$this->http->FindPreg("/Get[^<]+Account[^<]+Key[^<]+code[^<]+and[^<]+enter[^<]+here/ims")) {
            $this->parseQuestion("Questions v.2");

            return false;
        }

        return false;
    }

    public function parseQuestion($question)
    {
        $this->logger->notice(__METHOD__);

        switch ($question) {
            case 'Questions v.5.1':
                $this->logger->debug("Questions v.5.1");

                if (!$this->http->ParseForm(null, "//form[contains(@class, 'pure-form-stacked')]")) {
                    return false;
                }

                if ($question = $this->http->FindSingleNode("//*[contains(text(), 'Enter the verification code we sent')]")) {
                    $phone = $this->http->FindSingleNode("//*[contains(text(), 'Enter the verification code we sent')]/following-sibling::p[contains(@class,'obfuscated-phone')]");
                    $this->Question = $question . ' ' . $phone;
                    // Keep state
                    $this->State["Version"] = 5.1;
                    $this->State["Form"] = $this->http->Form;
                } else {
                    return false;
                }

                break;

            case 'Questions v.5':
                $this->logger->debug("Questions v.5");

                if (!$this->http->ParseForm(null, "//form[contains(@class, 'pure-form-stacked')]")) {
                    return false;
                }

                if ($question = $this->http->FindSingleNode("//*[contains(text(), 'For security purposes, please verify the missing')] | //*[contains(text(), 'If you have access to this phone, please verify the missing')]")) {
                    $this->Question = 'For security purposes, please verify the missing digits which should be in place of underscores: ' . $this->http->FindSingleNode("//input[@name='obfuscatedPhone']/@value");
                    // Keep state
                    $this->State["Version"] = 5;
                    $this->State["Form"] = $this->http->Form;
                } else {
                    return false;
                }

                break;

            case 'Questions v.4':
                $this->logger->debug("Questions v.4");

                if (!$this->http->ParseForm(null, "//form[contains(@class, 'pure-form-stacked')]")) {
                    return false;
                }
                $this->Question = "Please enter an Account Key code from your Yahoo app."; /*review*/
                // Keep state
                $this->State["Version"] = 4;
                $this->State["Form"] = $this->http->Form;

                break;

            case 'Questions v.3':
                $this->logger->debug("Questions v.3");

                if (!$this->http->ParseForm(null, "//form[contains(@action, '/account/module/authorize/verify')] | //form[//input[@name = 'code']]")) {
                    return false;
                }
                $this->Question = "Please enter a 8 character code from your Yahoo app."; /*review*/
                // Keep state
                $this->State["Version"] = 3;

                break;

            case 'Questions v.2':
                $this->logger->debug("Questions v.2");

                if (!$this->http->ParseForm(null, "//form[contains(@class, 'pure-form')]")) {
                    return false;
                }

                $question = $this->http->FindSingleNode("(//form[contains(@class, 'pure-form')]//*[contains(text(), '@')])[1]");
                $this->logger->debug("Email: " . $question);
                $value = $this->http->FindSingleNode("
                    //form[contains(@class, 'pure-form')]//div[*[contains(text(), '" . $question . "')] and contains(., 'code')]/ancestor::div/following-sibling::button[@name = 'index']/@value
                    | //form[contains(@class, 'pure-form')]//div[*[contains(text(), '" . $question . "')] and contains(., 'code')]/following-sibling::button[@name = 'index']/@value
                ");

                if (isset($question, $value)) {
                    $question = "Please enter Identification Code which was sent to the following email address: $question. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                }

                if (!isset($question, $value) && !$this->http->FindSingleNode("(//form[contains(@class, 'pure-form')]//div[contains(text(), '@')])[1]")) {
                    $question = $this->http->FindSingleNode("(//form[contains(@class,'pure-form')]//p[contains(text(),'Msg & data rates')]/preceding-sibling::div[1]/span)[1]");
                    $this->logger->debug("Phone: " . $question);
                    $value = $this->http->FindSingleNode("(//form[contains(@class,'pure-form')]//p[contains(text(),'Msg & data rates')]/ancestor::div[1]/following-sibling::button[@name = 'index']/@value)[1]");

                    if (isset($question, $value)) {
                        $question = "Please enter Identification Code which was sent to the following phone number: $question. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                    }
                }// if (!isset($question, $value) && !$this->http->FindSingleNode("(//form[@name = 'frmConfirm']//div[contains(text(), '@')])[1]"))

                // TODO
//                if (!isset($question, $value) && !$this->http->FindSingleNode("//form[contains(@class, 'pure-form')]")) {
//                    // Send as text message
//                    $question = $this->http->FindSingleNode("//form[contains(@class, 'pure-form')]//p[contains(text(), 'Send as text')]");
//                    $value = $this->http->FindSingleNode("//form[contains(@class, 'pure-form')]//p[contains(text(), 'Send as text')]/following-sibling::div[@class='display-name']");
//                    $this->logger->debug("Phone: ".$question);
//
//                    if (isset($question, $value))
//                        $question = "Please enter Identification Code which was sent to the following phone number: $question. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
//                }

                if (isset($question, $value)) {
                    $this->Question = $question;

                    $this->http->SetInputValue("index", $value);
                    $this->http->PostForm();

                    if (!$this->http->ParseForm(null, "//form[contains(@class, 'verification-form')]")) {
                        return false;
                    }
                    // Keep state
                    $this->State["Form"] = $this->http->Form;
                    $this->State["Version"] = 2;
                    $this->State["index"] = $value;
                }// if (isset($question, $value))
                else {
                    return false;
                }

                break;

            default:// Questions v.1
                $this->logger->debug("Questions v.1");
                $this->http->Form = $this->form;
                $this->http->FormURL = $this->formURL;
                $this->logger->debug("Email: " . $question);
                // Keep state
                $this->State["Response"] = $this->response;
                $this->State["Form"] = $this->http->Form;
                $this->State["z"] = $this->z;
                $this->State["Version"] = 1;
                // logs
                $this->http->Log("<pre>" . var_export($this->response, true) . "</pre>", false);
//                $this->http->Log("<pre>". var_export($this->http->Form, true)."</pre>", false);
//                $this->http->Log("<pre>". var_export($this->http->FormURL, true)."</pre>", false);
                if (!isset($question) || !isset($this->response->data[0])) {
                    return true;
                }

                if (strstr($question, '@')) {
                    $this->http->GetURL("https://login.yahoo.com/config/login_unlock?z=" . $this->z . "&c_type=" . $this->response->type . "&c_idx=0&c_stype=EMAIL&login=" . $question . "&_lang=en-US&_intl=us&rnd=" . time() . date("B"));
                    // keep form data
                    $this->http->GetURL("https://login.yahoo.com/config/login?.done=http%3A%2F%2Fanswers.yahoo.com%2Fmy-activity&.src=knowsrch&.intl=us");
//                    if (!$this->http->ParseForm("login_form"))
//                        return false;
                    $this->Question = "Please enter Identification Code which was sent to the following email address: $question. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                }// if (strstr($question, '@'))
                else {
                    $this->Question = $question;
                }

                break;
        }// switch ($question)
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        // from state
        if (isset($this->State["Response"])) {
            $this->response = $this->State["Response"];
        }

        if (isset($this->State["z"])) {
            $this->z = $this->State["z"];
        }

        if (isset($this->State["Form"])) {
            $this->http->Form = $this->State["Form"];
        }
        // Logs
//        $this->http->Log("<pre>". var_export($this->response, true)."</pre>", false);
//        $this->http->Log("<pre>". var_export($this->http->Form, true)."</pre>", false);
//        $this->http->Log("<pre>". var_export($this->http->FormURL, true)."</pre>", false);
//        $this->http->Log("<pre>". var_export($this->response, true)."</pre>", false);
        $step = $this->State["Version"] ?? $step;

        switch ($step) {
            case 5.1:
                $this->logger->notice("Questions v.5.1");
                $this->http->SetInputValue('code', $this->Answers[$this->Question]);
                $this->http->SetInputValue('verifyCode', 'true');

                if (!$this->http->PostForm()) {
                    return false;
                }

                unset($this->Answers[$this->Question]);

                if ($error = $this->http->FindSingleNode("//div[contains(@class, 'error-msg')]")) {
                    $this->AskQuestion($this->Question, $error, 'Questions v.5.1');

                    return false;
                }

                if ($continue = $this->http->FindSingleNode("//a[contains(text(),'Continue') and contains(@href,'/consent')]/@href")) {
                    $this->http->GetURL($continue);
                }

                break;

            case 5:
                $this->logger->notice("Questions v.5");
                $this->http->SetInputValue("missingDigitsParts1", $this->Answers[$this->Question]);

                if (!$this->http->PostForm()) {
                    return false;
                }

                // Questions v.5.1
                if ($this->http->FindSingleNode("//*[contains(text(), 'We will send you a verification code to verify')]")) {
                    if (!$this->http->ParseForm(null, "//form[contains(@class, 'pure-form-stacked')]")) {
                        return false;
                    }
                    $this->http->SetInputValue('sendCode', $this->http->FindSingleNode("//button[@name='sendCode']/@value"));

                    if (!$this->http->PostForm()) {
                        return false;
                    }

                    $this->parseQuestion("Questions v.5.1");

                    return false;
                }

                break;

            case 4:
                $this->logger->notice("Questions v.4");
                $this->http->SetInputValue("code", $this->Answers[$this->Question]);

                if (!$this->http->PostForm()) {
                    return false;
                }
                unset($this->Answers[$this->Question]);

                if ($error = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect Account Key. Make sure that you are signed into')]")) {
                    $this->AskQuestion($this->Question, $error);

                    return false;
                }

                break;

            case 3:
                $this->logger->notice("Questions v.3");
                $this->http->SetInputValue("code", $this->Answers[$this->Question]);
//                $this->http->SetInputValue("verify", "1");
                $this->http->SetInputValue("verify", "Sign in");

                if (!$this->http->PostForm()) {
                    return false;
                }
                unset($this->Answers[$this->Question]);

                break;

            case 2:
                $this->logger->notice("Questions v.2");
                $this->http->SetInputValue("index", $this->State["index"]);
                $this->http->SetInputValue("code", $this->Answers[$this->Question]);
                $this->http->SetInputValue("verifyCode", "Verify");

                if (!$this->http->PostForm()) {
                    return false;
                }
                // Invalid verification code. Please try again.
                if ($error = $this->http->FindSingleNode("//span[contains(text(), 'Invalid verification code.')]")) {
                    $this->AskQuestion($this->Question, $error);

                    return false;
                }// if ($error = $this->http->FindSingleNode("//span[contains(text(), 'Invalid verification code.')]"))
                unset($this->Answers[$this->Question]);
//                $this->sendNotification("Yahoo! (Answers). Answer was entered. Questions v.2");
                if ($continue = $this->http->FindSingleNode("//a[contains(text(),'Continue') and contains(@href,'/consent')]/@href")) {
                    $this->http->GetURL($continue);
                }

                break;

            default:// Question v.1
                $this->logger->notice("Questions v.1");

                if (!isset($this->response->type, $this->z)) {
                    return false;
                }

                if (strstr($this->Question, '@')) {
                    $this->http->SetInputValue(".2ndChallenge_email_code", $this->Answers[$this->Question]);
                } else {
                    $this->http->SetInputValue(".2ndChallenge_pwqa_ans_in", $this->Answers[$this->Question]);
                    $this->http->SetInputValue(".ndChallenge_pwqa_quest_in", $this->Question);
                }
                $this->http->SetInputValue(".2ndChallenge_type_in", $this->response->type);
                $this->http->SetInputValue(".z", $this->z);
                $this->http->SetInputValue(".ws", '1');

                if (!$this->http->PostForm()) {
                    return false;
                }
                $this->sendNotification("Yahoo! (Answers). Answer was entered");
//                // redirect
//                if (isset($this->response->status, $this->response->url) && $this->response->status == 'redirect') {
//                    $this->http->Log("Redirect to: {$this->response->url}");
//                    $this->http->NormalizeURL($this->response->url);
//                    $this->http->GetURL($this->response->url);
//                }
                $response = $this->http->JsonLog();
                // redirect
                if (isset($response->status, $response->url) && $response->status == 'redirect') {
                    $this->http->Log("Redirect to: {$response->url}");
                    $this->http->NormalizeURL($response->url);
                    $this->http->GetURL($response->url);
                }// if (isset($response->status, $response->url) && $response->status == 'redirect')

                if (isset($this->response->data[0])) {
                    $question = $this->response->data[0];
                }

                if ($this->http->FindPreg('/status":"error","sub_code":"9997"/ims') && isset($question)) {
                    $this->parseQuestion($question);

                    return false;
                }// if ($this->http->FindPreg('/status":"error","sub_code":"9997"/ims') && isset($question))
        }// switch ($this->State["Version"])

        return true;
    }

    public function Parse()
    {
//        $this->http->GetURL("http://answers.yahoo.com/activity");
        // Name
        $this->SetProperty("Name",
            $this->http->FindSingleNode("//div[@id = 'yucs-fs-name'] | //span[@class = 'nickname'] | //a[contains(@class, 'UserProfileBanner__userName')]")
            ?? $this->http->FindPreg('/nickname:\s*\'([^\']+)/ims')
        );
        // Level
        $this->SetProperty("Level", $this->http->FindPreg('/<span[^>]*id="level-text"[^>]*>\s*<p[^>]+>([^<]+)<\/p>/ims') ?? $this->http->FindSingleNode('//div[contains(@class, "LevelBadge__levelBadgeLarge__")]'));
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('
            //div[not(contains(@class, "LeaderBoard__leaderBoardListHeader"))]/span[normalize-space(text()) = "Points"]/preceding-sibling::span[1]
            | //div[contains(@class, "UserProfileBanner__progressText")]
        '));
        // Best Answers
        $this->SetProperty("BestAnswers", $this->http->FindSingleNode("//span[normalize-space(text()) = 'Best Answers']/preceding-sibling::span[1]"));
        // Answers
        $this->SetProperty("Answers", $this->http->FindSingleNode("//span[normalize-space(text()) = 'Answers']/preceding-sibling::span[1]"));
        // Questions
        $this->SetProperty("Questions", $this->http->FindSingleNode("//span[normalize-space(text()) = 'Questions']/preceding-sibling::span[1]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Join Yahoo! Answers')] | //h1[contains(text(), 'Join Yahoo Answers')]")) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }// if ($this->http->FindSingleNode("//h1[contains(text(), 'Join Yahoo! Answers')]"))
            else {
                // Change your password
                if ($this->http->FindSingleNode("//legend[contains(text(), 'Change your password')]")) {
                    throw new CheckException("Yahoo! (Answers) website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://login.yahoo.com/config/login;_ylt=AwrB2.XxlStUKFwAuSvj1KIX?.src=knowsrch&.intl=us&.lang=en-US&.done=https://answers.yahoo.com/activity';
        $arg['SuccessURL'] = 'http://answers.yahoo.com/';

        return $arg;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'recaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            $key = $this->http->FindPreg("/siteKey&#x3D;([^\&]+)/");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
