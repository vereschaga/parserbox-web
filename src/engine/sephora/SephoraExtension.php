<?php

namespace AwardWallet\Engine\sephora;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;
use Psr\Log\LoggerInterface;
use function AwardWallet\ExtensionWorker\beautifulName;

class SephoraExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    private ?string $fullName = null;
    private array $headers = [
        'Accept' => 'application/json',
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.sephora.com/profile/me";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//h2[contains(text(),"To view your profile, please")] | //button[@id="account_drop_trigger" and contains(.,"Hi,")]',
            EvaluateOptions::new()->visible(false));
        return $result->getNodeName() == 'BUTTON';
    }

    public function getLoginId(Tab $tab): string
    {
        $options = [
            'method' => 'get',
            'headers' => $this->headers,
        ];
        $data = $tab->fetch('https://www.sephora.com/api/users/profiles/current/full?includeTargeters=%2Fatg%2Fregistry%2FRepositoryTargeters%2FSephora%2FCCDynamicMessagingTargeter&includeApis=profile,basket,loves,shoppingList,targetersResult,targetedPromotion&cb=' . time(),
            $options)->body;
        $data = json_decode($data);
        return strtolower("{$data->profile->firstName} {$data->profile->lastName}");
    }

    public function logout(Tab $tab): void
    {
        sleep(1);
        $tab->evaluate('//button[contains(text(),"Sign Out")]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//button[@id="account_drop_trigger" and contains(.,"Sign In")] | //button[contains(text(), "View Sephora International Website")]', EvaluateOptions::new()->visible(false));
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//h2[contains(text(),"To view your profile, please")]/button')->click();
        sleep(1);
        $tab->evaluate('//form//input[@name="username"]')->setValue($credentials->getLogin());
        $tab->evaluate('//form//input[@name="password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//form//button[@data-at="sign_in_button"]')->click();

        $result = $tab->evaluate('//p[@data-at="sign_in_error"] | //button[@id="account_drop_trigger" and contains(.,"Hi,")]',
            EvaluateOptions::new()->visible(false));

        $this->logger->notice("[NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        if (str_starts_with($result->getInnerText(), "There is an error with your email and/or password.")) {
            return LoginResult::invalidPassword($result->getInnerText());
        }
        if (str_starts_with($result->getInnerText(), "Oops! Something went wrong. Please try again later.")) {
            return LoginResult::providerError($result->getInnerText());
        }
        if ($result->getNodeName() == 'BUTTON') {
            return new LoginResult(true);
        }
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $userId = $tab->getCookies()['DYN_USER_ID'] ?? null;

        if (!$userId) {
            $this->logger->debug('userId not found');
            return;
        }
        $st = $master->createStatement();

        $options = [
            'method' => 'get',
            'headers' => $this->headers,
        ];
        $data = $tab->fetch('https://www.sephora.com/api/users/profiles/current/full?includeTargeters=%2Fatg%2Fregistry%2FRepositoryTargeters%2FSephora%2FCCDynamicMessagingTargeter&includeApis=profile,basket,loves,shoppingList,targetersResult,targetedPromotion&cb=' . time(),
            $options)->body;
        $this->logger->info($data);
        $data = json_decode($data);
        $st->addProperty('Name', beautifulName("{$data->profile->firstName} {$data->profile->lastName}"));


        $body = $tab->fetch("https://www.sephora.com/api/bi/profiles/$userId/points?source=profile")->body;
        $this->logger->info($body);
        $data = json_decode($body);
        // Status - BI (INSIDER), VIB
        if ($status = $data->biStatus ?? null) {
            $st->addProperty('Status', $this->parseUSAStatus($status));
        }
        // Balance - New Balance
        $st->setBalance($data->beautyBankPoints);
        // spend $... to reach VIB status.
        if ($amountToNextSegment = $data->amountToNextSegment ?? null) {
            $st->addProperty('ToNextLevel', '$' . $amountToNextSegment);
        }
        // Status valid until
        if ($vibEndYear = $data->vibEndYear ?? null) {
            $st->addProperty('StatusValidUntil', '12/31/' . $vibEndYear);
        }
        // Next elite level
        if ($nextSegment = $data->nextSegment ?? null) {
            $st->addProperty('NextEliteLevel', $this->parseUSAStatus($nextSegment));
        }
        // Miles/Points to retain status - Spend $350 to keep your VIB status through 2018.
        if ($retainStatus = $this->findPreg('/Spend <span data\-price>(.+?)<\/span> to keep your.+?status through/smi', $body)) {
            $st->addProperty('PointsRetainStatus', $retainStatus);
        }
    }

    private function parseUSAStatus($status)
    {
        $this->logger->notice(__METHOD__);

        if ($status === 'BI') {
            $status = 'Insider';
        } elseif ($status === 'ROUGE') {
            $status = 'Rouge';
        }
        return $status;
    }

}
