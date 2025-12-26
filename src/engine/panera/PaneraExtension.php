<?php

namespace AwardWallet\Engine\panera;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class PaneraExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.panerabread.com/en-us/mypanera/profile-and-settings.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $tab->evaluate('//a[@aria-label="Sign In"] | //p[contains(@class, "mypanera-number")]');
        sleep(3);
        $el = $tab->evaluate('//a[@aria-label="Sign In"] | //p[contains(@class, "mypanera-number")]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//p[contains(@class, "mypanera-number")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[@aria-label="Sign In"]')->click();

        $login = $tab->evaluate('//input[@id="signInUsername"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="signInPassword"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@aria-label="Sign In" and not(@disabled)]')->click();

        $submitResult = $tab->evaluate('//p[contains(@class, "mypanera-number")] | //p[contains(text(), "We were unable to sign you in. Please check your credentials and try again")] | //h2[contains(text(), "Enter Verification Code")]/following-sibling::p');

        if (strstr($submitResult->getAttribute('class'), "mypanera-number")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getInnerText(), "We were unable to sign you in. Please check your credentials and try again")) {
            $error = $submitResult->getInnerText();

            return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $question = $submitResult->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="code"]');
            $input->setValue($answer);

            $otpSubmitResult = $tab->evaluate('//p[@id="codeDescription"]/span | //p[contains(@class, "mypanera-number")]');

            if ($otpSubmitResult->getNodeName() == 'SPAN') {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[contains(text(), "Sign Out")]')->click();
        $tab->evaluate('//button[contains(text(), "Sign In")]');
    }
}
