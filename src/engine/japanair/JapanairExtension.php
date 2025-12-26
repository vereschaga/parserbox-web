<?php

namespace AwardWallet\Engine\japanair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class JapanairExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2) {
            case 'Europe'://deprecated
                $url = 'https://www.de.jal.co.jp/er/en/jmb/';

                break;

            case 'br':
                $url = 'https://www.br.jal.co.jp/brl/pt/';

                break;

            case 'be':
                $url = 'https://www.nl.jal.co.jp/nll/en/?country=be';

                break;

            case 'cz':
                $url = 'https://www.at.jal.co.jp/atl/en/?country=cz';

                break;

            case 'dk':
                $url = 'https://www.nl.jal.co.jp/nll/en/?country=dk';

                break;

            case 'es':
                $url = 'https://www.es.jal.co.jp/esl/en/?country=es';

                break;

            case 'Japan'://deprecated
            case 'ja':
                $url = 'https://www.jal.co.jp/en/jmb/';

                break;

            case 'Asia'://deprecated
            case 'hl':
                $url = 'https://www.ar.jal.co.jp/arl/en/jmb/?country=hl';

                break;

            case 'ie':
                $url = 'https://www.uk.jal.co.jp/ukl/en/?city=DUB';

                break;

            case 'mx':
                $url = 'https://www.ar.jal.co.jp/arl/en/?city=MEX';

                break;

            case 'pl':
                $url = 'https://www.at.jal.co.jp/atl/en/?country=pl';

                break;

            case 'pt':
                $url = 'https://www.jal.co.jp/es/en/?city=LIS';

                break;

            case 'ru':
                $url = 'https://www.uk.jal.co.jp/ukl/en/?country=ru';

                break;

            case 'se':
                $url = 'https://www.nl.jal.co.jp/nll/en/?country=se';

                break;

            case 'sg':
                $url = 'https://www.sg.jal.co.jp/sgl/en/';

                break;

            case 'America'://deprecated
            case 'ar':
            case 'ca':
            case '':
                $url = 'https://www.ar.jal.co.jp/arl/en/jmb/';

                break;

            case 'hk':
            case 'au':
                $url = "https://www.{$options->login2}.jal.co.jp/{$options->login2}l/en/";

                break;

            default:
                $url = "https://www.{$options->login2}.jal.co.jp/{$options->login2}l/en/";

                break;
        }

        return $url;
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//span[@class="status-login"] | //span[@id="JS_logout"] | //strong[@class="name"]');

        return $el->getNodeName() == "STRONG" || strstr($el->getInnerText(), "LOG OUT");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//a[contains(@href, "jmb") and @aria-current="page"] | //strong[@class="name"]');

        if ($el->getNodeName() == 'A') {
            $el->click();
        }

        return $tab->evaluate('//strong[@class="name"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[contains(@class, "login-button-area")] | //span[@class="status-login"]')->click();

        $login = $tab->evaluate('//input[@name="id"]', EvaluateOptions::new()->timeout(30));
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="btn-wrap"]//button')->click();

        $submitResult = $tab->evaluate('
            //strong[@class="name"]
            | //h2[@class="panel-attention_hdg"]/following-sibling::p
            | //*[contains(text(), "System error. Please check the system maintenance page or try from the top page.")]
            | //input[@name="otp"]
        ');

        if ($submitResult->getNodeName() == 'STRONG') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'INPUT') {
            $question = $tab->evaluate('//a[contains(@href, "/jmb-login/contact/")]/../p')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@name="otp"]');
            $input->setValue($answer);

            $tab->evaluate('//div[@class="btn-wrap"]/button')->click();

            $otpSubmitResult = $tab->evaluate('//div[@id="JS_error"]//p | //span[@id="JS_logout"] | //strong[@class="name"]');

            if ($otpSubmitResult->getNodeName() == 'P') {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your account is locked. Please reset your password and try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($error, "The JMB membership number or password you entered is incorrect. Please check and try again")
                || strstr($error, "Your account is locked. Please reset your password and try again")
                || strstr($error, "JMB membership number or PIN is not correct")
                || strstr($error, "The information you entered could not be validated. In order to safeguard your personal information, we will terminate the service")
                || strstr($error, "The JAL Mileage Bank enrollment process is not complete")
                || strstr($error, "To update this Japan Airlines (JMB) account you need to select the country in the ‘Region’ field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information")
                || strstr($error, "JMB membership number or PIN is not correct")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "System error. Please check the system maintenance page or try from the top page.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www121.jal.co.jp/JmbWeb/LogOut_en.do');
        $tab->evaluate('//*[contains(text(), "You have logged out")]');
    }
}
