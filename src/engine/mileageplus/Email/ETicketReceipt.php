<?php

namespace AwardWallet\Engine\mileageplus\Email;

class ETicketReceipt extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-1.eml, mileageplus/it-6327465.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail($parser->getSubject());

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicketReceipt',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && preg_match("/eTicket Itinerary and Receipt for Confirmation [A-Z\d]{6}$/", $headers['subject'])
            || isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//tr[td[contains(text(), 'Traveler')] and td[contains(text(), 'eTicket Number')] and td[contains(text(), 'Frequent Flyer')]]")->length > 0
            || strpos($this->http->Response['body'], "Baggage check-in must occur with United or United Express") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\.com/", $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'pt'];
    }

    // Subject: eTicket Itinerary and Receipt for Confirmation ABC123
    // simple table with white and grey rows (it-1, it-4)

    protected function ParseEmail($subject)
    {
        $it = ["Kind" => "T"];

        if (preg_match("/eTicket Itinerary and Receipt for Confirmation ([A-Z\d]{6})/", $subject, $m)) {
            $it["RecordLocator"] = $m[1];
        }

        if (!isset($it["RecordLocator"])) {
            $it["RecordLocator"] = $this->http->FindSingleNode("//*[contains(@class,'eticketconfirmation') and not(contains(@class, 'header'))] | //*[contains(@id, 'ConfirmationNumber')]");
        }

        if (!isset($it["RecordLocator"])) {
            $it["RecordLocator"] = $this->http->FindSingleNode("//a[contains(text(), 'Check-In')]/preceding-sibling::span");
        }

        if (!isset($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//tr[contains(., 'Confirmation:') and not(.//tr)]/following-sibling::tr[1]");
        }

        if (!isset($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//tr[contains(., 'Confirmação:') and not(.//tr)]/following-sibling::tr[1]");
        }

        if (!isset($it["RecordLocator"])) {
            $it["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation:')]/following-sibling::*[1]", null, true, "/^[A-Z\d]{6}$/");
        }

        if (isset($it["RecordLocator"])) {
            $it["RecordLocator"] = preg_replace("/Check\-in.+/ims", "", $it["RecordLocator"]);
        }

        if ($flightInfo = $this->http->XPath->query("//text()[normalize-space(.)='Flight']/ancestor::tr[1][contains(., 'Departure')]/following-sibling::tr")) {
            if ($flightInfo->length === 0) {
                return null;
            }
            $segments = [];
            $count = 0;

            for ($i = 0; $i < $flightInfo->length; $i++) {
                $cells = $this->http->XPath->query("./*[name()='td' or name()='th']", $flightInfo->item($i));

                if (($cells->length === 7) && preg_match("/^[a-zA-Z]{3}\,\s?\d\d[a-zA-Z]{3}\d\d/", CleanXMLValue($cells->item(0)->nodeValue))) {
                    $segment = [];
                    $depDate = "";
                    $year = "";

                    if (preg_match("/(\d+[A-Z]{3})(\d+)/", $cells->item(0)->nodeValue, $matches)) {
                        $year = $matches[2];
                        $depDate = $matches[1] . $year;
                    }

                    foreach ($this->http->XPath->query(".//sup") as $sup) {
                        $sup->nodeValue = "";
                    }
                    $flight = CleanXMLValue($cells->item(1)->nodeValue);

                    if (preg_match("/^([A-Z\d]{2})([\d]+)$/", $flight, $m)) {
                        $segment["AirlineName"] = $m[1];
                        $segment["FlightNumber"] = $m[2];
                    } else {
                        $segment["FlightNumber"] = $flight;
                    }
                    $segment["BookingClass"] = CleanXMLValue($cells->item(2)->nodeValue);

                    $lines = [
                        "Dep" => CleanXMLValue($cells->item(3)->nodeValue),
                        "Arr" => CleanXMLValue($cells->item(4)->nodeValue),
                    ];

                    foreach ($lines as $prefix => $line) {
                        // full line - OSAKA, JAPAN (KIX) 8:45 AM (12FEB) Seat: 17G
                        // parsing from the end
                        $pos = stripos($line, "Seat:");

                        if ($pos !== false) {
                            $line = trim(substr($line, 0, $pos));
                        }
                        // date
                        if (preg_match("/^(.+)\s+\((\d+[A-Z]{3})\)$/", $line, $m)) {
                            $date = $m[2] . $year;
                            $line = $m[1];
                        } else {
                            $date = $depDate;
                        }
                        // time
                        if (preg_match("/^(.+)\s+([\d\:]+ *[apAP]\.?[Mm]\.?)$/", $line, $m)) {
                            $segment[$prefix . "Date"] = strtotime($date . " " . $m[2]);
                            $line = $m[1];
                        } elseif ($prefix == 'Arr' && isset($segment['DepDate'])) { // sometimes there is no arr date
                            $segment['ArrDate'] = MISSING_DATE;
                        }
                        // code
                        if (preg_match("/^(.+)\s*\(([A-Z]{3})[^\)]*\)$/", $line, $m)) {
                            $segment[$prefix . "Code"] = $m[2];
                            $line = $m[1];
                        }
                        // name
                        $segment[$prefix . "Name"] = $line;
                        // in 1 email full line was
                        // UKB 1:40 PM
                        if (preg_match("/^[A-Z]{3}$/", $line) && !isset($segment[$prefix . "Code"])) {
                            $segment[$prefix . "Code"] = $line;
                        }
                    }
                    $aircraft = CleanXMLValue($cells->item(5)->nodeValue);

                    if ($aircraft) {
                        $segment["Aircraft"] = $aircraft;
                    }
                    $meal = CleanXMLValue($cells->item(6)->nodeValue);

                    if ($meal) {
                        $segment["Meal"] = $meal;
                    }
                    $segment["Seats"] = [];

                    if (!empty($segment["DepDate"]) && !empty($segment["ArrDate"]) && !empty($segment["DepName"]) && !empty($segment["ArrName"]) && !empty($segment["FlightNumber"])) {
                        $segment["DepCode"] = $segment["DepCode"] ?? TRIP_CODE_UNKNOWN;
                        $segment["ArrCode"] = $segment["ArrCode"] ?? TRIP_CODE_UNKNOWN;
                    }
                    $segments[$count] = $segment;
                    $count++;
                }
            }
            $rows = $this->http->XPath->query("//div[contains(@id, 'ShowTravelers')]//tr");

            if ($rows->length === 0) {
                $rows = $this->http->XPath->query("//td[contains(., 'Traveler') and not(.//td)]/parent::tr/parent::*/tr");
            }

            if ($rows->length === 0) {
                $rows = $this->http->XPath->query("//td[contains(., 'Passageiro') and not(.//td)]/parent::tr/parent::*/tr");
            }
            $passengers = [];

            for ($i = 0; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $name = $this->http->FindSingleNode("td[1]", $row);

                if (stripos($name, 'Traveler') !== false) {
                    continue;
                }

                if (stripos($name, "/") !== false) {
                    $passengers[] = $name;
                }
                $seatArr = explode('/', $this->http->FindSingleNode("td[4]", $row));

                if (count($seatArr) == $count) {
                    foreach ($seatArr as $idx => $seat) {
                        $seat = trim($seat);

                        if ($seat != '---') {
                            $segments[$idx]["Seats"][] = $seat;
                        }
                    }
                }
            }

            if (count($passengers) > 0) {
                $it["Passengers"] = $passengers;
            }

            foreach ($segments as $i => $segment) {
                if (count($segment["Seats"]) > 0) {
                    $segments[$i]["Seats"] = implode(', ', $segment["Seats"]);
                } else {
                    unset($segments[$i]["Seats"]);
                }
            }
            $it["TripSegments"] = $segments;
            $total = $this->http->FindSingleNode("//td[contains(., 'eTicket Total:') and not(.//td)]/following-sibling::td[1]");
            $it["TotalCharge"] = str_ireplace(",", "", $total);
            $price = $this->http->FindSingleNode("//*[contains(text(), 'The airfare you paid on this itinerary totals:') and not(.//td)]", null, true, "/totals: ([\d\.\,]+)\D+$/");
            $it["BaseFare"] = str_ireplace(",", "", $price);
            $tax = $this->http->FindSingleNode("//*[contains(text(), 'The taxes, fees, and surcharges paid total:') and not(.//td)]", null, true, "/total: ([\d\.\,]+)\D+$/");
            $it["Tax"] = str_ireplace(",", "", $tax);
            $it["Currency"] = $this->http->FindSingleNode("(//td[contains(., 'eTicket Total:') and not(.//td)]/following-sibling::td[normalize-space(.)!=''])[last()]", null, true, "/^\w{3}$/");
        }

        return [$it];
    }

    protected function findParentNode($start, $tag = 'td', $limit = 5)
    {
        if (!$start) {
            return null;
        }

        if (is_string($start)) {
            $str = explode(' ', $start);
            $and = [];

            foreach ($str as $s) {
                $and[] = "contains(text(), '$s')";
            }

            if (!($node = $this->findFirstNode("//*[" . implode(" and ", $and) . "]"))) {
                return null;
            }
        } elseif ($start instanceof \DOMNode) {
            $node = $start;
        } else {
            return null;
        }

        if (strtolower($node->nodeName) == strtolower($tag)) {
            return $node;
        }

        return $this->http->XPath->query("./ancestor::tr[1]", $node)->item(0);
    }

    protected function findFirstNode($xpath, $parent = null)
    {
        $result = $this->http->XPath->query($xpath, $parent);

        if ($result->length === 0) {
            return null;
        } else {
            return $result->item(0);
        }
    }
}
