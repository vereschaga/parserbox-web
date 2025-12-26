<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-2251671.eml, rapidrewards/it-2251856.eml, rapidrewards/it-3812408.eml, rapidrewards/it-8.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match("/@luv\.southwest\.com/i", $from)
            || stripos($from, "no-reply@customercommunications.com") !== false
            || stripos($from, "Noreply@rapidrewardsshopping.com") !== false
            || stripos($from, "SouthwestAirlines.ProactiveCommunications@wnco.com") !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) ? preg_match("/southwestairlines@luv\.southwest\.com/i", $headers['from']) : false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Southwest Airlines Ticketless Travel information') !== false
            || stripos($body, 'As a valued Southwest Customer, we want to make the most of your travel experience') !== false
            || stripos($body, 'Thanks for choosing Southwest') !== false
            || $this->http->XPath->query("//a[contains(text(),'View Car Reservation') and contains(@href,'southwest.com/')]")->length > 0
            || (($this->http->XPath->query("//text()[normalize-space(.)='HOTEL Itinerary']")->length > 0)
            && ($this->http->XPath->query("//a[contains(@href,'southwest.com/')]")->length > 0));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[contains(text(),'CAR Itinerary')] | //*[contains(normalize-space(.),'CAR Confirmation')] | //*[contains(normalize-space(.),'Car itinerary')]")->length > 0) {
            return $this->parseCarReservation($parser);
        }

        if ($this->http->XPath->query("//*[contains(text(),'HOTEL Itinerary')]/ancestor::table[1]/following-sibling::table")->length > 0) {
            return $this->parseHotelReservation($parser);
        }

        return [];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseHotelReservation(\PlancakeEmailParser $parser)
    {
        $its = [];
        $confNos = $this->http->XPath->query("//text()[contains(.,'HOTEL Confirmation') or contains(.,'Cancellation Number:')]");

        foreach ($confNos as $root) {
            $i = 0;

            do {
                $root = $this->http->XPath->query("ancestor::td[1]", $root);
                $i++;
            } while ($i < 5 && $root->length > 0 && ($root = $root->item(0)) && !($found = strpos($root->nodeValue, "Check-In") !== false));

            if (empty($found)) {
                continue;
            }

            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//*[contains(text(),'HOTEL Confirmation:')]", $root, true, "#hotel confirmation: ([A-Z\d]+)#i");

            if (!isset($it['ConfirmationNumber']) && ($cancelled = $this->http->FindSingleNode(".//*[contains(text(),'Cancellation Number:')]", $root, true, "#Cancellation Number: ([A-Z\d]+)#i"))) {
                $it['ConfirmationNumber'] = $cancelled;
                $it['Status'] = 'Cancelled';
            }
            $it["GuestNames"] = $this->http->FindSingleNode(".//*[*[.='Guest Name:']]", $root, true, "/Guest Name:\s*(.*)$/s");
            $it["CancellationPolicy"] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)='Cancellation']/following::text()[normalize-space()][1]", $root, true, "/:\s*(.*)$/s");
            $text = CleanXMLValue($root->nodeValue);

            if (stripos($text, 'Check-In') !== false && stripos($text, 'Check-Out') !== false) {
                $tbody = '';
                $rootNew = $this->http->XPath->query("./descendant::text()[normalize-space(.)='Check-In']/ancestor::table[1]", $root);

                if ($rootNew->length > 0) {
                    $rootNew = $rootNew->item(0);
                } else {
                    $rootNew = $root;
                }

                if ($this->http->XPath->query("tbody", $rootNew)->length > 0) {
                    $tbody = "tbody/";
                }
                $it['HotelName'] = $this->http->FindSingleNode($tbody . "tr[1]/td[1]/*[1]", $rootNew);
                $lines = $this->http->FindNodes($tbody . "tr[1]/td[1]/*[2]/text()", $rootNew);
                $it["Address"] = [];

                foreach ($lines as $line) {
                    if (preg_match("/^Tel\. (.+)$/", $line, $m)) {
                        $it["Phone"] = $m[1];

                        break;
                    }
                    $it["Address"][] = $line;
                }
                $it["Address"] = implode(', ', $it["Address"]);
                $it['CheckInDate'] = strtotime($this->http->FindSingleNode($tbody . "tr[1]/td[2]/div[2]", $rootNew));
                $it['CheckOutDate'] = strtotime($this->http->FindSingleNode($tbody . "tr[1]/td[3]/div[2]", $rootNew));

                foreach (["RoomType" => "Room Request:", "Rooms" => "Number of Rooms:", "CancellationPolicy" => "Cancellation"] as $key => $search) {
                    if ($data = $this->http->FindSingleNode(".//*[contains(text(),'" . $search . "')]/following-sibling::text()[1]")) {
                        $it[$key] = trim($data, " :");
                    }
                }
            }

            $its[] = $it;
        }

        if (count($its) === 1) {
            $its[0]['Total'] = $this->http->FindSingleNode('.//td[contains(.,"Total Hotel Cost") and not(.//td)]/following-sibling::td[last()]', null, true, '/\$ ([\d\.]+)/');
            $its[0]['Currency'] = str_replace("$", "USD", $this->http->FindSingleNode('.//td[contains(.,"Total Hotel Cost") and not(.//td)]/following-sibling::td[last()]', null, true, '/(\$) [\d\.]+/'));
        }

        return [
            'emailType'  => 'HotelReservation',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    private function parseCarReservation(\PlancakeEmailParser $parser)
    {
        $its = [];
        //		traxo want all info
        //		$confNos = $this->http->XPath->query("//text()[contains(.,'CAR Confirmation')]");
        $confNos = $this->http->XPath->query("//text()[contains(.,'CAR Confirmation') or contains(.,'Cancellation Number:')]");

        foreach ($confNos as $root) {
            $i = 0;

            do {
                $root = $this->http->XPath->query("ancestor::td[1]", $root);
                $i++;
            } while ($i < 5 && $root->length > 0 && ($root = $root->item(0)) && !($found = strpos($root->nodeValue, "Pick-Up Location") !== false));

            if (empty($found)) {
                continue;
            }

            $it = ['Kind' => 'L'];
            $it['Number'] = $this->http->FindSingleNode(".//*[contains(text(),'CAR Confirmation:')]", $root, true, "#car confirmation: ([A-Z\d]+)#i");

            if (!isset($it['Number']) && ($cancelled = $this->http->FindSingleNode(".//*[contains(text(),'Cancellation Number:')]", $root, true, "#Cancellation Number: ([A-Z\d]+)#i"))) {
                $it['Number'] = $cancelled;
                $it['Status'] = 'Cancelled';
            }

            if ($this->http->FindSingleNode('.//text()[contains(.,"Drop Off Location")]', $root)) {
                $dropofftext = 'Drop Off';
            } else {
                $dropofftext = 'Return';
            }

            if (isset($it["Number"])) {
                $it['RenterName'] = $this->http->FindSingleNode(".//*[contains(text(),'Driver Name')]/following-sibling::text()", $root);
                $it['AccountNumbers'] = $this->http->FindSingleNode(".//*[contains(text(),'Rapid Rewards')]/following-sibling::text()", $root);
                $it['PickupLocation'] = $this->http->FindSingleNode(".//*[contains(text(),'Pick-Up Location')]/ancestor::div[1]/following-sibling::div[1]/text()[last()]", $root);
                $it['DropoffLocation'] = $this->http->FindSingleNode(".//*[contains(text(),'{$dropofftext} Location')]/ancestor::div[1]/following-sibling::div[1]/text()[last()]", $root);
                $it['PickupDatetime'] = strtotime($this->http->FindSingleNode(".//*[contains(text(),'Pick-Up Date')]/ancestor::div[1]/following-sibling::div[1]", $root));
                $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode(".//*[contains(text(),'{$dropofftext} Date')]/ancestor::div[1]/following-sibling::div[1]", $root));
                $it['CarModel'] = $this->http->FindSingleNode(".//*[contains(text(),'Vehicle Description')]/following-sibling::text()", $root);
            } else {
                // less html dependant
                $it['Number'] = $this->http->FindSingleNode(".//*[normalize-space(.)='CAR Confirmation:']/parent::*[normalize-space(.)!='CAR Confirmation:']", $root, true, "#car confirmation: ([A-Z\d]+)#i");
                $it["RenterName"] = $this->http->FindSingleNode(".//*[normalize-space(.)='Driver Name:']/parent::*[normalize-space(.)!='Driver Name:']", $root, true, "/Driver Name: (.+)/i");
                $it["PickupLocation"] = $this->http->FindSingleNode(".//*[normalize-space(.)='Pick-Up Location']/parent::*[normalize-space(.)!='Pick-Up Location']", $root, true, "/Pick-Up Location (.+)/i");
                $it["DropoffLocation"] = $this->http->FindSingleNode(".//*[normalize-space(.)='{$dropofftext} Location']/parent::*[normalize-space(.)!='{$dropofftext} Location']", $root, true, "/{$dropofftext} Location (.+)/i");
                $it["PickupDatetime"] = strtotime($this->http->FindSingleNode(".//*[normalize-space(.)='Pick-Up Date']/parent::*[normalize-space(.)!='Pick-Up Date']", $root, true, "/Pick-Up Date (.+)/i"));
                $it["DropoffDatetime"] = strtotime($this->http->FindSingleNode(".//*[normalize-space(.)='{$dropofftext} Date']/parent::*[normalize-space(.)!='{$dropofftext} Date']", $root, true, "/{$dropofftext} Date (.+)/i"));
                $it["CarModel"] = $this->http->FindSingleNode(".//*[normalize-space(.)='Vehicle Description:']/parent::*[normalize-space(.)!='Vehicle Description:']", $root, true, "/Vehicle Description: (.+)/i");
            }
            $its[] = $it;
        }

        if (count($its) === 1) {
            $its[0]['TotalCharge'] = $this->http->FindSingleNode('.//td[contains(.,"Estimated Car Cost") and not(.//td)]/following-sibling::td[last()]', null, true, '/\$ ([\d\.]+)/');
            $its[0]['Currency'] = str_replace("$", "USD", $this->http->FindSingleNode('.//td[contains(.,"Estimated Car Cost") and not(.//td)]/following-sibling::td[last()]', null, true, '/(\$) [\d\.]+/'));
        }
        //		traxo want all info
        //		if (stripos($this->http->Response['body'], "Your Cancellation request has been sent to the rental car company") !== false)
        //			foreach ($this->http->FindNodes("//text()[contains(.,'Cancellation Number')]/parent::*", null, "/Cancellation Number:\s*([\S]+)$/") as $conf)
        //				if ( isset($conf) )
        //					$its[] = [
        //						"Kind" => 'L',
        //						"Number" => $conf,
        //						"Cancelled" => true,
        //						"Status" => 'Cancelled',
        //					];

        return [
            'emailType'  => 'CarRental',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }
}
