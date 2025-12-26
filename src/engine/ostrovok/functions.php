<?php

class TAccountCheckerOstrovok extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://ostrovok.ru/orders/';

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn(): bool
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        return !is_null($this->http->FindPreg("/user_is_authorized\":true,\"user_email\":\"{$this->AccountFields['Login']}/i"));
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://ostrovok.ru/accounts/login/');
        $this->http->FormURL = 'https://ostrovok.ru/api/v3/site/accounts/login/';
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("csrfmiddlewaretoken", $this->http->getCookieByName("csrftoken"));
        $this->http->SetInputValue("next", self::REWARDS_PAGE_URL);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://ostrovok.ru/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $status = $this->http->JsonLog()->status ?? null;

        if ($status === 'ok') {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Error message')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/Неверный email или пароль/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/incorrect_password_or_username/")) {
            throw new CheckException('Неверный email или пароль', ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $settings = $this->http->JsonLog($this->http->FindPreg("/var settings\s*=\s*(.+);\s*appInstall\(settings\)\;/"), 3, false, 'fidelity_user_info');
        $entries = $settings->options->fidelity_user_info->data->user_data->entries ?? [];
        $accountType = $settings->options->fidelity_user_info->data->default_fidelity_account_type ?? null;

        if (!$accountType || empty($entries)) {
            $this->logger->notice("this type of account not found: {$accountType}");

            $status = $settings->options->fidelity_user_info->status ?? null;
            $status_v2 = $settings->options->fidelity_user_info_v2->status ?? null;
            $data_v2 = $settings->options->fidelity_user_info_v2->status->data ?? null;

            if ($status == 'disabled'
                && (
                    ($status_v2 == 'disabled' && $data_v2 === [])
                    || (empty($status_v2) && empty($data_v2))
                )
            ) {
                $this->logger->notice("no balance found on this account");
                $this->SetBalanceNA();
            } else {
                return;
            }
        }

        if (count($entries) > 2) {
            $this->sendNotification("ostrovok - need to check Balance");
        }

        foreach ($entries as $entry) {
            if ($entry->name != $accountType) {
                continue;
            }
            // Balance - On account ... Dreams
            $this->SetBalance($entry->amount);

            break;
        }

        $this->http->GetURL("https://ostrovok.ru/api/site/settings/person.json");
        $response = $this->http->JsonLog();
        // Name
        if (isset($response->first_name, $response->last_name)) {
            $this->SetProperty("Name", beautifulName("$response->first_name $response->last_name"));
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->setDefaultHeader("X-User-Mail", $this->AccountFields['Login']);

        $this->http->GetURL("https://ostrovok.ru/api/site/account/orders.json?type=paid");

        $response = $this->http->JsonLog(null, 3, true);
        $orders = ArrayVal($response, 'orders', []);

        $this->logger->debug("Total " . count($orders) . " itineraries found");

        foreach ($orders as $order) {
            $this->ParseItinerary($order);
        }

        return $result;
    }

    public function ParseItinerary($order)
    {
        $this->logger->notice(__METHOD__);
        $confNo = ArrayVal($order, 'external_id');
        $this->logger->info(sprintf('Parse Itinerary #%s', $confNo), ['Header' => 3]);

        $vhotel = ArrayVal($order, 'vhotel');
        $rateRequestParams = ArrayVal($order, 'rate_request_params');
        $checkOut = strtotime($vhotel['check_out_time'], strtotime($rateRequestParams['departure_date']));
        // Skip old itinerary
        if (!$this->ParsePastIts && isset($checkOut) && $checkOut < strtotime("-2 day")) {
            $this->logger->notice("Skip old itinerary");

            return;
        }

        $h = $this->itinerariesMaster->add()->hotel();

        if (isset($order['rate']['price_data'])) {
            $priceData = $order['rate']['price_data'];
            $h->price()
                ->total(round(ArrayVal($priceData, 'price'), 2))
                ->currency(ArrayVal($priceData, 'currency_code'));
        }// if (isset($order['rate']['price_data']))

        // Guests
        $guests = [];

        foreach (ArrayVal($order, 'guests', []) as $g) {
            $guests[] = sprintf('%s %s', ArrayVal($g, 'first_name'), ArrayVal($g, 'last_name'));
        }
        $travelers = array_filter(array_map(function ($item) {
            return beautifulName($item);
        }, $guests));

        $h->general()
            ->confirmation($confNo)
            ->travellers($travelers, true);

        $h->hotel()
            ->name(!empty($vhotel['name_en']) ? $vhotel['name_en'] : $vhotel['name'])
            ->address(!empty($vhotel['address_en']) ? $vhotel['address_en'] : $vhotel['address'])
            ->phone(str_replace('‒', '-', $vhotel['phone'] ?? null), false, true);

        $checkIn = strtotime($vhotel['check_in_time'], strtotime($rateRequestParams['arrival_date']));

        $h->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut);

        $deadline = $order['rate']['cancellation_info']['free_cancellation_before'] ?? null;

        if ($deadline) {
            $this->logger->debug($deadline);
            $deadline = strtotime($deadline);
            $deadline -= $deadline % 60;
            $h->booked()->deadline($deadline);
        }
//        if ($this->http->FindSingleNode('(//div[normalize-space(text()) = "Cancellation policy"]/following-sibling::div[1])[1]/div[contains(text(), "Non-refundable reservation")]', null, false)) {
//            $h->booked()->nonRefundable();
//        }

        if (isset($order['rate']['rate_name_orig']) || isset($order['rate']['trans']['ru']['room_name']) || isset($hotel['amenity_groups'][0]['amenities'])) {
            $r = $h->addRoom();
            $r->setRateType($order['rate']['rate_name_orig'] ?? null, false, true);
            $r->setType($order['rate']['trans']['ru']['room_name'] ?? null, false, true);
            // RoomTypeDescription
            $description = '';

            if (isset($hotel['amenity_groups'][0]['amenities'])) {
                $general = $hotel['amenity_groups'][0]['amenities'];

                foreach ($general as $amen) {
                    $description = sprintf('%s %s', $description, $amen);
                }

                $r->setDescription($description);
            }// if (isset($hotel['amenity_groups'][0]['amenities']))
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }
}
