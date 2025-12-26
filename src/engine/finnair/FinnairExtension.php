<?php

namespace AwardWallet\Engine\finnair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ContinueLoginInterface;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\message;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class FinnairExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ContinueLoginInterface
{
    private const APP_OTC_QUESTION = 'Please verify your identity by entering the verification code generated in your authenticator app.';
    private const PHONE_OTC_QUESTION = 'Please enter the 6-digit verification code that was sent to your phone number by text message.';

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.finnair.com/en/my-finnair-plus';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//app-login-finnair-plus | //span[@data-testid="member-number-formatted"]');
        $tab->saveHtml();
        $tab->saveScreenshot();

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@data-testid="member-number-formatted"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@formcontrolname="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@formcontrolname="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('
            //span[@data-testid="member-number-formatted"]
            | //div[contains(@class, "error") and not(@id)]
            | //div[@id="input-invalid"]
            | //div[h1[contains(text(), "Two-factor authentication")]]/p
        ');

        if ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "DIV" && strstr($submitResult->getAttribute('id'), "input-invalid")) {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } elseif ($submitResult->getNodeName() == 'P') {
            $tab->showMessage(Message::identifyComputer('Log in'));
            $question = $tab->findText('//div[h1[contains(text(), "Two-factor authentication")]]/p', FindTextOptions::new()->nonEmptyString());

            /*
            if (!strstr($question, self::APP_OTC_QUESTION) && !strstr($question, self::PHONE_OTC_QUESTION)) {
                $this->notificationSender->sendNotification('refs #25242 finnair - need to check question constants // IZ');
            }
            */

            $questionConstant = $this->getCorrectQuestion($question);

            if (!isset($questionConstant)) {
                $this->notificationSender->sendNotification('refs #25242 finnair - need to check question constants // IZ');
            }
            $this->logger->notice("isServerCheck: {$this->context->isServerCheck()}");
            $this->logger->notice("isBackground: {$this->context->isBackground()}");
            $this->logger->notice("isMailboxConnected: {$this->context->isMailboxConnected()}");

            if ($this->context->isServerCheck()) {
                $tab->logPageState();

                if (!$this->context->isBackground() || $this->context->isMailboxConnected()) {
                    $this->stateManager->keepBrowserSession(true);
                }

                return LoginResult::question($questionConstant ?? $question);
            } else {
                $loginIDElement = $tab->evaluate('//span[@data-testid="member-number-formatted"]',
                    EvaluateOptions::new()->nonEmptyString()->timeout(180)->allowNull(true));

                if ($loginIDElement) {
                    return new LoginResult(true);
                } else {
                    return LoginResult::identifyComputer();
                }
            }
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Login failed. Please check your username and password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function continueLogin(Tab $tab, Credentials $credentials): LoginResult
    {
        $questionConstants = [self::APP_OTC_QUESTION, self::PHONE_OTC_QUESTION];

        foreach ($questionConstants as $question) {
            if (!isset($credentials->getAnswers()[$question])) {
                continue;
            }

            $answer = $credentials->getAnswers()[$question] ?? null;

            if ($answer === null) {
                throw new \CheckException("expected answer for the question");
            }

            $input = $tab->evaluate('//input[@type="password"]');
            $input->setValue($answer);
            $tab->evaluate('//button[@type="submit"]')->click();
            $submitResult = $tab->evaluate('//p[contains(@class, "error")] | //span[@data-testid="member-number-formatted"]');

            $tab->saveScreenshot();

            if ($submitResult->getNodeName() == "SPAN") {
                return LoginResult::success();
            }

            if ($submitResult->getNodeName() === 'P') {
                $this->stateManager->keepBrowserSession(true);

                return LoginResult::question($question, $submitResult->getInnerText());
            }

            return LoginResult::success();
        }

        $this->logger->debug(var_export($credentials->getAnswers()));

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//fin-login-button')->click();
        $tab->evaluate('//*[@data-testid="profile-quick-view-logout-btn"]')->click();
        $tab->evaluate('//span[contains(text(), "Login")]');
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $tab->logPageState(); // for debug purposes
        $statement = $master->createStatement();

        $name = $tab->findText('//div[contains(@class, "plus-card-member-name")]/span', FindTextOptions::new()->nonEmptyString());

        if (isset($name)) {
            // Name
            $statement->addProperty('Name', $name);
        }

        $number = $tab->findText('//span[@data-testid="member-number-formatted"]', FindTextOptions::new()->nonEmptyString()->allowNull(true));

        if (isset($number)) {
            // Membership Number
            $statement->addProperty("Number", $number);
        }

        $status = $tab->findText('//span[@class="plus-card-tier-name"]', FindTextOptions::new()->nonEmptyString()->allowNull(true));

        if (isset($status) && strtolower($status) != 'basic') {
            $this->notificationSender->sendNotification('refs #25242 - need to check status // IZ');
        }

        if (isset($status)) {
            // Status
            $statement->addProperty('Status', $status);
        }

        $tierPoints = $tab->findText('//div[contains(@class, "tier-and-points-container")]/div/span', FindTextOptions::new()->nonEmptyString()->preg('/[\d,]+/')->allowNull(true));

        if (isset($tierPoints)) {
            // Tier points
            $statement->addProperty('Collected', $tierPoints);
        }
        // Points to next Tier
        $pointsToNextTier = $tab->findText('//div[contains(@class, "tier-and-points-container")]/div/div/span[contains(text(), "/")]', FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/[\d,]+/'));

        if (isset($pointsToNextTier)) {
            $statement->addProperty('PointsToNextTier', $pointsToNextTier);
        }
        // Balance - Award points
        $statement->setBalance($tab->findText('//fcom-icon[@data-testid="svg-library-avios-currency"]/following-sibling::span', FindTextOptions::new()->nonEmptyString()->preg('/[\d,]+/')->allowNull(true)));
        // Tracking period
        $trackingPeriodEnds = $tab->findText('//p[@data-testid="tier-progress-gain-maintain-non-lumo-info"]/following-sibling::p/span[contains(text(), "-")]', FindTextOptions::new()->nonEmptyString()->allowNull(true)->preg('/-\s(.*)/'));

        if (isset($trackingPeriodEnds)) {
            $statement->addProperty("TrackingPeriodEnds", $trackingPeriodEnds);
        }

        // Expiration Date
        if (strtolower($status) == 'junior') {
            $statement->setNeverExpires(true);
        } else {
            $expirationDate = $tab->findText('//div[contains(text(), "Your Avios are valid until")]', FindTextOptions::new()->nonEmptyString()->preg('/Your Avios are valid until (.*)\./')->allowNull(true));
            if (isset($expirationDate)) {
                $expirationDateParsed = strtotime($expirationDate);

                if (isset($expirationDateParsed)) {
                    $statement->setExpirationDate($expirationDateParsed);
                }    
            }
        }
    }

    private function getCorrectQuestion($question): ?string
    {
        $questionConstants = [self::APP_OTC_QUESTION, self::PHONE_OTC_QUESTION];

        foreach ($questionConstants as $questionConstant) {
            if (strstr($question, $questionConstant)) {
                return $questionConstant;
            }
        }

        return null;
    }
}
