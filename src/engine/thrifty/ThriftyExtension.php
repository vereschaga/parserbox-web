<?php

namespace AwardWallet\Engine\thrifty;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ThriftyExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.thrifty.com/bluechip/index.aspx';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//strong[contains(text(), "Join Now")] | //div[@id="BlueChipMainNotLoggedIn"] | //strong[contains(text(), "Book Now")]');

        return strstr($el->getInnerText(), "Book Now");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[contains(@id, "FirstName")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Hi, (.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->gotoUrl('https://www.thrifty.com/BlueChip/SignIn.aspx');

        $tab->evaluate('//input[contains(@name, "BlueChipID")]');

        sleep(3);

        $login = $tab->evaluate('//input[contains(@name, "BlueChipID")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[contains(@name, "Password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[contains(@id, "SignIn") and contains(@id, "Button")]')->click();

        $submitResult = $tab->evaluate('//span[contains(@id, "MainMemberLoggedIn")] | //span[@class="ErrorLabel"] | //span[@class="ValidatorMessage"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'SPAN' && strstr($submitResult->getAttribute('class'), "ErrorLabel")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The password you specified is not correct.  Please verify that you entered it correctly.")
                || strstr($error, "We were unable to find your Blue Chip number.  Please verify that you entered it correctly.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Information has been entered incorrectly multiple times against your account. For security reasons, please verify your account details and")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'SPAN' && strstr($submitResult->getAttribute('class'), "ValidatorMessage")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            return new LoginResult(true);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.thrifty.com/BlueChip/SignIn.aspx');
        $tab->evaluate('//input[contains(@name, "BlueChipID")]');
    }
}
