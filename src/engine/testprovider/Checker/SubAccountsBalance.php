<?php

namespace AwardWallet\Engine\testprovider\Checker;

use AwardWallet\Engine\testprovider\Success;

class SubAccountsBalance extends Success
{
    public function Parse()
    {
        $login2 = $this->AccountFields['Login2'] ?? null;

        switch ($login2) {
            case 'null_main_balance':
                $this->parseNullMainBalance();

                break;

            case 'zero_main_balance':
                $this->parseZeroMainBalance();

                break;

            case 'currency_main_balance':
                $this->parseBalanceCurrency(false);

                break;

            case 'currency_main_balance_combine':
                $this->parseBalanceCurrency(true);

                break;

            default:
                $this->parseDefault();

                break;
        }
    }

    private function parseNullMainBalance()
    {
        $this->SetBalanceNA();
        $this->SetProperty('CombineSubAccounts', true);
        $this->AddSubAccount([
            'Code'              => 'first',
            'Number'            => 'SubNumber 1',
            'DisplayName'       => 'First subaccount',
            'Balance'           => rand(1, 10000),
            'BalanceInTotalSum' => true,
        ]);
    }

    private function parseZeroMainBalance()
    {
        $this->SetBalance(0);
        $this->SetProperty('CombineSubAccounts', true);
        $this->AddSubAccount([
            'Code'              => 'first',
            'Number'            => 'SubNumber 1',
            'DisplayName'       => 'First subaccount',
            'Balance'           => rand(1, 10000),
            'BalanceInTotalSum' => true,
        ]);
    }

    private function parseBalanceCurrency(bool $combineSubAccounts)
    {
        $this->SetBalanceNA();
        $this->SetProperty('CombineSubAccounts', $combineSubAccounts);
        $this->AddSubAccount([
            'Code'        => 'first',
            'DisplayName' => 'First subaccount',
            'Balance'     => 'CNY ' . rand(1, 10000),
            'Currency'    => 'CNY',
        ]);
    }

    private function parseDefault()
    {
        $this->SetBalance(10);
        $this->SetProperty('CombineSubAccounts', false);

        $this->AddSubAccount([
            'Code'              => 'first',
            'Number'            => 'SubNumber 1',
            'DisplayName'       => 'First subaccount',
            'Balance'           => rand(1, 10000),
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
