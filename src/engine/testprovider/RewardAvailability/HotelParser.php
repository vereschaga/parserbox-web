<?php

namespace AwardWallet\Engine\testprovider\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use AwardWallet\Engine\testprovider\Success;

class HotelParser extends Success
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC);
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

        if ('Australia' === $this->AccountFields['RaRequestFields']['Destination']) {
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

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->notice(__METHOD__);

        if ($fields['Destination'] === 'Australia') {
            return $this->parseAU($fields);
        }

        return $this->parseUS($fields);
    }

    private function parseUS(array $fields)
    {
        return [
            'hotels' => [
                [
                    'name'             => 'Sheraton Philadelphia Downtown Hotel',
                    'checkInDate'      => date('Y-m-d', $fields['CheckIn']),
                    'checkOutDate'     => date('Y-m-d', $fields['CheckOut']),
                    'pointsPerNight' => 18000,
                    'fullCashPricePerNight' => 250,
                    'rooms' => [
                        [
                            "type" => "Suite",
                            "name" => "One Bedroom Queen Suite (Plus Sofa Bed)",
                            "description" => "This suite features a bedroom with one queen bed, a separate living room with a sectional sleeper sofa, and a fully equipped kitchen amid 396–600 square feet.",
                            "rates" => [
                                [
                                    "name" => "Standard Suite Points Plus Cash",
                                    "description" => "STANDARD SUITE POINTS PLUS CASH - Valid on 1-Bedroom Suite only. - Standard guarantee/cxl policy changed to Standard Rate.",
                                    "pointsPerNight" => 13500,
                                    "cashPerNight" => 255
                                ]
                            ]
                        ],
                        [
                            "type" => "Room",
                            "name" => "King Bed Den Guestroom",
                            "description" => "With one king bed, this room offers spacious interiors and a work area amid 233–439 square feet of space.",
                            "rates" => [
                                [
                                    "name" => "Standard Room Free Night",
                                    "pointsPerNight" => 18000,
                                    "cashPerNight" => 250
                                ]
                            ]
                        ]
                    ],
                    'awardCategory'=>1,
                    'hotelDescription' => 'Pets Not Allowed. Hotel is no longer pet-friendly',
                    //                    'numberOfNights' => 1, // will calc
                    'distance'        => 123123,
                    'rating'          => 3.6,
                    'numberOfReviews' => 12343,
                    'address'         => '201 North 17th Street, Philadelphia, Pennsylvania 19103 United States',
                    'detailedAddress' => [],
                    'phone'           => '+1-22-3333',
                    'url'             => '',
                    'preview'         => $fields['DownloadPreview'] ? 'some_base64_string' : null,
                ],
            ],
        ];
    }

    private function parseAU(array $fields)
    {
        return ['hotels' => []];
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
