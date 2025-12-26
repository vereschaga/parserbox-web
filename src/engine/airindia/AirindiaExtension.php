<?php

namespace AwardWallet\Engine\airindia;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AirindiaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.airindia.com/in/en/flying-returns/account-summary.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="username"] | //p[contains(@class, "auth-user-other-info")]/span[@class="user-ffn" and text() and not(text()="ID- ")]');
        sleep(3);
        $el = $tab->evaluate('//input[@id="username"] | //p[contains(@class, "auth-user-other-info")]/span[@class="user-ffn" and text() and not(text()="ID- ")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//p[contains(@class, "auth-user-other-info")]/span[@class="user-ffn" and text()]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $this->logger->debug('switching to email auth: ' . strstr($credentials->getLogin(), '@'));

        if (strstr($credentials->getLogin(), '@')) {
            $this->logger->debug('switching to email auth!');
            $tab->evaluate('//button[@id="custom-passwordless-button"]')->click();
        }

        $login = $tab->evaluate('//input[@id="username"]');
        sleep(3);
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//span[@id="error-element-username"] | //div[@id="prompt-alert"] | //input[@id="code"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Could not find an account associated with this email")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);

            $otpSubmitResult = $tab->evaluate('//p[contains(@class, "auth-user-other-info")]/span[@class="user-ffn" and text() and not(text()="ID- ")]',
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
        $tab->evaluate('//button[@class="logoutbtn"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[@id="signIn"]');
    }
}
