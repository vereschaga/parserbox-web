<?php

namespace AwardWallet\Engine\qantas;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class QantasExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    private $lastName;

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->lastName = $options->login2;

        return 'https://www.qantas.com/gb/en.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@class, "login-widget")]//form[@name="LSLLoginForm"] | //button[@name="logoutButton"]', EvaluateOptions::new()->visible(false));

        return strstr($el->getNodeName(), "BUTTON");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[@class="ql-login-member-details-body"]//strong | //span[@data-testid="member-id"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3); // prevent incorrect click
        $tab->evaluate('//div[@class="login-ribbon"]/button')->click();

        $login = $tab->evaluate('//input[@id="form-member-id-input"]');
        $login->setValue($credentials->getLogin());

        $lastName = $tab->evaluate('//input[@id="form-member-surname-input"]');
        $lastName->setValue($this->lastName);

        $password = $tab->evaluate('//input[@id="form-member-pin-input"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="ql-login-column"]//button[@class="ql-login-submit-button"]')->click();

        $submitResult = $tab->evaluate('//div[@class="ql-login-error-heading"] | //span[@data-testid="member-id"] | //div[contains(text(), "Account Verification")]');

        if (strstr($submitResult->getNodeName(), "SPAN")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getNodeName(), "DIV") && strstr($submitResult->getAttribute('class'), "ql-login-error-heading")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The details do not match our records")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, "Account is now locked")) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            return new LoginResult(false, $error);
        } else {
            foreach ($credentials->getAnswers() as $question => $answer) {
                try {
                    $this->logger->debug("search for '{$question}' question...");
                    $el = $tab->evaluate('//label[contains(text(), "' . $question . '")]/../input');
                    $this->logger->debug("set input value: '{$answer}' ...");
                    $el->setValue($answer);
                } catch (ElementNotFoundException $e) {
                    $this->logger->debug('question not found, skip it');
                }
            }

            $tab->evaluate('//button[@type="submit" and contains(text(), "VERIFY")]')->click();

            $questionsSubmitResult = $tab->evaluate('//div[@class="ql-login-error-heading"] | //span[@data-testid="member-id"]');

            if (strstr($questionsSubmitResult->getNodeName(), "DIV")) {
                return new LoginResult(false, $questionsSubmitResult->getInnerText()); // TODO: Need to add error code. At the time of writing the autologin, it was unknown what error code to issue in this case
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="login-ribbon"]/button')->click();
        $tab->evaluate('//button[@name="logoutButton"]')->click();
        $tab->evaluate('//div[@class="ql-login-success-text"]');
    }
}
