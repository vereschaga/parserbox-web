<?php

namespace AwardWallet\Engine\aeroplan;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Message;
use AwardWallet\ExtensionWorker\SelectFrameOptions;
use AwardWallet\ExtensionWorker\Tab;

class AeroplanExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.aircanada.com/aeroplan/member/dashboard?lang=en-CA';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $loginFieldOrBalance = $tab->evaluate('//a[normalize-space()="Status"] 
        | //span[normalize-space()="Join Aeroplan"] 
        | //span[normalize-space()="Aeroplan number or email"]');

        return $loginFieldOrBalance->getNodeName() === 'A';
    }

    public function getLoginId(Tab $tab): string
    {
        //url profile page
        $tab->gotoUrl("https://www.aircanada.com/ca/en/aco/home/app.html#/viewprofile");

        $loginIdElm = $tab->evaluate('//text()[normalize-space()="Aeroplan number"]/following::text()[normalize-space()][1]',
            EvaluateOptions::new()
                ->nonEmptyString()
                ->allowNull(true)
                ->timeout(70));

        if ($loginIdElm) {
            $loginId = $loginIdElm->getInnerText();

            return str_replace(' ', '', $loginId);
        } else {
            $this->logger->warning('LoginId not found');
        }

        return '';
    }

    public function logout(Tab $tab): void
    {
        $this->logger->info('!Try Logout');
        $tab->gotoUrl("https://www.aircanada.com/clogin/pages/logout");
        $logOutTrue = $tab->evaluate('//span[normalize-space()="Join Aeroplan"]',
            EvaluateOptions::new()
                ->allowNull(true)
                ->timeout(70));

        if ($logOutTrue->getNodeName() !== 'SPAN') {
            $currenctURL = $tab->getUrl();

            if (preg_match("/(?:logout|redirect)/", $currenctURL)) {
                $tab->gotoUrl($currenctURL);

                $logOutTrue = $tab->evaluate('//span[normalize-space()="Join Aeroplan"]',
                    EvaluateOptions::new()
                        ->allowNull(true)
                        ->timeout(70));
            }
            $this->logger->info('logout ERROR!!!');
        }

        if ($logOutTrue->getNodeName() !== 'SPAN') {
            $this->logger->info('logout ERROR!!!');
        }
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate("//text()[normalize-space()='Sign in']/following::text()[normalize-space()='Sign in']/ancestor::*[1]")->click();

        $login = $tab->evaluate('//input[contains(@name, "username")]');
        $login->setValue($credentials->getLogin());
        $password = $tab->evaluate('//input[contains(@id, "password")]');
        $password->setValue($credentials->getPassword());
        sleep(1); // Otherwise, the button doesn't work.
        $tab->evaluate("//input[contains(@id, 'password')]/following::input[1]")->click();

        $enterCodeOrCaptcha = $tab->evaluate('//h2[normalize-space()="Please enter your code"] 
        | //div[contains(@class,"gigya-error-msg-active")]
        | //div[contains(@id,"gig_captcha_")]',
            EvaluateOptions::new()
                ->visible(true)
                ->allowNull(true)
                ->timeout(70));

        // Recaptcha
        if (stristr($enterCodeOrCaptcha->getAttribute('id'), 'gig_captcha_')
            || stristr($enterCodeOrCaptcha->getInnerText(), 'To login, confirm you are not a robot')) {
            $tab->showMessage('In order to log in into this account, you need to solve the CAPTCHA below and click the "Sign in" button.');

            $enterCodeOrCaptcha = $tab->evaluate('//h2[normalize-space()="Please enter your code"] 
            | //div[contains(@class,"gigya-error-msg-active")]',
                EvaluateOptions::new()->allowNull(true)->timeout(120));
        }

        if (stristr($enterCodeOrCaptcha->getAttribute('class'), 'gigya-error-msg-active')) {
            //$tab->showMessage('Please check the checkbox and click Sign in');

            $enterCodeOrCaptcha = $tab->selectFrameContainingSelector("//div[contains(@class, 'gigya-info-message-strip-text')]",
                SelectFrameOptions::new()->method("evaluate")
                    ->visible(true)
                    ->timeout(70)
                    ->allowNull(true));

            if (!$enterCodeOrCaptcha) {
                return LoginResult::captchaNotSolved();
            }

            $enterCodeOrCaptcha = $enterCodeOrCaptcha->evaluate("//div[contains(@class, 'gigya-info-message-strip-text')]");
        }

        if (stristr($enterCodeOrCaptcha->getInnerText(), 'Please enter your code')
        || stristr($enterCodeOrCaptcha->getInnerText(), 'To login, confirm you are not a robot')
        || stristr($enterCodeOrCaptcha->getInnerText(), 'We sent a verification code')
        ) {
            $tab->showMessage(Message::identifyComputer('Submit'));
            $errorOrTitle = $tab->evaluate('//a[normalize-space()="Status"]',
                EvaluateOptions::new()
                    //->visible(false)
                    ->allowNull(true)
                    ->timeout(70));

            if (!$errorOrTitle) {
                $currentUrl = $tab->getUrl();
                //refreshing the page because it froze
                if (stripos($currentUrl, "mode=afterLogin") !== false
                   || stripos($currentUrl, "redirect") !== false) {
                    $tab->gotoUrl($currentUrl);
                    $errorOrTitle = $tab->evaluate('//a[normalize-space()="Status"]',
                       EvaluateOptions::new()
                           ->visible(false)
                           ->allowNull(true)
                           ->timeout(70));
                }
            }

            if (!$errorOrTitle) {
                return LoginResult::identifyComputer();
            }

            if ($errorOrTitle->getNodeName() === 'A') {
                $tab->gotoUrl("https://www.aircanada.com/ca/en/aco/home/app.html#/viewprofile");

                return new LoginResult(true);
            } else {
                $error = $errorOrTitle->getInnerText();

                return new LoginResult(false, $error);
            }
        }

        return LoginResult::success();
    }
}
