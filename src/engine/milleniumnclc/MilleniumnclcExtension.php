<?php

namespace AwardWallet\Engine\milleniumnclc;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class MilleniumnclcExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.millenniumhotels.com/en/my-millennium/scratchpad/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@name="username"] | //span[@class="key-label" and contains(text(), "MEMBER NUMBER")]/following-sibling::span');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@class="key-label" and contains(text(), "MEMBER NUMBER")]/following-sibling::span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="username"]');
        sleep(3);
        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
        $submitResult = $tab->evaluate('//span[contains(@class, "error-tip") and text()] | //div[@class="dialog-email-check-container"] | //img[@class="user-avatar"]', EvaluateOptions::new()->timeout(60));

        if ($submitResult->getNodeName() == 'IMG') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);

            $otpSubmitResult = $tab->evaluate('//span[@class="key-label" and contains(text(), "MEMBER NUMBER")]/following-sibling::span',
                EvaluateOptions::new()->timeout(180)->allowNull(true));

            if (!$otpSubmitResult) {
                return LoginResult::identifyComputer();
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@id="opt-logout" and contains(@class, "signout")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//input[@name="username"]');
    }
}
