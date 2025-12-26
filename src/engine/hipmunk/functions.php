<?php
class TAccountCheckerHipmunk extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.hipmunk.com/");

        $this->http->Form = [];
        $this->http->setDefaultHeader("X-Csrf-Token", "null");
        $this->http->setDefaultHeader("X-Hipmunk-API", "true");
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->FormURL = 'https://www.hipmunk.com/api/batch_track';
        $this->http->SetFormText("user_id=5502b2656d32a836a400000c&refresh_id=r5502bbfa549f4e3a810000fb&visit_id=5502bbfa549f4e3a810000f5&batched_events=%5B%7B%22event%22%3A%22leave%22%2C%22data%22%3A%22%7B%5C%22prev_event%5C%22%3A%5C%22front_forms_expanded%5C%22%2C%5C%22prev_category%5C%22%3A%5C%22flight-search%5C%22%2C%5C%22time_since_prev_event%5C%22%3A18586%2C%5C%22category%5C%22%3A%5C%22%5C%22%7D%22%7D%5D&batched_completed=%5B%5D&cookies_enabled=true&revision=2.2&variant=default", "&");

        if (!$this->http->PostForm()) {
            return false;
        }

        $csrf = $this->http->getCookieByName("_csrf_token", "www.hipmunk.com");

        if (!isset($csrf)) {
            return false;
        }
        $this->http->setDefaultHeader("X-Csrf-Token", $csrf);
        $this->http->Form = [];
        $this->http->FormURL = 'https://www.hipmunk.com/api/login';
        $this->http->SetFormText("email={$this->AccountFields['Login']}&password={$this->AccountFields['Pass']}&country=US&language=en&revision
=2.2&variant=default", "&");

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return false;
        }
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (isset($response->session)) {
            return true;
        }
        // That password is incorrect.
        if (isset($response->errors[0]->msg)) {
            throw new CheckException($response->errors[0]->msg, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->PostURL("https://www.hipmunk.com/api/user", ["location" => 'https://www.hipmunk.com/', "web_referer" => ""]);
        $response = $this->http->JsonLog();
        // Name
        if (isset($response->user->first_name, $response->user->last_name)) {
            $this->SetProperty("Name", beautifulName("{$response->user->first_name} {$response->user->last_name}"));
        }

        if (!empty($this->Properties['Name']) || isset($response->user->email)) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $result = [];

        $this->http->GetURL("https://www.hipmunk.com/api/bookings/?revision=2.1&variant=default&_=" . time() . date("B"));
        $response = $this->http->JsonLog(null, false);

        if (!isset($response->bookings)) {
            return $result;
        }
        $this->http->Log("Total " . count($response->bookings) . " bookings were found");

        foreach ($response->bookings as $booking) {
            if (isset($booking->check_out, $booking->id) && strtotime($booking->check_out) >= time()) {
                $this->http->GetURL("https://www.hipmunk.com/hotel_receipt/{$booking->id}");
                $result[] = $this->ParseItinerary();
            } else {
                $this->http->Log("Skip old reservation {$booking->booking_code_text}");
            }
        }// foreach ($response->bookings as $booking)

        return $result;
    }

    private function ParseItinerary()
    {
        $result = [];
        $result['Kind'] = "R";
        // ConfirmationNumber
        $result['ConfirmationNumber'] = $this->http->FindPreg("/Booking Confirmation:\s*#\s*(\d+)/ims");
        // ReservationDate
        $result['ReservationDate'] = strtotime($this->http->FindPreg("/Booking Created:([\sa-z\-\d]+\,\s*\d{4})/ims"));
        // TripNumber
        $result['TripNumber'] = $this->http->FindSingleNode("//h2[contains(text(), 'Itinerary Number')]/span", null, true, '/#\s*(\d+)/ims');
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode("//h1[@class = 'hotel-header-bar__name']");
        // Address
        $result['Address'] = $this->http->FindSingleNode("(//div[@class = 'hotel-header-bar__content']/div[@class = 'info-row'])[1]");
        // Phone
        $result['Phone'] = $this->http->FindSingleNode("//div[@class = 'customer-service-info']/b");
        // CheckInDate
        $result['CheckInDate'] = strtotime($this->http->FindSingleNode("//b[contains(text(), 'Check In:')]/following-sibling::node()[1]", null, true, "/\,\s*(.+)/ims"));
        // CheckOutDate
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode("//b[contains(text(), 'Check Out:')]/following-sibling::node()[1]", null, true, "/\,\s*(.+)/ims"));
        // Rate
        $result['Rate'] = $this->http->FindPreg("/([\d\,\.\s]+) per night/");
        // RoomType
        $result['RoomType'] = $this->http->FindSingleNode("//tr[td[@class = 'subdetail']]/preceding-sibling::tr[1]/td[1]");
        // Guests
        $result['Guests'] = $this->http->FindSingleNode("//td[@class = 'subdetail']", null, true, "/(\d)\s*adult/ims");
        // Kids
        $result['Kids'] = $this->http->FindSingleNode("//td[@class = 'subdetail']", null, true, "/(\d)\s*(?:kid|child)/ims");
        // Rooms
        $result['Rooms'] = $this->http->FindSingleNode("//td[@class = 'subdetail']", null, true, "/(\d)\s*room/ims");
        // Taxes
        $result['Taxes'] = $this->http->FindSingleNode("//td[contains(text(), 'Tax')]/following-sibling::td[@class = 'price']");
        // Cost
        $result['Cost'] = $this->http->FindSingleNode("//td[contains(., 'room')]/following-sibling::td[@class = 'price']/text()[last()]");
        // Total
        $result['Total'] = $this->http->FindSingleNode("//td[b[contains(text(), 'Total in')]]/following-sibling::td[@class = 'price']/text()[last()]");
        // Currency
        $result['Currency'] = $this->http->FindSingleNode("//b[contains(text(), 'Total in')]", null, true, "/in\s*([^<]+)/ims");
        // CancellationPolicy
        $result['CancellationPolicy'] = $this->http->FindSingleNode("//h3[contains(text(), 'Cancellation Policy')]/following-sibling::p[1]");

        return $result;
    }
}
