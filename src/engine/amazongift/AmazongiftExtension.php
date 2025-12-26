<?php

namespace AwardWallet\Engine\amazongift;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\Element;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AmazongiftExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;
    private $login2;
    private $login3;

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->logger->debug("Region => {$options->login2}");
        $this->login2 = $options->login2;
        $this->login3 = $options->login3;

        /*
        switch ($options->login2) {
            case 'UK':
                return "https://www.amazon.co.uk/ref=ap_frn_logo";

            case 'France':
                return "https://www.amazon.fr/ref=ap_frn_logo";

            case 'Canada':
                return "https://www.amazon.ca/ref=nav_logo";

            case 'Germany':
                return "https://www.amazon.de/ref=ap_frn_logo";

            case 'Japan':
                return "https://www.amazon.co.jp/";

            default:
                return "https://www.amazon.com";
        }
        */

        if (
            isset($options->login2)
            && in_array($options->login2, ["UK", "France", "Canada", "Germany", "Japan"])
        ) {
            switch ($options->login2) {
                case 'UK':
                    return "https://www.amazon.co.uk/ref=ap_frn_logo";

                case 'France':
                    return "https://www.amazon.fr/ref=ap_frn_logo";

                case 'Canada':
                    return "https://www.amazon.ca/ref=nav_logo";

                case 'Germany':
                    return "https://www.amazon.de/ref=ap_frn_logo";

                case 'Japan':
                    return "https://www.amazon.co.jp/";

                default:
                    return "https://www.amazon.com";
            }
        } else {
            switch ($options->login3) {
                case "amazonaff":
                    return 'https://www.amazon.com/ap/signin?openid.return_to=https%3A%2F%2Faffiliate-program.amazon.com%2F&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=amzn_associates_us&openid.mode=checkid_setup&marketPlaceId=ATVPDKIKX0DER&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.pape.max_auth_age=0';

                case "amazonturk":
                    return 'https://www.mturk.com/mturk/beginsignin';

                default:
                    return 'https://www.amazon.com/ap/signin?_encoding=UTF8&openid.assoc_handle=usflex&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.com%2Fgp%2Fyourstore%2Fhome%3Fie%3DUTF8%26ref_%3Dnav_custrec_signin';
            }
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//input[@id="ap_email" or @id = "ap_email_login"] | //a[@id="nav-item-signout"] | //div[@id="nav-flyout-ya-signin"]', EvaluateOptions::new()->visible(false));

        return $el->getNodeName() == 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//span[@id="nav-link-accountList-nav-line-1"]', FindTextOptions::new()->preg("/^\w+, (.+)$/"));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $el = $tab->evaluate('
            //input[@id="ap_email"]
            | //input[@id = "ap_email_login"]
            | //input[@id="ap_password"]
            | //input[@id="signInSubmit"]
            | //input[@id="continue"]
            | //a[@id="ap_switch_account_link"]
            | //input[contains(@name, "captcha_input")]
        ');

        if ($captchaResult = $this->processCaptcha($tab, $el)) {
            return $captchaResult;
        }

        $login = $tab->evaluate('//input[@id="ap_email" or @id = "ap_email_login"]', EvaluateOptions::new()->allowNull(true)->timeout(0));
        $pass = $tab->evaluate('//input[@id="ap_password"]', EvaluateOptions::new()->allowNull(true)->timeout(0));

        $sbm = $tab->evaluate('//input[@id="signInSubmit"]', EvaluateOptions::new()->allowNull(true)->timeout(0));
        $continue = $tab->evaluate('//input[@id="continue"] | //span[@id = "continue"]//input', EvaluateOptions::new()->allowNull(true)->timeout(0));

        $switch = $tab->evaluate('//a[@id="ap_switch_account_link"]', EvaluateOptions::new()->allowNull(true)->timeout(0));

        if ($switch) {
            $login->click();
            $tab->evaluate('//a[@data-name="sign_out_request"]')->click();

            return $this->processStandardForm($tab, $credentials);
        }

        if ($login && !$pass && $continue) {
            return $this->processSerialForm($tab, $credentials);
        } elseif ($login && $pass && $sbm) {
            return $this->processStandardForm($tab, $credentials);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        /*
        $tab->gotoUrl("https://www.amazon.com/gp/flex/sign-out.html?path=%2Fgp%2Fyourstore%2Fhome&useRedirectOnSuccess=1&signIn=1&action=sign-out&ref_=nav_AccountFlyout_signout");
        $tab->evaluate('//input[@class="a-button-input"]');
        */

        if (
            isset($this->login2)
            && in_array($this->login2, ["UK", "France", "Canada", "Germany", "Japan"])
        ) {
            switch ($this->login2) {
                case "France":
                    $tab->gotoUrl("https://www.amazon.fr/gp/flex/sign-out.html/ref=nav_youraccount_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1");

                    break;

                case "Canada":
                    $tab->gotoUrl('https://www.amazon.ca/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1');

                    break;

                case "Germany":
                    $tab->gotoUrl('https://www.amazon.de/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1');

                    break;

                case "Japan":
                    $tab->gotoUrl('https://www.amazon.co.jp/-/en/gp/flex/sign-out.html?path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1&action=sign-out&ref_=nav_AccountFlyout_signout');

                    break;

                default:
                    $tab->gotoUrl("https://www.amazon.co.uk/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1");

                    break;
            }
        } else {
            switch ($this->login3) {
                case "amazonaff":
                    $tab->gotoUrl('http://affiliate-program.amazon.com/gp/flex/associates/sign-out.html?ie=UTF8&action=sign-out');
                    // no break
                case "amazonturk":
                    $tab->gotoUrl('https://www.mturk.com/mturk/beginsignout');
                    // no break
                default:
                    $tab->gotoUrl('http://www.amazon.com/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1');
            }
        }

        $tab->evaluate('//input[@class="a-button-input"]');
    }

    private function processSerialForm(Tab $tab, Credentials $credentials): LoginResult
    {
        $this->logger->notice(__METHOD__);
        $generalXpath = '
            //div[@class="a-alert-content" and text()]
            | //div[@class="a-alert-content"]/p[text()]

            | //form[@id="verification-code-form"]
            | //form[contains(@action,"verify")]
            | //div[@id="auth-error-message-box"]
            | //a[contains(@href,"=nav_youraccount_btn")]

            | //input[contains(@name, "captcha_input")]
        ';

        $tab->evaluate('//input[@id="ap_email" or @id = "ap_email_login"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="continue"] | //span[@id = "continue"]//input')->click();

        $submitResult = $tab->evaluate($generalXpath . ' | //input[@id="ap_password"]');

        if ($captchaResult = $this->processCaptcha($tab, $submitResult)) {
            return $captchaResult;
        }

        $submitResult = $tab->evaluate($generalXpath . ' | //input[@id="ap_password"]');

        if ($questionResult = $this->processQuestion($tab, $submitResult)) {
            return $questionResult;
        }

        $submitResult = $tab->evaluate($generalXpath . ' | //input[@id="ap_password"]');

        if ($loginResult = $this->checkLoginResult($submitResult)) {
            return $loginResult;
        }

        $tab->evaluate('//input[@id="ap_password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//input[@id="signInSubmit"]')->click();

        $submitResult = $tab->evaluate($generalXpath);

        if ($captchaResult = $this->processCaptcha($tab, $submitResult)) {
            return $captchaResult;
        }

        $submitResult = $tab->evaluate($generalXpath);

        if ($questionResult = $this->processQuestion($tab, $submitResult)) {
            return $questionResult;
        }

        $submitResult = $tab->evaluate($generalXpath);

        return $this->checkLoginResult($submitResult);
    }

    private function processStandardForm(Tab $tab, Credentials $credentials)
    {
        $this->logger->notice(__METHOD__);

        $generalXpath = '
            //div[@class="a-alert-content" and text()]
            | //div[@class="a-alert-content"]/p[text()]

            | //form[@id="verification-code-form"]
            | //form[contains(@action,"verify")]
            | //div[@id="auth-error-message-box"]
            | //a[contains(@href,"=nav_youraccount_btn")]

            | //input[contains(@name, "captcha_input")]
        ';

        $tab->evaluate('//input[@id="ap_email" or @id = "ap_email_login"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="ap_password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//input[@id="signInSubmit"]')->click();

        $submitResult = $tab->evaluate($generalXpath);

        if ($captchaResult = $this->processCaptcha($tab, $submitResult)) {
            return $captchaResult;
        }

        $submitResult = $tab->evaluate($generalXpath);

        if ($questionResult = $this->processQuestion($tab, $submitResult)) {
            return $questionResult;
        }

        $submitResult = $tab->evaluate($generalXpath);

        return $this->checkLoginResult($submitResult);
    }

    private function processCaptcha(Tab $tab, Element $element)
    {
        $this->logger->notice(__METHOD__);

        if (
            $element->getNodeName() == 'INPUT'
            && $element->getAttribute('name') == 'captcha_input'
        ) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('
                //div[@class="a-alert-content" and text()]
                | //div[@class="a-alert-content"]/p[text()]

                | //form[@id="verification-code-form"]
                | //form[contains(@action,"verify")]
                | //div[@id="auth-error-message-box"]
                | //a[contains(@href,"=nav_youraccount_btn")]
            ', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$submitResult) {
                return LoginResult::captchaNotSolved();
            }
        } else {
            $this->logger->debug('captcha not found');
        }
    }

    private function processQuestion(Tab $tab, Element $element)
    {
        $this->logger->notice(__METHOD__);

        if (
            $element->getNodeName() == 'FORM'
            && (
                strstr($element->getAttribute('action'), 'verify')
                || strstr($element->getAttribute('id'), 'verification-code-form')
            )
        ) {
            $tab->showMessage(tab::MESSAGE_IDENTIFY_COMPUTER);
            $question = $tab->evaluate('//div[@id="channelDetailsForOtp"]//span[contains(@class, "transaction-approval-word-break")]')->getInnerText();

            $otpSubmitResult = $tab->evaluate('
                //div[@id="auth-error-message-box"]
                | //a[contains(@href,"=nav_youraccount_btn")]
                | //div[@id="invalid-otp-code-message"]
            ', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$otpSubmitResult) {
                return LoginResult::identifyComputer();
            }

            if ($otpSubmitResult->getNodeName() == 'DIV' && in_array($otpSubmitResult->getAttribute('id'), ['auth-error-message-box', 'invalid-otp-code-message'])) {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }

            if ($otpSubmitResult->getNodeName() == 'A') {
                return new LoginResult(true);
            }
        } else {
            $this->logger->debug('question not found');
        }
    }

    private function checkLoginResult(Element $element)
    {
        $this->logger->notice(__METHOD__);

        if (
            in_array($element->getNodeName(), ['DIV', 'P'])
        ) {
            return new LoginResult(false, $element->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif (
            $element->getNodeName() == 'A'
        ) {
            return new LoginResult(true);
        } else {
            $this->logger->debug('unknown login result');
        }
    }

    /*
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        if ($this->login3) {
            $tab->evaluate('//a[contains(@href, "signin")]')->click();
        }

        $submitResult = $tab->evaluate('//input[@name="email"] | //input[contains(@name, "captcha_input")]');

        if ($submitResult->getNodeName() == 'INPUT' && $submitResult->getAttribute('name') == 'captcha_input') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('
                //div[@class="a-alert-content" and text()]
                | //div[@class="a-alert-content"]/p[text()]
                | //input[@name="password"]
            ', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$submitResult) {
                return LoginResult::captchaNotSolved();
            }
        }

        $login = $tab->evaluate('//input[@name="email"]');
        $login->setValue($credentials->getLogin());
        $tab->evaluate('//input[@class="a-button-input"]')->click();

        $submitResult = $tab->evaluate('
            //div[@class="a-alert-content" and text()]
            | //div[@class="a-alert-content"]/p[text()]
            | //input[@name="password"]
            | //input[contains(@name, "captcha_input")]
        ');

        if ($submitResult->getNodeName() == 'INPUT' && $submitResult->getAttribute('name') == 'captcha_input') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('
                //div[@class="a-alert-content" and text()]
                | //div[@class="a-alert-content"]/p[text()]
                | //input[@name="password"]
            ', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$submitResult) {
                return LoginResult::captchaNotSolved();
            }
        }

        if (in_array($submitResult->getNodeName(), ['DIV', 'P'])) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());
        $tab->evaluate('//input[@class="a-button-input"]')->click();

        $submitResult = $tab->evaluate('
            //div[@class="a-alert-content" and text()]
            | //div[@class="a-alert-content"]/p[text()]

            | //form[@id="verification-code-form"]
            | //form[contains(@action,"verify")]
            | //div[@id="auth-error-message-box"]
            | //a[contains(@href,"=nav_youraccount_btn")]

            | //input[contains(@name, "captcha_input")]
        ');

        if ($submitResult->getNodeName() == 'INPUT' && $submitResult->getAttribute('name') == 'captcha_input') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('
                //div[@class="a-alert-content" and text()]
                | //div[@class="a-alert-content"]/p[text()]

                | //form[@id="verification-code-form"]
                | //form[contains(@action,"verify")]
                | //div[@id="auth-error-message-box"]
                | //a[contains(@href,"=nav_youraccount_btn")]
            ', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$submitResult) {
                return LoginResult::captchaNotSolved();
            }
        }

        if (in_array($submitResult->getNodeName(), ['DIV', 'P'])) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif ($submitResult->getNodeName() == 'A') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'FORM') {
            $tab->showMessage(tab::MESSAGE_IDENTIFY_COMPUTER);
            $question = $tab->evaluate('//div[@id="channelDetailsForOtp"]//span[contains(@class, "transaction-approval-word-break")]')->getInnerText();

            $otpSubmitResult = $tab->evaluate('
                //div[@id="auth-error-message-box"]
                | //a[contains(@href,"=nav_youraccount_btn")]
                | //div[@id="invalid-otp-code-message"]
            ', EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$otpSubmitResult) {
                return LoginResult::identifyComputer();
            }

            if ($otpSubmitResult->getNodeName() == 'DIV' && in_array($otpSubmitResult->getAttribute('id'), ['auth-error-message-box', 'invalid-otp-code-message'])) {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }

            if ($otpSubmitResult->getNodeName() == 'A') {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }
    */
}
