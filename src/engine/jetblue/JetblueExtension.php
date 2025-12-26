<?php

namespace AwardWallet\Engine\jetblue;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class JetblueExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://trueblue.jetblue.com/my-dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $loginFieldOrNumber = $tab->evaluate('//form[normalize-space(@action)="/signin"]//input[@name="identifier"]
        | //span[contains(.,"#")]/following-sibling::span[normalize-space() and contains(@class,"value")]', EvaluateOptions::new()->timeout(60));

        return $loginFieldOrNumber->getNodeName() === 'SPAN';
    }

    public function getLoginId(Tab $tab): string
    {
        $accountNumber = $tab->evaluate('//span[contains(.,"#")]/following-sibling::span[normalize-space() and contains(@class,"value")]')->getInnerText();
        $this->logger->debug('Account Number: ' . $accountNumber);

        return $accountNumber;
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[contains(@class,"profile-container")]')->click();
        $tab->evaluate('//a[normalize-space(@data-qaid)="signOut" and contains(normalize-space(),"Sign Out")]')->click();

        // waiting for main page or login page
        $result = $tab->evaluate('//a[contains(@class,"main-nav-link") and not(contains(@class,"profile-container"))]/descendant::text()[normalize-space()="TrueBlue"]
        | //input[normalize-space(@autocomplete)="username"]', EvaluateOptions::new()->timeout(60));

        if ($result->getNodeName() === 'INPUT') { // login page
            return;
        }

        $tab->evaluate('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"JetBlue Airways")]', EvaluateOptions::new()->timeout(60)); // footer
        sleep(1);
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//form[normalize-space(@action)="/signin"]//input[@name="identifier"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//form[normalize-space(@action)="/signin"]//input[@name="credentials.passcode"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[normalize-space(@action)="/signin"]//input[normalize-space(@value)="Sign in"]')->click();

        $errorOrNumber = $tab->evaluate('//*[normalize-space() and contains(@class,"o-form-error-container") and contains(@class,"o-form-has-errors")]
        | //span[contains(.,"#")]/following-sibling::span[normalize-space() and contains(@class,"value")]
        | //h2[contains(text(),"Get a verification email")]');

        if (stristr($errorOrNumber->getInnerText(), 'Get a verification email')) {
            $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);
            $errorOrNumber = $tab->evaluate('//span[contains(.,"#")]/following-sibling::span[normalize-space() and contains(@class,"value")]',
                EvaluateOptions::new()->timeout(180)->allowNull(true));
            if ($errorOrNumber) {
                return new LoginResult(true);
            } else {
                return LoginResult::identifyComputer();
            }
        }
        elseif ($errorOrNumber->getNodeName() === 'SPAN'
            && preg_match('/^\d.+\d$/', $errorOrNumber->getInnerText()) > 0
        ) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $errorOrNumber->getInnerText();

            return new LoginResult(false, $error, null, null);
        }
    }
}
