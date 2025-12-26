<?php

namespace AwardWallet\Engine\goldcrown\Transfer;

require_once __DIR__ . '/../TAccountCheckerGoldcrownSelenium.php';

class Transfer extends \TAccountCheckerGoldcrownSelenium
{
    public $timeout = 10;

    public $idle = false;

    private $targetProviderCode;

    private $targetAccountNumber;

    private $sourceRewardsQuantity;

    public function checkErrors()
    {
        //# Site Down for Maintenance
        if ($message = $this->http->FindSingleNode("//h5[contains(text(), 'Site Down for Maintenance')]")) {
            throw new \ProviderError($message);
        }
        //# Site Down for Maintenance
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Our site is currently down for maintenance')]/parent::*")) {
            throw new \ProviderError($message);
        }
        //# Our site is temporarily unavailable
        if ($message = $this->http->FindPreg("/(Our site is temporarily unavailable\.)/ims")) {
            throw new \ProviderError($message);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new \ProviderError($message);
        }

        return false;
    }

    public function transfer($targetProviderCode, $targetAccountNumber, $sourceRewardsQuantity, $fields = [])
    {
        $this->log('=BEGIN==> TRANSFER');
        $this->ArchiveLogs = true;
        $this->http->Log("Target provider code: $targetProviderCode");
        $this->http->Log("Target account number: $targetAccountNumber");
        $this->http->Log("Source rewards quantity: $sourceRewardsQuantity");
        $this->idle = true;
        $this->targetProviderCode = $targetProviderCode;
        $this->targetAccountNumber = $targetAccountNumber;
        $this->sourceRewardsQuantity = $sourceRewardsQuantity;
        $this->checkTransferParameters();

        try {
            $this->transferInternal();
            $this->http->cleanup();
            $this->log('==END===> TRANSFER');

            return true;
        } catch (\CheckException $e) {
            $this->saveResponse();
            $this->http->cleanup();

            throw $e;
        } catch (\Exception $e) {
            $this->log($e->getMessage(), LOG_LEVEL_ERROR);

            if ($this->driver) {
                $this->saveResponse();
            }
            $this->http->cleanup();

            return false;
        }
    }

    private function checkTransferParameters()
    {
        //		if ($this->targetProviderCode != 'singaporeair')
//			throw new \UserInputError('Unsupported target provider');
//		if ($this->sourceRewardsQuantity < 5000)
//			throw new \UserInputError('Unsupported source rewards quantity');
    }

    private function transferInternal()
    {
        //		$status = $this->LoadLoginForm();
        //		if (!$status)
        //			throw new \EngineError('Load login form failed');
        //		$status = $this->Login();
        //		if (!$status)
        //			throw new \EngineError('Login failed');
        $this->fillTransferParameters();

        if (!$this->idle) {
            $this->submit();
        } else {
            $this->log('Idle run, no submit');
        }
    }

    private function fillTransferParameters()
    {
        $this->log('=BEGIN==> FILL TRANSFER PARAMETERS');
        $this->http->GetURL('http://www.bestwestern.com/rewards/redeem/options.asp?category=TRAVEL');

        switch ($this->targetProviderCode) {
//			// $10USD Best Western Travel Card for 2400 Points
//			case '':
//				break;
            // 1000 Aeroplan Miles for 5000 Points
            case 'aeroplan':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=AC1-P&pv=5000&category=TRAVEL&name=1000%20Aeroplan%20Miles';

                break;
            // 1000 Alaska Airlines Mileage Plan miles for 5000 Points
            case 'alaskaair':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=EI-P&pv=5000&category=TRAVEL&name=1000%20Alaska%20Airlines%20Mileage%20Plan%20miles';

                break;
            // 1000 American Airlines AAdvantage miles for 5000 Points
            case 'aa':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=Z9-P&pv=5000&category=TRAVEL&name=1000%20American%20Airlines%20AAdvantage%20miles';

                break;
            // 1000 Asiana Club Airline Miles for 5000 Points
            case 'asiana':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=ASIAAIRO&pv=5000&category=TRAVEL&name=1000%20Asiana%20Club%20Airline%20Miles';

                break;
            // 1000 US Airways Dividend Miles for 5000 Points
            case 'dividendmiles':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=AIRWDIV&pv=5000&category=TRAVEL&name=1000%20US%20Airways%20Dividend%20Miles';

                break;
            // 1200 Southwest Airlines Rapid Rewards® Points for 5000 Points
            case 'rapidrewards':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=SWRR1200&pv=5000&category=TRAVEL&name=1200%20Southwest%20Airlines%20Rapid%20Rewards%C2%AE%20Points';

                break;
            // 1600 AeroMexico Club Premier Kilometers for 5000 Points
            case 'aeromexico':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=AMXCP-1&pv=5000&category=TRAVEL&name=1600%20AeroMexico%20Club%20Premier%20Kilometers';

                break;
            // 80 AIR MILES for 5000 Points
            case 'airmilesca':
            case 'airmilesnetherlands':
            case 'airmilesme':
                $url = 'http://www.bestwestern.com/rewards/redeem/detail.asp?id=AIRMILES80&pv=5000&category=TRAVEL&name=80%20AIR%20MILES';

                break;

            default:
                throw new \UserInputError('Unsupported provider code');
        }

        try {
            $this->http->GetURL($url);
            $quantity = floor($this->sourceRewardsQuantity / 5000);
            $this->driver->executeScript('document.getElementById("quantity").setAttribute("value", "' . $quantity . '")');

            $continueButton = $this->waitForElement(\WebDriverBy::xpath('//a[normalize-space(.) = "Continue"]'));

            if (!$continueButton) {
                throw new \EngineError('Failed to find continue button');
            }
            $continueButton->click();

            $membershipNumber = $this->waitForElement(\WebDriverBy::id('air_mem_number'));

            if (!$membershipNumber) {
                throw new \EngineError('Failed to find membership number input');
            }
            $membershipNumber->sendKeys($this->targetAccountNumber);
        } catch (UnexpectedAlertOpenException $e) {
            // Typically such exception means that user do not have enough points
            if (preg_match('#\(text:\s+(.*?)\)#i', $e->getMessage(), $m)) {
                throw new \UserInputError($m[1]);
            } else {
                throw new \EngineError($e->getMessage());
            }
        }
        $this->log('==END===> FILL TRANSFER PARAMETERS');
    }

    private function submit()
    {
        $this->log('=BEGIN==> SUBMIT');
        // TODO: Recheck parameters
        $orderRewardButton = $this->waitForElement(\WebDriverBy::xpath('//a[normalize-space(.) = "Order Reward"]'));

        if (!$orderRewardButton) {
            throw new \EngineError('Failed to find "order reward" button');
        }

        if ($this->idle) {
            return true;
        }
        $orderRewardButton->click();

        $this->saveResponse();
        $successRegexp = '#Your\s+order\s+has\s+been\s+entered\s+into\s+the\s+system..*?A\s+confirmation\s+e-mail\s+summarizing\s+this\s+transaction\s+has\s+been\s+sent\s+to\s+your\s+registered\s+e-mail\s+address.#is';

        if ($successMessage = $this->http->FindPreg($successRegexp)) {
            $successMessage = strip_tags($successMessage);
            $this->ErrorMessage = $successMessage;
            $this->log('Transfer succeeded');
        } else {
            throw new \EngineError('Unknown transfer error');
        }
        $this->log('==END===> SUBMIT');
    }

    private function log($msg, $loglevel = null)
    {
        $this->http->Log($msg, $loglevel);
    }
}
