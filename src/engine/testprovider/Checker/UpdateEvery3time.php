<?php

namespace AwardWallet\Engine\testprovider\Checker;

use AwardWallet\Engine\testprovider\Success;

class UpdateEvery3time extends Success
{
    public function Parse()
    {
        $balance = (0 === (int) date('i') % 3) || !isset($this->AccountFields['Balance']) ? rand(0, 1000) : $this->AccountFields['Balance'];

        $this->SetBalance($balance);
        $this->SetProperty('CombineSubAccounts', false);

        $this->AddSubAccount([
            'Code'              => 'first',
            'Number'            => 'SubNumber 1',
            'DisplayName'       => 'First subaccount',
            'Balance'           => $balance,
            'BalanceInTotalSum' => true,
        ]);

        $this->AddSubAccount([
            'Code'              => 'second',
            'Number'            => 'SubNumber 2',
            'DisplayName'       => 'Second subaccount',
            'Balance'           => rand(1, 10000),
            'BalanceInTotalSum' => true,
        ]);
    }
}
