<?php

namespace AwardWallet\Engine\worldpoints\Transfer;

class Transfer extends \TAccountCheckerWorldpoints
{
    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->ArchiveLogs = true;

        try {
            // TODO: Give mnemonic names to methods (e.g. fillMemberInfo) and implement transfer code
            $this->checkTransferParameters();
            $this->fillTransferParameters($targetProviderCode, $targetAccountNumber, $numberOfMiles);
            $this->submit();
        } catch (\CheckException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->http->Log($e->getMessage(), LOG_LEVEL_ERROR);

            return false;
        }
    }

    protected function checkTransferParameters()
    {
    }

    protected function fillTransferParameters($targetProviderCode, $targetAccountNumber, $sourceRewardsQuantity)
    {
        $targetProviders = [
            'airmiles'  => '253',
            'british'   => '252',
            'etihad'    => '264',
            'lufthansa' => '250',
            'virgin'    => '254',
            'skywards'  => '267',
        ];

        $this->http->GetURL('https://rewards.heathrow.com/group/lhr/travel-partners');

        if (!$this->http->ParseForm("//form[@id='_lhrorderrewardportlet_WAR_lhrorderrewardportlet_INSTANCE_5vTX_rewardListForm']")) {
            throw new \Exception('Failed load transfer page');
        }

        $this->http->SetInputValue('selectedRewardId', $targetProviders[$targetProviderCode]);

        if (!$this->http->PostForm()) {
            throw new \Exception('Failed select target provider form submit');
        }
    }

    protected function submit()
    {
    }
}
