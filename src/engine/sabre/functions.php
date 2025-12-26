<?php

class TAccountCheckerSabre extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://virtuallythere.com/new/login.html");
        $this->http->Form = ["action" => "getUsernameAndPassword"];
        $this->http->FormURL = "https://virtuallythere.com/new/loginAjaxHandler.json";
        $this->http->PostForm();

        $this->http->FormURL = "https://virtuallythere.com/new/loginAjaxHandler.json";
        $this->http->Form = [];
        $this->http->Form["action"] = "login";
        $this->http->Form["userName"] = $this->AccountFields["Login"];
        $this->http->Form["password"] = $this->AccountFields["Pass"];
        $this->http->Form["rememberMe"] = "true";

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://virtuallythere.com/new/login.html';
        $arg['SuccessURL'] = 'https://virtuallythere.com/new/myTripsHome.html';

        return $arg;
    }

    public function Login()
    {
        $this->http->PostForm();
        $response = $this->http->JsonLog(null, true, true);

        foreach (["error", "passwordError", "usernameError"] as $key) {
            if ($response["result"][$key] != "") {
                if (!stristr($response["result"][$key], 'System Unavailable')) {
                    throw new CheckException(preg_replace("/\..+$/", "", $response["result"][$key]), ACCOUNT_INVALID_PASSWORD);
                } else {
                    throw new CheckException(preg_replace("/\..+$/", "", $response["result"][$key]), ACCOUNT_PROVIDER_ERROR);
                }
            }
        }

        if ($response["result"]["userName"] != "") {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://virtuallythere.com/new/myTripsHome.html?validate=false");
        $this->http->GetURL("https://www.virtuallythere.com/new/profileView.html");
        $this->SetProperty("Email", $this->http->FindSingleNode("//input[@id='login']/@value"));
        $fname = $this->http->FindSingleNode("//input[@name='firstName']/@value");
        $lname = $this->http->FindSingleNode("//input[@name='lastName']/@value");
        $mname = $this->http->FindSingleNode("//input[@name='middleName']/@value");

        if ($mname != "") {
            $mname .= " ";
        }
        $this->SetProperty("Name", beautifulName("$fname $mname$lname"));
        $this->SetBalanceNA();
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->GetURL("https://virtuallythere.com/new/myTripsHome.html?validate=false");
        $links = $this->http->FindNodes("//a[contains(@onclick, 'myTripsViewFuture')]/@onclick");

        if (count($links) == 0) {
            $this->http->Log('No Future Trips');

            return $result;
        }

        if ($this->http->FindSingleNode("//select[@id='languageCode']/option[@selected]/@value") !== '0') {
            $this->http->Log('Changing language');
            $this->http->FormURL = 'https://virtuallythere.com/new/profileView.html';
            $this->http->Form = [
                'changeAction' => 'changeLanguage',
                'languageCode' => '0',
            ];
            $this->http->setDefaultHeader('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
            $this->http->PostForm();
        }
        $this->http->GetURL("https://virtuallythere.com/new/myTripsHome.html?validate=false");
        $this->http->ParseForm('myTripsForm');
        $url = $this->http->FormURL ?? 'https://virtuallythere.com/new/myTripsHome.html';

        foreach ($links as $link) {
            if (preg_match("/myTripsViewFuture\(\'([^\']+)\'\,\'([^\']+)\'\)/", $link, $matches)) {
                $this->http->FormURL = $url;
                $this->http->Form = [
                    'action' => 'fetchItinerary',
                    'pnr'    => $matches[1],
                    'id'     => $matches[2],
                ];
            }// if (preg_match("/myTripsViewFuture\(\'([^\']+)\'\,\'([^\']+)\'\)/", $link, $matches))

            if ($this->http->PostForm()) {
                $it = $this->ParseItinerary();

                if (!empty($it)) {
                    $result = array_merge($result, $it);
                }
            }// if ($this->http->PostForm())
            //$this->http->GetURL("https://virtuallythere.com/new/myTripsHome.html?validate=false");
        }// foreach ($links as $link)

        return $result;
    }

    public function ParseItinerary()
    {
        $VTParser = new TVirtuallyThereParser($this->db);
        $arHotels = $VTParser->ParseHotels($this->http->XPath, $this->http);
        $arCars = $VTParser->ParseCars($this->http->XPath, $this->http);
        $arTrips = $VTParser->ParseTrips($this->http->XPath, $this->http);
        $VTParser->ParseNew($this->http, $arHotelsNew, $arCarsNew, $arTripsNew);
        $arHotels = array_merge($arHotels, $arHotelsNew);
        $arCars = array_merge($arCars, $arCarsNew);
        $arTrips = array_merge($arTrips, $arTripsNew);
        $result = [];

        foreach ($arCars as $idx => $it) {
            $arCars[$idx]["Kind"] = "L";
            $result[] = $arCars[$idx];
        }

        foreach ($arTrips as $idx => $it) {
            $arTrips[$idx]["Kind"] = "T";
            $result[] = $arTrips[$idx];
        }

        foreach ($arHotels as $idx => $it) {
            $arHotels[$idx]["Kind"] = "R";
            $result[] = $arHotels[$idx];
        }

        foreach ($result as $i => $it) {
            unset($result[$i]["ProviderID"]);
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation Code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName"      => [
                "Caption"  => "Passenger Last Name",
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.virtuallythere.com/new/login.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $arFields["ConfNo"] = isset($arFields["ConfNo"]) ? strtoupper($arFields["ConfNo"]) : "";
        $arFields["LastName"] = isset($arFields["LastName"]) ? strtoupper($arFields["LastName"]) : "";
        $this->http->GetURL("https://virtuallythere.com/new/reservations.html?pnr={$arFields["ConfNo"]}&name={$arFields["LastName"]}");
        $it = $this->ParseItinerary();
    }
}
