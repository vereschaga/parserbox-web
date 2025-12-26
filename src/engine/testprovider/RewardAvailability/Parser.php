<?php

namespace AwardWallet\Engine\testprovider\RewardAvailability;

use AwardWallet\Common\Parsing\LuminatiProxyManager;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\testprovider\Success;

class Parser extends Success
{
    use ProxyList;
    private $debug;

    public static function getRASearchLinks(): array
    {
        return ['https://awardwallet.com/'=>'main', 'https://pointme.com/'=>'partner'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyBrightData();
        $this->debug = $this->AccountFields['DebugState'] ?? false;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function Login()
    {
        $this->logger->info('Debug mode: ' . var_export($this->AccountFields['DebugState'] ?? false, true));

        if (empty($this->AccountFields['RaRequestFields'])) {
            return true;
        }

        if ('CAD' === $this->AccountFields['RaRequestFields']['Currencies'][0]) {
            return $this->twoFactor();
        }

        return true;
    }

    public function ProcessStep($step)
    {
        if ($step === 'QuestionOtc') {
            $this->logger->info('Step QuestionOtc');

            return $this->twoFactor();
        }

        return false;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => [
                'USD', // example route
                'CAD', // otc question
                'AUD', // many routes
            ],
            'supportedDateFlexibility' => 0,
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->notice(__METHOD__);

        if ($fields['Currencies'][0] === 'AUD') {
            return $this->parseAUD($fields);
        }

        if ($this->debug) {
            $this->http->GetURL('http://lumtest.com/myip.json');

            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $wrappedProxyPort = $wrappedProxy->createPort($this->http->getProxyParams());

            $this->http->SetProxy($wrappedProxyPort['proxyHost'] . ':' . $wrappedProxyPort['proxyPort']);
            $this->http->setProxyAuth($wrappedProxyPort['proxyLogin'], $wrappedProxyPort['proxyPassword']);

            $this->http->GetURL('http://lumtest.com/myip.json');

            $lpm = $this->services->get(LuminatiProxyManager\Client::class);
            $externalProxy = $this->getProxyHost('netnut');

            $port = (new LuminatiProxyManager\Port)
                ->setExternalProxy([$externalProxy])
                ->banMediaContent()
                ->setBanUrlContent('test.com');

            $portNumber = $lpm->createProxyPort($port);

            $this->setLpmProxy(
                $lpm->getInternalIp() . ':' . $portNumber,
                "https://api.netnut.io/myIP.aspx"
            );

            $lpm->deleteProxyPort($portNumber);
        }

        return $this->parseUSD($fields);
    }

    private function parseUSD(array $fields)
    {
        return [
            'routes' => [
                [
                    'distance'    => null,
                    'num_stops'   => 0,
                    'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                    'redemptions' => ['miles' => 55000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => 5.6, 'fees' => null],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 17:30', // '2021-12-10 17:30',
                                'airport'  => 'CDG',
                                'terminal' => "A",
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 06:50',//'2021-12-11 06:50',
                                'airport'  => 'JFK',
                                'terminal' => 1,
                            ],
                            'meal'        => 'Dinner',
                            'cabin'       => 'business',
                            'fare_class'  => 'HN',
                            'flight'      => ['SN0269'],
                            'airline'     => 'SN',
                            'operator'    => 'SN',
                            'distance'    => null,
                            'aircraft'    => 'Boeing 787 jet',
                            'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                            'num_stops'   => 0,
                            'classOfService'   => 'ddd',
                        ],
                    ],
                    'tickets'    => null,
                    'award_type' => 'Standard Reward',
                ],
                [
                    'distance'    => null,
                    'num_stops'   => 1,
                    'times'       => ['flight' => '15:56', 'layover' => null],
                    'redemptions' => ['miles' => 69000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => null, 'fees' => 35.64],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 18:00',//'2021-12-10 21:00',
                                'dateTime' => 1639170000,
                                'airport'  => 'NRT',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 09:35',//'2021-12-11 08:50',
                                'dateTime' => 1639212600,
                                'airport'  => 'YVR',
                                'terminal' => null,
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'economy',
                            'fare_class' => 'HN',
                            'flight'     => ['BA0297'],
                            'airline'    => 'BA',
                            'operator'   => 'BA',
                            'distance'   => null,
                            'aircraft'   => 'Boeing 787 jet',
                            'times'      => ['flight' => '08:55', 'layover' => '00:00'],
                            'tickets'    => 6,
                            'classOfService'   => 'ddd',
                        ],
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 20:20',//'2021-12-11 11:30',
                                'dateTime' => 1639222200,
                                'airport'  => 'YVR',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 23:19',//'2021-12-11 13:50',
                                'dateTime' => 1639230600,
                                'airport'  => 'LAX',
                                'terminal' => '3',
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'business',
                            'fare_class' => 'HN',
                            'flight'     => ['AA2715'],
                            'airline'    => 'AA',
                            'operator'   => 'AA',
                            'distance'   => null,
                            'aircraft'   => 'Airbus A321 jet',
                            'times'      => ['flight' => '04:41', 'layover' => '00:00'],
                            'tickets'    => 7,
                            'classOfService'   => 'ggg',
                        ],
                    ],
                    'tickets'    => '6',
                    'award_type' => 'Latitude Reward',
                ],
            ],
        ];
    }

    private function parseAUD(array $fields)
    {
        return [
            'routes' => [
                [
                    'distance'    => null,
                    'num_stops'   => 0,
                    'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                    'redemptions' => ['miles' => 65000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => 5.6, 'fees' => null],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 17:30', // '2021-12-10 17:30',
                                'airport'  => 'CDG',
                                'terminal' => "A",
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 06:50',//'2021-12-11 06:50',
                                'airport'  => 'JFK',
                                'terminal' => 1,
                            ],
                            'meal'        => 'Dinner',
                            'cabin'       => 'business',
                            'fare_class'  => 'HN',
                            'flight'      => ['BA0269'],
                            'airline'     => 'BA',
                            'operator'    => 'BA',
                            'distance'    => null,
                            'aircraft'    => 'Boeing 787 jet',
                            'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                            'num_stops'   => 0,
                        ],
                    ],
                    'tickets'    => null,
                    'award_type' => 'Standard Reward',
                ],
                [
                    'distance'    => null,
                    'num_stops'   => 0,
                    'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                    'redemptions' => ['miles' => 45000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => 5.6, 'fees' => null],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 17:30', // '2021-12-10 17:30',
                                'airport'  => 'CDG',
                                'terminal' => "A",
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 07:10',//'2021-12-11 06:50',
                                'airport'  => 'JFK',
                                'terminal' => 1,
                            ],
                            'meal'        => 'Dinner',
                            'cabin'       => 'business',
                            'fare_class'  => 'HN',
                            'flight'      => ['BA0269'],
                            'airline'     => 'BA',
                            'operator'    => 'BA',
                            'distance'    => null,
                            'aircraft'    => 'Boeing 787 jet',
                            'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                            'num_stops'   => 0,
                        ],
                    ],
                    'tickets'    => null,
                    'award_type' => 'Standard Reward',
                ],
                [
                    'distance'    => null,
                    'num_stops'   => 0,
                    'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                    'redemptions' => ['miles' => 65000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => 5.6, 'fees' => null],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 17:30', // '2021-12-10 17:30',
                                'airport'  => 'CDG',
                                'terminal' => "A",
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 04:10',//'2021-12-11 06:50',
                                'airport'  => 'JFK',
                                'terminal' => 1,
                            ],
                            'meal'        => 'Dinner',
                            'cabin'       => 'economy',
                            'fare_class'  => 'HN',
                            'flight'      => ['BA0269'],
                            'airline'     => 'BA',
                            'operator'    => 'BA',
                            'distance'    => null,
                            'aircraft'    => 'Boeing 787 jet',
                            'times'       => ['flight' => '11:20', 'layover' => '00:00'],
                            'num_stops'   => 0,
                        ],
                    ],
                    'tickets'    => null,
                    'award_type' => 'Standard Reward',
                ],
                [
                    'distance'    => null,
                    'num_stops'   => 1,
                    'times'       => ['flight' => '15:56', 'layover' => null],
                    'redemptions' => ['miles' => 69000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => null, 'fees' => 35.64],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 21:00',//'2021-12-10 21:00',
                                'dateTime' => 1639170000,
                                'airport'  => 'JFK',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 08:50',//'2021-12-11 08:50',
                                'dateTime' => 1639212600,
                                'airport'  => 'LHR',
                                'terminal' => null,
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'business',
                            'fare_class' => 'HN',
                            'flight'     => ['BA0297'],
                            'airline'    => 'BA',
                            'operator'   => 'BA',
                            'distance'   => null,
                            'aircraft'   => 'Boeing 787 jet',
                            'times'      => ['flight' => '08:55', 'layover' => '00:00'],
                            'tickets'    => 6,
                        ],
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 11:33',//'2021-12-11 11:30',
                                'dateTime' => 1639222200,
                                'airport'  => 'LHR',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 13:50',//'2021-12-11 13:50',
                                'dateTime' => 1639230600,
                                'airport'  => 'CDG',
                                'terminal' => '3',
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'economy',
                            'fare_class' => 'HN',
                            'flight'     => ['AA2715'],
                            'airline'    => 'AA',
                            'operator'   => 'AA',
                            'distance'   => null,
                            'aircraft'   => 'Airbus A321 jet',
                            'times'      => ['flight' => '04:41', 'layover' => '00:00'],
                            'tickets'    => 7,
                        ],
                    ],
                    'tickets'    => '6',
                    'award_type' => 'Latitude Reward',
                ],
                [
                    'distance'    => null,
                    'num_stops'   => 1,
                    'times'       => ['flight' => '15:56', 'layover' => null],
                    'redemptions' => ['miles' => 69000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => null, 'fees' => 35.64],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 21:00',//'2021-12-10 21:00',
                                'dateTime' => 1639170000,
                                'airport'  => 'JFK',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 08:50',//'2021-12-11 08:50',
                                'dateTime' => 1639212600,
                                'airport'  => 'LHR',
                                'terminal' => null,
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'business',
                            'fare_class' => 'HN',
                            'flight'     => ['BA0297'],
                            'airline'    => 'BA',
                            'operator'   => 'BA',
                            'distance'   => null,
                            'aircraft'   => 'Boeing 787 jet',
                            'times'      => ['flight' => '08:55', 'layover' => '00:00'],
                            'tickets'    => 6,
                        ],
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 11:33',//'2021-12-11 11:30',
                                'dateTime' => 1639222200,
                                'airport'  => 'LHR',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 13:50',//'2021-12-11 13:50',
                                'dateTime' => 1639230600,
                                'airport'  => 'CDG',
                                'terminal' => '3',
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'business',
                            'fare_class' => 'HN',
                            'flight'     => ['AA2715'],
                            'airline'    => 'AA',
                            'operator'   => 'AA',
                            'distance'   => null,
                            'aircraft'   => 'Airbus A321 jet',
                            'times'      => ['flight' => '04:41', 'layover' => '00:00'],
                            'tickets'    => 7,
                        ],
                    ],
                    'tickets'    => '6',
                    'award_type' => 'Latitude Reward',
                ],
                [
                    'distance'    => null,
                    'num_stops'   => 1,
                    'times'       => ['flight' => '15:56', 'layover' => null],
                    'redemptions' => ['miles' => 59000, 'program' => 'british'],
                    'payments'    => ['currency' => 'USD', 'taxes' => null, 'fees' => 35.64],
                    'connections' => [
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate']) . ' 21:00',//'2021-12-10 21:00',
                                'dateTime' => 1639170000,
                                'airport'  => 'EWR',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 08:50',//'2021-12-11 08:50',
                                'dateTime' => 1639212600,
                                'airport'  => 'LHR',
                                'terminal' => null,
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'business',
                            'fare_class' => 'HN',
                            'flight'     => ['BA0297'],
                            'airline'    => 'BA',
                            'operator'   => 'BA',
                            'distance'   => null,
                            'aircraft'   => 'Boeing 787 jet',
                            'times'      => ['flight' => '08:55', 'layover' => '00:00'],
                            'tickets'    => 6,
                        ],
                        [
                            'departure' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 11:33',//'2021-12-11 11:30',
                                'dateTime' => 1639222200,
                                'airport'  => 'LHR',
                                'terminal' => null,
                            ],
                            'arrival' => [
                                'date'     => date('Y-m-d', $fields['DepDate'] + 60 * 60 * 24) . ' 13:50',//'2021-12-11 13:50',
                                'dateTime' => 1639230600,
                                'airport'  => 'CDG',
                                'terminal' => '3',
                            ],
                            'meal'       => 'Dinner',
                            'cabin'      => 'business',
                            'fare_class' => 'HN',
                            'flight'     => ['AA2715'],
                            'airline'    => 'AA',
                            'operator'   => 'AA',
                            'distance'   => null,
                            'aircraft'   => 'Airbus A321 jet',
                            'times'      => ['flight' => '04:41', 'layover' => '00:00'],
                            'tickets'    => 7,
                        ],
                    ],
                    'tickets'    => '6',
                    'award_type' => 'Latitude Reward',
                ],
            ],
        ];
    }

    private function twoFactor()
    {
        $question = 'Enter code that was sent to email testprovider@email.com';

        if (!isset($this->Answers[$question])) {
            $this->logger->info('OTC is missing');
            $this->AskQuestion($question);
            $this->Step = 'QuestionOtc';

            return false;
        }
        $this->logger->info('Two factor');
        $this->logger->info('Question: ' . $this->Question);
        $this->logger->info('Code: ' . $this->Answers[$this->Question]);

        return true;
    }
}
