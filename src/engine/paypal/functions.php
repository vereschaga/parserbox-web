<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerPaypal extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;
    use ProxyList;

    public const XPATH_CAPTCHA = "//input[@id = 'splitPasswordCaptcha' or @id = 'splitEmailCaptcha' or @id = 'splitHybridCaptcha']";
    public const XPATH_SMS_TO_PHONE = "//p[span[contains(text(), 'Receive a text') or contains(text(), 'Receive an SMS') or contains(text(), 'Reciba un mensaje de texto') or contains(text(), '接收短信') or contains(text(), 'Een sms-bericht ontvangen') or contains(text(), 'Receber uma mensagem de texto') or contains(text(), 'Recevoir un message texte') or contains(text(), 'Recibir un mensaje de texto') or contains(text(), 'Ricevi un SMS') or contains(text(), 'Recibir un SMS') or contains(text(), 'Получить SMS-сообщение') or contains(text(), 'Recevoir un SMS') or contains(text(), 'Solicite que le llamemos') or contains(text(), 'SMS erhalten') or contains(text(), '接收簡訊') or contains(text(), 'Получить SMS-сообщение') or contains(text(), 'SMS 메시지 수신') or contains(text(), '接收短訊') or contains(text(), 'Få sms') or contains(text(), 'SMSを受信する') or contains(text(), 'Een sms-bericht ontvangen') or contains(text(), 'Motta en tekstmelding') or contains(text(), 'Vastaanota tekstiviesti')]]/following-sibling::*[@class = 'verification-method']
                    | //p[contains(text(), 'Receive a text') or contains(text(), 'Receive an SMS') or contains(text(), 'Получить SMS-сообщение') or contains(text(), '接收短信') or contains(text(), 'SMS erhalten') or contains(text(), 'Recibir un mensaje de texto') or contains(text(), '接收簡訊') or contains(text(), 'Recevoir un message texte') or contains(text(), 'Recevoir un SMS') or contains(text(), 'Een sms-bericht ontvangen') or contains(text(), 'Ricevi un SMS') or contains(text(), 'Motta en tekstmelding') or contains(text(), 'Vastaanota tekstiviesti') or contains(text(), 'Få sms')]/following-sibling::*[@class = 'verification-method']
                    | //p[contains(text(), 'Receive a text') or contains(text(), 'Receive an SMS') or contains(text(), 'Получить SMS-сообщение') or contains(text(), '接收短信') or contains(text(), 'SMS erhalten') or contains(text(), 'Recibir un mensaje de texto') or contains(text(), '接收簡訊') or contains(text(), 'Recevoir un message texte') or contains(text(), 'Recevoir un SMS') or contains(text(), 'Een sms-bericht ontvangen') or contains(text(), 'Ricevi un SMS') or contains(text(), 'SMS') or contains(text(), 'Receber uma mensagem de texto') or contains(text(), '接收短訊') or contains(text(), 'Motta en tekstmelding') or contains(text(), 'Vastaanota tekstiviesti') or contains(text(), 'Quero receber uma mensagem de texto') or contains(text(), 'Få sms')]/following-sibling::div/*[@class = 'verification-method']";

    private $business = false;
    private $seleniumURL = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (!isset($properties['SubAccountCode'])
            || (!strstr($properties['SubAccountCode'], "paypal"))) {
            if (isset($properties['Currency'])) {
                switch ($properties['Currency']) {
                    case 'USD':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                        break;

                    case 'GBP':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                        break;

                    case 'EUR':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                        break;

                    case 'BRL':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "R$%0.2f " . $properties['Currency']);

                        break;

                    case 'AUD':
                    case 'CAD':
                    case 'HKD':
                    case 'MXN':
                    case 'NZD':
                    case 'SGD':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f " . $properties['Currency']);

                        break;

                    case 'JPY':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "¥%0.2f " . $properties['Currency']);

                        break;

                    case 'INR':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "₹%0.2f " . $properties['Currency']);

                        break;

                    case 'MYR':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "RM%0.2f");

                        break;

                    case 'ILS':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&#8362;%0.2f " . $properties['Currency']);

                        break;

                    case 'PHP':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "P%0.2f " . $properties['Currency']);

                        break;

                    case 'TWD':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "NT$%0.2f " . $properties['Currency']);

                        break;

                    case 'THB':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "฿%0.2f " . $properties['Currency']);

                        break;

                    default:
                        /*
                        if (strlen($properties['Currency']) < 3) {
                            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "{$properties['Currency']}%0.2f");
                        }
                        */
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
                }// switch ($properties['Currency'])
            }// if (isset($properties['Currency']))

            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        // for retries
//        $this->http->setDefaultHeader("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");
//        $this->http->unsetDefaultHeader("X-Requested-With");

        $this->http->setCookie("LANG", "en_US%3bUS", ".paypal.com");
        $this->http->GetURL("https://www.paypal.com/signin/?country.x=US&locale.x=en_US");

        return $this->selenium();

        if (!$this->http->ParseForm("login")) {
            return false;
        }
        $this->http->SetInputValue("login_email", $this->AccountFields['Login']);
        $this->http->SetInputValue("login_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("fn_sync_data", "%7B%22f%22%3A%22debe92f7d89c43c8814a0430f14ed984%22%2C%22s%22%3A%22UNIFIED_LOGIN%22%2C%22chk%22%3A%7B%22ts%22%3A1500894960157%2C%22eteid%22%3A%5B4228806527%2C8660879481%2C11461823553%2C3459404827%2C9243982456%2C10645963641%2C10819029218%2C-2027848999%5D%2C%22tts%22%3A24854%7D%2C%22dc%22%3A%22%7B%5C%22screen%5C%22%3A%7B%5C%22colorDepth%5C%22%3A24%2C%5C%22pixelDepth%5C%22%3A24%2C%5C%22height%5C%22%3A900%2C%5C%22width%5C%22%3A1440%2C%5C%22availHeight%5C%22%3A832%2C%5C%22availWidth%5C%22%3A1440%7D%2C%5C%22ua%5C%22%3A%5C%22Mozilla%2F5.0%20(Macintosh%3B%20Intel%20Mac%20OS%20X%2010_12_6)%20AppleWebKit%2F537.36%20(KHTML%2C%20like%20Gecko)%20Chrome%2F59.0.3071.115%20Safari%2F537.36%5C%22%7D%22%2C%22err%22%3A%22%22%7D");

//        $this->http->SetInputValue("flow_name", "signin");
//        $this->http->SetInputValue("fso_enabled", "17");
//        $this->http->SetInputValue("bp_mid", "v=1;a1=na~a2=na~a3=na~a4=Mozilla~a5=Netscape~a6=5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36~a7=20030107~a8=na~a9=true~a10=~a11=true~a12=MacIntel~a13=na~a14=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36~a15=true~a16=ru~a17=windows-1251~a18=www.paypal.com~a19=na~a20=na~a21=na~a22=na~a23=1440~a24=900~a25=24~a26=831~a27=na~a28=Tue May 12 2015 13:52:21 GMT+0500 (YEKT)~a29=5~a30=na~a31=yes~a32=na~a33=yes~a34=no~a35=no~a36=yes~a37=yes~a38=online~a39=no~a40=MacIntel~a41=yes~a42=no~");
        unset($this->http->Form['captcha']);

        $this->http->setDefaultHeader("Accept", "application/json, text/javascript, */*; q=0.01");
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.paypal.com/";

        return $arg;
    }

    public function captchaForm()
    {
        $this->logger->notice(__METHOD__);
        $jse = $this->http->FindPreg("/'jse';jsenode.value = '([^\']+)/");
        $debugnodeName = $this->http->FindPreg("/debugnode.name = '([^\']+)/");
        $debugnodeValue = $this->http->FindPreg("/debugnode.value = '([^\']+)/");
        $xppcts = $this->http->FindPreg('/xppcts(?:.+?x20|\s*=\s*)(\w+)/');
        $captcha = $this->parseCaptcha();

        if ($captcha === false || !$this->http->ParseForm("challenge")) {
            return false;
        }
        $this->http->SetInputValue("captcha", $captcha);
        $this->http->SetInputValue("jse", $jse);
        $this->http->SetInputValue("ads_token_js", "61523h0302d9m8r7g026348070p2r3mla85ded8e3251d5ba");
//        if ($debugnodeName) {
//            $this->http->SetInputValue($debugnodeName, $debugnodeValue);
//        }
//        if ($xppcts) {
//            $this->http->setCookie('xppcts', $xppcts);
//        }
        $this->http->SetInputValue("continue", "Continue");
        $headers = [
            'Referer'                   => 'https://www.paypal.com/signin?country.x=US&locale.x=en_US',
            'Upgrade-Insecure-Requests' => '1',
        ];

        if (!$this->http->PostForm($headers)) {
            return false;
        }

        return true;
    }

    public function Login()
    {
//        if (!$this->http->PostForm())
//            return false;

        $response = $this->http->JsonLog();
        // captcha
        if (isset($response->htmlResponse)) {
            $this->http->SetBody($response->htmlResponse);
            $recognizerAttempt = 0;
            $this->logger->debug("[Try recognize captcha]: #" . $recognizerAttempt);

            $this->captchaForm();
            $response = $this->http->JsonLog();

//            do {
//                $recognizerAttempt++;
//                if (isset($response->htmlResponse)) {
//                    $this->logger->debug("[Try recognize captcha]: #".$recognizerAttempt);
//                    $this->http->SetBody($response->htmlResponse);
//                    if ($wrongAnser = $this->http->FindSingleNode("//p[contains(text(), 'The code you entered is incorrect. Please try again.')]"))
//                        $this->recognizer->reportIncorrectlySolvedCAPTCHA();
//                    $this->captchaForm();
//                    $response = $this->http->JsonLog();
//                }// if (isset($response->htmlResponse))
//            }
//            while (isset($wrongAnser) && !is_null($wrongAnser) && $recognizerAttempt < 4);
        }// if ($captcha = $this->parseCaptcha())

        $this->http->setDefaultHeader("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");

        if (isset($response->returnUrl)) {
            $returnUrl = $response->returnUrl;
            $this->logger->debug("returnUrl -> {$returnUrl}");
            $this->http->NormalizeURL($returnUrl);
            $this->http->GetURL($returnUrl);

            if ($this->verificationAccount()) {
                return false;
            }
        }// if (isset($response->returnUrl)) {
        elseif (isset($response->notifications->msg)) {
            $this->logger->debug("MSG -> {$response->notifications->msg}");
            /*
             * https://www.paypal.com/auth/validatecaptcha
             *
             * _csrf=EZaMhd4m9Y/Pn0xNEEOw/nTc1zx4rFuR6FLTE=
             * ads_token=6bc122ec0bbbf6a3431540bccd090293
             * ads_token_js=6-8241228940-82-82-82106-933431540-824486090293
             * captcha=
             */
            if (strstr($response->notifications->msg, 'Please enter the captcha code.')) {
                $csrf = $response->_csrf;
//                $this->http->GetURL($response->captcha->captchaImgUrl);
//                if (!$this->http->PostForm())
//                    return false;
            }

            if ($response->notifications->msg == 'Some information you entered isn\'t right.') {
                throw new CheckException(stripcslashes($response->notifications->msg), ACCOUNT_INVALID_PASSWORD);
            }
            // Some of your info isn\'t correct. Please try again.
            if ($response->notifications->msg == 'Some of your info isn\'t correct. Please try again.') {
                throw new CheckException(stripcslashes($response->notifications->msg), ACCOUNT_INVALID_PASSWORD);
            } elseif (strstr($response->notifications->msg, "Sorry, we can't log you in. If you think there's a problem with your account")) {
                throw new CheckException(str_replace('<a href="/us', '<a target="_blank" href="https://www.paypal.com/us', $response->notifications->msg), ACCOUNT_INVALID_PASSWORD);
            } elseif (strstr($response->notifications->msg, "It looks like you've tried too many times. Try again later")) {
                throw new CheckException(str_replace('<a href="/us/cgi-bin/helpscr?cmd=_help', '<a target="_blank" href="https://www.paypal.com/us/cgi-bin/helpscr?cmd=_help', $response->notifications->msg), ACCOUNT_PROVIDER_ERROR);
            } else {
                $this->logger->error("Error -> " . $response->notifications->msg);
            }
        }// elseif (isset($response->notifications->msg))
        elseif // Invalid credentials
        ($message = $this->http->FindSingleNode("(//div[contains(@class, 'alert-warning')])[1]")) {
            if (strstr($message, 'Please make sure you enter your email address and password correctly')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } elseif (strstr($message, 'Your email address needs to be in the format username@domain.com')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } elseif (strstr($message, "We're sorry, but we're having trouble completing your request")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } elseif (strstr($message, "Provide information about your account.")) {
                $this->http->GetURL("https://www.paypal.com/webapps/business/money");
                $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'available')]"));
            } elseif (strstr($message, "We're sorry. We need you to login again to verify additional security information.")) {
                $this->logger->notice(">>> " . $message);

                if (!$this->http->ParseForm("login_form")) {
                    return false;
                }
                $this->http->SetInputValue("login_email", $this->AccountFields['Login']);
                $this->http->SetInputValue("login_password", $this->AccountFields['Pass']);
                $this->http->SetInputValue("submit", "Log In");
                $this->http->PostForm();
                // Confirm Account Ownership
                if ($this->http->FindSingleNode('//p[contains(text(), "We\'re unable to validate your security key. For your protection, we\'ve restricted access to your account.")]') && $this->http->ParseForm(null, 1, true, "//form[@class = 'edit']")) {
                    $this->logger->notice("Go to security question page");
                    $this->http->SetInputValue("verify_identity_2fa", "verify_identity_2fa");
                    $this->http->PostForm();
                }
            } else {
                $this->logger->error("ERROR -> $message");
            }
        }// if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-warning')]"))
        // Some information you entered isn't right.
        // // We're sorry, we can't log you in.
        // For security reasons, you’ll need to reset your password.
        // We're having some trouble completing your request. Please try again shortly.
        // It looks like you've tried too many times. Try again later, or reset your password.
        if ($message = $this->http->FindSingleNode('//section[@id = "login"]//p[
                contains(text(), "Some information you entered isn\'t right.")
                or contains(text(), "We\'re sorry. We can\'t log you in.")
                or contains(text(), "We\'re sorry, we can\'t log you in.")
                or contains(text(), "For security reasons, you’ll need to")
                or contains(text(), "We\'re having some trouble completing your request. Please try again shortly.")
                or contains(text(), "It looks like you\'ve tried too many times. Try again later, or ")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, we can't log you in. If you think there's a problem with your account, contact us and we'll help resolve it.
        if ($message = $this->http->FindSingleNode('(//section[@id = "login"]//p[
                        contains(text(), "Sorry, we can\'t log you in. If you think there\'s a problem with your account")
                        or contains(text(), "Wir können Sie nicht einloggen. Falls Sie Hilfe brauchen, wenden Sie sich an den")
                        or contains(text(), "Não foi possível acessar sua conta.")
                        or contains(text(), "Nous ne pouvons pas vous connecter.")
                        or contains(text(), "Inloggen is niet mogelijk.")
                        or contains(text(), "Lo sentimos, pero no puede iniciar sesión en este momento. Si necesita ayuda,")
                        or contains(text(), "抱歉，我們無法讓你登入。請")
                        or contains(text(), "You haven’t confirmed your mobile yet. Use your email for now.")
                        or contains(text(), "מצטערים, לא ניתן להכניס אותך לחשבון.")
                    ])[1]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // It wasn't possible to log you in. Please contact customer service for more help, or try logging in again.
        if ($message = $this->http->FindSingleNode('//section[@id = "login"]//p[contains(text(), "It wasn\'t possible to log you in")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Something went wrong on our end
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Something went wrong on our end")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Update account information
        if ($this->http->FindSingleNode('//h2[contains(text(), "Update account information")]')
            // Boost your login options
            || $this->http->FindSingleNode('//h1[contains(text(), "Boost your login options")]')
            // Skip offer
            || $this->http->FindSingleNode("//a[contains(text(), 'Proceed to Account Overview')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Go to Account Overview')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Naar mijn rekening')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Zu Ihrem Kundenkonto')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Vai al tuo conto')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Перейти на страницу обзора счета')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Ir a mi cuenta')]")
            || $this->http->FindSingleNode("//p[contains(@class, 'account-btn')]/a[contains(@href, 'cgi-bin/webscr?cmd=_account')]")) {
            $this->logger->notice("Skip offer");
            $this->http->GetURL("https://www.paypal.com/myaccount/summary/");
        }

        // skip phone confirmation
        if ($this->http->FindSingleNode("//form[@action = '/signin/phone-confirmation/send-otp']")) {
            $this->http->GetURL("https://www.paypal.com/myaccount/home");
        }

        if ($this->http->FindSingleNode('//h2[contains(text(), "Update account information")]')
            // Boost your login options
            || $this->http->FindSingleNode('//h1[contains(text(), "Boost your login options")]')
            // Skip offer
            || $this->http->FindSingleNode("//a[contains(text(), 'Proceed to Account Overview')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Go to Account Overview')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Naar mijn rekening')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Zu Ihrem Kundenkonto')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Vai al tuo conto')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Перейти на страницу обзора счета')]")
            || $this->http->FindSingleNode("//a[contains(text(), 'Ir a mi cuenta')]")
            || $this->http->FindSingleNode("//p[contains(@class, 'account-btn')]/a[contains(@href, 'cgi-bin/webscr?cmd=_account')]")) {
            $this->logger->notice("Skip offer");
            $this->http->GetURL("https://www.paypal.com/myaccount/summary/");
        }
        // Tell us about your business
        if ($this->http->FindSingleNode('//h1[contains(text(), "Tell us about your business")]')
            // Why is my account access limited?
            || $this->http->FindSingleNode('//strong[contains(text(), "Why is my account access limited?")]')
            || $this->http->FindSingleNode('//p[contains(text(), "我们发现了一些异常活动，需要您帮助我们确保您的账户安全。请点击“下一步”进行身份验证并更改您的密码。")]')
            || $this->http->FindSingleNode('//p[contains(text(), "We noticed some unusual activity on your account and need your help making it more secure.") and contains(., "Create a new password that is unique to this account.")]')
            || $this->http->FindSingleNode('//p[contains(text(), "Nous avons constaté une activité inhabituelle et nous avons besoin de votre aide pour sécuriser votre compte.")]')
            || $this->http->FindSingleNode('//p[contains(text(), "Choose a password that\'s hard to guess and unique to this account.")]')
            || $this->http->FindSingleNode('//p[contains(text(), "Choose a password and PIN that\'s hard to guess and unique to this account.")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Change your login information")]')
            /*
             * PayPal is looking out for you
             *
             * We noticed some unusual activity on your account and suggest that
             * you create a new password to help ensure that your account remains secure.
             */
            || strstr($this->seleniumURL, 'https://www.paypal.com/authflow/password-recovery/change')
            // This information is required to verify your identity. ...
            || $this->http->currentUrl() == 'https://www.paypal.com/us/merchantsignup/personalInfo') {
            $this->throwProfileUpdateMessageException();
        }

        // Confirm a different way
        if ($href = $this->http->FindSingleNode("//a[contains(text(), 'Confirm a different way') or @class = 'tryDifferentWay']/@href")) {
            $this->logger->notice("Redirect to SQ -> We just need to confirm it’s you");
            $this->http->NormalizeURL($href);
            $this->http->GetURL($href);
        }
        // check question
        if ($this->parseQuestion()) {
            return false;
        }

        // business
        if ($this->http->FindPreg("/prop7=\"business\";/ims") || $this->http->FindPreg("/\"type\":\"BUSINESS\"\}\,/ims")) {
            $this->business = true;
        }

        if ($redirectLink = $this->http->FindSingleNode("//a[contains(@href, 'cmd=%5flogin%2ddone')]/@href")) {
            $this->http->GetURL($redirectLink);
        }

        if ($this->http->FindSingleNode("//input[@id = 'remindLater']/@id") && $this->http->ParseForm("addForm")) {
            $this->logger->notice("Skip mobile confirmation");
            $this->http->FormURL = 'https://www.paypal.com/webapps/customerprofile/skip';
            $this->http->SetInputValue("formAction", "add");
            $this->http->SetInputValue("remindLater", "Remind Me Later");
        }

        if ($this->http->currentUrl() == 'https://www.paypal.com/businessexp/fees/interchange-fees'
            || $this->http->currentUrl() == 'https://www.paypal.com/webscr') {
            $this->throwProfileUpdateMessageException();
        }

        $this->logger->notice("Account -> " . (($this->business) ? "business" : "premier"));

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
            || $this->http->FindSingleNode("(//a[contains(@href, 'signout')]/@href)[1]")
            || $this->http->currentUrl() == 'https://www.paypal.com/webapps/business/'
            || strstr($this->http->currentUrl(), 'country_lang.x=true')
            || strstr($this->http->currentUrl(), 'businessexp/summary')
            || strstr($this->http->currentUrl(), 'https://www.paypal.com/mep/dashboard') // redirect from https://www.paypal.com/businessexp/summary
            || $this->business == 'business') {
            return true;
        }

        if ($this->http->currentUrl() == 'https://www.paypal.com/us/merchantsignup/businessInfo') {
            $this->throwProfileUpdateMessageException();
        }
        // We just need to confirm it’s you. To continue, simply respond to the notification we sent to your PayPal app.
        if ($message = $this->http->FindPreg("/(?:We just need to confirm it\’s you\. To continue, simply respond to the notification we sent to your PayPal app\.|Wir wollen nur kurz bestätigen, dass Sie es sind. Antworten Sie einfach auf die Nachricht in Ihrer PayPal-App\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Some of your info isn't correct. Please try again.
        if ($message = $this->http->FindSingleNode('//section[@id = "login"]//p[contains(text(), "Some of your info isn\'t correct.")]')) {
            // Some of your info isn't correct. Need a hand?
            if (strstr($message, "Need a hand?")) {
                throw new CheckException("Some of your info isn't correct. Please try again.", ACCOUNT_INVALID_PASSWORD);
            } else {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }
        // Sorry, we can't log you in. If you think there's a problem with your account, contact us and we'll help resolve it.
        if ($message = $this->http->FindSingleNode('//section[@id = "login"]//p[contains(text(), "Sorry, we can\'t log you in. If you think there\'s a problem with your account") or contains(text(), "Wir können Sie nicht einloggen. Falls Sie Hilfe brauchen, wenden Sie sich an den") or contains(text(), "Nous ne pouvons pas vous connecter.") or contains(text(), "Inloggen is niet mogelijk.") or contains(text(), "Lo sentimos, pero no puede iniciar sesión en este momento. Si necesita ayuda,")]')) {
            throw new CheckException(str_replace('<a href="/us/cgi-bin/helpscr?cmd=_help', '<a target="_blank" href="https://www.paypal.com/us/cgi-bin/helpscr?cmd=_help', $message), ACCOUNT_INVALID_PASSWORD);
        }
        // Some of your info is incorrect. Try again or log in using your email address or user ID.
        if ($message = $this->http->FindSingleNode('//section[@id = "login"]//p[contains(text(), "Some of your info is incorrect. Try again or log in using your")]')) {
            throw new CheckException(strip_tags($message), ACCOUNT_INVALID_PASSWORD);
        }
        // Váš požadavek bohužel nemůžeme zpracovat. Zkuste to znovu později.
        if ($message = $this->http->FindSingleNode('//h2[@id = "error-header" and contains(text(), "Váš požadavek bohužel nemůžeme zpracovat. Zkuste to znovu později.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "verificationFailed"]//h1[ 
                contains(text(), "We\'re sorry, we couldn\'t confirm it\'s you.")
                or contains(text(), "抱歉，無法確認你的身份")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkAnswers()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("state: " . var_export($this->State, true));

        if (isset($this->LastRequestTime)) {
            $timeFromLastRequest = time() - $this->LastRequestTime;
        } else {
            $timeFromLastRequest = SECONDS_PER_DAY * 30;
        }
        $this->logger->debug("time from last code request: " . $timeFromLastRequest);

        if ($timeFromLastRequest > 300 && count($this->Answers) > 0) {
            $this->logger->notice("resetting answers, expired");
            $this->Answers = [];
        }
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        if ($this->http->FindSingleNode("//p[contains(text(), 'Confirm your phone number and 6-digit code.')]")
            && $this->http->ParseForm("otploginform")) {
            $mobile = $this->http->FindSingleNode("//input[@name = 'mobile']/@value");
            $this->logger->debug(">>> Your mobile number: {$mobile}");

            // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $this->State["CodeSent"] = true;
            $this->State["CodeSentDate"] = time();
            $this->State["FormWithSMS"] = $this->http->Form;
            $this->State["URLFormWithSMS"] = $this->http->FormURL;

            $question = "Please enter Security Code which was sent to the following phone number: $mobile. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            $this->State["Question"] = $question;

            $this->http->SetInputValue("send_sms", "Send SMS");
            $this->http->SetInputValue("cmd", "_flow");
            $this->logger->debug("sending code to {$mobile}");

            if ($this->http->PostForm()) {
                $this->AskQuestion($question, null, "Question");

                return true;
            }// if ($this->http->PostForm())
        } elseif ($this->http->FindSingleNode('//p[contains(text(), "We don\'t recognize the device you\'re using.")]')
                && $this->http->ParseForm("authFrm")) {
            $phone = $this->http->FindSingleNode("//select[@id = 'phoneOption']//option[@value != '']");
            $this->logger->debug(">>> Your phone number: {$phone}");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $this->State["CodeSent"] = true;
            $this->State["CodeSentDate"] = time();
            $this->State["URLFormWithCode"] = $this->http->FormURL;
            $this->State["FormWithCode"] = $this->http->Form;

            $question = "Please enter your 6-digit code";
            $this->State["Question"] = $question;

            $this->http->SetInputValue("_default_continue_btn_label", "Continue");
            $this->http->SetInputValue("_eventId_continue", "Continue");
            $this->http->SetInputValue("_sms_ivr_continue_btn_label", "Continue");
            $this->http->SetInputValue("jsEnabled", "1");
            $this->http->SetInputValue("phoneOption", "0");
            $this->http->SetInputValue("selectOption", "IVR");
//            $this->http->SetInputValue("execution", "e1s1");

            $this->logger->debug("call to phone -> {$phone}");

            if ($this->http->PostForm()) {
                if (!isset($securityCode)) {
                    return false;
                }

                // When you receive the call, please enter the security code using your phone's keypad.
                $securityCode = $this->http->FindSingleNode("//div[@id = 'phoneInput']");
                $this->logger->debug(">>> Security code: {$securityCode}");

                $question = "When you receive the call to phone -> {$phone}, please enter the security code ({$securityCode}) using your phone's keypad. Then please enter your 6-digit code bellow and click 'Ok'"; /*review*/

                if (!$this->http->ParseForm("authFrm")) {
                    return false;
                }

                $this->AskQuestion($question, null, "Question");
                $this->sendNotification("call was made on the phone // RR");

                return true;
            }// if ($this->http->PostForm())
        } elseif (($this->http->FindSingleNode("//h2[
                    contains(text(), 'Confirm your identity by answering security questions')
                    or contains(text(), 'Rispondi alle domande di sicurezza')
                ]")
                && $this->http->ParseForm("authform"))
            || $this->http->ParseForm(null, '//form[contains(@class, "securityQuestionsForm")]')
        ) {
            $this->logger->notice(">>> Just questions");

            $this->State["questionCount"] = count($this->http->FindNodes("//label[contains(@for, 'answer') or contains(@for, 'SECURITY_QUESTION_')]"));

            $csrf = $this->http->FindPreg("/_csrf\":\"([^\"]+)/");

            if ($csrf) {
                $this->State["Version"] = 'sq_2018';
                $this->State["Form"] = [
                    "_csrf"                        => $csrf,
                    "action"                       => "ANSWER",
                    "answer"                       => [], // array of answers
                    "clientInstanceId"             => $this->http->FindPreg("/clientInstanceId=([^&]+)/", false, $this->seleniumURL) ?? "7b34b777-77ea-4819-becb-fbe882fcbfe9",
                    "selectedChallengeAnswerIndex" => 0,
                    "selectedChallengeType"        => "securityQuestions",
                ];
            } else {
                unset($this->State["Version"]);
                unset($this->State["Form"]);
            }

            $needAnswer = false;

            for ($n = 0; $n < $this->State["questionCount"]; $n++) {
                $question = $this->http->FindSingleNode("//label[contains(@for, 'answer') or contains(@for, 'SECURITY_QUESTION_')]", null, false, null, $n);

                if (isset($question)) {
                    $this->http->Form["Question" . ($n + 1)] = $question;
                    $this->http->Form["InputQuestion" . ($n + 1)] = $this->http->FindSingleNode("(//label[contains(@for, 'answer') or contains(@for, 'SECURITY_QUESTION_')]/following-sibling::span[1]/input/@name)[" . ($n + 1) . "] | (//label[contains(@for, 'answer') or contains(@for, 'SECURITY_QUESTION_')]/following-sibling::input/@name)[" . ($n + 1) . "]");

                    if (!isset($this->Answers[$question])) {
                        $this->AskQuestion($question, null, "Question");
                        $needAnswer = true;
                    }// if (!isset($this->Answers[$question]))
                }// if (isset($question))
            }// for ($n = 0; $n < $this->State["questionCount"]; $n++)

            if (!$needAnswer && !empty($this->State["questionCount"])) {
                $this->logger->debug("return to ProcessStep");

                if ($this->ProcessStep('Question')) {
                    $this->Parse();
                }

                return true;
            }// if (!$needAnswer)
            else {
                $this->logger->debug("return true");

                return true;
            }
        }// Just questions
        elseif ($this->http->FindPreg('/>(?:We just need to confirm it’s you\.?||We just need some additional info to confirm it\'s you\.|We just need some additional information to confirm it\'s you\.|We just need some more information to confirm it\'s you\.|Please tell us how you would like to confirm it’s you.|Verificación de seguridad|我们只需确认是您本人|We moeten alleen bevestigen dat u het bent|Precisamos confirmar que é você|Nous devons confirmer votre identité|Simplemente debemos confirmar que usted es quien dice ser.|Abbiamo bisogno di confermare che sei proprio tu\.|Só precisamos de confirmar que é você|Нам необходимо подтвердить, что это действительно вы.|Нам нужно подтвердить, что это действительно вы.|Nous souhaitons simplement confirmer qu\&\#x27;il s\&\#x27;agit bien de vous.|Necesitamos confirmar su identidad|Wie wollen Sie bestätigen, dass Sie es sind\?|Wir wollen nur kurz bestätigen, dass Sie es sind|我們只是需要確認你的身分|我們需要一些額外資料來確認你的身份。|我們只需你再提供一些資料以確認你的身份。|עלינו לוודא שזה אתה|간단하게 본인 확인만 하면 됩니다.|我們旨在確認這是你本人|Dites-nous comment vous souhaitez confirmer que c\'est bien vous.|Vi behöver bara få bekräftat att det är du|Vi må bare bekrefte at det faktisk er deg\.|ご本人様の確認を行う必要があります|Nous allons maintenant vérifier les informations de votre compte PayPal\.|דרושים לנו מספר פרטים נוספים כדי לאשר שזה אתה.)\s*</')
                && (($mobile = $this->http->FindSingleNode(self::XPATH_SMS_TO_PHONE, null, true, "/(?:Mobile|Celular|手机|Mobiel|Móvil|Numero di cellulare|Мобильный|Casa|Handy|行動電話|휴대폰|手機|Mobil|モバイル|Móvil|Home|住家電話|Telemóvel|住宅|家庭|Koti|Work|Thuis|Telepon Seluler|Privat)\s*([^<]+)/ims"))
                || ($mobile = $this->http->FindSingleNode(self::XPATH_SMS_TO_PHONE, null, true, "/^([\d\-\s\•\(\)]+)$/ims"))
                || ($mobile = $this->http->FindSingleNode("//p[contains(., 'קבל הודעת טקסט')]/following-sibling::*[@class = 'verification-method'] | //p[contains(., 'קבל הודעת טקסט')]/following-sibling::div/*[@class = 'verification-method']", null, true, "/([\d\-\•]+)/ims")))
                || ($mobile2 = $this->http->FindSingleNode("//p[contains(., 'Have us call you') or contains(., 'Prefiro que me liguem')or contains(., 'Recevoir un appel de notre part')]/following-sibling::*[@class = 'verification-method'] | //p[contains(., 'Have us call you') or contains(., 'Prefiro que me liguem')or contains(., 'Recevoir un appel de notre part')]/following-sibling::div/*[@class = 'verification-method']", null, true, "/(?:Mobile|Home|Celular|Início|Casa|Móvil|Personnel|Work)\s*([^<]+)/ims"))) {
            if (!isset($mobile) && isset($mobile2)) {
                $mobile = $mobile2;
                $challenges = 'ivr';
            }// if (!isset($mobile) && isset($mobile2))
            else {
                $challenges = 'sms';
            }

            $this->logger->debug("Let’s make sure it’s you: Receive a text to: {$mobile}");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $csrf = $this->http->FindPreg('/\"_csrf\":\"([^\"]+)/ims');

            if (!$csrf) {
                return false;
            }

            $headers = [
                "X-Requested-With" => "XMLHttpRequest",
                "Content-Type"     => "application/json;charset=utf-8",
                "Accept"           => "application/json, text/plain, */*",
                "Referer"          => $this->seleniumURL,
            ];
            /*
            $data = '{"challenges":"'.$challenges.'","smsOption":"'.$mobile.'","_csrf":"'.$csrf.'"}';
            $this->http->PostURL("https://www.paypal.com/authflow/entry", $data, $headers);
            */
            $clientInstanceId = $this->http->FindPreg('/clientInstanceId=([^&]+)/', false, $this->seleniumURL);
            $authflowDocumentId = $this->http->FindPreg('/authflowDocumentId=([^&]+)/', false, $this->seleniumURL);
            $data = [
                "_csrf"                        => $csrf,
                "authflowDocumentId"           => $authflowDocumentId,
                "action"                       => "SELECT_CHALLENGE",
                "answer"                       => null,
                "clientInstanceId"             => $clientInstanceId,
                "selectedChallengeAnswerIndex" => 0,
                "selectedChallengeType"        => $challenges,
            ];
//            $this->http->PutURL("https://www.paypal.com/authflow/challenges/sms", $data, $headers);
//            $response = $this->http->JsonLog(null, true, true);

            $question = "Please enter a special 6-digit security code which was sent to your mobile {$mobile}";
            $this->State["Question"] = $question;

//            if (!isset($response['authflowDocumentStatus']))
//                return false;

            $this->State["Form"] = $data;
            $this->State["Headers"] = $headers;
            /*
            // debug
            if (!isset($response['data']))
                return false;

            $this->State["Form"] = ArrayVal($response['data'], 'form');
            */

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }

        if ($mobile = $this->http->FindSingleNode('//span[
                contains(text(), "We\'ve sent a 6-digit verification code to")
                or contains(text(), "We\'ve sent a 6-digit security code to")
                or contains(text(), "We sent a 6-digit security code to")
                or contains(text(), "Vi har sendt en 6-sifret sikkerhetskode")
                or contains(text(), "我們已發送 6 位數的安全代碼至")
                or contains(text(), "Nous avons envoyé un code de sécurité à 6")
                or contains(text(), "We hebben een zescijferige beveiligingscode verzonden naar")
                or contains(text(), "Мы отправили 6-значный код безопасности на номер телефона")
                or contains(text(), "Vi har skickat en 6-siffrig säkerhetskod till")
                or contains(text(), "我们已经将一个6位数的验证码发送到")
                or contains(text(), "6 位數的安全代碼已發送至")
                or contains(text(), "Enviamos um código de segurança de 6 dígitos para")
                or contains(text(), "Hemos enviado un código de seguridad de 6 dígitos a")
                or contains(text(), "Wysłaliśmy 6-cyfrowy kod bezpieczeństwa pod numer")
            ]', null, true, "/(?:code to|kode til|碼至|送至|chiffres au|naar|телефона|till|送到|para|dígitos a|pod numer)\s*([^<\.]+)/")
            ?? $this->http->FindSingleNode('//span[
                    contains(text(), "Na číslo")
                ]/span')
            ?? $this->http->FindSingleNode('//span[
                contains(text(), "Wir haben einen 6-stelligen Sicherheitscode an")
            ]', null, true, "/code an\s*([^<]+)\s+gesendet/")
            ?? $this->http->FindSingleNode('//span[
                contains(text(), "jsme zaslali šestimístný bezpečnostní kód.")
            ]', null, true, "/Na\s*číslo\s*([^<]+)\s+jsme/")
        ) {
            $csrf = $this->http->FindPreg('/\"_csrf\":\"([^\"]+)/ims');

            if (!$csrf) {
                return false;
            }

            $question = "Please enter a special 6-digit security code which was sent to your mobile {$mobile}";
            $this->State["Question"] = $question;
            $clientInstanceId = $this->http->FindPreg('/clientInstanceId=([^&]+)/', false, $this->seleniumURL);
            $authflowDocumentId = $this->http->FindPreg('/stepupContext=([^&]+)/', false, $this->seleniumURL);
            $data = [
                "_csrf"                        => $csrf,
                "authflowDocumentId"           => $authflowDocumentId,
                "action"                       => "SELECT_CHALLENGE",
                "answer"                       => null,
                "clientInstanceId"             => $clientInstanceId,
                "selectedChallengeAnswerIndex" => 0,
                "selectedChallengeType"        => 'sms',
            ];
            $this->State["Form"] = $data;

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }
        /*
         * 2 factor, step 0 (optional)
         *
         * We’ll send you a text with a special code. Just tell us which number to send the text to.
         */
        if (($mobile = $this->http->FindSingleNode("//select[@name = 'chooseSoftToken']/option[1]"))
            && ($mobileValue = $this->http->FindSingleNode("//select[@name = 'chooseSoftToken']/option[1]/@value"))
            && $this->http->ParseForm("softTokens")) {
            $this->logger->notice("2 factor, step 0 (optional)");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $this->http->SetInputValue("chooseSoftToken", $mobileValue);
            $this->http->SetInputValue("btnSelectSoftToken", "Send Me the Text");
            $this->http->PostForm();
        }
        /*
         * 2 factor, 19 Nov 2018
         *
         * Get your one-time code
         */
        if (($mobile = $this->http->FindSingleNode("(//input[@name = 'selectedToken']/following-sibling::label)[1]", null, true, "/(xxxxxxxx?\d+)/"))
            && ($mobileValue = $this->http->FindSingleNode("(//input[@name = 'selectedToken']/@value)[1]"))) {
            $this->logger->notice("2 factor");

            $csrf = $this->http->FindPreg("/_csrf\":\"([^\"]+)/");

            if (!$csrf) {
                return false;
            }

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $question = "We sent a 6-digit code to {$mobile}";

            $data = [
                "tokenType"       => "sms_otp",
                "tokenIdentifier" => $mobileValue,
                "_csrf"           => $csrf,
            ];
            $headers = [
                "Accept"           => "*/*",
                "Content-Type"     => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->http->PutURL("https://www.paypal.com/authflow/twofactor/", json_encode($data), $headers);
            $this->http->JsonLog();

            /**
             * {"otpCode":"123456","tokenIdentifier":{"value":"{$mobileValue}".
             *
             * https://www.paypal.com/authflow/twofactor
             *
             * POST /authflow/twofactor HTTP/1.1
                Host: www.paypal.com
                User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:55.0) Gecko/20100101 Firefox/55.0
                Accept: * /*
                Accept-Language: en-US,en;q=0.5
                Accept-Encoding: gzip, deflate, br
                X-Requested-With: XMLHttpRequest
                Content-Type: application/json
                Referer: https://www.paypal.com/authflow/twofactor/?returnUri=signin&country.x=US&locale.x=en_US&nonce=2018-11-19T06%3A35%3A36ZrFOB2aY64UAqYeyMTh90OsCZXlYKuhnQP6a5Wf4TV-A&stsReturnUrl=https%3A%2F%2Fwww.paypal.com%2Fsignin&mkey=authContext:3f901b4e2085406588f630c31f34215a
                Content-Length: 289
                Cookie:
                DNT: 1
                Connection: keep-alive
             */
            $this->State["Question"] = $question;
            $this->State["Version"] = '2fa_2018';
            $this->State["Form"] = [
                "otpCode"         => "",
                "tokenIdentifier" =>
                    [
                        "value" => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"([^\"]+)/"),
                        "text"  => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"[^\"]+\",\"text\":\"([^\"]+)/"),
                        "type"  => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"[^\"]+\",\"text\":\"[^\"]+\",\"type\":\"([^\"]+)\"/"),
                    ],
                "_csrf"          => $csrf,
            ];

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";
        }
        /*
         * 2 factor
         */
        if (($question = $this->http->FindSingleNode("//p[contains(text(), 'sent a special 6-digit code to your mobile') or contains(text(), 'Wir haben einen 6-stelligen Code auf Ihr Handy gesendet')]"))
            && $this->http->ParseForm("2fa")) {
            $this->logger->notice("2 factor");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $this->State["Question"] = $question;

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        } elseif ($question = $this->http->FindSingleNode('//span[contains(text(), "We sent a 6-digit code to ")]')) {
            $this->logger->notice("2 factor");

            $this->State["Question"] = $question;
            $this->State["Version"] = '2fa_2018';
            $this->State["Form"] = [
                "otpCode"         => "",
                "tokenIdentifier" =>
                    [
                        "value" => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"([^\"]+)/"),
                        "text"  => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"[^\"]+\",\"text\":\"([^\"]+)/"),
                        "type"  => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"[^\"]+\",\"text\":\"[^\"]+\",\"type\":\"([^\"]+)\"/"),
                    ],
                "_csrf"           => $this->http->FindPreg("/_csrf\":\"([^\"]+)/"),
            ];

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }
        // refs #13821
        if (($this->http->FindPreg("/>(?:Quick security check|Verificación de seguridad)</ims") && $this->http->FindPreg("/>(?:We just need to confirm it’s you\.?|We just need some additional info to confirm it's you\.|We just need some additional information to confirm it's you\.|We just need some more information to confirm it's you\.|Solo necesitamos algunos datos adicionales para confirmar su identidad\.)</ims")
                && $this->http->FindSingleNode(self::XPATH_SMS_TO_PHONE, null, true, "/(?:Please add a phone|Agregue un teléfono)/ims"))
            || $this->http->FindPreg("/(?:Wir wollen nur kurz bestätigen, dass Sie es sind. Antworten Sie einfach auf die Nachricht auf Ihrem Handy\.|We just need to confirm it’s you. To continue, simply respond to the notification we've sent to your PayPal app\.)/")) {
            throw new CheckException("To retrieve your PayPal account balance please go to your PayPal account and <a target='_blank' href='https://www.paypal.com/myaccount/settings/securityQuestions/edit/'>set up security questions</a>. After that please try updating this account one more time.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/
        /*
         * refs #13821
         *
         * Verification via security questions/sms/phone call
         */
        if ((($this->http->currentUrl() == 'https://www.paypal.com/safe/activity'
            || $this->seleniumURL == 'https://www.paypal.com/safe/activity')
            && ($link = $this->http->FindSingleNode("//a[contains(text(), 'Продолжить') or contains(text(), 'Continue') or contains(text(), 'Weiter') or contains(text(), '続行') or contains(text(), 'Continua') or contains(text(), '继续') or contains(text(), '繼續') or contains(text(), '계속') or contains(text(), 'Fortsett') or contains(text(), 'Devam') or contains(text(), 'המשך') or contains(text(), 'Doorgaan') or contains(text(), 'Fortsæt') or contains(text(), 'Fortsätt')]/@href")))
            /*
             * Quick security check
             *
             * We just need to confirm it’s you
             */
            || ($this->http->FindPreg("/>(Quick security check)</ims") && $this->http->FindPreg("/>(We just need to confirm it\’s you)</ims"))
            || ($link = $this->http->FindSingleNode("//a[@id = 'tryAnotherOption']/@href"))) {
            $this->logger->notice("Мы обеспокоены появлением потенциально несанкционированных действий.");
            $this->logger->notice("Verification via security questions/sms/phone call");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            return $this->verificationAccount();
        }
        /*
         * 2 factor, security key
         *
         * Please enter the code which was generated by your security key
         */
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Enter your code')]")
            && $this->http->FindPreg("/To generate a new code, press the button on your security key/")
            && $this->http->ParseForm("2fa")) {
            $this->logger->notice("2 factor, security key");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $question = "Please enter the code generated by your security key";
            $this->State["Question"] = $question;
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }
        /*
         * 2 factor, security key
         *
         * To get a new code, press the button on your Security Key (Serial number ...).
         */
        if ($this->http->FindSingleNode("
                    //h1[
                        contains(text(), 'Enter your code')
                        or contains(text(), 'Informe seu código')
                    ]
                    | //p[
                        contains(text(), 'Enter your code')
                        or contains(text(), 'Informe seu código')
                    ]
                ")
            && $this->http->FindPreg("/(?:To get a new code, press the button on your Security Key|Informe o código de segurança de 6 dígitos do seu aplicativo de autenticação.|Enter the 6-digit security code from your authenticator app\.)/")) {
            $this->logger->notice("2 factor");

            $serialNumber = $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"[^\"]+\",\"text\":\"([^\"]+)/");

            if ($serialNumber) {
                $question = "Please enter the code which was generated by your Security Key (Serial number {$serialNumber})";
            } else {
                $question = "Please enter the 6-digit security code from your authenticator app.";
            }
            $this->State["Question"] = $question;
            $this->State["Version"] = '2fa_2018';
            $this->State["Form"] = [
                "otpCode"         => "",
                "tokenIdentifier" =>
                    [
                        "value" => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"([^\"]+)/"),
                        "text"  => $serialNumber,
                        "type"  => $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"[^\"]+\",\"text\":\"[^\"]+\",\"type\":\"([^\"]+)\"/") ?? $this->http->FindPreg("/\"tokenIdentifiers\":\[\{\"value\":\"[^\"]+\",\"type\":\"([^\"]+)\"/"),
                    ],
                "_csrf"           => $this->http->FindPreg("/_csrf\":\"([^\"]+)/"),
            ];

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }

        return false;
    }

    public function verificationAccount()
    {
        $this->logger->notice(__METHOD__);
        $needAnswerOnQuestion = null;
        // redirect
        $this->jsonRedirect();
        // try to find security questions
        $data = $this->http->FindSingleNode("//body/@data-data");
        // @ - need to use JSON_BIGINT_AS_STRING in json_decode
        $response = @$this->http->JsonLog($data);
        // refs #13821
        if (isset($response->data->challengeSetModel->challengeMap->SECURITY_QUESTION)) {
            $this->logger->debug("Security questions were found");
            // Security questions
            $questions = $response->data->challengeSetModel->challengeMap->SECURITY_QUESTION->verifier;

            $this->State["Question"] = 'Verification via security questions';

            // Populate form
            $this->logger->debug("Populating the security questions' form");
            $this->http->Form = [];
//                $this->http->FormURL = "https://www.paypal.com/webapps/authmessaging/unifiedloginauthflow?execution=e1s1";
            // selectOption=SECURITY_QUESTION
            $this->http->SetInputValue("selectOption", $response->data->challengeSetModel->challengeMap->SECURITY_QUESTION->type);
            $this->http->SetInputValue("jsEnabled", "1");
            // execution=e1s1
            $this->http->SetInputValue("execution", $this->http->FindPreg("/execution=([^\&\=\?]+)/ims", false, $this->http->currentUrl()));
            $this->http->SetInputValue("_sms_ivr_continue_btn_label", "Continue");
            $this->http->SetInputValue("_default_continue_btn_label", "Continue");
            $this->http->SetInputValue("_eventId_continue", "Continue");
            // securityQuestion0=...
            // securityQuestion1=...
            unset($this->State["AllQuestions"]);

            foreach ($questions as $question) {
                $q = $question->value;
                $needAnswerOnQuestion = $q;
                $this->logger->debug("Question{$question->key} -> {$q}");
                $this->State["AllQuestions"][$question->key] = $q;

                if (!isset($this->Answers[$q])) {
                    $this->AskQuestion($q);
                    $needAnswerOnQuestion = $q;
                }// if (!isset($this->Answers[$q]))
                else {
                    $this->http->SetInputValue("securityQuestion" . $question->key, $this->Answers[$q]);
                }
            }// foreach ($questions as $question)
        }// if (isset($data->data->challengeSetModel->challengeMap->SECURITY_QUESTION))
        elseif (isset($response->data->challengeSetModel->challengeMap->SMS) || isset($response->data->challengeSetModel->challengeMap->IVR)) {
            // refs #13821
            throw new CheckException("To retrieve your PayPal account balance please go to your PayPal account and <a target='_blank' href='https://www.paypal.com/myaccount/settings/securityQuestions/edit/'>set up security questions</a>. After that please try updating this account one more time.", ACCOUNT_PROVIDER_ERROR); /*review*/
        /*elseif (isset($response->data->challengeSetModel->challengeMap->SMS)) {
            $this->logger->debug("SMS option was found");
            // Security questions
            $numbers = $response->data->challengeSetModel->challengeMap->SMS->verifier;

            $this->State["Question"] = 'Verification via SMS';

            // Populate form
            $this->logger->debug("Populating sms' form");
            $this->http->Form = [];
            // selectOption=SMS
            $this->http->SetInputValue("selectOption", $response->data->challengeSetModel->challengeMap->SMS->type);
            $this->http->SetInputValue("jsEnabled", "1");
            // execution=e1s1
            $this->http->SetInputValue("execution", $this->http->FindPreg("/execution=([^\&\=\?]+)/ims", false, $this->http->currentUrl()));
            $this->http->SetInputValue("_sms_ivr_continue_btn_label", "Continue");
            $this->http->SetInputValue("_default_continue_btn_label", "Continue");
            $this->http->SetInputValue("_eventId_continue", "Continue");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck())
                $this->Cancel();

            foreach ($numbers as $number) {
                $phone = str_replace('Mobile ', '', $number->value);
                $this->logger->debug("Phone {$number->key} -> {$phone}");
                if ($number->key != -1) {
                    $this->http->SetInputValue("textOption", $number->key);
                    $question = "Please enter the security code from the text which was sent to your phone {$phone}";
                    $needAnswerOnQuestion = $question;
                    break;
                }// if ($number->key != -1)
                else
                    $this->logger->notice("Skip wrong option -> {$phone}");
            }// foreach ($numbers as $number)
        }// if (isset($data->data->challengeSetModel->challengeMap->SECURITY_QUESTION))
        elseif (isset($response->data->challengeSetModel->challengeMap->IVR)) {*/
//            $this->logger->debug("Phone call option was found");
//            // Phone numbers
//            $numbers = $response->data->challengeSetModel->challengeMap->IVR->verifier;
//
//            // prevent code spam    // refs #6042
//            if ($this->isBackgroundCheck())
//                $this->Cancel();
//
//            $this->State["Question"] = 'Verification via phone call';
//
//            // Populate form
//            $this->logger->debug("Populating the security questions' form");
//            $this->http->Form = [];
////                $this->http->FormURL = "https://www.paypal.com/webapps/authmessaging/unifiedloginauthflow?execution=e1s1";
//            // selectOption=SECURITY_QUESTION
//            $this->http->SetInputValue("selectOption", $response->data->challengeSetModel->challengeMap->IVR->type);
//            $this->http->SetInputValue("jsEnabled", "1");
//            // execution=e1s1
//            $this->http->SetInputValue("execution", $this->http->FindPreg("/execution=([^\&\=\?]+)/ims", false, $this->http->currentUrl()));
//            $this->http->SetInputValue("_sms_ivr_continue_btn_label", "Continue");
//            $this->http->SetInputValue("_default_continue_btn_label", "Continue");
//            $this->http->SetInputValue("_eventId_continue", "Continue");
//
//            foreach ($numbers as $number) {
//                $phone = $number->value;
//                $this->logger->debug("Phone {$number->key} -> {$phone}");
//
//                if ($number->key != -1) {
//                    $this->http->SetInputValue("phoneOption", $number->key);
//                    break;
//                }// if ($number->key != -1)
//                else
//                    $this->logger->notice("Skip wrong option -> {$phone}");
//            }// foreach ($numbers as $number)
//            $this->http->PostForm();
//
//            $question = "When you receive an automated voice call from PayPal please enter the following security pin code: 12345 using your phone’s keypad to authenticate yourself.";
//            $needAnswerOnQuestion = $question;
//
//            $this->jsonRedirect();
//
//            $response = $this->http->JsonLog();
        }// elseif (isset($response->data->challengeSetModel->challengeMap->IVR))
        else {
            $this->logger->notice("Security questions, SMS and Phone call options weren't found");
            // Create a new password that is easy for you to remember, but hard for anyone to guess.
            // Create a new password that is easy for you to remember, but hard for anyone else to guess.
            if ($this->http->FindSingleNode("//p[contains(text(), 'Create a new password that is easy for you to remember, but hard for anyone ')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Create a new password. Make sure it’s strong and that you keep it safe.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Cree una contraseña nueva que sea fácil de recordar, pero difícil de adivinar.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Crie uma nova senha que seja fácil de lembrar, mas difícil para outras pessoas adivinharem.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Wählen Sie ein neues Passwort, das Sie sich gut merken können. Für andere muss es schwer zu erraten sein.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Maak een nieuw wachtwoord dat voor u gemakkelijk te onthouden, maar voor anderen moeilijk te raden is.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Задайте новый пароль, который будет легко запомнить вам, но трудно угадать другим.')]")
                || $this->http->FindSingleNode("//p[contains(text(), '创建一个您自己容易记住但别人很难猜到的新密码')]")
                || $this->http->FindSingleNode("//p[contains(text(), '建立新密碼。確認密碼強度夠高，並妥善保存密碼。')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Crea una nuova password semplice da ricordare per te, ma difficile da indovinare per gli altri')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Skapa ett nytt lösenord som är lätt för dig att komma ihåg, men svårt för andra att gissa.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Cree una nueva contraseña que sea fácil de recordar para usted, pero difícil de adivinar para cualquier otra persona.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'צור סיסמה חדשה שיהיה לך קל לזכור, אך לאחרים יהיה קשה לנחש.')]")
                || $this->http->FindSingleNode('//p[contains(text(), "Choose a password and PIN that\'s hard to guess and unique to this account.")]')
                || strstr($this->seleniumURL, 'https://www.paypal.com/authflow/password-recovery/change')
                || $this->http->FindSingleNode('//p[contains(text(), "Seleccione una contraseña que resulte difícil de adivinar y sea exclusiva para esta cuenta.")]')) {
                $this->throwProfileUpdateMessageException();
            }
        }

        if (!empty($needAnswerOnQuestion)) {
            $this->logger->notice("return true");
            $this->Question = $needAnswerOnQuestion;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }// if (!empty($needAnswerOnQuestion))

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        // We sent a 6-digit security code to ••••••@gm•••.•••. You may need to check your Junk or Spam folder.
        if (isset($this->State["Question"]) && strstr($this->State["Question"], 'We sent a 6-digit security code to ') && strstr($this->State["Question"], '@') && isset($this->State["Form"])) {
            $form = $this->State["Form"];
            $form["answer"] = $this->Answers[$this->Question];
            $this->http->PutURL("https://www.paypal.com/authflow/challenges/email", json_encode($form));
            $this->http->JsonLog();
            unset($this->Answers[$this->Question]);
        }// if (strstr($this->State["Question"], 'We sent a 6-digit security code to ') && isset($this->State["Form"]))

        if (isset($this->State["Version"], $this->State["Form"]) && $this->State["Version"] == '2fa_2018') {
            $data = $this->State["Form"];
            $data["otpCode"] = $this->Answers[$this->Question];
            unset($this->State["Version"]);
            unset($this->State["Form"]);
            $headers = [
                "Accept"           => "*/*",
                "Content-Type"     => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->http->PostURL('https://www.paypal.com/authflow/twofactor', json_encode($data), $headers);
            $response = $this->http->JsonLog();
            unset($this->Answers[$this->Question]);
            // There’s an issue with the code you entered. Let’s try that again.
            if (isset($response->message) && $response->message == "2FA twoFactorLogin failed: Incorrect OTP") {
                $this->AskQuestion($this->Question, $response->message, "Question");

                return false;
            }// if (isset($response->message) && $response->message == "2FA twoFactorLogin failed: Incorrect OTP")

            if (isset($response->success) && $response->success == 'true') {
                $this->http->GetURL("https://www.paypal.com/myaccount/home");
            }
        }// if (isset($this->State["Version"]) && $this->State["Version"] == '2fa_2018')
        elseif (isset($this->State["Question"]) && $this->State["Question"] == 'Verification via security questions' && isset($this->State["AllQuestions"])) {
            $this->logger->notice("Verification via security questions");

            foreach ($this->State["AllQuestions"] as $key => $question) {
                if (!isset($this->Answers[$question])) {
                    $this->AskQuestion($question, null, "Question");

                    return false;
                }// if (!isset($this->Answers[$question]))
                $this->http->SetInputValue("securityQuestion" . $key, $this->Answers[$question]);
            }// foreach ($this->State["AllQuestions"] as $key => $question)
            $this->logger->debug("form:");
            $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
            $this->http->PostForm();

            $this->jsonRedirect();

            // <p class="page-error open"> One or more answers are incorrect. Please try again. </p>
            // @ - need to use JSON_BIGINT_AS_STRING in json_decode
            $response = @$this->http->JsonLog();

            if (isset($response->data->errors->fieldError)) {
                foreach ($response->data->errors->fieldError as $keyQuestion => $param) {
                    $this->logger->debug("{$keyQuestion} -> {$param->msg}");
                    $key = $this->http->FindPreg("/securityQuestion(\d+)/", false, $keyQuestion);
                    $this->logger->debug("key -> {$key}");
                    $this->logger->debug(var_export($this->State["AllQuestions"], true), ["pre" => true]);

                    if (isset($this->State["AllQuestions"][$key])
                        && $this->Answers[$this->State["AllQuestions"][$key]]
                        && strstr($param->msg, 'One or more answers are incorrect')) {
                        $this->AskQuestion($this->State["AllQuestions"][$key], $param->msg, "Question");

                        return false;
                    }// ... && strstr($param->msg, 'One or more answers are incorrect'))
                }// foreach ($response->data->errors->fieldError as $question)
            }// if (isset($response->data->errors->fieldError))

            // Your account is verified! Thanks for verifying your account.
            $this->logger->debug("js redirect");
            sleep(2);

            if ($this->http->ParseForm("authSuccessFrm")) {
                $this->http->PostForm();
            } else {
                $data = [
                    "_eventId_close" => "Continue",
                    "execution"      => "e1s2",
                    "jsEnabled"      => "1",
                ];
                $this->http->PostURL("https://www.paypal.com/webapps/authmessaging/unifiedloginauthflow?execution=e1s2", $data);
            }
            $this->jsonRedirect();
            $this->jsonRedirect();
        }// if ($this->State["Question"] == 'Verification via security questions' && isset($this->State["AllQuestions"]))
        elseif (isset($this->State["Question"]) && $this->State["Question"] == 'Verification via SMS') {
            $this->http->SetInputValue("verificationCode", $this->Answers[$this->Question]);
            unset($this->Answers[$this->Question]);
            $this->logger->debug("form:");
            $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
            $this->http->PostForm();

            $this->sendNotification("Verification via SMS // RR");

            $this->jsonRedirect();

            // <p id="verificationCodeSubmit" class="help-error error-submit open"> The security code isn't correct. Please try again. </p>
            $response = $this->http->JsonLog();
            // todo

            // Your account is verified! Thanks for verifying your account.
            $this->logger->debug("js redirect");
            sleep(2);

            if ($this->http->ParseForm("authSuccessFrm")) {
                $this->http->PostForm();
            } else {
                $data = [
                    "_eventId_close" => "Continue",
                    "execution"      => "e1s2",
                    "jsEnabled"      => "1",
                ];
                $this->http->PostURL("https://www.paypal.com/webapps/authmessaging/unifiedloginauthflow?execution=e1s2", $data);
            }
            $this->jsonRedirect();
            $this->jsonRedirect();
        }// if ($this->State["Question"] == 'Verification via security questions')
        elseif (
            isset($this->http->Form["Question1"], $this->http->Form["InputQuestion1"])
            || (isset($this->State["Question"], $this->State["Version"]) && $this->State["Version"] == 'sq_2018')
        ) {
            $questions = [];
            $answers = [];

            for ($n = 0; $n < $this->State["questionCount"]; $n++) {
                $question = ArrayVal($this->http->Form, "Question" . ($n + 1));

                if ($question != '') {
                    $questions[] = $question;

                    if (!isset($this->Answers[$question])) {
                        $this->AskQuestion($question, null, "Question");

                        return false;
                    }// if (!isset($this->Answers[$question]))
                    $answers[] = $this->Answers[$question];
                    $this->http->SetInputValue($this->http->Form["InputQuestion" . ($n + 1)], $this->Answers[$question]);
                    unset($this->http->Form["Question" . ($n + 1)]);
                    unset($this->http->Form["InputQuestion" . ($n + 1)]);
                }// if ($question != '')
            }// for ($n = 0; $n < 2; $n++)

            $this->sendNotification("Entering Security Questions // RR");

            $this->logger->debug("questions:");
            $this->logger->debug(var_export($questions, true), ["pre" => true]);

            if (count($questions) != 2) {
                return false;
            }

            if (isset($this->State["Version"], $this->State["Form"]) && $this->State["Version"] == 'sq_2018') {
                $headers = [
                    "Accept"           => "application/json, text/plain, */*",
                    "Content-Type"     => "application/json;charset=utf-8",
                    "X-Requested-With" => "XMLHttpRequest",
                ];
                $this->State["Form"]["answer"] = $answers;
                $this->http->PutURL("https://www.paypal.com/authflow/challenges/securityQuestions", json_encode($this->State["Form"]), $headers);
                $response = $this->http->JsonLog();

                $status = $response->authflowDocumentStatus ?? null;

                if ($status == 'IN_PROGRESS') {
                    // There’s an issue with the security code you entered. Please try again.
                    if ($this->http->FindPreg('/"error":"answersError"\}/ims')) {
                        foreach ($questions as $q) {
                            unset($this->Answers[$q]);
                            $this->AskQuestion($q, "There’s an issue with the security code you entered. Please try again.", 'Question');
                        }

                        return false;
                    }

                    foreach ($response->challenges->challengeList as $challengeList) {
                        if ($challengeList->status != 'ACTIVE' && $challengeList->type != 'card') {
                            $this->logger->debug("Skip options {$challengeList->type}");

                            continue;
                        }// if ($challengeList->status != 'ACTIVE' && $challengeList->type != 'card')

                        foreach ($challengeList->choices as $choice) {
                            if (
                                !isset($choice->type)
                                || $choice->type != 'Master'
                                || !($digits = $this->http->FindPreg("/x-(.+)/", false, $choice->value))
                            ) {
                                $this->logger->debug("Skip choice: {$choice->type} / {$choice->value}");

                                continue;
                            }
                            $question = "Enter the full number of your master credit card with the final digits ($digits).";

                            if (!isset($this->Answers[$question])) {
                                $this->State["Version"] = 'sq_2018';
                                $this->AskQuestion($question, null, 'Question');

                                return false;
                            }// if (!isset($this->Answers[$question]))

                            break;
                        }// foreach ($challengeList->choices as $choice)
                    }// foreach ($response->challenges->challengeList as $challengeList)
                }// if ($status == 'IN_PROGRESS')
            } else {
                $this->http->PostForm();
            }
        }// elseif (isset($this->http->Form["Question1"], $this->http->Form["InputQuestion1"]))
        else {
            if (!isset($this->State["Question"])) {
                $this->logger->error("Question is not found");

                return false;
            }
            $this->logger->debug("Question: {$this->State["Question"]}");

            if (isset($this->Answers[$this->State["Question"]])) {
                if (strstr($this->State["Question"], "Please enter Security Code")) {
                    $this->http->SetInputValue("otp", $this->Answers[$this->State["Question"]]);
                    $this->http->SetInputValue("submit.x", "submit");
                    $this->http->PostForm();
                    $this->sendNotification("Entering Security Code // RR");

                    $this->checkAnswers();
                }

                if ($this->State["Question"] == "Please enter your 6-digit code") {
                    $this->logger->debug("Something...");
                    $this->http->SetInputValue("_default_continue_btn_label", "Continue");
                    $this->http->SetInputValue("_eventId_continue", "Continue");
                    $this->http->SetInputValue("_sms_ivr_continue_btn_label", "Continue");
                    $this->http->SetInputValue("jsEnabled", "1");
                    $this->http->SetInputValue("selectOption", "IVR_PIN");
//                $this->http->SetInputValue("execution", "e1s2");
                    $this->http->PostForm();
                    $this->sendNotification("Entering Security Code // RR");
                }// if ($this->State["Question"] == "Please enter your 6-digit code")
                // 2 factor
                if (strstr($this->State["Question"], "sent a special 6-digit code to your mobile")
                    || strstr($this->State["Question"], "Wir haben einen 6-stelligen Code auf Ihr Handy gesendet")
                    // 2 factor, security key
                    || $this->State["Question"] == "Please enter the code generated by your security key") {
                    $this->logger->notice("2 factor");
                    $this->http->SetInputValue("security-code", $this->Answers[$this->State["Question"]]);
                    $this->http->PostForm();
                    unset($this->Answers[$this->State["Question"]]);
                    // Looks like there was a problem with the code you entered. Let’s try that again.
                    if ($error = $this->http->FindSingleNode("//p[contains(text(), 'Looks like there was a problem with the code you entered. Let’s try that again.')]")) {
                        $this->AskQuestion($this->State["Question"], $error);

                        return false;
                    }
                }// if (strstr($this->State["Question"], "sent a special 6-digit code to your mobile"))

                if (strstr($this->State["Question"], "Please enter a special 6-digit security code which was sent to your mobile")
                    && isset($this->State["Form"])) {
                    $this->logger->notice("Entering a special 6-digit security code which was sent to mobile...");

//                    if (!isset($this->State["Form"]['smsOption'])) {
//                        $this->logger->error("something went wrong");
//                        return false;
//                    }
                    $this->sendNotification("Entering Security Code which was sent to mobile // RR");

                    $data = $this->State["Form"];
                    $data['action'] = 'ANSWER';
                    $data['answer'] = $this->Answers[$this->State["Question"]];

                    if (isset($this->State["Headers"])) {
                        $headers = $this->State["Headers"];
                    } else {
                        $headers = [
                            "X-Requested-With" => "XMLHttpRequest",
                            "Content-Type"     => "application/json;charset=utf-8",
                            "Accept"           => "application/json, text/plain, */*",
                            "Origin"           => "https://www.paypal.com",
                        ];
                    }
                    $this->http->PutURL('https://www.paypal.com/authflow/challenges/sms', json_encode($data), $headers);
                    /*
                    $data = '{"challenges":"'.$this->State["Form"]['challenges'].'","smsOption":"'.$this->State["Form"]['smsOption'].'","smsAnswer":"'.$this->Answers[$this->State["Question"]].'","_csrf":"'.$this->State["Form"]['_csrf'].'"}';
                    $headers = [
                        "X-Requested-With" => "XMLHttpRequest",
                        "Content-Type"     => "application/json",
                        "Accept"           => "application/json",
                    ];
                    $this->http->PostURL("https://www.paypal.com/authflow/entry", $data, $headers);
                    */
                    $response = $this->http->JsonLog();
                    // There’s an issue with the security code you entered. Please try again.
                    if ($this->http->FindPreg('/"errors":\{"smsAnswer":"smsAnswerError"\}/ims')) {
                        $this->AskQuestion($this->State["Question"], "There’s an issue with the security code you entered. Please try again.", "Question");

                        return false;
                    }
                    unset($this->Answers[$this->State["Question"]]);
                    unset($this->State["Form"]);

                    // redirect to done page
                    sleep(3);

                    if (isset($response->data->domain->returnUri)) {
                        $url = $response->data->domain->returnUri;
                        $this->logger->notice("redirect to -> {$url}");
                        $this->http->NormalizeURL($url);
                        $this->http->GetURL($url);
                    }// if (isset($response->data->domain->returnUri))
//                    if (isset($response->sys->legacyAppUrl)) {
//                        $url = $response->sys->legacyAppUrl;
//                        $this->http->Log("redirect to -> {$url}");
//                        $this->http->NormalizeURL($url);
//                        $this->http->GetURL($url);
//                    }
                    elseif (isset($response->urlForRedirection)) {
                        $url = $response->urlForRedirection;
                        $this->logger->notice("redirect to -> {$url}");
                        $this->http->NormalizeURL($url);
                        $this->http->PostURL($url, [], $headers);
                    }

                    $this->checkAnswers();
                }// if (strstr($this->State["Question"], "Please enter a special 6-digit security code which was sent to your mobile"))
            }// if (isset($this->Answers[$this->State["Question"]]))
        }

        return true;
    }

    public function jsonRedirect()
    {
        $this->logger->notice(__METHOD__);
        // redirect URL in json
        $response = $this->http->JsonLog();

        if (isset($response->redirectUrl)) {
            $redirectUrl = $response->redirectUrl;
            $this->logger->debug("js redirect to -> {$redirectUrl}");
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }// if (isset($response->redirectUrl))
    }

    public function Parse()
    {
        if ($newAccount = $this->http->FindSingleNode("//div[@id = 'header-buttons']/a[contains(@href, '/cgi-bin/webscr?cmd=_account')]/@href")) {
            $this->logger->notice("Skip notification");
            $this->http->GetURL(trim($newAccount));
        }
        $this->logger->notice("Account -> " . (($this->business) ? "business" : "premier"));

        if ($this->http->currentUrl() == 'https://www.paypal.com/webapps/business/'
            || $this->http->currentUrl() == 'https://www.paypal.com/businessexp/summary'
            || strstr($this->http->currentUrl(), 'country_lang.x=true')
            || strstr($this->http->currentUrl(), 'businessexp/summary')
            || strstr($this->http->currentUrl(), 'https://www.paypal.com/mep/dashboard')// redirect from https://www.paypal.com/businessexp/summary)
            || strstr($this->seleniumURL, 'https://www.paypal.com/mep/dashboard')// redirect from https://www.paypal.com/businessexp/summary)
            || ($this->http->currentUrl() == 'https://www.paypal.com' && $this->business == 'business')
            || ($this->seleniumURL == 'https://www.paypal.com' && $this->business == 'business')) {
            // redirect from https://www.paypal.com/businessexp/summary)
            if (
                strstr($this->http->currentUrl(), 'https://www.paypal.com/mep/dashboard')
                || strstr($this->seleniumURL, 'https://www.paypal.com/mep/dashboard')
            ) {
                $this->logger->notice("JSON Version 2");
                $this->http->GetURL("https://www.paypal.com/businesswallet/api/balance", ["X-Requested-With" => "XMLHttpRequest"]);
                $data = $this->http->JsonLog();
            } else {
                $this->logger->notice("JSON Version");
                $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
                $this->http->GetURL("https://www.paypal.com/webapps/business/moneyBasic?cache=" . time() . date("B"));
                $response = $this->http->JsonLog();

                if (isset($response->data)) {
                    $data = $response->data;
                } else {
                    $data = $response;
                }
            }
            // Name
            if (isset($data->user->userFullName)) {
                $this->SetProperty("Name", beautifulName($data->user->userFullName));
            } elseif (isset($data->user->firstName, $data->user->lastName)) {
                $this->SetProperty("Name", beautifulName($data->user->firstName . " " . $data->user->lastName));
            } elseif (isset($data->user->fullName)) {// JSON Version 2.1
                $this->SetProperty("Name", beautifulName($data->user->fullName));
            } else {
                $this->logger->notice("Name is not found");

                $this->http->GetURL("https://www.paypal.com/bizcomponents/userInfo", ["X-Requested-With" => "XMLHttpRequest"]);
                $response = $this->http->JsonLog();
                $this->SetProperty("Name", beautifulName($response->data->user->fullName ?? null));
            }
            // Balance
            if (isset($data->money->balanceModel->totalAvailableAmount->amountUnformatted)) {
                $this->SetBalance($data->money->balanceModel->totalAvailableAmount->amountUnformatted);
            } elseif (isset($data->balance->moneyDetails->balance->availableMoney->total->formattedCurrency)) {
                $this->SetBalance($data->balance->moneyDetails->balance->availableMoney->total->formattedCurrency);
            } else {
                $this->logger->notice("Balance is not found");
            }
            // Currency
            if (isset($data->money->balanceModel->totalAvailableAmount->currency)) {
                $this->SetProperty("Currency", $data->money->balanceModel->totalAvailableAmount->currency);
            } elseif (isset($data->balance->moneyDetails->balance->availableMoney->total->formattedCurrency)) {
                $this->SetProperty("Currency", $this->currency($data->balance->moneyDetails->balance->availableMoney->total->formattedCurrency));
            } else {
                $this->logger->notice("Currency is not found");
            }

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if (strstr($this->http->currentUrl(), 'cgi-bin/webscr?cmd=_account')
                    || strstr($this->http->currentUrl(), 'country_lang.x=true')
                    || $this->http->Response['code'] == 500) {
                    $this->logger->notice("Try standard Version");
                    $this->standardVersion();
                } elseif ($this->http->FindPreg('/"statusCode":500/')) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        } else {
            $this->standardVersion();
        }
    }

    public function standardVersion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//p[contains(text(), 'Your account access has been limited')]")) {
            $this->http->GetURL("https://www.paypal.com/myaccount/summary/");
        }

        $this->getBalance();
        // Name
        $name = $this->http->FindSingleNode("//div[@id = 'headline']/h2", null, true, "/Welcome\s*\,\s*([^<]+)/ims");

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), 'Hello again,') or contains(text(), 'Hi again,') or contains(text(), 'Hi,') or contains(text(), 'Olá,') or contains(text(), '¡Hola de nuevo,') or contains(text(), 'Hei igjen,') or contains(text(), 'Hola,') or contains(text(), 'Здравствуйте,') or contains(text(), '您好，') or contains(text(), 'Witaj,')] | //p[contains(@class, 'nemo_welcomeMessageHeader') and contains(text(), 'שלום,')]", null, true, "/(?:,|，)\s*([^<\!\.！]+)/ims")));
        }

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), ', 안녕하세요?') or contains(text(), '，你好') or contains(text(), '様、')]", null, true, "/([^<\,\，\様]+)/ims")));
        }

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), 'Hello') or contains(text(), 'Hallo') or contains(text(), 'Bonjour')]", null, true, "/\w+\s*([^<\!]+)/ims")));
        }

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), 'مرحباً')]", null, true, "/\W+\s*([^<\!]+)/ims")));
        }

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), 'Hej igen')]", null, true, "/\w+\s*\w+\s*([^<\!]+)/ims")));
        }

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//span[contains(text(), 'Bonjour ')]", null, true, "/\w+\s*([^,]+)/ims")));
        }

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//span[contains(@class, 'welcome-container')]", null, true, "/,\s*([^!]+)/ims")));
        }

        if (empty($name)) {
            $name = beautifulName(Html::cleanXMLValue($this->http->FindSingleNode("//span[contains(@class, 'welcome-container')]", null, true, "/Good\s*\w+\s*([^,]+),/ims")));
        }
        $this->SetProperty("Name", $name);

        // business -> https://paypalmanager.paypal.com/login.do
        if ($this->business) {
            $this->http->GetURL("https://paypalmanager.paypal.com/login.do");
            $this->http->GetURL("https://paypalmanager.paypal.com/home.do");
            // Name
            $name = $this->http->FindSingleNode("//div[@id = 'title']/h2", null, true, "/Welcome\s*([^<]+)/ims");

            if (!empty($name)) {
                $this->SetProperty("Name", beautifulName(Html::cleanXMLValue($name)));
            }
        }// if ($this->business)
        else {// personal -> https://www.paypal.com/myaccount/home
            // Name
            if ($profilePage = $this->http->FindSingleNode("//a[contains(@href, '/myaccount/settings')]/@href")) {
                $this->http->NormalizeURL($profilePage);
                $this->http->GetURL($profilePage);
            } else {
                $this->http->GetURL("https://www.paypal.com/webapps/settings/");
            }
            $name = $this->http->FindSingleNode("//p[contains(@class, 'vx_globalNav-displayName')]");

            if ($name) {
                $this->SetProperty("Name", beautifulName($name));
            }
        }

        /*
        // Paypal points
        $this->http->GetURL("https://www.paypal.com/us/cgi-bin/webscr?cmd=%5faccount&nav=0%2e0");
        $links = $this->http->FindNodes("//a[contains(@name, 'Details') and not(contains(@href, '/wallet/balance'))]/@href");
        if (count($links) > 1) {
            // todo: refs #13645
            $this->ArchiveLogs = true;
            $this->sendNotification("refs #13645. Multiple Rewards may be were found // RR");
        }
        foreach ($links as $link) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            // View my Rewards
            $this->http->GetURL("https://www.paypal.com/cgi-bin/webscr?cmd=_profile-buyer-credit&displaytab=rewards");
            // DisplayName
            $displayName = $this->http->FindSingleNode("//div[@id = 'headline']/h2");
            $code = $this->http->FindSingleNode("//div[@id = 'headline']/h2", null, true, "/\(X-(\d)+/ims");
            // Expiration Date
            $balance = $this->http->FindPreg("/Rewards<\/h3>\s*<span>([^<]+)\s*points/ims");
            if (isset($code, $balance, $displayName))
                $subAccounts[] = array(
                    'Code'           => 'paypal'.$code,
                    'DisplayName'    => $displayName,
                    'Balance'        => str_replace('&#x2c;', ',', $balance),
                );
        }
        if (isset($subAccounts)) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->SetProperty("SubAccounts", $subAccounts);
        }
        */
        /*
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    $this->getBalance();

                    if ($this->http->FindSingleNode("//table[@id = 'balanceDetails']//td[contains(text(), 'USD')]/following-sibling::td[contains(text(), 'No balance needed to shop or send money')]"))
                        $noBalance = true;
                    else
                        $noBalance = false;

                    if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                        $this->http->GetURL("https://history.paypal.com/cgi-bin/webscr?cmd=_history");
                        if ($this->http->FindPreg("/<body>\s*Fatal Failure\s*<br>\s*<\/body>/")) {
                            sleep(5);
                            $this->http->GetURL("https://history.paypal.com/cgi-bin/webscr?cmd=_history");
                        }
                        $this->getBalance();

                        // No balance needed to shop or send money
                        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
                            && $this->http->FindPreg("/<body>\s*Fatal Failure\s*<br>\s*<\/body>/") && $noBalance)
                            $this->SetBalanceNA();
                        */

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://www.paypal.com/myaccount/wallet/balance");
//                    $balance = $this->http->FindSingleNode("//p[contains(@class, 'balanceDetails-amount')]");
            $balanceJSON = $this->http->FindPreg("/totalAvailable[^\{]+(\{[^\}]+\})/");
            $this->logger->debug($balanceJSON);
            $this->logger->debug(urldecode($balanceJSON));
            $balanceJSON = $this->http->JsonLog(urldecode($balanceJSON));
            /*
            $balance =
                $this->http->FindPreg("/totalAvailable\":\{\"amount\":\"([^\"]+)\",\"currency\":\"[^\"]+\"\},/")
                ?? $this->http->FindPreg("/totalAvailable&quot;:\{&quot;unformattedAmount&quot;:[^,]+,&quot;amount&quot;:&quot;([^,]+)‬&quot;,&quot;balanceFormatted&quot;:&quot;[^,]+‬&quot;,&quot;balanceFormatSepCurrencyCode&quot;:&quot;[^,]+‬&quot;,&quot;currency&quot;:&quot;[^,]+&quot;/")
                ?? $this->http->FindPreg("/totalAvailable&quot;:\{&quot;unformattedAmount&quot;:[^,]+,&quot;amount&quot;:&quot;([^,]+)&quot;,&quot;balanceFormatted&quot;:&quot;[^,]+&quot;,&quot;balanceFormatSepCurrencyCode&quot;:&quot;[^,]+&quot;,&quot;currency&quot;:&quot;[^,]+&quot;/")
                ?? $this->http->FindPreg("/totalAvailable&quot;:\{&quot;unformattedAmount&quot;:[^,]+,&quot;amount&quot;:&quot;([^&]+)&quot;,&quot;balanceFormatted&quot;:&quot;[^&]+[A-Z]{3}&quot;/");
            */
            $balance = $balanceJSON->amount ?? null;
            $this->SetBalance($balance);
//                    $currency = $this->http->FindPreg("/([^\d]+)/", false, $balance);
            /*
            $currency =
                $this->http->FindPreg("/totalAvailable\":\{\"amount\":\"[^\"]+\",\"currency\":\"([^\"]+)\"\},/")
                ?? $balance = $this->http->FindPreg("/totalAvailable&quot;:\{&quot;unformattedAmount&quot;:[^,]+,&quot;amount&quot;:&quot;[^,]+‬&quot;,&quot;balanceFormatted&quot;:&quot;[^,]+‬&quot;,&quot;balanceFormatSepCurrencyCode&quot;:&quot;[^,]+‬&quot;,&quot;currency&quot;:&quot;([^,]+)&quot;/")
                ?? $this->http->FindPreg("/totalAvailable&quot;:\{&quot;unformattedAmount&quot;:[^,]+,&quot;amount&quot;:&quot;[^,]+&quot;,&quot;balanceFormatted&quot;:&quot;[^,]+&quot;,&quot;balanceFormatSepCurrencyCode&quot;:&quot;[^,]+&quot;,&quot;currency&quot;:&quot;([^,]+)&quot;/")
                ?? $this->http->FindPreg("/totalAvailable&quot;:\{&quot;unformattedAmount&quot;:[^,]+,&quot;amount&quot;:&quot;[^&]+&quot;,&quot;balanceFormatted&quot;:&quot;[^&]+([A-Z]{3})&quot;/");
            */
            $currency = $balanceJSON->currency ?? null;
            $this->logger->debug("Currency -> " . $currency);

            if ($currency) {
                $this->SetProperty("Currency", $currency);
            } elseif ($this->ErrorCode == ACCOUNT_CHECKED) {
                $this->sendNotification("Check currency {$balance} // RR");
            }
        }
//            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
//        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function getBalance()
    {
        $this->logger->notice(__METHOD__);
        // Balance - PayPal balance
        if (!$this->SetBalance(implode('', $this->http->FindNodes("//div[contains(@class, 'balanceNumeral')]/span[contains(@class, 'large') or contains(@class, 'h2')]")))) {
            if (!$this->SetBalance($this->http->FindSingleNode("//table[@id = 'balanceDetails']//td[contains(text(), 'USD')]/following-sibling::td[not(contains(text(), 'No balance needed to shop or send money'))]"))) {
                if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(@class, 'balance') and string-length(text()) < 10]"))) {
//                if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(@class, 'balance')
//                            and not(contains(text(), 'No balance needed to shop or send money'))
//                            and not(normalize-space(text()) = 'Balance')
//                            and not(contains(text(), 't need a PayPal balance to shop or send money.'))
//                            and not(contains(text(), 'ter saldo para fazer compras ou enviar pagamentos'))
//                            and not(contains(text(), '잔액이 없어도 쇼핑 및 결제대금 송금이 가능합니다.'))]")))
                    if (!$this->SetBalance($this->http->FindSingleNode("//td[contains(., 'Total in') and contains(., 'USD')]/following-sibling::td", null, true, "/([\d\,\.\-\$]+)/ims"))) {
                        if (!$this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), 'Balance') or contains(text(), 'PayPal balance') or contains(text(), '잔액') or contains(text(), 'Saldo') or contains(text(), 'Solde') or contains(text(), 'Остаток') or contains(text(), 'الرصيد') or contains(text(), '余额') or contains(text(), '餘額')]/following-sibling::p[span[contains(text(), 'USD')]]", null, true, "/([\d\,\.\-\$]+)/ims"))) {
                            if (!$this->SetBalance($this->http->FindSingleNode("//h3[contains(text(), '余额')]/following-sibling::p[span[contains(text(), 'CNY')]] | //h3[contains(text(), 'Saldo') or contains(text(), 'Balance')or contains(text(), 'PayPal balance')]/following-sibling::p[span[contains(text(), 'BRL') or contains(text(), 'NOK') or contains(text(), 'EUR') or contains(text(), 'SEK') or contains(text(), 'MXN') or contains(text(), 'PLN')]] | //h3[contains(text(), '餘額') or contains(text(), 'Balance')]/following-sibling::p[span[contains(text(), 'TWD')]]"))) {
                                $this->SetBalance($this->http->FindSingleNode("//h3[
                                        contains(text(), 'Balance')
                                        or contains(text(), 'Guthaben')
                                        or contains(text(), 'Solde')
                                        or contains(text(), 'Остаток')
                                        or contains(text(), '餘額')
                                        or contains(text(), '残高')
                                        or contains(text(), 'יתרה')
                                    ]/following-sibling::p[span[
                                        contains(text(), 'GBP')
                                        or contains(text(), 'EUR')
                                        or contains(text(), 'AUD')
                                        or contains(text(), 'CAD')
                                        or contains(text(), 'MYR')
                                        or contains(text(), 'HKD')
                                        or contains(text(), 'RUB')
                                        or contains(text(), 'JPY')
                                        or contains(text(), 'CHF')
                                        or contains(text(), 'NZD')
                                        or contains(text(), 'SGD')
                                        or contains(text(), 'PHP')
                                        or contains(text(), 'DKK')
                                        or contains(text(), 'CZK')
                                        or contains(text(), 'ILS')
                                        or contains(text(), 'THB')
                                        or contains(text(), 'INR')]
                                    ]
                                    | //span[contains(@class, 'test_balance-tile-currency')]
                                    ")
                                );
                            }
                        }
                    }
                }
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // No cash balance
            if ($this->http->FindSingleNode("//table[@id = 'balanceDetails']//td[contains(text(), 'USD')]/following-sibling::td[contains(text(), 'No balance needed to shop or send money')] | //div[@class = 'balanceModule']//span[contains(text(), 'No balance needed to shop or send money')]")
            || $this->http->FindSingleNode('//div[@class = "nemo_balanceNumeral"]/span[contains(@class, "balance") and contains(text(), "You don\'t need a PayPal balance to shop or send money.") or contains(text(), "No balance needed to shop or send money")]')) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $currency = $this->http->FindSingleNode("(//span[contains(@class, 'balance')])[1]", null, true, "/[A-Z]{3}/");

        if (!isset($currency)) {
            $currency = $this->currency($this->Balance);
        }
        $this->logger->debug("Currency -> " . $currency);

        if (in_array($currency, ['GBP', 'EUR', 'CNY', 'BRL', 'AUD', 'CAD', 'NOK', 'MYR', 'TWD', 'HKD', 'RUB', 'JPY', 'SEK', 'CHF', 'MXN', 'NZD', 'SGD', 'PHP', 'DKK', 'PLN', 'CZK', 'ILS', 'THB', 'INR'])) {
            $this->SetProperty("Currency", $currency);
        }
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//iframe[contains(@src, 'recaptcha')]/@src", null, true, "/siteKey=([^<\&]+)/");
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

    protected function parseCaptcha($id = 'vvvvvvvvvvvvvvvvv')
    {
        $this->logger->notice(__METHOD__);
        $captchaURL = $this->http->FindSingleNode("//div[@id = 'captcha']//img/@src | //div[@id = '{$id}']//div[@class = 'captcha-image']//img/@src");

        if (!$captchaURL) {
            return false;
        }
        $this->logger->debug("captcha -> {$captchaURL}");
        $this->http->NormalizeURL($captchaURL);
        $file = $this->http->DownloadFile($captchaURL, "jpeg");
        // rucapthca blocking our requests due paypal captchas
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $selenium->UseSelenium();
        $selenium->useFirefox();
//        $selenium->disableImages();
        $selenium->useCache();
        $selenium->http->saveScreenshots = true;
        $selenium->http->start();
        $selenium->Start();
        $selenium->http->GetURL("https://www.paypal.com/signin/?country.x=US&locale.x=en_US");

        $this->securityChallenge($selenium);

        $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 0);
        $btn = $selenium->waitForElement(WebDriverBy::id('btnNext'), 0);
        $this->savePageToLogs($selenium);

        if ($loginInput && !$btn) {
            $this->logger->notice("Old Login form");
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id('btnLogin'), 0);
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                    document.getElementById(\'btnLogin\').click();
                }, 500)');
        }// if ($loginInput && !$btn)
        else {
            $this->logger->notice("New Login form");
            $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 3);
            $captchaInput = $selenium->waitForElement(WebDriverBy::xpath(self::XPATH_CAPTCHA), 0);
            $captcha = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'captcha-image']/img"), 0);

            if ($captcha && $captchaInput) {
//                $captcha->getAttribute('src');
                $captcha = $this->parseCaptcha('splitEmailSection');

                if ($captcha === false) {
                    return false;
                }
                $captchaInput->sendKeys($captcha);
            }

            if (!$loginInput || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }
            $this->savePageToLogs($selenium);

            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $selenium->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                }, 500)');
            $btn->click();

            $this->securityChallenge($selenium);

            // wrong captcha answer
            if ($selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Try entering the code again.')]"), 0)) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                throw new CheckRetryNeededException();
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 5);

            $captcha = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'captcha-image']/img"), 0);
            $captchaInput = $selenium->waitForElement(WebDriverBy::xpath(self::XPATH_CAPTCHA), 0);

            if ($captcha && $captchaInput) {
//                $captcha->getAttribute('src');
                $this->savePageToLogs($selenium);
                $captcha = $this->parseCaptcha('splitPasswordSection');

                if ($captcha === false) {
                    return false;
                }
                $captchaInput->sendKeys($captcha);
            }

            $btn = $selenium->waitForElement(WebDriverBy::id('btnLogin'), 0);

            if (!$btn) {
                $btn = $selenium->waitForElement(WebDriverBy::id('btnNext'), 0);
            }
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$btn) {
                $this->logger->error("something went wrong");
                // Sorry, we can't log you in. If you think there's a problem with your account, contact us and we'll help resolve it.
                if ($message = $this->http->FindSingleNode('(//section[@id = "login"]//p[
                        contains(text(), "Sorry, we can\'t log you in. If you think there\'s a problem with your account")
                        or contains(text(), "Wir können Sie nicht einloggen. Falls Sie Hilfe brauchen, wenden Sie sich an den")
                        or contains(text(), "Nous ne pouvons pas vous connecter.")
                        or contains(text(), "Inloggen is niet mogelijk.")
                        or contains(text(), "Lo sentimos, pero no puede iniciar sesión en este momento. Si necesita ayuda,")
                        or contains(text(), "抱歉，我們無法讓你登入。請")
                        or contains(text(), "You haven’t confirmed your mobile yet. Use your email for now.")
                        or contains(text(), "מצטערים, לא ניתן להכניס אותך לחשבון.")
                    ])[1]')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // That email format isn’t right
                if ($message = $this->http->FindSingleNode('//p[@class = "invalidError" and (
                        contains(text(), "That email format isn’t right")
                        or contains(text(), "That email or phone number format isn’t right")
                        or contains(text(), "That email or mobile number format isn’t right")
                    )]')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                return false;
            }
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                    document.getElementById(\'btnLogin\').click();
                }, 500)');
//            $btn->click();
        }

        $this->securityChallenge($selenium);

        $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log out") or @id = "sign_out"] | //h1[contains(text(), "Security Challenge")]'), 3);

        if ($selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Security Challenge")]'), 0)) {
            throw new CheckRetryNeededException(3, 7);
        }

        $this->savePageToLogs($selenium);

        // Help us keep your account secure
        if ($cont = $selenium->waitForElement(WebDriverBy::xpath('
                //a[contains(text(), "Not now")]
                | //a[contains(text(), "Proceed to Account Overview")]
            '), 0)
        ) {
            $cont->click();
            sleep(3);
            $this->savePageToLogs($selenium);
        }

        if ($cont = $selenium->waitForElement(WebDriverBy::xpath('//a[@href = "secure"] | //input[@name = "safeContinueButton"]'), 0)) {
            $this->logger->debug("secure");

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[@class = "processing"]'), 0)) {
                sleep(5);
                $cont = $selenium->waitForElement(WebDriverBy::xpath('//a[@href = "secure"] | //input[@name = "safeContinueButton"]'), 0);
                $this->savePageToLogs($selenium);
            }

            if (!$this->http->FindSingleNode('//p[
                    contains(text(), "Nous avons constaté une activité inhabituelle et nous avons besoin de votre aide pour sécuriser votre compte.")
                    or contains(text(), "We noticed some unusual activity and need help securing your account.")
                    or contains(text(), "Observamos algumas atividades incomuns e precisamos de ajuda para proteger sua conta.")
                    or contains(text(), "Hemos identificado movimientos poco usuales y necesitamos de su ayuda para mantener su cuenta protegida.")
                    or contains(text(), "通常と異なる取引が検出されました。お客さまのアカウント保護にご協力ください。")
                ]')
                && !$this->http->FindSingleNode("//p[contains(text(), 'اضغط على \"التالي\" لتأكيد هويتك وتغيير كلمة المرور')]")) {
                $cont->click();
                sleep(5);
            }
            $this->savePageToLogs($selenium);
            // Create a new password that is easy for you to remember, but hard for anyone to guess.
            // Create a new password that is easy for you to remember, but hard for anyone else to guess.
            if ($this->http->FindSingleNode("//p[contains(text(), 'Create a new password that is easy for you to remember, but hard for anyone ')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Create a new password. Make sure it’s strong and that you keep it safe.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Cree una contraseña nueva que sea fácil de recordar, pero difícil de adivinar.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Crie uma nova senha que seja fácil de lembrar, mas difícil para outras pessoas adivinharem.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Wählen Sie ein neues Passwort, das Sie sich gut merken können. Für andere muss es schwer zu erraten sein.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Maak een nieuw wachtwoord dat voor u gemakkelijk te onthouden, maar voor anderen moeilijk te raden is.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Задайте новый пароль, который будет легко запомнить вам, но трудно угадать другим.')]")
                || $this->http->FindSingleNode("//p[contains(text(), '创建一个您自己容易记住但别人很难猜到的新密码')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Crea una nuova password semplice da ricordare per te, ma difficile da indovinare per gli altri')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Skapa ett nytt lösenord som är lätt för dig att komma ihåg, men svårt för andra att gissa.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Observamos algumas atividades incomuns e precisamos de ajuda para proteger sua conta.')]")
                || $this->http->FindSingleNode("//p[contains(text(), '通常と異なる取引が検出されました。お客さまのアカウント保護にご協力ください。')]")
                || $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We noticed some unusual activity and need help securing your account.')]"), 0)
                || $this->http->FindSingleNode("//p[contains(text(), 'Hemos identificado movimientos poco usuales y necesitamos de su ayuda para mantener su cuenta protegida.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Cree una nueva contraseña que sea fácil de recordar para usted, pero difícil de adivinar para cualquier otra persona.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'צור סיסמה חדשה שיהיה לך קל לזכור, אך לאחרים יהיה קשה לנחש.')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'اضغط على \"التالي\" لتأكيد هويتك وتغيير كلمة المرور')]")
            ) {
                $this->throwProfileUpdateMessageException();
            }
        }// if ($cont = $selenium->waitForElement(WebDriverBy::xpath('//a[@href = "secure"] | //input[@name = "safeContinueButton"]'), 0))

        if ($cont = $selenium->waitForElement(WebDriverBy::xpath('//a[@href = "authjump"]'), 0)) {
            $this->logger->debug("2fa");
            $cont->click();

            $this->securityChallenge($selenium);

            $this->savePageToLogs($selenium);

            // refs #13821
            throw new CheckException("To retrieve your PayPal account balance please go to your PayPal account and <a target='_blank' href='https://www.paypal.com/myaccount/settings/securityQuestions/edit/'>set up security questions</a>. After that please try updating this account one more time.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }
        /**
         * Boost your login options.
         *
         * You'll be able to sign in to PayPal using this number on all types of devices and browsers.
         * Simply confirm your mobile number below.
         */
        if ($selenium->http->currentUrl() == 'https://www.paypal.com/signin/enable-phone-password') {
            $this->savePageToLogs($selenium);

            $selenium->http->GetURL("https://www.paypal.com/myaccount/home");
            $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log out") or @id = "sign_out"]'), 3);
        }
        // Confirm a different way
        if ($href = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Confirm a different way') or @class = 'tryDifferentWay']"), 0)) {
            $this->logger->notice("Redirect to SQ -> We just need to confirm it’s you");
            $href = $href->getAttribute('href');
            $selenium->http->NormalizeURL($href);
            $selenium->http->GetURL($href);
        }
        /*
         * Let's confirm it's you
         *
         * Open the PayPal app on your phone and follow the prompt to continue.
         */
        if ($btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Try another way')]"), 0)) {
            $this->logger->notice("Redirect to SQ -> Let's confirm it's you");
            $btn->click();
        }

        if (
            ($sqBtn = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Use Security Key')]"), 0))
            && ($cont = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-nemo = "twofactorTryAnotherWaySubmit"]'), 0))
        ) {
            $sqBtn->click();
            $cont->click();
        }
        /**
         * Quick security check
         * We just need some additional info to confirm it's you.
         *
         * select option "Answer your security questions"
         */
        if (($sqBtn = $selenium->waitForElement(WebDriverBy::xpath('//p[
                contains(text(), "Receive an email")
                or contains(text(), "接收電子郵件")
                or contains(text(), "Recibir un correo electrónico")
                or contains(text(), "이메일 받기")
                or contains(text(), "接收电子邮件")
                or contains(text(), "接收電郵")
                or contains(text(), "קבל הודעת טקסט")
            ]'), 0))
            && ($cont = $selenium->waitForElement(WebDriverBy::xpath('//input[@value = "Next"] | //button[@data-nemo = "entrySubmit"]'), 0))
        ) {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $sqBtn->click();
            $cont->click();
            $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'selectedChallengeType' and @value = 'email' and @checked]"), 5, false);
            $this->savePageToLogs($selenium);

            $clientInstanceId = $this->http->FindPreg("/clientInstanceId=([^&]+)/");
//            $authflowDocumentId = $this->http->FindPreg("/authflowDocumentId=([^&]+)/");//todo
            $csrf = $this->http->FindPreg("/\"_csrf\":\"([^\"]+)/");
            $email = $this->http->FindPreg("/\{\"type\":\"email\",\"status\":\"ACTIVE\",\"emailAddresses\":\[\"([^\"]+)/");

            if (!$email) {
                $email = $this->http->FindPreg("/\"email\":\{\"status\":\"ACTIVE\",\"type\":\"email\",\"choices\":\[\{\"value\":\"([^\"]+)\"/");
            }

            if (!$clientInstanceId || !$csrf || !$email) {
                $this->logger->error("something went wrong");

                if ($csrf && $email) {
                    $email = json_decode('"' . $email . '"');

                    $question = "We sent a 6-digit security code to {$email}. You may need to check your Junk or Spam folder.";
                    $this->State["Question"] = $question;
                    $this->State["Form"] = [
                        "_csrf"             => $csrf,
                        "type"              => "email",
                        "status"            => "ACTIVE",
                        "emailAddresses"    => [
                            $email,
                        ],
                        "questionType"      => "EMAIL_PIN",
                        "chosenAnswerIndex" => 0,
                        "answer"            => "",
                    ];
                    $this->logger->debug("sending code to {$email}");
                    $this->AskQuestion($question, null, 'Question');
                }

                return false;
            }
            $email = json_decode('"' . $email . '"');

            $question = "We sent a 6-digit security code to {$email}. You may need to check your Junk or Spam folder.";
            $this->State["Question"] = $question;
            $this->State["Form"] = [
                "clientInstanceId"  => $clientInstanceId,
                "_csrf"             => $csrf,
                "type"              => "email",
                "status"            => "ACTIVE",
                "emailAddresses"    => [
                    $email,
                ],
                "questionType"      => "EMAIL_PIN",
                "chosenAnswerIndex" => 0,
                "answer"            => "",
            ];
            $this->logger->debug("sending code to {$email}");
            $this->AskQuestion($question, null, 'Question');

            return false;
        }
        // Security questions
        elseif (
            ($btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@data-nemo = 'entrySubmit']"), 0))
            && (
                // Security questions
                $this->http->FindSingleNode("//input[@name = 'selectedChallengeType' and @value = 'securityQuestions']/@value")
                // phone number
                || $this->http->FindSingleNode("//li[@class = 'challenge-option selected' and @data-nemo = 'smsChallengeOption']//input/@value")
                || $this->http->FindSingleNode("//li[@class = 'challenge-option selected single-element' and @data-nemo = 'smsChallengeOption']//input/@value")
                || $this->http->FindSingleNode("//li[@class = 'challenge-option' and @data-nemo = 'emailChallengeOption']//input/@value")
                || $this->http->FindSingleNode("//li[@class = 'challenge-option selected single-element' and @data-nemo = 'ivrChallengeOption']//input/@value")
            )
        ) {
            $sqBtn = $selenium->waitForElement(WebDriverBy::xpath("//li[contains(@class, 'challenge-option') and @data-nemo = 'smsChallengeOption']/label"), 0)
                ?? $selenium->waitForElement(WebDriverBy::xpath("//li[contains(@class, 'challenge-option') and @data-nemo = 'emailChallengeOption']/label"), 0)
                ?? $selenium->waitForElement(WebDriverBy::xpath("//li[contains(@class, 'challenge-option') and @data-nemo = 'ivrChallengeOption']/label"), 0)
                ?? $selenium->waitForElement(WebDriverBy::xpath("//li[contains(@class, 'challenge-option') and @data-nemo = 'securityQuestionsChallengeOption']/label"), 0);

            if ($sqBtn) {
                $x = $sqBtn->getLocation()->getX();
                $y = $sqBtn->getLocation()->getY() - 200;
                $selenium->driver->executeScript("window.scrollBy($x, $y)");
                sleep(1);
                $sqBtn->click();
            }

            if ($this->http->FindSingleNode("//li[@class = 'challenge-option selected' and @data-nemo = 'smsChallengeOption']//input/@value")
                || $this->http->FindSingleNode("//li[@class = 'challenge-option selected single-element' and @data-nemo = 'ivrChallengeOption']//input/@value")) {
                /*
                throw new CheckException("To retrieve your PayPal account balance please go to your PayPal account and <a target='_blank' href='https://www.paypal.com/myaccount/settings/securityQuestions/edit/'>set up security questions</a>. After that please try updating this account one more time.", ACCOUNT_PROVIDER_ERROR);/*review*/
                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }
            }

            $btn->click();
            $selenium->waitForElement(WebDriverBy::xpath("//form[@class = 'securityQuestionsForm'] | //input[@name = 'answer']"), 7);
        }

        $this->savePageToLogs($selenium);
        // curl
        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->seleniumURL = $selenium->http->currentUrl();
        $this->logger->notice("[Selenium URL]: {$this->seleniumURL}");
        $this->savePageToLogs($selenium);

        $selenium->http->cleanup();

        return true;
    }

    private function securityChallenge($selenium)
    {
        /** @var TAccountCheckerMarriott $selenium */
        $this->logger->notice(__METHOD__);
        // captcha
        $iframe = $selenium->waitForElement(WebDriverBy::xpath("//form[contains(@action, '/auth/validatecaptcha')]"), 4, false);

        if ($iframe) {
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }
            $selenium->driver->executeScript("$('form[name = \"challenge\"]').append('<input type=\"hidden\" name=\"recaptcha\" value=\"" . $captcha . "\">');");
            $selenium->driver->executeScript("$('form[name = \"challenge\"]').submit();");

            sleep(3);

            return true;
        }

        return false;
    }
}
