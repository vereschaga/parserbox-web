<?php

namespace AwardWallet\Engine\mileageplus\Email;

class PartnerHotel extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-2.eml, mileageplus/it-3.eml, mileageplus/it-5.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "PartnerHotel",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'unitedairlines@united.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//tr[td[contains(text(), 'Traveler')] and td[contains(text(), 'eTicket Number')] and td[contains(text(), 'Frequent Flyer')]]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\.com/", $from);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    // subject Your Reservation Has Been Confirmed – Itinerary Number
    // or Confirm Cancellation Itinerary #: (it-2, it-3, it-5)

    protected function ParseEmail()
    {
        $it = ["Kind" => 'R'];
        $it['ConfirmationNumbers'] = $this->http->FindSingleNode('//td[contains(text(), "Your Booking Confirmation Number") and not(.//td)]/following-sibling::td[1]', null, true, "#[^\-]+ - [^\-]+ - (\d{5,})($|\s+- )?#");
        $it['ConfirmationNumber'] = $this->http->FindSingleNode('//td[contains(text(), "Itinerary Number:") and not(.//td)]/following-sibling::td[1]');
        $hotelNameNode = $this->http->XPath->query('//tr[contains(string(), "Property Details")]/following-sibling::tr[contains(string(), "Additional Amenities")]/preceding-sibling::tr[last()]')->item(0);

        if ($hotelNameNode) {
            $it['HotelName'] = CleanXMLValue($hotelNameNode->nodeValue);
            $address = array_filter($this->http->FindNodes('./following-sibling::tr[.//img[contains(@src, "star")]]/following-sibling::tr[count(./following-sibling::tr[contains(string(), "Property Details")]) = 1]', $hotelNameNode), 'strlen');

            if (isset($address[0])) {
                $it['Address'] = $address[0];
            } else {
                $address = array_filter($this->http->FindNodes('//tr[.//img[contains(@src, "star")] and count(.//table) = 1]/following-sibling::tr'));

                if (isset($address[0])) {
                    $it['Address'] = $address[0];
                }
            }

            if (isset($address[1])) {
                $it['Phone'] = $address[1];
            }
        } else {
            // cancellation emails
            $rows = $this->http->FindNodes("//tr[contains(., 'Cancellation Details') and following-sibling::tr[contains(., 'Check-in')]]/following-sibling::tr");

            if (!empty($rows)) {
                $it["HotelName"] = $rows[0];
            }

            foreach ($rows as $idx => $row) {
                if (stripos($row, 'reviews') !== false) {
                    if (isset($rows[$idx + 1])) {
                        $it["Address"] = $rows[$idx + 1];
                    }

                    break;
                }
            }
        }

        $it['CheckInDate'] = strtotime($this->http->FindSingleNode('//td[contains(string(), "Check-in:") and not(.//td)]/following-sibling::td[1]'));
        $it['CheckOutDate'] = strtotime($this->http->FindSingleNode('//td[contains(string(), "Check-out:") and not(.//td)]/following-sibling::td[1]'));
        $it['RoomType'] = $this->http->FindSingleNode('//td[contains(string(), "Room type:") and not(.//td)]/following-sibling::td[1]');
        $it['Rooms'] = $this->http->FindSingleNode('//td[contains(string(), "Rooms:") and not(.//td)]/following-sibling::td[1]');

        if (preg_match('/^(.+)\s*,\s*Adult.?\s*:\s*(\d+)(\s*,?\s*(Kid.?|Child(\w+)?)\s*:\s*(\d+))?/ims', $this->http->FindSingleNode('(//td[contains(string(), "Guests:") and not(.//td)]/following-sibling::td[1])[1]'), $matches)) {
            $it['GuestNames'] = [$matches[1]];
            $it['Guests'] = $matches[2];

            if (isset($matches[7])) {
                $it['Kids'] = $matches[7];
            }
        }

        if ($this->http->FindSingleNode('//*[contains(text(), "Your reservation is confirmed")]')) {
            $it["Status"] = "Confirmed";
            $it['CancellationPolicy'] = $this->http->FindSingleNode('//tr[contains(string(), "Room Details")]/following-sibling::tr[contains(string(), "Cancellation Policy")]/following-sibling::tr[1]');
        }

        if ($this->http->FindSingleNode('(//*[contains(text(), "Your reservation has been cancelled")])[1]')) {
            $it["Status"] = "Cancelled";
            $it['CancellationPolicy'] = $this->http->FindSingleNode('//tr[contains(string(), "Cancellation Details")]/following-sibling::tr[contains(string(), "Cancellation Policy")]/following-sibling::tr[1]');
        }

        $chargesNode = $this->http->XPath->query('//tr[contains(string(), "Charges")]/following-sibling::tr[1][contains(string(), "All prices are displayed in")]//table[1]')->item(0);

        if ($chargesNode) {
            $it['Currency'] = $this->http->FindSingleNode('(.//tr[1])[1]', $chargesNode, true, '/All prices are displayed in\s+(?:.\s+)?(\S+)/ims');
            $it['Total'] = $this->http->FindSingleNode('.//td[contains(string(), "Total Charges") and not(.//td)]/following-sibling::td[1]', $chargesNode, true, '/((\d+.\d+|\d+)(.\d+)?)/ims');

            if (null === ($it['Taxes'] = $this->http->FindSingleNode('.//td[contains(string(), "Tax Recovery Charges and Service Fees") and not(.//td)]/following-sibling::td[1]', $chargesNode, true, '/((\d+.\d+|\d+)(.\d+)?)/ims'))) {
                $it['Taxes'] = $this->http->FindSingleNode('.//td[contains(string(), "Taxes") and not(.//td)]/following-sibling::td[1]', $chargesNode, true, '/((\d+.\d+|\d+)(.\d+)?)/ims');
            }
            $it['Total'] = $this->http->FindSingleNode('.//td[contains(string(), "Room: 1:") and not(.//td)]/following-sibling::td[1]', $chargesNode, true, '/((\d+.\d+|\d+)(.\d+)?)/ims');
        }

        return [$it];
    }
}
