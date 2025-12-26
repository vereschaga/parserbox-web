<?php

namespace AwardWallet\Engine\tesco;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;

class TescoExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        if ($options->login3 == 'Ireland') {
            return 'https://secure.tesco.ie/Clubcard/MyAccount/Home.aspx';
        }

        return 'https://www.tesco.com/account/login/en-GB?from=https%3A%2F%2Fwww.tesco.com%2Faccount%2Fpersonal-details%2Fen-GB%2F';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $tab->logPageState();
        $loginFieldOrBalance = $tab->evaluate('//input[@name="email"] | //h1[contains(text(),"Personal details")]');

        return $loginFieldOrBalance->getInnerText() === 'Personal details';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//h2[text()="Email"]/../../following-sibling::div//span/p',
            EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $logout = $tab->evaluate('//a[@href = "/account/login/en-GB/logout"]', EvaluateOptions::new()->visible(false));
        $logout->click();
        $tab->evaluate('//a[contains(text(), "Sign in")] | //a[@data-testid = "sign-in"]', EvaluateOptions::new()->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->querySelector('input[name="email"]');
        $login->setValue($credentials->getLogin());

        if ($nextButton = $tab->evaluate('//button[normalize-space()="Next"]', EvaluateOptions::new()->allowNull(true)->timeout(0))) {
            $nextButton->click();
        }

        $password = $tab->querySelector('input[name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="signin-button"]')->click();

        $errorOrTitle = $tab->evaluate('
        //div[@data-test="banner-error"]//p[contains(@class,"notification__title")] |
        //h1[contains(text(),"Personal details")] | 
        //h1[contains(text(),"Update your details")] |
        //p[contains(@class, "description")]');

        if ($errorOrTitle->getInnerText() === 'Personal details') {
            $this->logger->info('logged in');

            return LoginResult::success();
        } else {
            $this->logger->info('error logging in');
            $error = $errorOrTitle->getInnerText();

            return LoginResult::providerError($error);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $statement = $master->createStatement();
        $name = $tab->evaluate('//h2[normalize-space()="Name"]/../../following-sibling::div//span[starts-with(@aria-label,"M") or starts-with(@aria-label, "D")]');
        $statement->addProperty('Name', $this->findPreg('/\s*^(?:M|D)\w+\s+(.+)/', $name->getInnerText()));

        $myAccountLink = $tab->evaluate("//a[span[contains(text(), 'My account')]]", EvaluateOptions::new()->visible(false));
        $myAccountLink->click();

        $myPointsLink = $tab->evaluate('//a[@href = "https://secure.tesco.com/clubcard/mypoints"]', EvaluateOptions::new()->visible(false));
        $myPointsLink->click();

        try {
            $options = [
                'method' => 'get',
                'headers' => []
            ];
            $json = $tab->fetch('https://secure.tesco.com/clubcard/app/points/api/telo/pointsCardSummary?page=myvouchers', $options);
            $this->logger->info($json->statusText);
            $this->logger->info($json->status);
            $this->logger->info($json->body);
            $json = json_decode($json);

            foreach ($json->boxInfos as $info) {
                if (in_array($info->label, ['Points balance', 'Points'])) {
                    $statement->setBalance($info->value);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            if ($balanceElement = $tab->evaluate("//span[contains(@class, 'balanceValue')]", EvaluateOptions::new()->allowNull(true)->timeout(0))) {
                $balance = $balanceElement->getInnerText();

                if ($balance !== null) {
                    $statement->setBalance($balance);
                }
            }
        }

        $this->parseVouchers($tab, $accountOptions, $statement);
    }

    private function parseVouchers(Tab $tab, AccountOptions $options, Statement $statement)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->clubCardSecurityVerification($tab, $options)) {
            return;
        }
        // Waiting for Vouchers
        // $tab->evaluate('.//div[contains(@id, "lwVouchersAvailable")]');
        // Vouchers to spend now
        $balanceVoucher = $tab->evaluate('//div[@id="lwVouchersAvailable"]//div[contains(@class,"__TableWrapper")]', EvaluateOptions::new()->visible(false));

        if ($balanceVoucher) {
            $balanceVoucher = $this->findPreg('/([\d.,]+)/i', $balanceVoucher->getInnerText());
            $statement->addSubAccount([
                'Code'        => 'tescoVouchers',
                'DisplayName' => 'Value in Clubcard vouchers',
                'Balance'     => $balanceVoucher,
            ]);
        } else {
            $this->logger->debug("Value in Clubcard vouchers not found");
        }

        // Vouchers refs #7879
        $vouchers = $tab->evaluateAll("//div[@id='lwActive']//div[contains(@class,'__VoucherWrapper')]");
        $this->logger->debug('Total ' . count($vouchers) . ' vouchers were found');

        foreach ($vouchers as $voucher) {
            $code = $this->findPreg('/>(\d{10,})</', $tab->evaluate('.//div[contains(@class, "__BarcodeContainer")]',
                EvaluateOptions::new()->contextNode($voucher)->visible(false))->getInnerHtml());
            $balance = $tab->evaluate('.//span[contains(@class, "__ActiveVouchersDesc")]', EvaluateOptions::new()->contextNode($voucher));
            $exp = $this->findPreg('/Expires\s*(.+)/', $tab->evaluate('.//span[contains(@class, "__ExpiryLabel")]', EvaluateOptions::new()->contextNode($voucher))->getInnerText());
            $displayName = "Voucher #{$code}";
            $this->logger->debug($displayName);
            $this->logger->debug("[Balance]: {$balance->getInnerText()}");
            $this->logger->debug("[Exp]: {$exp}");

            if ($exp = strtotime($exp)) {
                $statement->addSubAccount([
                    'Code'           => "tescoVoucher{$code}",
                    'DisplayName'    => $displayName,
                    'Balance'        => $balance->getInnerText(),
                    'ExpirationDate' => $exp,
                ]);
            } else {
                $statement->addSubAccount([
                    'Code'        => "tescoVoucher{$code}",
                    'DisplayName' => $displayName,
                    'Balance'     => $balance->getInnerText(),
                ]);
            }
        }
    }

    private function clubCardSecurityVerification(Tab $tab, AccountOptions $options)
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//*[@id="voucherslink"]/a[@href="/clubcard/myvouchers"]', EvaluateOptions::new()->visible(false))->click();

        if ($tab->evaluate('//h3[normalize-space()="Vouchers"] | //*[@id = "verify-clubcard-link"] | //button[@id = "send-clubcard-button"]')->getNodeName() === 'H3') {
            $this->logger->notice('return true');

            return true;
        }

        $tab->evaluate('//*[@id = "verify-clubcard-link"] | //button[@id = "send-clubcard-button"]')->click();
        $verificationBtn = $tab->evaluate('//button[@data-tracking="account verification:submit button"]');
        $digits = $tab->findTextAll('//label[contains(@for,"digit")]');
        $this->logger->notice('Found ' . count($digits) . ' digits');

        $i = 0;

        foreach ($digits as $digit) {
            $i++;
            $this->logger->debug('getInnerText: ' . $digit);
            $value = $options->login2[(int) $digit - 1];
            $this->logger->debug('value: ' . $value);
            $tab->evaluate("(//input[contains(@name,'digit')])[$i]")->setValue($value);
        }
        $verificationBtn->click();

        return true;
    }

    private function findPreg($pattern, $subject)
    {
        if (preg_match($pattern, $subject, $matches)) {
            return $matches[1] ?? $matches[0];
        }
        $this->logger->info("regexp not found: $pattern");

        return null;
    }
}
