<?php

namespace AwardWallet\Engine\redroof;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use function AwardWallet\ExtensionWorker\beautifulName;

class RedroofExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private array $headers = [
        'Accept' => '*/*',
        'Content-Type' => 'application/json',
        'Origin' => 'https://www.redroof.com',
        'Referer' => 'https://www.redroof.com/'
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.redroof.com/members";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="txtEmail"] | //p[contains(text(),"Your Member Number:")]',
            EvaluateOptions::new()->timeout(15));
        return str_starts_with($result->getInnerText(), "Your Member Number:");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//p[contains(text(),"Your Member Number:")]/following-sibling::p/strong',
            FindTextOptions::new()->preg('/^\d+$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//div[@class="site-header-signin-wrapper"]//a[contains(text(),"Sign Out")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//div[@class="site-header-signin-wrapper"]//a[contains(text(),"Sign In")]', EvaluateOptions::new()->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="txtEmail"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="txtPassword"]')->setValue($credentials->getPassword());
        sleep(1);
        $tab->evaluate('//button[contains(text(),"SIGN IN")]')->click();

        $result = $tab->evaluate('
                //div[contains(@class,"fade alert alert-danger show")]
                | //p[contains(text(),"Your Member Number:")] 
            ');
        $this->logger->notice("[RESULT NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");


        if (str_starts_with($result->getInnerText(), "Login failure")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (str_starts_with($result->getInnerText(), "Your Member Number:")) {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $accountOptions = [
            'method' => 'get',
            'headers' => $this->headers
        ];

        $profile = $tab->fetch('https://prd-e-gwredroofwebapi.redroof.com/api/v1/member/get-profile-page',
            $accountOptions)->body;
        $this->logger->info($profile);
        $profile = json_decode($profile)->memberProfile;
        $st = $master->createStatement();
        // Balance - Total Balance
        $st->SetBalance(str_replace(',', '', $profile->pointsBalanceFormatted ?? null));
        // Name
        $st->addProperty("Name", beautifulName(($profile->firstName ?? null) . " " . ($profile->lastName ?? null)));
        // Account Number
        $st->addProperty("Number", $profile->LoyaltyAccountNbr ?? null);

        // Expiration date // refs #3837, https://redmine.awardwallet.com/issues/3837#note-9
        if ($st->getBalance() > 0) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $memberRewardTransactions = $response->redicardActivity ?? [];
            $this->logger->debug("Total " . count($memberRewardTransactions) . " transactions were found");

            foreach ($memberRewardTransactions as $memberRewardTransaction) {
                $date = $memberRewardTransaction->date;
                $pointsAmount = $memberRewardTransaction->pointsAmount;
                $transactionType = $memberRewardTransaction->transactionType;

                if ($transactionType == 'Stay' && $pointsAmount > 0) {
                    // Last Activity
                    $lastActivity = $date;
                    $st->addProperty("LastActivity", $lastActivity);
                    // Expiration Date - 14 months
                    if ($lastActivity = strtotime($lastActivity)) {
                        $exp = strtotime("+14 month", $lastActivity);
                        $st->setExpirationDate($exp);
                    }

                    break;
                }
            }
        }

        // Certificates
        $this->logger->info('Certificates', ['Header' => 3]);
        $memberCertificates = $response->CertificateActivity ?? [];
        $this->logger->debug("Total " . count($memberCertificates) . " certificates were found");

        foreach ($memberCertificates as $certificate) {
            $certificateNumber = $certificate->CertificateNumber;
            $expirationDate = $certificate->ExpirationDate;
            $status = $certificate->Status;

            if (strtolower($status) == 'issued') {
                $st->AddSubAccount([
                    'Code'              => 'redroofCertificate' . $certificateNumber,
                    'DisplayName'       => "Cert #{$certificateNumber}",
                    'Balance'           => null,
                    'ExpirationDate'    => strtotime($expirationDate),
                    'IssuedTo'          => $certificate->issueDate,
                    'StatusCertificate' => $status,
                ], true);
            }
        }
    }

}
