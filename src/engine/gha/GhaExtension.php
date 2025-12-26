<?php

namespace AwardWallet\Engine\gha;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class GhaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.ghadiscovery.com/member/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[contains(@class, "tid-inputUserName")] | //button[contains(@class, "tid-viewDetailsMember")]/preceding-sibling::div/span[contains(@class, "text-dark")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//button[contains(@class, "tid-viewDetailsMember")]/preceding-sibling::div/span[contains(@class, "text-dark")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[contains(@class, "tid-inputUserName")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@class, "tid-inputUserPass")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@class, "tid-signInButton") and not(contains(@class, "opacity-40")) and @type="submit"]')->click();

        $tab->evaluate('//span[contains(@class, "text-danger")] | //button[contains(@class, "tid-viewDetailsMember")]/preceding-sibling::div/span[contains(@class, "text-dark")] | //iframe[contains(@title, "recaptcha")]');

        sleep(3);

        $submitResult = $tab->evaluate('//span[contains(@class, "text-danger")] | //button[contains(@class, "tid-viewDetailsMember")]/preceding-sibling::div/span[contains(@class, "text-dark")] | //iframe[contains(@title, "recaptcha")]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//span[contains(@class, "text-danger")] | //button[contains(@class, "tid-viewDetailsMember")]/preceding-sibling::div/span[contains(@class, "text-dark")]', EvaluateOptions::new()->timeout(60));
        }

        if (strstr($submitResult->getAttribute('class'), "text-dark")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Wrong username or password.")
                || strstr($error, "This field is mandatory.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "System is currently unavailable. Please try again later.")) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($error, "Your account has been blocked due to multiple unsuccessful attempts. Please reset your password to access your account.")) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[contains(@class, "tid-topNavProfileIcon")]')->click();
        $tab->evaluate('//div[contains(@class, "tid-profileNavLogout")]')->click();
        $tab->evaluate('//input[contains(@class, "tid-inputUserName")]');
    }
}
