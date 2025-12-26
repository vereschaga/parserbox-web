<?php

namespace AwardWallet\Engine\goldpassport\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class HotelParser extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $roomRates;
    private $fields;
    private $query;
    private $curlBrowser;
    // TODO - tmp param
    private $withExtenedeCheck = true;

    public static function getRASearchLinks(): array
    {
        return ['https://www.hyatt.com/' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [1920, 1080],
        ];

        if ($this->attempt % 2 === 0) {
            $this->setProxyBrightData();
        } else {
            $this->setProxyGoProxies();
        }

        if (!isset($this->State['Resolution']) || $this->attempt > 1) {
            $this->logger->notice("set new resolution");
            $resolution = $resolutions[array_rand($resolutions)];
            $this->State['Resolution'] = $resolution;
        } else {
            $this->logger->notice("get resolution from State");
            $resolution = $this->State['Resolution'];
            $this->logger->notice("restored resolution: " . implode('x', $resolution));
        }
//        $this->setScreenResolution($resolution);

        $this->useChromeExtension(\SeleniumFinderRequest::CHROME_EXTENSION_DEFAULT);

        if (!isset($this->AccountFields["UserID"]) || $this->AccountFields["UserID"] != 7) {
            $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        }
        $this->seleniumOptions->addPuppeteerStealthExtension = false;
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
        // It breaks everything
        $this->usePacFile(false);
//        $selenium->useCache();

        /*$this->useFirefoxPlaywright();
        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;*/

        /*        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);

                $request = FingerprintRequest::firefox();
                $request->browserVersionMin = 100;
                $request->platform = 'Linux x86_64';
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                $this->seleniumOptions->addHideSeleniumExtension = false;
                $this->setKeepProfile(true);
                $this->disableImages();
                $this->http->saveScreenshots = true;
                $this->usePacFile(false);

                if ($fingerprint) {
                    $this->http->setUserAgent($fingerprint->getUseragent());
                    $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
                }*/
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $this->logger->notice(__METHOD__);

        if ($fields['Rooms'] > 2) {
            $this->SetWarning('Maximum 2 rooms');

            return ['hotels' => []];
        }

        if ($fields['CheckOut'] == $fields['CheckIn']) {
            $this->SetWarning('You can’t book a day-use room.');

            return ['hotels' => []];
        }

        $fields['Nights'] = ($fields['CheckOut'] - $fields['CheckIn']) / 24 / 60 / 60;

        $query = $this->createQueryString($fields);
        $destination = rawurlencode($fields['Destination']);
        $url = "https://www.hyatt.com/search/hotels/en-US/{$destination}?{$query}";

        try {
            $this->http->GetURL($url);
            sleep(2);

            if (strpos($this->http->Response['body'], 'possible it is an HTTP ERROR 429') !== false) {
                $this->http->GetURL($url);
            }
        } catch (\WebDriverCurlException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());
            $this->increaseTimeLimit();

            try {
                $this->http->GetURL($url);
            } catch (\WebDriverCurlException | \WebDriverException $e) {
                $this->logger->error($e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if (strpos($this->http->Response['body'], 'possible it is an HTTP ERROR 429') !== false) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $this->waitFor(function () {
            return
                $this->waitForElement(\WebDriverBy::xpath("
                (//div[@data-js='hotel-list'])[1]
                | //div[normalize-space(text())='No results']/following-sibling::div[contains(.,'Adjust your search by')]
                "), 0);
        }, 20);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//div[normalize-space(text())='No results']/following-sibling::div[contains(.,'Adjust your search by')]")) {
            $this->SetWarning("There are no Hyatt hotels that match your search. Please try again");

            return ['hotels' => []];
        }

        $json = $this->http->FindSingleNode("//script[contains(.,'hotelData')]");
        $data = null;

        if ($json) {
            $this->logger->warning($json, ['pre' => true]);
            $res = $this->http->FindPreg("/(\{.+\})\][^]]*\]\)/", false, $json);

            if (is_string($res)) {
                $json = stripcslashes($res);
            }
            $data = $this->http->JsonLog($json, 1);
        }

        if ($data) {
            $hotels = $this->parseDataJson($fields, $data, $url);
        } else {
            throw new \CheckRetryNeededException(5, 0);
            $this->logger->debug($json);
            $hotels = $this->parseDataHtml($fields);
        }

        return ['hotels' => $hotels];
    }

    private function createQueryString($fields)
    {
        $arrayParams = [
            'checkinDate'  => date('Y-m-d', $fields['CheckIn']),
            'checkoutDate' => date('Y-m-d', $fields['CheckOut']),
            'rooms'        => $fields['Rooms'],
            'adults'       => $fields['Adults'],
            'kids'         => $fields['Kids'],
            'rate'         => 'Standard',
            'rateFilter'   => 'woh',
        ];

        for ($i = 1; $i <= $fields['Kids']; $i++) {
            $key = "childAge{$i}";
            $arrayParams[$key] = 14;
        }

        return http_build_query($arrayParams);
    }

    private function parseDataJson($fields, $data, $referer)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        // сразу увеличим. старт браузера бывает сильно тугой
        $this->increaseTimeLimit();

        foreach ($data->hotelData->hotelSummaries as $i => $root) {
            $this->logger->debug('hotel #' . $i);

            if (!isset($root->hpesrId)) {
                $this->logger->error("something went wrong");
                $this->sendNotification("check restart // ZM");

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->increaseTimeLimit();

            if ($root->bookabilityStatus !== 'BOOKABLE') {
                $this->logger->debug("skip hotel. status: " . $root->bookabilityStatus);

                continue;
            }
            $mainPoints = $root->leadingRate->points;
            $mainRate = $root->leadingRate->rate;

            if (!$mainPoints) {
                continue;
            }

            // TODO need to fix method notAcceptingPointsAwards
            if ($this->withExtenedeCheck && $this->notAcceptingPointsAwards($fields, $root->hotelDetail->spiritCode,
                    $root->hpesrId)) {
                $this->logger->debug("skip hotel. notAcceptingPointsAwards");

                continue;
            }

            if (!empty($this->roomRates)) {
                $rooms = [];

                foreach ($this->roomRates['roomRates'] as $code => $room) {
                    $rates = [];

                    foreach ($room['ratePlans'] as $ratePlan) {
                        $rates[] = [
                            'name'                    => $ratePlan['name'],
                            'description'             => $ratePlan['ratePlanDescription'],
                            'pointsPerNight'          => $ratePlan['totalPoints'],
                            'cashPerNight'            => round($ratePlan['totalBeforeTax']),
                            'currency'                => $room['currencyCode'],
                        ];
                    }
                    $rooms[] = [
                        'type'        => $room['roomType']['type'],
                        'name'        => $room['roomType']['title'],
                        'description' => $room['roomType']['description'],
                        'rates'       => $rates,
                    ];
                }
            } else {
                $rooms = [
                    [
                        'name' => '---', // TODO!!!!!
                        'rates'=> [
                            [
                                'pointsPerNight'            => $mainPoints,
                                'fullCashPricePerNight'     => round($mainRate),
                            ],
                        ],
                    ],
                ];
            }

            $preview = null;

            if ($fields['DownloadPreview']) {
                $imgUrl = $root->hotelDetail->thumbnails->standard;
                $this->logger->warning($imgUrl);
                $preview = $this->getBase64FromImageUrl($imgUrl);
            }

            $rating = $verifiedNumReviews = null;

            if (isset($root->hotelDetail->hotelRating)) {
                $rating = $root->hotelDetail->hotelRating->rating;
                $verifiedNumReviews = $root->hotelDetail->hotelRating->verifiedNumReviews;
            }

            $res = [
                'name'                    => $root->hotelDetail->name,
                'checkInDate'             => date('Y-m-d H:i', strtotime($root->hotelDetail->checkinTime, $fields['CheckIn'])),
                'checkOutDate'            => date('Y-m-d H:i', strtotime($root->hotelDetail->checkoutTime, $fields['CheckOut'])),
                'rooms'                   => $rooms,
                'hotelDescription'        => $root->hotelDetail->description,
                'numberOfNights'          => $fields['Nights'],
                'pointsPerNight'          => $mainPoints,
                'fullCashPricePerNight'   => round($mainRate),
                'currency'                => $root->leadingRate->currencyCode,
                'distance'                => round($root->distance, 2) . ' mi',
                'rating'                  => $rating,
                'awardCategory'           => isset($root->hotelDetail->gpCategory) ? (int) $root->hotelDetail->gpCategory : null,
                'numberOfReviews'         => $verifiedNumReviews,
                'detailedAddress'         => [
                    'addressLine' => trim(implode(' ',
                        [$root->hotelDetail->address1, $root->hotelDetail->address2 ?? ""])),
                    'city'        => $root->hotelDetail->city,
                    'stateName'   => null,
                    'countryName' => $root->hotelDetail->country,
                    'postalCode'  => $root->hotelDetail->zipcode,
                    'lat'         => (float) $root->hotelDetail->latitude,
                    'lng'         => (float) $root->hotelDetail->longitude,
                    'timezone'    => $root->hotelDetail->timezone,
                ],
                'phone'   => $root->hotelDetail->phone,
                'url'     => $root->hotelDetail->fullPropertySiteURL,
                'preview' => $preview,
            ];
            $res['address'] = implode(
                ', ',
                array_filter(
                    array_intersect(
                        $res['detailedAddress'],
                        ['addressLine' => true, 'city' => true, 'stateName' => true, 'countryName' => true]
                    )
                )
            );
            $this->logger->debug(var_export($res, true), ['pre' => true]);
            $result[] = $res;
        }

        if (empty($result) && count($data->hotelData->hotelSummaries) > 0) {
            $this->SetWarning("Unfortunately, this hotel is not accepting World of Hyatt points or award during those dates. Explore our other rates or modify your search.");
        }

        return $result;
    }

    private function notAcceptingPointsAwards($fields, $spiritCode, $hpesrId): bool
    {
        $this->roomRates = [];

        return $this->notAcceptingPointsAwardsUrl($fields, $spiritCode, $hpesrId);

        // NB: данный метод врет на проверках roomRates
        $checkIn = date('Y-m-d', $fields['CheckIn']);
        $checkOut = date('Y-m-d', $fields['CheckOut']);

        $url = "https://www.hyatt.com/shop/service/rooms/roomrates/{$spiritCode}?spiritCode={$spiritCode}&rooms={$fields['Rooms']}&adults={$fields['Adults']}&checkinDate={$checkIn}&checkoutDate={$checkOut}&kids={$fields['Kids']}&rate=Standard";
        $referer = "https://www.hyatt.com/shop/rooms/{$spiritCode}?checkinDate={$checkIn}&checkoutDate={$checkOut}&rooms=1&adults={$fields['Adults']}&kids={$fields['Kids']}&rate=Standard&rateFilter=woh&hpesrId=" . $hpesrId;

        $script = '
            fetch("' . $url . '", {
                "credentials": "include",
                "headers": {
                    "Accept": "*/*",
                    "Accept-Language": "en-US,en;q=0.5",
                    "Sec-Fetch-Dest": "empty",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Site": "same-origin",
                    "Pragma": "no-cache",
                    "Cache-Control": "no-cache"
                },
                "referrer": "' . $referer . '",
                "method": "GET",
                "mode": "cors"
            })
            .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "' . $spiritCode . '";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                })
            ;
        ';
        $this->logger->info($script, ['pre' => true]);
        $this->driver->executeScript($script);

        $dataSpirit = $this->waitForElement(\WebDriverBy::xpath('//script[@id="' . $spiritCode . '"]'), 15, false);
        $this->saveResponse();

        if (!$dataSpirit) {
            $this->logger->debug('no rate data');

            throw new \CheckRetryNeededException(5, 0);
        }

        /*        try {
                    $resString = $dataSpirit->getAttribute($spiritCode);
                } catch (\WebDriverException $e) {
                    $this->sendNotification("check getAttribute // ZM");
                }*/
        $resString = $this->http->FindSingleNode('//script[@id="' . $spiritCode . '"]/@' . $spiritCode);
        $resString = htmlspecialchars_decode($resString);
        $data = $this->http->JsonLog($resString, 1);

        if (empty($data->roomRates)) {
            return true;
        }

        return false;
    }

    private function notAcceptingPointsAwardsUrl($fields, $spiritCode, $hpesrId): bool
    {
        try {
            // тесты показали, что пока правда по такой проверке
            $checkIn = date('Y-m-d', $fields['CheckIn']);
            $checkOut = date('Y-m-d', $fields['CheckOut']);

            $this->http->RetryCount = 0;

            try {
                $referer = "https://www.hyatt.com/shop/rooms/{$spiritCode}?checkinDate={$checkIn}&checkoutDate={$checkOut}&rooms=1&adults={$fields['Adults']}&kids={$fields['Kids']}&rate=Standard&rateFilter=woh&hpesrId=" . $hpesrId;
                $this->http->GetURL($referer);
                usleep(random_int(7, 35) * 100000);
                $url = "https://www.hyatt.com/shop/service/rooms/roomrates/{$spiritCode}?spiritCode={$spiritCode}&rooms={$fields['Rooms']}&adults={$fields['Adults']}&checkinDate={$checkIn}&checkoutDate={$checkOut}&kids={$fields['Kids']}&rate=Standard";
                $this->http->GetURL($url);
            } catch (\WebDriverException | \WebDriverCurlException $e) {
                if (strpos($e->getMessage(), 'possible it is an HTTP ERROR 429') === false) {
                    throw $e;
                }
                $this->sendNotification("check retry 429 // ZM");
                $referer = "https://www.hyatt.com/shop/rooms/{$spiritCode}?checkinDate={$checkIn}&checkoutDate={$checkOut}&rooms=1&adults={$fields['Adults']}&kids={$fields['Kids']}&rate=Standard&rateFilter=woh&hpesrId=" . $hpesrId;
                $this->http->GetURL($referer);
                usleep(random_int(7, 35) * 100000);
                $url = "https://www.hyatt.com/shop/service/rooms/roomrates/{$spiritCode}?spiritCode={$spiritCode}&rooms={$fields['Rooms']}&adults={$fields['Adults']}&checkinDate={$checkIn}&checkoutDate={$checkOut}&kids={$fields['Kids']}&rate=Standard";
                $this->http->GetURL($url);
            }
            $this->http->RetryCount = 2;
            $this->saveResponse();

            $response = $this->http->FindSingleNode("//body/pre[contains(.,'roomRate')]");

            if (empty($response)) {
                return true;
            }
            $data = $this->http->JsonLog($response, 1, true);

            if (isset($data['responseInfo'])) {
                if ($data['responseInfo'] === 'qualifiedRateUnavailable') {
                    return true;
                }
                $this->logger->emergency(var_export($data['roomRates'], true));
                $this->sendNotification("check responseInfo // ZM");
            }
            $this->roomRates = $data;

            return false;
        } catch (\WebDriverException | \WebDriverCurlException $e) {
//            $this->sendNotification("check exception on check // ZM");

            return true;
        }
    }

    private function parseDataHtml($fields)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $nodes = $this->http->XPath->query('//div[contains(@data-js,"hotel-card")]/div');

        if ($nodes->length === 0) {
            throw new \CheckException('can\'t find any hotels', ACCOUNT_ENGINE_ERROR);
        }
        $this->sendNotification('Html format // ZM');

        foreach ($nodes as $i => $root) {
            $this->logger->debug('hotel #' . $i);
            $points = trim(str_replace(',', '',
                $this->http->FindSingleNode('.//div[contains(@class,"points-rate")]/div[1]', $root)));

            if (!$points) {
                continue;
            }

            $name = $this->http->FindSingleNode('.//div[contains(@class,"HotelCard_header")]', $root);
            $chekIn = date('Y-m-d H:i', $fields['CheckIn']);
            $chekOut = date('Y-m-d H:i', $fields['CheckOut']);
            $description = $this->http->FindSingleNode('.//div[contains(@data-js,"mobile-detail-source")]//div[contains(@data-js,"hotel-description")]',
                $root);
            $cache = (float) str_replace('$', '',
                $this->http->FindSingleNode('.//div[contains(@class,"cash-rate")]/div[1]', $root));
            $distance = (float) $this->http->FindSingleNode('.//div[contains(@class,"RatingAndDistance_")]/span[3]',
                $root);
            $rating = (float) $this->http->FindSingleNode('.//div[contains(@class,"RatingAndDistance_")]/span[1]', $root,
                false, "/(.+?)\s+\(/");
            $reviews = (int) $this->http->FindSingleNode('.//div[contains(@class,"RatingAndDistance_")]/span[1]', $root,
                false, "/\((\d+)\)/");
            $address = implode(', ',
                $this->http->FindNodes('.//div[contains(@data-js,"mobile-detail-source")]//div[contains(@data-js,"hotel-address")]/span',
                    $root));
            $url = $this->http->FindSingleNode('./@data-property-site-url', $root);
            $preview = null;

            if ($fields['DownloadPreview']) {
                $imgUrl = $this->http->FindSingleNode('.//ul[@data-js="media-collection"]/li[1]/img/@src', $root);
                $this->logger->warning($imgUrl);
                $preview = $this->getBase64FromImageUrl($imgUrl);
            }

            $res = [
                'name'             => (trim($name) != '') ? $name : null,
                'checkInDate'      => $chekIn,
                'checkOutDate'     => $chekOut,
                'roomType'         => null,
                'hotelDescription' => (trim($description) != '') ? $description : null,
                'numberOfNights'   => $fields['Nights'],
                'pointsPerNight'   => $points,
                'cashPerNight'     => ($cache) ?: null,
                'distance'         => ($distance) ?: null,
                'rating'           => ($rating) ?: null,
                'numberOfReviews'  => ($reviews) ?: null,
                'address'          => (trim($address) != '') ? $address : null,
                'phone'            => null,
                'url'              => (trim($url) != '') ? $url : null,
                'preview'          => $preview,
            ];
            $this->logger->debug(var_export($res, true), ['pre' => true]);
            $result[] = $res;
        }

        return $result;
    }

    private function getBase64FromImageUrl(?string $url): ?string
    {
        if (null === $url) {
            return null;
        }
        $this->logger->info('download image: ' . $url);

        $image = file_get_contents($url);
        $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true);

        file_put_contents($file, $image);
        $imageSize = getimagesize($file);

        if (!$imageSize) {
            return null;
        }

        if ($imageSize[0] > 640) {
            $image = new \Imagick($file);
            $image->scaleImage(640, 0);

            file_put_contents($file, $image);
            $imageSize = getimagesize($file);
        }

        $imageData = base64_encode(file_get_contents($file));
//        $this->logger->debug("<img src='data:{$imageSize['mime']};base64,{$imageData}' {$imageSize[3]} />", ['HtmlEncode'=>false]);
        unlink($file);

        return $imageData;
    }
}
