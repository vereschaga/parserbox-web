<?php

namespace AwardWallet\Engine\testprovider\Checker;

/**
 * this class will check, scenario when subaccount missed.
 */
class SubAccountMissed extends \TAccountChecker
{
    public function GetHistoryColumns()
    {
        return [
            "No."            => "Info",
            "Activity"       => "Info",
            "Activity Date"  => "PostingDate",
            "Description"    => "Description",
            "Award Miles"    => "Miles",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function Parse()
    {
        $this->SetBalance(198);

        if (!isset($this->State['collected'])) {
            $this->AddSubAccount([
                "Balance"     => 1,
                "Code"        => "SubAcc1",
                "DisplayName" => "SubAccount 1",
                "HistoryRows" => [
                    [
                        'Activity'      => 'something N1',
                        'Activity Date' => strtotime(date("Y-m-d", strtotime("-3 days"))),
                        'Award Miles'   => '+2,500',
                        'Description'   => 'Subacc1 hist 1',
                    ],
                ],
            ]);
            $this->State['collected'] = 1;
        } else {
            switch ($this->State['collected']) {
                case 1:
                    $this->AddSubAccount([
                        "Balance"     => 2,
                        "Code"        => "SubAcc1",
                        "DisplayName" => "SubAccount 1",
                        "HistoryRows" => [
                            [
                                'Activity'      => 'something N1',
                                'Activity Date' => strtotime(date("Y-m-d", strtotime("-3 days"))),
                                'Award Miles'   => '+2,500',
                                'Description'   => 'Subacc1 hist 1',
                            ],
                            [
                                'Activity'      => 'something N2',
                                'Activity Date' => strtotime(date("Y-m-d", strtotime("-2 days"))),
                                'Award Miles'   => '+2,500',
                                'Description'   => 'Subacc1 hist 2',
                            ],
                        ],
                    ]);
                    $this->State['collected'] = 2;

                    break;

                case 2:
                    $this->AddSubAccount([
                        "Balance"     => 3,
                        "Code"        => "SubAcc1",
                        "DisplayName" => "SubAccount 1",
                        "HistoryRows" => [
                            [
                                'Activity'      => 'something N2',
                                'Activity Date' => strtotime(date("Y-m-d", strtotime("-2 days"))),
                                'Award Miles'   => '+2,500',
                                'Description'   => 'Subacc1 hist 2',
                            ],
                            [
                                'Activity'      => 'something N3',
                                'Activity Date' => strtotime(date("Y-m-d", strtotime("-1 days"))),
                                'Award Miles'   => '+2,500',
                                'Description'   => 'Subacc1 hist 3',
                            ],
                        ],
                    ]);
                    $this->State['collected'] = 3;

                    break;

                default:
                    unset($this->State['collected']);
            }
        }
    }
}
