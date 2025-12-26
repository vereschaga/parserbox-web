<?php

namespace AwardWallet\Engine\hainan\RewardAvailability;

use AwardWallet\Engine\ProxyList;

class ParserSel extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    private $memCookies;

    public function IsLoggedIn()
    {
        return true;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromium(\SeleniumFinderRequest::CHROMIUM_80);
        $this->disableImages();
        $this->http->setHttp2(true);
//        $this->setProxyBrightData(null, 'static', 'cn');
        $resolutions = [
            //            [1152, 864],
            //            [1280, 720],
            //            [1280, 768],
            //            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($chosenResolution);
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->logger->error("you can check max 9 travellers");

            return [];
        }

        $this->http->GetURL("https://www.hainanairlines.com/US/US/Home");

        $btnSF = $this->waitForElement(\WebDriverBy::xpath("//button[contains(normalize-space(),'Search flights')]"));

        if (!$btnSF) {
            $this->saveResponse();

            return [];
        }
        $btnSF->click();
        $span = $this->waitForElement(\WebDriverBy::xpath("//span/label[contains(normalize-space(),'Redeem your award flight(s)')]"),
            0);

        if (!$span) {
            $this->saveResponse();

            return [];
        }
        $span->click();
        $dep = $this->waitForElement(\WebDriverBy::id('filght-search-from-AWARD'), 0);
        $arr = $this->waitForElement(\WebDriverBy::id('filght-search-to-AWARD'), 0);

        if (!$dep || !$arr) {
            $this->saveResponse();

            return [];
        }
//        $btnSF->click();

        $span = $this->waitForElement(\WebDriverBy::xpath("//form[contains(@class,'flight-form-srch-AWARD')]//span/label[contains(normalize-space(),'One Way')]"),
            2);

        if (!$span) {
            $this->saveResponse();

            return [];
        }
        $span->click();

        $dep = $this->waitForElement(\WebDriverBy::id('filght-search-from-AWARD'), 0);
        $dep->click();
        $dep->clear();
        $dep->sendKeys($fields['DepCode']);
        $this->driver->executeScript("$('#filght-search-from-AWARD').parent('div').parent('div').nextAll('ul').find('li:contains(\"({$fields['DepCode']})\")').click()");
        $this->depName = $this->driver->executeScript("return $('#filght-search-from-AWARD').val();");
        sleep(1);
        $arr->click();
        $arr->clear();
        $arr->sendKeys($fields['ArrCode']);
        $this->driver->executeScript("$('#filght-search-to-AWARD').parent('div').parent('div').nextAll('ul').find('li:contains(\"({$fields['ArrCode']})\")').click()");
        $this->arrName = $this->driver->executeScript("return $('#filght-search-to-AWARD').val();");
        sleep(1);

        $d1 = date('m/d/Y', $fields['DepDate']);
        $d2 = date('Ymd0000', $fields['DepDate']);
        $cabin = in_array($fields['Cabin'], ['economy', 'premiumEconomy']) ? 'E' : 'B';
        $cabinName = in_array($fields['Cabin'], ['economy', 'premiumEconomy']) ? 'Economy' : 'Business';

        $dateField = $this->waitForElement(\WebDriverBy::id('filght-search-departure-date-AWARD'));

        if (!$dateField) {
            $this->saveResponse();

            return [];
        }

        $dateField->click();
        $this->driver->executeScript("
        form =$('form.flight-form-srch-AWARD'); 
        form.find('#filght-search-departure-date-AWARD').val('{$d1}'); 
        
        form.find('#filght-search-departure-date-AWARD').next('input').val('{$d2}');
        ");
        $this->saveResponse();

        $cabinField = $this->waitForElement(\WebDriverBy::xpath('//form[contains(@class,"flight-form-srch-AWARD")]//button[contains(@class,"pax-cabin-select-dropdown-btn")]'));

        if (!$cabinField) {
            $this->saveResponse();

            return [];
        }

        $cabinField->click();

        $this->driver->executeScript("$('#filght-search-cabin-class-AWARDSelectBoxItContainer>span').click();");

        $cabinField = $this->waitForElement(\WebDriverBy::xpath("//a[normalize-space()='{$cabinName}']"));

        if (!$cabinField) {
            $this->saveResponse();

            return [];
        }
        $checked = (int) ($this->waitForElement(\WebDriverBy::id("search-pax-adt-num-AWARD"))->getText());

        if ($checked !== $fields['Adults']) {
            $btnAdtMin = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-controls='search-pax-adt-num-AWARD'][normalize-space()='-']"));

            while ($checked !== 1) {
                $btnAdtMin->click();
                $checked--;
            }

            if ($fields['Adults'] > 1) {
                $btnAdtPlus = $this->waitForElement(\WebDriverBy::xpath("//button[@aria-controls='search-pax-adt-num-AWARD'][normalize-space()='+']"));

                while ($checked !== $fields['Adults']) {
                    $btnAdtPlus->click();
                    $checked++;
                }
            }
        }

//        sleep(1);
//        $this->driver->executeScript("
//        form.find('select[name=\"CABIN\"').val('{$cabin}');
//        form.find('input[name=\"NB_ADT\"').val('{$fields['Adults']}');
//        ");

        $cabinClode = $this->waitForElement(\WebDriverBy::xpath('//form[contains(@class,"flight-form-srch-AWARD")]//div[contains(@class,"pax-cabin-select-row")]//button[normalize-space()="Close"]'));

        if ($cabinClode) {
            $cabinClode->click();
        }

        $this->saveResponse();
        $this->logger->error('send submit');
        $this->driver->executeScript("
        form =$('form.flight-form-srch-AWARD');
        form.find('div.flight-search-btn-container>button[type=\"submit\"]').click();
        ");

//        $btn = $this->waitForElement(\WebDriverBy::xpath("//form[contains(@class,'flight-form-srch-AWARD')]//button[normalize-space()='Find']"),
//            2);
//        if (!$btn) {
//            return [];
//        }
//        $btn->click();

        sleep(100);

        return [];

//        sleep(5);
//        // save page to logs
//        $selenium->http->SaveResponse();
//
//        $this->selenium();
//
//        if (!$this->http->ParseForm(null, "//form[@data-form='AWARD']")) {
//            throw new \CheckException('page not load', ACCOUNT_ENGINE_ERROR);
//        }
//
//        $memForm = $this->http->Form;
//        $memFormURL = $this->http->FormURL;
//        $this->http->NormalizeURL($memFormURL);
//        $memFormURL = 'https://www.hainanairlines.com'.$memFormURL;
//
//        $this->logger->debug(var_export($memForm, true));
//        $this->logger->debug($memFormURL);
//
//        // try get airports info for requests
//        $http2 = clone $this->http;
//        $this->http->brotherBrowser($http2);
//
//        $http2->removeCookies();
//
//        foreach ($this->memCookies as $cookie) {
//            if (!in_array($cookie['name'], ['reese84', 'DWM_XSITECODE'])) {
//                $http2->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
//                    $cookie['expiry'] ?? null);
//            }
//        }
//
//        $headers = [
//            'Accept' => 'application/json, text/javascript, */*; q=0.01',
//            'Referer' => 'https://www.hainanairlines.com/US/US/Home',
//            'X-Requested-With' => 'XMLHttpRequest'
//        ];
//
//        $http2->GetURL("https://www.hainanairlines.com/HUPortal/dyn/portal/locationPicker/locationPickerCodeNameMap?SITE=CBHZCBHZ&LANGUAGE=US&PAGE=HOME&COUNTRY_SITE=US", $headers);
//        $dataNames = $http2->JsonLog(null, 1, true);
//
//        $http2->GetURL("https://www.hainanairlines.com/HUPortal/dyn/portal/locationPicker/customizedRegionCodeMap?SITE=CBHZCBHZ&LANGUAGE=US&PAGE=HOME&COUNTRY_SITE=US&CODE=WORLD", $headers);
//        $dataWorld = $http2->JsonLog(null, 1, true);
//
//        $http2->GetURL("https://www.hainanairlines.com/HUPortal/dyn/portal/locationPicker/customizedRegionCodeMap?SITE=CBHZCBHZ&LANGUAGE=US&PAGE=HOME&COUNTRY_SITE=US&CODE=AWARDREG", $headers);
//        $dataAward = $http2->JsonLog(null, 1, true);
//
//        $headers = [
//            'Accept' => '*/*',
//            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
//            'Referer' => 'https://www.hainanairlines.com/US/US/Home'
//        ];
//        $postData = "COUNTRY_SITE=US&LANGUAGE=US&SITE=CBHZCBHZ&PAGE=FSIP&FSIP_HAS_CHD_INF=false&FSIP_SOURCE_COUNTRY_CODES=CN&FSIP_DESTINATION_COUNTRY_CODES=CN&FSIP_TRIP_TYPE=O";
//        $http2->PostURL("https://www.hainanairlines.com/HUPortal/dyn/portal/flightSearchIntermediatePage",
//            $postData, $headers);
//
//        $data = $http2->JsonLog();
//
//        $this->http->Form = $memForm;
//        $this->http->FormURL = $memFormURL;
//        foreach ($this->memCookies as $cookie) {
//            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
//                $cookie['expiry'] ?? null);
//        }
//
//                if (isset($this->http->Form['departureLoc'])){
//                    $this->logger->debug('set: departureLoc');
//                    $this->http->Form['departureLoc'] = 'Capital International(PEK),Beijing,China';
//                } else return [];
//
//                if (isset($this->http->Form['arrivalLoc'])){
//                    $this->logger->debug('set: arrivalLoc');
//                    $this->http->Form['arrivalLoc'] = 'Guangzhou(CAN),China';
//                } else return [];
//
//                if (isset($this->http->Form['B_LOCATION_1'])){
//                    $this->logger->debug('set: B_LOCATION_1');
//                    $this->http->Form['B_LOCATION_1'] = 'PEK';
//                } else return [];
//
//                if (isset($this->http->Form['E_LOCATION_1'])){
//                    $this->logger->debug('set: E_LOCATION_1');
//                    $this->http->Form['E_LOCATION_1'] = 'CAN';
//                } else return [];
//
//                if (isset($this->http->Form['TRIP_TYPE'])){
//                    $this->logger->debug('set:TRIP_TYPE');
//                    $this->http->Form['TRIP_TYPE'] = 'O';
//                } else return [];
//
//                if (isset($this->http->Form['CABIN'])){
//                    $this->logger->debug('set:CABIN');
//                    $this->http->Form['CABIN'] = in_array($fields['Cabin'], ['economy', 'premiumEconomy']) ? 'E' : 'B';
//                } else return [];
//
//                if (isset($this->http->Form['NB_ADT'])){
//                    $this->logger->debug('set:NB_ADT');
//                    $this->http->Form['NB_ADT'] = $fields['Adults'];
//                } else return [];
//
//                if (isset($this->http->Form['departure-date'])){
//                    $this->logger->debug('set: departure-date');
//                    $this->http->Form['departure-date'] = date('m/d/Y',$fields['DepDate']);
//                } else return [];
//
//                if (isset($this->http->Form['B_DATE_1'])){
//                    $this->logger->debug('set: B_DATE_1');
//                    $this->http->Form['B_DATE_1'] = date('Ymd0000',$fields['DepDate']);
//                } else return [];
//
//                if (isset($this->http->Form['arrival-date'])){
//                    unset($this->http->Form['arrival-date']);
//                }
//
//                if (isset($this->http->Form['NB_B15'])){
//                    unset($this->http->Form['NB_B15']);
//                }
//
//                if (isset($this->http->Form['DIRECT_NON_STOP'])){
//                    unset($this->http->Form['DIRECT_NON_STOP']);
//                }
//
//                $this->http->PostURL($memFormURL, $memForm);
//                if ($this->http->ParseForm(null, '//form[contains(@action,"booking/availability")]'))
//                    $this->http->PostForm();
//
        ////                if (isset($this->http->Form[''])){
        ////                    $this->logger->debug('set:');
        ////                    $this->http->Form[''] = ;
        ////                } else return [];
//
        ////        $this->selenium();
//

        return ["routes" => $this->parseRewardFlights($fields)];
    }

    private function parseRewardFlights($fields): array
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        return [];
    }

    private function sumLayovers($layover1, $layover2)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($layover1, $layover2)) {
            return $layover1 ?? $layover2;
        }
        $durArr1 = explode(':', $layover1);
        $durArr2 = explode(':', $layover2);

        if (count($durArr1) !== 2 || count($durArr2) !== 2) {
            $this->logger->error('check sumLayovers(' . var_export($layover1, true) . ',' . var_export($layover2,
                    true) . ')');

            return null;
        }

        $h = (int) $durArr1[0] + (int) $durArr2[0];
        $m = (int) $durArr1[1] + (int) $durArr2[1];
        [$hh, $m] = $this->separateTime($m);
        $h += $hh;
        $layover = null;

        if (array_sum([$h, $m]) > 0) {
            $layover = implode(":", [$h, str_pad($m, 2, "0", STR_PAD_LEFT)]);
        }
        $this->logger->debug("sumLayovers: " . $layover);

        return $layover;
    }

    private function separateTime(int $segment, ?int $delimiter = 60): array
    {
        $h = (int) ($segment / $delimiter);
        $m = $segment % $delimiter;

        return [$h, $m];
    }
}
