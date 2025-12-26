<?php

namespace AwardWallet\Engine\mileageplus;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class MileageplusExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.united.com/en/us/myunited';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $loginFieldOrLogOut = $tab->evaluate('
        //form//input[@id="MPIDEmailField"] 
        | //button[@id="switch-account-button"]
        | //span[contains(text(),"MileagePlus Number")]');

        return $loginFieldOrLogOut->getInnerText() == 'MileagePlus Number';
    }

    public function getLoginId(Tab $tab): string
    {
        $accountNumber = $tab->findText('//span[contains(text(),"MileagePlus Number")]/following-sibling::text()',
            FindTextOptions::new()->nonEmptyString()->preg('/^\s*(\w+)\s*$/'));

        return $accountNumber ?? '';
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="loginButton"]')->click();
        $tab->evaluate('//button[span[contains(text(),"SIGN OUT")]]')->click();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $switch = $tab->evaluate('//button[@id="switch-account-button"]', EvaluateOptions::new()->allowNull(true)->timeout(5));

        if ($switch) {
            $switch->click();
        }

        $tab->evaluate('//form//input[@id="MPIDEmailField"]')->setValue($credentials->getLogin());
        $tab->evaluate('//form//button[@type="submit"]')->click();
        $errorOrLogOut = $tab->evaluate('//div[contains(@class,"atm-c-alert--error")]//p', EvaluateOptions::new()->allowNull(true)->timeout(3));

        if ($errorOrLogOut) {
            $error = $errorOrLogOut->getInnerText();

            if (str_contains($error, "Sorry, something went wrong. Please try again.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }
        }

        $tab->evaluate('//form//input[@id="password"]')->setValue($credentials->getPassword());
        $rememberMe = $tab->evaluate('//form//input[@name="rememberMe"]');

        if (!$rememberMe->checked()) {
            $rememberMe->click();
        }

        $tab->evaluate('//form//button[@type="submit"]')->click();

        $errorOrLogOut = $tab->evaluate('//div[contains(@class,"atm-c-alert--error")]//p 
        | //h2[contains(text(),"To confirm your identity,")] 
        | //span[contains(text(),"Enter the verification code sent to ")] 
        | //span[contains(text(),"MileagePlus Number")]');

        // Enter the verification code sent to
        if (str_contains($errorOrLogOut->getInnerText(), 'Enter the verification code sent')) {
            $tab->showMessage(Tab::identifyComputerMessage("Continue"));
            $errorOrLogOut = $tab->evaluate('//span[contains(text(),"MileagePlus Number")]', EvaluateOptions::new()->timeout(180));

            if (!$errorOrLogOut) {
                return LoginResult::identifyComputer();
            } else {
                return new LoginResult(true);
            }
        }

        // To confirm your identity, please answer the following security questions:
        if (str_contains($errorOrLogOut->getInnerText(), 'To confirm your identity, ')) {
            $this->checkQuestion($tab, $credentials);

            $rememberMe = $tab->evaluate('//input[@name="isRememberDevice"]');

            if (!$rememberMe->checked()) {
                $tab->evaluate('//label[@for="' . $rememberMe->getAttribute('id') . '"]')->click();
            }

            $tab->evaluate('//button[@type="submit" and @data-test-id="nextButton"]')->click();

            return new LoginResult(true);
        } elseif (strtoupper($errorOrLogOut->getInnerText()) == 'MileagePlus Number') {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $errorOrLogOut->getInnerText();

            if (str_contains($error, "The account information entered is invalid. Try again.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }
        }

        return new LoginResult(false);
    }

    private function checkQuestion(Tab $tab, Credentials $credentials)
    {
        /*$form = $tab->evaluate('//form[contains(@class,"MyUnitedAuthQuestions")]');
        sleep(5);
        $options = $tab->evaluateAll('(//select[contains(@name,"AuthQuestions")])[1]/option');
        $this->logger->debug("options found: " . count($options));

        return new LoginResult(false);*/

        $form = $tab->evaluate('//form[contains(@class,"MyUnitedAuthQuestions")]');
        $fieldset = $tab->evaluateAll('.//div[contains(@class,"MyUnitedAuthQuestions")]/div[contains(@class,"atm-c-select-field ")]',
            EvaluateOptions::new()->contextNode($form));
        $this->logger->debug("questions found: " . count($fieldset));
        function stripQuote($str)
        {
            return trim(str_replace(['(required)', '"', "'", '\\'], '', $str));
        }
        $this->logger->debug("getAnswers");
        $this->logger->debug(var_export($credentials->getAnswers(), true));

        foreach ($fieldset as $item) {
            $q = stripQuote($tab->findText('.//label', FindTextOptions::new()->contextNode($item)));
            $select = $tab->evaluate('.//select[contains(@name,"AuthQuestions")]', EvaluateOptions::new()->contextNode($item));

            foreach ($credentials->getAnswers() as $question => $answer) {
                $this->logger->debug($q . ' = ' . stripQuote($question));

                if ($q === stripQuote($question)) {
                    $key = null;
                    $options = $tab->evaluateAll('./option', EvaluateOptions::new()->contextNode($select)->visible(false));

                    foreach ($options as $option) {
                        $this->logger->debug(stripQuote($option->getInnerText()) . ' = ' . stripQuote($answer));

                        if (stripQuote($option->getInnerText()) === stripQuote($answer)) {
                            $key = $option->getAttribute('value');
                        }
//                        if ($key === null) {
//                            $key = $tab->evaluate('.//option[contains(text(),"' . $answer . '")]')->getAttribute('value');
//                        }
                        if ($key != null) {
                            $this->logger->debug("key $key");
                            $select->setValue($key);

                            break;
                        }
                    }
                }
            }
        }

        return true;
    }

    /* public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
     {
         $this->fileLogger->logFile($tab->screenshot(), ".jpeg");
         $st = $master->createStatement();

         $loginButton = $tab->findText('//button[@id="loginButton"]/@aria-label');
         // Hi, Alexi Premier Silver 711 miles
         $st->setBalance($this->findPreg('/Hi, .+? ([\d.,]+) miles/', $loginButton));
         // Hello
         $st->addProperty('Name', $tab->findText('//h2[starts-with(text(),"Hello,")]', FindTextOptions::new()->preg('/,\s+(.+)/')));
         $st->addProperty('MemberStatus', $this->findPreg("/Hi, {$st->getProperties()['Name']} ([\w\s]+) [\d.,]+ miles/", $loginButton));

         // MileagePlus Number
         $st->setNumber($tab->findText('//span[contains(text(),"MileagePlus Number")]/following-sibling::text()'));

     }*/
}
