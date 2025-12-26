<?php

namespace AwardWallet\Engine\hhonors\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use SeleniumFinderRequest;

class HotelParser extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $downloadPreview;
    private string $token;
    private array $hotelsIds;
    private string $country;
    private string $referer;

    public static function getRASearchLinks(): array
    {
        return ['https://www.hilton.com/en/search/find-hotels/' => 'search page'];
    }

    // Обязательный метод для инициализации браузера.
    public function InitBrowser()
    {
        parent::InitBrowser(); // Обязательный вызов конструктора родительского класса.
        $this->UseSelenium(); // Указываем, что используется selenium, а не curl.

        // $this->useFirefox(SeleniumFinderRequest::FIREFOX_53); // Блоки
        // $this->useFirefox(SeleniumFinderRequest::FIREFOX_59); // Сначала работал, потом пошли блоки
        // $this->useFirefox(SeleniumFinderRequest::FIREFOX_84); // Тоже блоки

        // $this->useGoogleChrome(SeleniumFinderRequest::CHROME_84); // Блоки
        // $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95); // Вообще не работает, + с большой скоростью пишет логи, которые занимают всё дисковое пространство
        // $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99); // Блоки; когда поменял местами getToken() и GetURL("https://www.hilton.com/en/search/?{$query}") - заработало.
                                                                     // Но стопорится на getHotelPrices()
        // $this->useChromePuppeteer(); // Блоки

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_59); // Поменял местами getToken() и GetURL("https://www.hilton.com/en/search/?{$query}") - опять заработало.
                                                                      // getHotelPrices() тоже отрабатывает


        //$this->disableImages(); // Отключаем загрузку изображений, может негативно повлиять на детект селениума как бота.
        //$this->setScreenResolution([1280, 800]); // Устанавливаем разрешение экрана.
        $this->setScreenResolution([1920, 1080]); // Устанавливаем разрешение экрана.
        $this->http->saveScreenshots = true; // Включаем скриншоты: теперь при вызове метода $this->saveResponse() будет производиться скриншот веб-страницы, на которой мы находимся в данный момент.

        $array = ['ca', 'us', 'fr', 'uk', 'es']; // Массив с кодами стран.
        $this->country = $array[random_int(0, count($array) - 1)]; // Случайный выбор страны для установки proxy.
        //$this->country = 'us';
        $this->setProxyGoProxies(null, $this->country); // Установка proxy из выбранной страны.
        $this->country = strtoupper($this->country);
        $this->logger->info('Страна: ' . $this->country);
    }

    // Обязательный метод; определяем залогинены ли мы на сайте: true или false.
    // Если мы в принципе не логинимся на сайте, то просто возвращаем false (типа всегда не залогинены).
    public function IsLoggedIn()
    {
        return false;
    }

    // Обязательный метод; нужен для того, чтобы получить страницу/форму для логина.
    // Если форма успешно загружена, то возвращается true, если нет - то false.
    // Если не нужно логиниться, то просто возвращаем true (типа всегда успешно загружаем форму для логина).
    public function LoadLoginForm()
    {
        return true;
    }

    // Обязательный метод; нужен для того, чтобы логиниться на сайте.
    // Если успешно залогинились - возвращает true, если нет - false.
    // Если не нужно логиниться, то просто возвращаем true (типа всегда успешно логинимся).
    public function Login()
    {
        return true;
    }

    // Последний обязательный метод; осуществляет непосредственный парсинг информации с сайта.
    // Предыдущие методы были лишь подготовительными.
    // Возвращает массив с ключом hotels - результат поиска (массив отелей).
    // Если по заданным параметра нет вариантов размещения в отелях на сайте, то ответ ['hotels'=>[]].
    public function ParseRewardAvailability(array $fields): array
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]); // Выводим заголовок второго уровня с текстом "Parse Reward Availability".
        $this->logger->notice(__METHOD__); // Выводим название метода, в котором находимся.

        // Получаем из $fields даты заезда и выезда в виде строк формата 'Y-m-d'.
        $checkInStr = date('Y-m-d', $fields['CheckIn']);
        $checkOutStr = date('Y-m-d', $fields['CheckOut']);

        // Устанавливаем флаг, определяющий нужно ли загружать превью. Если в параметрах запроса не указано, то не загружаем.
        $this->downloadPreview = $fields['DownloadPreview'] ?? false;

        // Осуществляем предварительные проверки
        if (!$this->isPreCheck($fields, $checkInStr, $checkOutStr)) {
            return ['hotels' => []];
        }

        // Если все проверки пройдены, то загружаем главную страницу сайта.
        $this->http->GetURL('https://www.hilton.com/en');


//        $headers = [
//            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
//            'Referer' => 'https://www.hilton.com/en/book/reservation/rooms/?ctyhocn=HOUTSQQ&arrivalDate=2025-02-07&departureDate=2025-02-09&redeemPts=true&room1NumAdults=1&room1NumChildren=1',
//
//        ];
//        $this->http->GetURL('https://www.hilton.com/en/book/reservation/rooms/?ctyhocn=HOUTSQQ&arrivalDate=2025-02-07&departureDate=2025-02-09&redeemPts=true&room1NumAdults=1&room1NumChildren=1',
//        $headers);
//        return ['hotels' => []];


        $this->token = $this->getToken(
            'https://www.hilton.com/dx-customer/auth/applications/token?appName=dx_shop_search_app',
            'https://www.hilton.com/en/',
        'window.__ENV.DX_AUTH_API_CUSTOMER_APP_ID');

        // Подготавливаем параметры GET-запроса
        $query = http_build_query([
            'query' => $fields['Destination'],
            'arrivalDate' => $checkInStr,
            'departureDate' => $checkOutStr,
            'flexibleDates' => 'false',
            'numRooms' => $fields['Rooms'],
            'numAdults' => $fields['Adults'],
            'numChildren' => $fields['Kids'],
            'room1ChildAges' => 14,
            'room1AdultAges' => '',
            'redeemPts' => 'true',
            //'specialRateTokens' => '',
            //'sortBy' => 'DISTANCE',
            //'sessionToken' => '9cf0350a-d3d3-4720-983e-c49dc284424a',
        ]);

        // Подготавливаем URL, делаем GET-запрос, загружаем веб-страницу и делаем её скриншот.
        $url = "https://www.hilton.com/en/search/?{$query}";
        $this->referer = $url;
        $headers = [
            'Accept' => 'application/json; charset=utf-8',
            'Content-Type' => 'application/json; charset=utf-8',
            'Referer' => '$referrer',
        ];
        $this->http->GetURL($url, $headers);
        $this->saveResponse();

        // Проверяем на ошибки (ищем конкретные текстовки на веб-странице). Если текстовки найдены, то выбрасывается исключение, и дальше код не выполняется.
        // Если ошибок нет, то checkErrors() вернёт true и условие не выполнится.
        if (!$this->isMainCheck()) {
            return ['hotels' => []];
        }

        // Получаем список отелей с информацией о них
        $hotelsInfo = $this->getHotelsInfo($fields, $checkInStr, $checkOutStr);
        // $this->logger->info("<pre>".print_r($hotelsInfo, true) . "</pre>");
        $this->saveResponse();

        $parsedHotels = $this->parseHotelsInfo($hotelsInfo);

        for ($i = 0; $i < count($parsedHotels); $i++) {
            $rooms = $this->parseRoomsAndRatesForHotel($this->hotelsIds[$i], $fields, $checkInStr, $checkOutStr);
            $parsedHotels[$i]['rooms'] = $rooms;
        }

        return ['hotels' => $parsedHotels];
//
//        // Пытаемся получить данные при помощи метода GetData
//        try {
//            $prices = $this->getHotelPrices($hotelsIds, $fields, $checkInStr, $checkOutStr);
//        } catch (\WebDriverException $e) {
//            $this->logger->debug('Было выброшено исключение. Текст ошибки:');
//            $this->logger->error($e->getMessage());
//            return ['hotels' => []];
//            // throw new \CheckRetryNeededException(5, 0);
//        }

        // Парсим данные из полученного JSON.
        //$res = $this->parseRespData($data);

        //return ['hotels' => $res];
    }

    // Метод, осуществляющий предварительные проверки параметров запроса ($fields)
    private function isPreCheck($fields, $checkInStr, $checkOutStr) {
        // Перед осуществлением запросов к сайту нужна обязательная проверка корректности параметров запроса.
        // Если количество комнат больше 9, то устанавливаем предупреждение, что максимальное количество комнат - 9.
        // И возвращаем пустой массив отелей.
        if ($fields['Rooms'] > 9) {
            $this->SetWarning('Maximum 9 rooms');

            return false;
        }

        // Вторая проверка на корректность параметров запроса.
        // Если дата заезда равна дате выезда, то устанавливаем предупреждение, что комнату нельзя снять только на день.
        // И возвращаем пустой массив отелей.
        if ($checkInStr == $checkOutStr) {
            $this->SetWarning('You can’t book a day-use room.');

            return false;
        }

        // Третья проверка на корректность параметров запроса.
        // Постой не может длиться дольше 90 дней.
        // Устанавливаем предупреждение и возвращаем пустой массив отелей.
        $diffInDays = ($fields['CheckOut'] - $fields['CheckIn']) / (60 * 60 * 24);

        if ($diffInDays > 90) {
            $this->SetWarning('Maximum 90 days for book.');

            return false;
        }

        return true;
    }

    // Метод проверяет наличие ошибок, которые могут возникнуть в ходе запроса.
    // Делает это путем поиска конкретных текстовок на веб-странице.
    // Если текстовки найдены, то произошла ошибка и выбрасывается исключение с кодом ACCOUNT_PROVIDER_ERROR.
    // Если текстовки не найдены, то ошибок нет: возвращаем true.
    private function isMainCheck()
    {
        if ($this->waitForElement(\WebDriverBy::xpath('
            //h2[contains(text(),"find the page you are looking")]
            | //h2[starts-with(normalize-space(),"Showing")]
            | //div[contains(text(),"entries contained an error")]
            | //h1[contains(text(),"WE\'RE SORRY!")]
        '), 10)) {
            $this->saveResponse();
            if ($this->waitForElement(\WebDriverBy::xpath("//h2[starts-with(normalize-space(),'Showing')]"),
                0)) {
                return true; // no errors
            }
            throw new \CheckException('Invalid search data. Please verify your entries and try again', ACCOUNT_PROVIDER_ERROR);
        }

        if ($err = $this->waitForElement(\WebDriverBy::xpath('//h2[contains(text(),"something went wrong.")]'), 0)) {
            $this->logger->error($err->getText());

            throw new \CheckException($err->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    private function getToken($url, $referrer, $apiId): string
    {
        $this->logger->notice(__METHOD__); // Выводим название метода, в котором находимся.

        $script = "
            function fetchWithTimeout(url, options, timeout = 10000) {
                const controller = new AbortController();
                const id = setTimeout(() => controller.abort(), timeout);
                return fetch(url, {...options, signal: controller.signal})
                    .finally(() => clearTimeout(id));
            };
            
            async function getToken() {
                try {
                    let response = await fetchWithTimeout('$url', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json; charset=utf-8',
                            'Content-Type': 'application/json; charset=utf-8',
                            'Referer': '$referrer'
                        },
                        body: JSON.stringify({'app_id': $apiId})
                    });
                                
                    let result = await response.json();
                    return JSON.stringify(result);
                }
                catch (err) {
                    return JSON.stringify(err);
                }
            }
            
            return getToken();
        ";

        // Выводим сам скрипт как есть (используя тег <pre>).
        $this->logger->debug("Execute script:");
        $this->logger->debug($script, ['pre' => true]);

        // Выполняем скрипт и получаем ответ в виде JSON-строки
        $jsonStr = $this->driver->executeScript($script);

        $this->saveResponse();

        // Преобразуем JSON в ассоциативный массив с данными
        $tokenData = $this->http->JsonLog($jsonStr, 1);
        // Если массив пуст, выбрасываем исключение с текстовкой 'no token' (так как токен в таком случае не будет получен)
        if (!isset($tokenData->access_token)) {
            throw new \CheckException('no token', ACCOUNT_ENGINE_ERROR);
        }

        // Добавим вывод содержимого этого массива.
        $this->logger->debug(var_export($tokenData, true));
        // Возвращаем токен и его тип.
        return $tokenData->token_type . ' ' . $tokenData->access_token;
    }

    private function getMainHotelsInfo(array $fields, $queryLimit = 20): array
    {
        $this->logger->notice(__METHOD__); // Выводим название метода.

        $apiUrl = 'https://www.hilton.com/graphql/customer?operationName=geocode_hotelSummaryOptions&originalOpName=geocode_hotelSummaryOptions&appName=dx_shop_search_app&bl=en';

        $script = "
            function fetchWithTimeout(url, options, timeout = 10000) {
                const controller = new AbortController();
                const id = setTimeout(() => controller.abort(), timeout);
                return fetch(url, {...options, signal: controller.signal})
                    .finally(() => clearTimeout(id));
            };
            
            // функция для получения списка доступных отелей
            async function getHotels() {
                let payload = {
                    'query': 'query geocode_hotelSummaryOptions(\$address: String, \$distanceUnit: HotelDistanceUnit, \$language: String!, \$placeId: String, \$queryLimit: Int!, \$sessionToken: String) {  geocode(    language: \$language    address: \$address    placeId: \$placeId    sessionToken: \$sessionToken  ) {    match {      id      address {        city        country        state        postalCode      }      name      type      geometry {        location {          latitude          longitude        }        bounds {          northeast {            latitude            longitude          }          southwest {            latitude            longitude          }        }      }    }    hotelSummaryOptions(distanceUnit: \$distanceUnit, sortBy: distance) {      bounds {        northeast {          latitude          longitude        }        southwest {          latitude          longitude        }      }      amenities {        id        name        hint      }      amenityCategories {        name        id        amenityIds      }      brands {        code        name      }      hotels(first: \$queryLimit) {        _id: ctyhocn        amenityIds        brandCode        ctyhocn        distance        distanceFmt        facilityOverview {          allowAdultsOnly          homeUrlTemplate        }        name        display {          open          openDate          preOpenMsg          resEnabled          resEnabledDate          treatments        }        contactInfo {          phoneNumber        }        address {          addressLine1          city          country          state        }        localization {          coordinate {            latitude            longitude          }        }        images {          master(ratios: [threeByTwo]) {            altText            ratios {              size              url            }          }        }        tripAdvisorLocationSummary {          numReviews          ratingFmt(decimal: 1)          ratingImageUrl        }        leadRate {          hhonors {            lead {              dailyRmPointsRate              dailyRmPointsRateNumFmt: dailyRmPointsRateFmt(hint: number)              ratePlan {                ratePlanName @toTitleCase                ratePlanDesc              }            }            max {              rateAmount              rateAmountFmt              dailyRmPointsRate              dailyRmPointsRateRoundFmt: dailyRmPointsRateFmt(hint: round)              dailyRmPointsRateNumFmt: dailyRmPointsRateFmt(hint: number)              ratePlan {                ratePlanCode              }            }            min {              rateAmount(decimal: 1)              rateAmountFmt              dailyRmPointsRate              dailyRmPointsRateRoundFmt: dailyRmPointsRateFmt(hint: round)              dailyRmPointsRateNumFmt: dailyRmPointsRateFmt(hint: number)              ratePlan {                ratePlanCode              }            }          }        }      }    }    ctyhocnList: hotelSummaryOptions(distanceUnit: \$distanceUnit, sortBy: distance) {      hotelList: hotels(first: 150) {        ctyhocn      }    }  }  geocodeEn: geocode(    language: \"en\"    address: \$address    placeId: \$placeId    sessionToken: \$sessionToken  ) {    match {      name    }  }}',
                    'operationName': 'geocode_hotelSummaryOptions',
                    'variables': {
                        'address': '$fields[Destination]', // здесь указываем город, для которого ищем отели
                        'language': 'en',
                        'placeId': null,
                        'queryLimit': $queryLimit // здесь указываем сколько отелей должно быть в ответе
                    }
                }
                
                try {
                    let response = await fetchWithTimeout('$apiUrl', {
                        'headers': {
                            'Accept': '*/*',
                            'Authorization': '$this->token',
                            'Content-Type': 'application/json',
                            'Referrer': 'https://www.hilton.com/en/search/',
                        },
                        'body': JSON.stringify(payload),
                        'method': 'POST',
                    });
            
                    let result = await response.json();
                    return JSON.stringify(result.data.geocode.hotelSummaryOptions.hotels);
                }
                catch {
                    return null;
                }
            }

            return getHotels();
        ";

        // Печатаем кода скрипта как есть (используя тег <pre>).
        $this->logger->debug("Execute script:");
        $this->logger->debug($script, ['pre' => true]);

        // Выполняем скрипт
        $jsonStr = $this->driver->executeScript($script);

        $this->saveResponse();

        // Если ответ - пустой, то выбрасываем исключение, которое заставляет парсер работать повторно 5 раз (?).
        if (!$jsonStr) {
            return [];
            //throw new \CheckRetryNeededException(5, 0);
        }

        // Преобразовываем JSON-строку в ассоциативный массив
        $mainHotelsInfo = $this->http->JsonLog($jsonStr, 1, true);

        // Производим индексацию массива и возвращаем его
        $indexedMainHotelsInfo = [];

        foreach ($mainHotelsInfo as $hotel) {
            $indexedMainHotelsInfo[$hotel['_id']] = $hotel;
        }

        return $indexedMainHotelsInfo;
    }

    // функция для получения ценовой информации для списка отелей
    private function getPriceHotelsInfo(array $hotelsIds, array $fields, string $checkInStr, string $checkOutStr): array
    {
        $this->logger->notice(__METHOD__); // Выводим название метода.
        $url = 'https://www.hilton.com/graphql/customer?appName=dx_shop_search_app&operationName=shopMultiPropAvail&originalOpName=shopMultiPropAvailPoints&bl=en';

        $hotelsIdsStr = "'" . implode("', '", $hotelsIds) . "'";

        $script = "
            function fetchWithTimeout(url, options, timeout = 10000) {
                const controller = new AbortController();
                const id = setTimeout(() => controller.abort(), timeout);
                return fetch(url, {...options, signal: controller.signal})
                    .finally(() => clearTimeout(id));
            };
            
            // функция для получения ценовой информации для списка отелей
            async function getHotelPrices() {
                let payload = {
                    'query': 'query shopMultiPropAvail(\$ctyhocns: [String!], \$language: String!, \$input: ShopMultiPropAvailQueryInput!) {\\n  shopMultiPropAvail(input: \$input, language: \$language, ctyhocns: \$ctyhocns) {\\n    ageBasedPricing\\n    ctyhocn\\n    currencyCode\\n    statusCode\\n    statusMessage\\n    lengthOfStay\\n    notifications {\\n      subType\\n      text\\n      type\\n    }\\n    summary {\\n      hhonors {\\n        dailyRmPointsRate\\n        dailyRmPointsRateFmt\\n        rateChangeIndicator\\n        ratePlan {\\n          ratePlanName @toTitleCase\\n        }\\n      }\\n      lowest {\\n        cmaTotalPriceIndicator\\n        feeTransparencyIndicator\\n        rateAmountFmt(strategy: ceiling, decimal: 0)\\n        rateAmount(currencyCode: \"USD\")\\n        ratePlanCode\\n        rateChangeIndicator\\n        ratePlan {\\n          attributes\\n          ratePlanName @toTitleCase\\n          specialRateType\\n          confidentialRates\\n        }\\n        amountAfterTax(currencyCode: \"USD\")\\n        amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n      }\\n      status {\\n        type\\n      }\\n    }\\n  }\\n}',
                    'operationName': 'shopMultiPropAvail',
                    'variables': {
                        'input': {
                            'guestId': 0,
                            'guestLocationCountry': '$this->country',
                            'arrivalDate': '$checkInStr',
                            'departureDate': '$checkOutStr',
                            'numAdults': $fields[Adults],
                            'numChildren': $fields[Kids],
                            'numRooms': $fields[Rooms],
                            'childAges': 14,
                            'ratePlanCodes': [],
                            'rateCategoryTokens': [],
                            'specialRates': {
                                'aaa': false,
                                'aarp': false,
                                'corporateId': '',
                                'governmentMilitary': false,
                                'groupCode': '',
                                'hhonors': true,
                                'pnd': '',
                                'offerId': null,
                                'promoCode': '',
                                'senior': false,
                                'smb': false,
                                'travelAgent': false,
                                'teamMember': false,
                                'familyAndFriends': false,
                                'owner': false,
                                'ownerHGV': false
                            }
                        },
                        'ctyhocns': [$hotelsIdsStr],
                        'language': 'en'
                    }
                }
            
                let response = await fetchWithTimeout('$url', {
                    'headers': {
                        'Accept': '*/*',
                        'Authorization': '$this->token',
                        'Content-Type': 'application/json',
                        'Referrer': 'https://www.hilton.com/en/search/',
                    },
                    'body': JSON.stringify(payload),
                    'method': 'POST',
                });
            
                let result = await response.json();
                return JSON.stringify(result);
                //return JSON.stringify(result.data.shopMultiPropAvail);
            }

            return getHotelPrices();
        ";

        $this->logger->debug("Execute script:");
        $this->logger->debug($script, ['pre' => true]);

        // Выполняем скрипт
        $jsonStr = $this->driver->executeScript($script);

        $this->saveResponse();

        // Преобразовываем JSON-строку в ассоциативный массив
        $priceHotelsInfo = $this->http->JsonLog($jsonStr, 1, true)['data']['shopMultiPropAvail'];

        // Производим индексацию массива и возвращаем его
        $indexedPriceHotelsInfo = [];

        foreach ($priceHotelsInfo as $hotel) {
            $indexedPriceHotelsInfo[$hotel['ctyhocn']] = $hotel;
        }

        return $indexedPriceHotelsInfo;
    }

//    // Метод, который позволяет получить данные по отелям в виде JSON.
//    private function getData(array $fields, string $checkInStr, string $checkOutStr): array
//    {
//        $this->logger->notice(__METHOD__); // Выводим название метода.
//
//        $hotels = $this->getHotels($fields);
//
//
//        // Если ответ - пустой, то выбрасываем исключение, которое заставляет парсер работать повторно 5 раз (?).
//        if (!$hotels) {
//            return [];
//            //throw new \CheckRetryNeededException(5, 0);
//        }
//
//        $hotelSummaries = [];
//        $hotelsIds = [];
//
//
//        // Если после преобразования JSON-ответа получили непустой массив, то:
////        if (is_array($result) && empty($result)) {
////            // Делаем скриншот страницы
////            $this->saveResponse();
////            if ($msg = $this->waitForElement(\WebDriverBy::xpath("//h2[starts-with(normalize-space(),'Showing 0 hotels')]/following::*[normalize-space()][1]"),
////                0)) {
////                $text = $msg->getText();
////                if (strpos($text, 'find any hotels for you in this area') !== false) {
////                    $this->SetWarning($text);
////                } else {
////                    $this->sendNotification("check message // ZM");
////                }
////            }
////            return [];
////        }
//
//        foreach ($hotels as $hotelSummary) {
//            $hotelSummaries[$hotelSummary['_id']] = $hotelSummary;
//            $hotelsIds[] = $hotelSummary['_id'];
//        }
//
//        $hotelsIdsChunks = array_chunk($hotelsIds, 20); //
//
//        foreach ($hotelsIdsChunks as $chunkIndex => $value) {
////            $body = addslashes('{"query":"query shopMultiPropAvail($ctyhocns: [String!], $language: String!, $input: ShopMultiPropAvailQueryInput!) {\n  shopMultiPropAvail(input: $input, language: $language, ctyhocns: $ctyhocns) {\n    ageBasedPricing\n    ctyhocn\n    currencyCode\n    statusCode\n    statusMessage\n    lengthOfStay\n    notifications {\n      subType\n      text\n      type\n    }\n    summary {\n      hhonors{\n      dailyRmPointsRate\n     dailyRmPointsRateFmt\n     rateChangeIndicator\n    ratePlan {\n    ratePlanName @toUpperCase\n    }\n     }\n       lowest {\n        cmaTotalPriceIndicator\n        feeTransparencyIndicator\n        rateAmountFmt(strategy: trunc, decimal: 0)\n        rateAmount(currencyCode: \"USD\")\n        ratePlanCode\n        rateChangeIndicator\n        ratePlan {\n          attributes\n          ratePlanName @toUpperCase\n          specialRateType\n          confidentialRates\n        }\n        amountAfterTax(currencyCode: \"USD\")\n        amountAfterTaxFmt(decimal: 0, strategy: trunc)\n      }\n      status {\n        type\n      }\n    }\n  }\n}","operationName":"shopMultiPropAvail","variables":{"input":{"guestId":0,"guestLocationCountry":"US","arrivalDate":"' . $checkInStr . '","departureDate":"' . $checkOutStr . '","numAdults":' . $fields['Adults'] . ',"numChildren":0,"numRooms":' . $fields['Rooms'] . ',"childAges":[],"ratePlanCodes":[],"rateCategoryTokens":[],"specialRates":{"aaa":false,"aarp":false,"corporateId":"","governmentMilitary":false,"groupCode":"","hhonors":true,"pnd":"","offerId":null,"promoCode":"","senior":false,"smb":false,"travelAgent":false,"teamMember":false,"familyAndFriends":false,"owner":false,"ownerHGV":false}},"ctyhocns":["' . implode('","',
////                    $value) . '"],"language":"en"}}');
////            $script = '
////                fetch("https://www.hilton.com/graphql/customer?operationName=shopMultiPropAvail&originalOpName=shopMultiPropAvailPoints&appName=dx_shop_search_app&bl=en", {
////                  "headers": {
////                    "accept": "*/*",
////                    "authorization": "' . $this->token . '",
////                    "content-type": "application/json",
////                  },
////                  "referrer": "https://www.hilton.com/en/search/",
////                  "body": "' . $body . '",
////                  "method": "POST",
////                }).then( response => response.json())
////                  .then( result => {
////                    let script = document.createElement("script");
////                    let id = "shopMultiPropAvail-response' . $chunkIndex . '";
////                    script.id = id;
////                    script.setAttribute(id, JSON.stringify(result.data.shopMultiPropAvail));
////                    document.querySelector("body").append(script);
////                });
////            ';
////
////            $this->logger->debug("Execute script:");
////            $this->logger->debug($script, ['pre' => true]);
////            $this->driver->executeScript($script);
//            $result = $this->getHotelPrices($value, $fields, $checkInStr, $checkOutStr);
//
//            // Добавим вывод содержимого этого массива.
//            break;
//        }
//
////        for ($i = 0, $iMax = count($hotelsIds); $i < $iMax; $i++) {
////            $json = $this->waitForElement(\WebDriverBy::xpath("//script[@id='shopMultiPropAvail-response{$i}']"), 10,
////                false);
////
////            if (!$json) {
////                throw new \CheckRetryNeededException(5, 0);
////            }
////            $result = $this->http->JsonLog($json->getAttribute("shopMultiPropAvail-response{$i}"), 1, true);
////
////            foreach ($result as $shopMultiPropAvail) {
////                $hotelSummaries[$shopMultiPropAvail['ctyhocn']] = $hotelSummaries[$shopMultiPropAvail['ctyhocn']] + $shopMultiPropAvail;
////            }
////        }
////
////        return $hotelSummaries;
//
//        return [];
//    }

    private function getHotelsInfo($fields, $checkInStr, $checkOutStr, $queryLimit = 20): array
    {
        $this->logger->notice(__METHOD__); // Выводим название метода.

        $mainHotelsInfo = $this->getMainHotelsInfo($fields, $queryLimit);

        $priceHotelsInfo = [];
        $this->hotelsIds = array_keys($mainHotelsInfo);
        $hotelIdsChunks = array_chunk(array_keys($mainHotelsInfo), 20);

        foreach ($hotelIdsChunks as $hotelIds) {
            $priceHotelsInfo = array_merge($priceHotelsInfo, $this->getPriceHotelsInfo($hotelIds, $fields, $checkInStr, $checkOutStr));
        }

        $hotelsInfo = [];

        foreach(array_map(null, $mainHotelsInfo, $priceHotelsInfo) as list($mainHotelInfo, $priceHotelInfo)) {
            $hotelsInfo[] = array_merge($mainHotelInfo, $priceHotelInfo);
        }

        return $hotelsInfo;
    }

    private function parseHotelsInfo($hotelsInfo): array
    {
        $this->logger->notice(__METHOD__);
        $parsedData = [];
        $msgSkippedHotel = null;

        foreach ($hotelsInfo as $hotelInfo) {
            if ($hotelInfo['summary']['status']['type'] !== 'AVAILABLE' || !isset($hotelInfo['summary']['hhonors'])) {
                $this->logger->debug('Я тут!');
                continue;
            }

            // Если нельзя оплатить поинтами (нужно разобраться, что здесь происходит)
//            if (!isset($hotelInfo['summary']['hhonors']['dailyRmPointsRate'])) {
//                $this->logger->debug('no points rate. skip');
//
//                if ($hotelInfo['notifications'][0]['type'] === 'info' && !isset($msgSkippedHotel)) {
//                    $msgSkippedHotel = $hotelInfo['notifications'][0]['text'] ?? null;
//                }
//                $skipedHotel = true;
//
//                continue;
//            }

            // Загружаем превью, если требуется
            $preview = null;

            try {
                if (isset($hotelInfo['images']['master']['ratios'][0]) && $this->downloadPreview) {
                    $urlImg = $hotelInfo['images']['master']['ratios'][0]['url'] ?? null;
                    $preview = $this->getBase64FromImageUrl($urlImg);
                }
            }
            catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }

            // формируем адрес отеля
            $addressItemsOrder = ['addressLine1', 'city', 'state', 'country'];
            $address = '';

            foreach ($addressItemsOrder as $addressItem) {
                if (empty($hotelInfo['address'][$addressItem])) {
                    continue;
                }

                if (!empty($address)) {
                    $address = $address . ', ';
                }

                $address = $address . $hotelInfo['address'][$addressItem];
            }

            if ($address === '') {
                $address = null;
            }

            $parsedData[] = [
                'name' => $hotelInfo['name'] ?? null,
                'checkInDate' => date('Y-m-d H:i', $this->AccountFields['RaRequestFields']['CheckIn']),
                'checkOutDate' => date('Y-m-d H:i', $this->AccountFields['RaRequestFields']['CheckOut']),
                'rooms' => [],
                'hotelDescription' => null,
                'numberOfNights' => $hotelInfo['lengthOfStay'] ?? null,
                'pointsPerNight' => $hotelInfo['summary']['hhonors']['dailyRmPointsRate'],
                'fullCashPricePerNight' => $hotelInfo['summary']['lowest']['amountAfterTax'],
                'distance' => $hotelInfo['distanceFmt'] ?? null,
                'rating' => $hotelInfo['tripAdvisorLocationSummary']['ratingFmt'] ?? null,
                'awardCategory' => null,
                'numberOfReviews' => $hotelInfo['tripAdvisorLocationSummary']['numReviews'] ?? null,
                'address' => $address,
                'phone' => $hotelInfo['contactInfo']['phoneNumber'] ?? null,
                'url' => $hotelInfo['facilityOverview']['homeUrlTemplate'] ?? null,
                'preview' => $preview ?? null,
            ];
        }

//        if (isset($skipedHotel)) {
//            if (isset($msgSkippedHotel)) {
//                $this->SetWarning($msgSkippedHotel);
//            }
//            $this->sendNotification("check skipped // ZM");
//        }

        return $parsedData;
    }

    private function getHotelRoomsInfo(string $hotelId, array $fields, string $checkInStr, string $checkOutStr): array
    {
        $this->logger->notice(__METHOD__); // Выводим название метода.

        $smallHotelId = substr($hotelId, 0, 5);

        $script = "
            function fetchWithTimeout(url, options, timeout = 10000) {
                const controller = new AbortController();
                const id = setTimeout(() => controller.abort(), timeout);
                return fetch(url, {...options, signal: controller.signal})
                    .finally(() => clearTimeout(id));
            };
            
            // функция для получения ценовой информации для списка отелей
            async function getHotelRoomsInfo() {
                let payload = {
                    'query': 'query hotel_shopAvailOptions_shopPropAvail(\$arrivalDate: String!, \$ctyhocn: String!, \$departureDate: String!, \$language: String!, \$guestLocationCountry: String, \$numAdults: Int!, \$numChildren: Int!, \$numRooms: Int!, \$displayCurrency: String, \$guestId: BigInt, \$specialRates: ShopSpecialRateInput, \$rateCategoryTokens: [String], \$selectedRoomRateCodes: [ShopRoomRateCodeInput!], \$ratePlanCodes: [String], \$pnd: String, \$offerId: BigInt, \$cacheId: String!, \$knownGuest: Boolean, \$modifyingReservation: Boolean, \$currentlySelectedRoomTypeCode: String, \$currentlySelectedRatePlanCode: String, \$childAges: [Int], \$adjoiningRoomStay: Boolean, \$programAccountId: BigInt, \$roomTypeSortInput: [ShopRoomTypeSortInput!]) {\\n  hotel(ctyhocn: \$ctyhocn, language: \$language) {\\n    ctyhocn\\n    shopAvailOptions(input: {offerId: \$offerId, pnd: \$pnd}) {\\n      maxNumChildren\\n      altCorporateAccount {\\n        corporateId\\n        name\\n      }\\n      contentOffer {\\n        name\\n      }\\n    }\\n    shopAvail(\\n      cacheId: \$cacheId\\n      input: {guestLocationCountry: \$guestLocationCountry, arrivalDate: \$arrivalDate, departureDate: \$departureDate, displayCurrency: \$displayCurrency, numAdults: \$numAdults, numChildren: \$numChildren, numRooms: \$numRooms, guestId: \$guestId, specialRates: \$specialRates, rateCategoryTokens: \$rateCategoryTokens, selectedRoomRateCodes: \$selectedRoomRateCodes, ratePlanCodes: \$ratePlanCodes, knownGuest: \$knownGuest, modifyingReservation: \$modifyingReservation, childAges: \$childAges, adjoiningRoomStay: \$adjoiningRoomStay, programAccountId: \$programAccountId}\\n    ) {\\n      currentlySelectedRoom: roomTypes(\\n        filter: {roomTypeCode: \$currentlySelectedRoomTypeCode}\\n      ) {\\n        adaAccessibleRoom\\n        roomTypeCode\\n        roomRates(filter: {ratePlanCode: \$currentlySelectedRatePlanCode}) {\\n          ratePlanCode\\n          rateAmount\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          rateChangeIndicator\\n          ratePlan {\\n            ratePlanName\\n            commissionable\\n            confidentialRates\\n            specialRateType\\n            hhonorsMembershipRequired\\n            redemptionType\\n          }\\n          pointDetails {\\n            pointsRateFmt\\n          }\\n        }\\n      }\\n      statusCode\\n      summary {\\n        specialRates {\\n          specialRateType\\n          roomCount\\n        }\\n        requestedRates {\\n          ratePlanCode\\n          ratePlanName\\n          roomCount\\n        }\\n      }\\n      notifications {\\n        subText\\n        subType\\n        title\\n        text\\n      }\\n      addOnsAvailable\\n      currencyCode\\n      roomTypes(sort: \$roomTypeSortInput) {\\n        roomTypeCode\\n        adaAccessibleRoom\\n        numBeds\\n        roomTypeName\\n        roomTypeDesc\\n        roomOccupancy\\n        executive\\n        suite\\n        code: roomTypeCode\\n        name: roomTypeName\\n        adjoiningRoom\\n        thumbnail: carousel(first: 1) {\\n          _id\\n          altText\\n          variants {\\n            size\\n            url\\n          }\\n        }\\n        quickBookRate {\\n          cashRatePlan\\n          roomTypeCode\\n          rateAmount\\n          rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n          rateChangeIndicator\\n          feeTransparencyIndicator\\n          cmaTotalPriceIndicator\\n          ratePlanCode\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          roomTypeCode\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          ratePlan {\\n            commissionable\\n            confidentialRates\\n            ratePlanName\\n            specialRateType\\n            hhonorsMembershipRequired\\n            redemptionType\\n            serviceChargesAndTaxesIncluded\\n          }\\n          serviceChargeDetails\\n          pointDetails(perNight: true) {\\n            pointsRate\\n            pointsRateFmt\\n          }\\n        }\\n        moreRatesFromRate {\\n          rateChangeIndicator\\n          feeTransparencyIndicator\\n          cmaTotalPriceIndicator\\n          roomTypeCode\\n          rateAmount\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          serviceChargeDetails\\n          ratePlanCode\\n          ratePlan {\\n            confidentialRates\\n            serviceChargesAndTaxesIncluded\\n          }\\n        }\\n        bookNowRate {\\n          roomTypeCode\\n          rateAmount\\n          rateChangeIndicator\\n          feeTransparencyIndicator\\n          cmaTotalPriceIndicator\\n          ratePlanCode\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          roomTypeCode\\n          ratePlan {\\n            commissionable\\n            confidentialRates\\n            ratePlanName\\n            specialRateType\\n            hhonorsMembershipRequired\\n            disclaimer {\\n              diamond48\\n            }\\n            serviceChargesAndTaxesIncluded\\n          }\\n          serviceChargeDetails\\n        }\\n        redemptionRoomRates(first: 1) {\\n          rateChangeIndicator\\n          pointDetails(perNight: true) {\\n            pointsRate\\n            pointsRateFmt\\n          }\\n          sufficientPoints\\n          pamEligibleRoomRate {\\n            ratePlan {\\n              ratePlanCode\\n              rateCategoryToken\\n              redemptionType\\n            }\\n            roomTypeCode\\n            sufficientPoints\\n          }\\n        }\\n      }\\n      lowestPointsInc\\n    }\\n  }\\n}',
                    'operationName': 'hotel_shopAvailOptions_shopPropAvail',
                    'variables': {
                        'guestLocationCountry': '$this->country',
                        'arrivalDate': '$checkInStr',
                        'departureDate': '$checkOutStr',
                        'numAdults': $fields[Adults],
                        'numChildren': $fields[Kids],
                        'numRooms': $fields[Rooms],
                        'displayCurrency': null,
                        'ctyhocn': '$smallHotelId',
                        'language': 'en',
                        'guestId': null,
                        'specialRates': {
                            'aaa': false,
                            'aarp': false,
                            'governmentMilitary': false,
                            'hhonors': true,
                            'pnd': '',
                            'senior': false,
                            'teamMember': false,
                            'owner': false,
                            'ownerHGV': false,
                            'familyAndFriends': false,
                            'travelAgent': false,
                            'smb': false,
                            'specialOffer': false,
                            'specialOfferName': null
                        },
                        'pnd': null,
                        'cacheId': 'fb3231c2-4eb0-401b-99d4-1a3a692c2517',
                        'offerId': null,
                        'knownGuest': false,
                        'modifyingReservation': false,
                        'currentlySelectedRoomTypeCode': null,
                        'currentlySelectedRatePlanCode': null,
                        'childAges': null,
                        'adjoiningRoomStay': false,
                        'roomTypeSortInput': []
                    }
                }
            
                let response = await fetchWithTimeout('https://www.hilton.com/graphql/customer?appName=dx-res-ui&operationName=hotel_shopAvailOptions_shopPropAvail&originalOpName=getShopAvail&bl=en&ctyhocn=$smallHotelId', {
                    'headers': {
                        'Accept': '*/*',
                        'Authorization': '$this->token',
                        'Content-Type': 'application/json',
                        'Referrer': 'https://www.hilton.com/en/book/reservation/rooms/',
                    },
                    'body': JSON.stringify(payload),
                    'method': 'POST',
                });
            
                let result = await response.json();
                return JSON.stringify(result);
            }

            return getHotelRoomsInfo();
        ";

        $this->logger->debug("Execute script:");
        $this->logger->debug($script, ['pre' => true]);

        // Выполняем скрипт
        $jsonStr = $this->driver->executeScript($script);

        return $this->http->JsonLog($jsonStr, 1, true)['data']['hotel']['shopAvail']['roomTypes'];
    }

    private function getRoomRatesInfo(string $hotelId, string $roomTypeCode, array $fields, string $checkInStr, string $checkOutStr): array
    {
        $this->logger->notice(__METHOD__); // Выводим название метода.

        $smallHotelId = substr($hotelId, 0, 5);

        $script = "
            function fetchWithTimeout(url, options, timeout = 10000) {
                const controller = new AbortController();
                const id = setTimeout(() => controller.abort(), timeout);
                return fetch(url, {...options, signal: controller.signal})
                    .finally(() => clearTimeout(id));
            };
            
            // функция для получения ценовой информации для списка отелей
            async function getRoomRatesInfo() {
                let payload = {
                    'query': 'query hotel_shopAvailOptions_shopPropAvail(\$arrivalDate: String!, \$ctyhocn: String!, \$departureDate: String!, \$language: String!, \$guestLocationCountry: String, \$numAdults: Int!, \$numChildren: Int!, \$numRooms: Int!, \$displayCurrency: String, \$guestId: BigInt, \$specialRates: ShopSpecialRateInput, \$rateCategoryTokens: [String], \$selectedRoomRateCodes: [ShopRoomRateCodeInput!], \$ratePlanCodes: [String], \$pnd: String, \$offerId: BigInt, \$cacheId: String!, \$knownGuest: Boolean, \$selectedRoomTypeCode: String, \$childAges: [Int], \$adjoiningRoomStay: Boolean, \$modifyingReservation: Boolean, \$programAccountId: BigInt) {\\n  hotel(ctyhocn: \$ctyhocn, language: \$language) {\\n    ctyhocn\\n    shopAvailOptions(input: {offerId: \$offerId, pnd: \$pnd}) {\\n      maxNumChildren\\n      altCorporateAccount {\\n        corporateId\\n        name\\n      }\\n      contentOffer {\\n        name\\n      }\\n    }\\n    shopAvail(\\n      cacheId: \$cacheId\\n      input: {guestLocationCountry: \$guestLocationCountry, arrivalDate: \$arrivalDate, departureDate: \$departureDate, displayCurrency: \$displayCurrency, numAdults: \$numAdults, numChildren: \$numChildren, numRooms: \$numRooms, guestId: \$guestId, specialRates: \$specialRates, rateCategoryTokens: \$rateCategoryTokens, selectedRoomRateCodes: \$selectedRoomRateCodes, ratePlanCodes: \$ratePlanCodes, knownGuest: \$knownGuest, childAges: \$childAges, adjoiningRoomStay: \$adjoiningRoomStay, modifyingReservation: \$modifyingReservation, programAccountId: \$programAccountId}\\n    ) {\\n      statusCode\\n      addOnsAvailable\\n      summary {\\n        specialRates {\\n          specialRateType\\n          roomCount\\n        }\\n        requestedRates {\\n          ratePlanCode\\n          ratePlanName\\n          roomCount\\n        }\\n      }\\n      notifications {\\n        subText\\n        subType\\n        title\\n        text\\n      }\\n      currencyCode\\n      roomTypes(filter: {roomTypeCode: \$selectedRoomTypeCode}) {\\n        roomTypeCode\\n        adaAccessibleRoom\\n        adjoiningRoom\\n        numBeds\\n        roomTypeName\\n        roomTypeDesc\\n        roomOccupancy\\n        executive\\n        suite\\n        code: roomTypeCode\\n        name: roomTypeName\\n        thumbnail: carousel(first: 1) {\\n          _id\\n          altText\\n          variants {\\n            size\\n            url\\n          }\\n        }\\n        quickBookRate {\\n          ratePlan {\\n            specialRateType\\n            serviceChargesAndTaxesIncluded\\n          }\\n        }\\n        roomOnlyRates {\\n          roomTypeCode\\n          ratePlanCode\\n          rateAmount\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          rateChangeIndicator\\n          feeTransparencyIndicator\\n          cmaTotalPriceIndicator\\n          guarantee {\\n            guarPolicyCode\\n            cxlPolicyCode\\n          }\\n          ratePlan {\\n            attributes\\n            commissionable\\n            confidentialRates\\n            ratePlanName\\n            ratePlanDesc\\n            ratePlanCode\\n            hhonorsMembershipRequired\\n            advancePurchase\\n            serviceChargesAndTaxesIncluded\\n          }\\n          hhonorsDiscountRate {\\n            rateChangeIndicator\\n            ratePlanCode\\n            roomTypeCode\\n            rateAmount\\n            rateAmountFmt(decimal: 0, strategy: ceiling)\\n            rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n            amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n            fullAmountAfterTax: amountAfterTaxFmt\\n            guarantee {\\n              guarPolicyCode\\n              cxlPolicyCode\\n            }\\n            ratePlan {\\n              attributes\\n              commissionable\\n              confidentialRates\\n              ratePlanName\\n              ratePlanDesc\\n              ratePlanCode\\n              advancePurchase\\n              serviceChargesAndTaxesIncluded\\n            }\\n          }\\n          serviceChargeDesc: serviceChargeDetails\\n        }\\n        requestedRoomRates {\\n          ratePlanCode\\n          rateAmount\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          rateChangeIndicator\\n          feeTransparencyIndicator\\n          cmaTotalPriceIndicator\\n          ratePlan {\\n            attributes\\n            commissionable\\n            confidentialRates\\n            ratePlanName\\n            ratePlanDesc\\n            hhonorsMembershipRequired\\n            serviceChargesAndTaxesIncluded\\n          }\\n          serviceChargeDesc: serviceChargeDetails\\n        }\\n        specialRoomRates {\\n          ratePlanCode\\n          rateAmount\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          rateChangeIndicator\\n          feeTransparencyIndicator\\n          cmaTotalPriceIndicator\\n          ratePlan {\\n            attributes\\n            commissionable\\n            confidentialRates\\n            ratePlanName\\n            ratePlanDesc\\n            hhonorsMembershipRequired\\n            serviceChargesAndTaxesIncluded\\n          }\\n          serviceChargeDesc: serviceChargeDetails\\n        }\\n        packageRates {\\n          roomTypeCode\\n          rateAmount\\n          rateAmountFmt(decimal: 0, strategy: ceiling)\\n          rateAmountUSD: rateAmount(currencyCode: \"USD\")\\n          amountAfterTaxFmt(decimal: 0, strategy: ceiling)\\n          fullAmountAfterTax: amountAfterTaxFmt\\n          rateChangeIndicator\\n          feeTransparencyIndicator\\n          cmaTotalPriceIndicator\\n          ratePlanCode\\n          ratePlan {\\n            attributes\\n            commissionable\\n            confidentialRates\\n            ratePlanName\\n            ratePlanDesc\\n            ratePlanCode\\n            hhonorsMembershipRequired\\n            serviceChargesAndTaxesIncluded\\n          }\\n          guarantee {\\n            guarPolicyCode\\n            cxlPolicyCode\\n          }\\n          serviceChargeDesc: serviceChargeDetails\\n        }\\n        redemptionRoomRates(first: 1) {\\n          cashRatePlan\\n          rateChangeIndicator\\n          pointDetails(perNight: true) {\\n            effectiveDateFmt(format: \"medium\", language: \$language)\\n            effectiveDateFmtAda: effectiveDateFmt(format: \"long\", language: \$language)\\n            pointsRate\\n            pointsRateFmt\\n          }\\n          sufficientPoints\\n          pamEligibleRoomRate {\\n            ratePlan {\\n              ratePlanCode\\n              rateCategoryToken\\n            }\\n            roomTypeCode\\n          }\\n          roomTypeCode\\n          ratePlan {\\n            ratePlanDesc\\n            ratePlanName\\n            redemptionType\\n          }\\n          ratePlanCode\\n          totalCostPoints\\n          totalCostPointsFmt\\n        }\\n      }\\n      lowestPointsInc\\n    }\\n  }\\n}',
                    'operationName': 'hotel_shopAvailOptions_shopPropAvail',
                    'variables': {
                        'guestLocationCountry': '$this->country',
                        'arrivalDate': '$checkInStr',
                        'departureDate': '$checkOutStr',
                        'numAdults': $fields[Adults],
                        'numChildren': $fields[Kids],
                        'numRooms': $fields[Rooms],
                        'displayCurrency': null,
                        'ctyhocn': '$smallHotelId',
                        'language': 'en',
                        'guestId': null,
                        'specialRates': {
                            'aaa': false,
                            'aarp': false,
                            'governmentMilitary': false,
                            'hhonors': true,
                            'pnd': '',
                            'senior': false,
                            'teamMember': false,
                            'owner': false,
                            'ownerHGV': false,
                            'familyAndFriends': false,
                            'travelAgent': false,
                            'smb': false,
                            'specialOffer': false,
                            'specialOfferName': null
                        },
                        'pnd': null,
                        'cacheId': '7c94a02e-6700-438e-8cc7-53c41219178b',
                        'offerId': null,
                        'knownGuest': false,
                        'modifyingReservation': false,
                        'currentlySelectedRoomTypeCode': null,
                        'currentlySelectedRatePlanCode': null,
                        'childAges': null,
                        'adjoiningRoomStay': false,
                        'selectedRoomTypeCode': '$roomTypeCode'
                    }
                }
            
                let response = await fetchWithTimeout('https://www.hilton.com/graphql/customer?appName=dx-res-ui&operationName=hotel_shopAvailOptions_shopPropAvail&originalOpName=getRoomRates&bl=en&ctyhocn=$smallHotelId', {
                    'headers': {
                        'Accept': '*/*',
                        'Authorization': '$this->token',
                        'Content-Type': 'application/json',
                        'Referrer': 'https://www.hilton.com/en/book/reservation/rates/',
                    },
                    'body': JSON.stringify(payload),
                    'method': 'POST',
                });
            
                let result = await response.json();
                return JSON.stringify(result);
            }

            return getRoomRatesInfo();
        ";

        $this->logger->debug("Execute script:");
        $this->logger->debug($script, ['pre' => true]);

        // Выполняем скрипт
        $jsonStr = $this->driver->executeScript($script);

        return $this->http->JsonLog($jsonStr, 1, true)['data']['hotel']['shopAvail']['roomTypes'][0]['redemptionRoomRates'];
    }

    private function parseRoomsAndRatesForHotel(string $hotelId, $fields, $checkInStr, $checkOutStr): array
    {
        $this->logger->notice(__METHOD__); // Выводим название метода.

        $hotelRoomsInfo = $this->getHotelRoomsInfo($hotelId, $fields, $checkInStr, $checkOutStr);
        $rooms = [];

        foreach ($hotelRoomsInfo as $hotelRoomInfo) {
            $room = [
                'type' => $hotelRoomInfo['suite'] === true ? 'suite' : 'room',
                'name' => $hotelRoomInfo['roomTypeName'],
                'description' => $hotelRoomInfo['roomTypeDesc'],
            ];

            $roomRatesInfo = $this->getRoomRatesInfo($hotelId, $hotelRoomInfo['roomTypeCode'], $fields, $checkInStr, $checkOutStr);
            $rates = [];

            foreach ($roomRatesInfo as $roomRateInfo) {
                $rate = [
                    'name' => $roomRateInfo['ratePlan']['ratePlanName'],
                    'description' => $roomRateInfo['ratePlan']['ratePlanDesc'],
                    'pointsPerNight' => round($roomRateInfo['totalCostPoints'] / count($roomRateInfo['pointDetails']), 2),
                    'cashPerNight' => null,
                ];

                $rates[] = $rate;
            }



            $room['rates'] = $rates;
            $rooms[] = $room;
        }

        return $rooms;
    }

    private function getBase64FromImageUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $file = $this->http->DownloadFile($url);
        $imageSize = getimagesize($file);
        $imageData = base64_encode(file_get_contents($file));

        if (!empty($imageSize)) {
            $this->logger->debug("<img src='data:{$imageSize['mime']};base64,{$imageData}' {$imageSize[3]} />",
                ['HtmlEncode' => false]);
        }

        return $imageData;
    }
}