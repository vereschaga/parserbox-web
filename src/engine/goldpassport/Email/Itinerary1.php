<?php

namespace AwardWallet\Engine\goldpassport\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-5.eml";

    /**
     * @example goldpassport/it-5.eml
     */
    public function ParseEConcierge()
    {
        $http = $this->http;
        $xpath = $http->XPath;

        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $this->http->FindSingleNode('//td[contains(., "Confirmation Number:") and not(.//td)]/following-sibling::td[1]');
        $result['HotelName'] = $this->http->FindSingleNode('//*[contains(text(), "Greetings from")]', null, true, '/Greetings from\s*(.+)/ims');
        $hotelInfo = $this->http->FindSingleNode('//*[contains(text(), "Greetings from")]');

        if (preg_match('/Greetings from\s*(.+)-\s*(.+)/ims', $hotelInfo, $matches)) {
            $result['HotelName'] = $matches[1];
            $result['Address'] = $matches[1] . ', ' . $matches[2];
        } else {
            $result['HotelName'] = $result['Address'] = $hotelInfo;
        }
        $result['CheckInDate'] = strtotime($this->http->FindSingleNode('//td[contains(., "Check-in:") and not(.//td)]/following-sibling::td[1]'));
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode('//td[contains(., "Check-out:") and not(.//td)]/following-sibling::td[1]'));
        $result['RoomType'] = strtotime($this->http->FindSingleNode('//td[contains(., "Room Type:") and not(.//td)]/following-sibling::td[1]'));

        $numberOfPeopleNode = $xpath->query('//text()[contains(., "Number of people:")][1]')->item(0);

        if ($numberOfPeopleNode) {
            if (preg_match('/Number of people:\s*(\d+)/ims', CleanXMLValue($numberOfPeopleNode->nodeValue), $matches)) {
                if (($number = (int) $matches[1]) > 0) {
                    $result['Guests'] = $number;
                    $result['GuestNames'] = array_values(array_map('beautifulName', array_filter($http->FindNodes("./following-sibling::text()[position() > 0 and position() <= {$number}]", $numberOfPeopleNode), 'strlen')));
                }
            }
        }

        return $result;
    }

    /**
     * @example goldpassport/it-3.eml
     * @example goldpassport/it-4.eml
     */
    public function ParseReservationConfirmationDetails()
    {
        return null; // covered by 393

        $http = $this->http;
        $xpath = $http->XPath;

        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $http->FindSingleNode('//td[contains(., "Online Confirmation:") and not(.//td)]/following-sibling::td[1]');

        $result['HotelName'] = $http->FindSingleNode('//*[
            contains(., "~") and
            contains(., "-") and
            not(.//span)
        ]', null, true, '/~[^~]+~\s*(.+)/ims');
        // NEED MORE EMAILS
        $result['Address'] = $result['HotelName'];
        $result['ReservationDate'] = strtotime($http->FindSingleNode('//td[contains(., "Date Booked:") and not(.//td)]/following-sibling::td[1]'));
        $result['GuestNames'] = [
            beautifulName($http->FindSingleNode('//td[contains(., "Reservation Name:") and not(.//td)]/following-sibling::td[1]')),
        ];
        $result['CheckInDate'] = strtotime($http->FindSingleNode('//td[contains(., "Arrival Date:") and not(.//td)]/following-sibling::td[1]'));
        $result['CheckOutDate'] = strtotime($http->FindSingleNode('//td[contains(., "Departure Date:") and not(.//td)]/following-sibling::td[1]'));

        $result['RoomType'] = $http->FindSingleNode('//td[contains(., "Room Type:") and not(.//td)]/following-sibling::td[1]');
        $result['Rooms'] = $http->FindSingleNode('//td[contains(., "Number of Rooms:") and not(.//td)]/following-sibling::td[1]');
        $result['Guests'] = $http->FindSingleNode('//td[contains(., "Number of Guests:") and not(.//td)]/following-sibling::td[1]');
        $result['Total'] = $http->FindSingleNode('//td[contains(., "Total Charge:") and not(.//td)]/following-sibling::td[1]', null, true, '/(\d+.\d+|\d+)/ims');
        $result['CancellationPolicy'] = $http->FindSingleNode('//td[contains(., "Cancel Policy:") and not(.//td)]/following-sibling::td[1]');

        return $result;
    }

    /**
     * @example goldpassport/it-1.eml
     * @example goldpassport/it-2.eml
     */
    public function ParseReservationConfirmation()
    {
        return null; // covered by 393.php

        $http = $this->http;
        $xpath = $http->XPath;

        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $http->FindSingleNode('//*[contains(text() ,"Confirmation Number")]/following::text()[php:functionString("CleanXMLValue", .) != ""][1]');

        if ($hotelNameNode = $xpath->query('//strong[php:functionString("stripos", string(), "Hotel Check-Out Time:") != false()]/following-sibling::strong[1]')->item(0)); else {
            $hotelNameNode = $xpath->query('//b[php:functionString("stripos", string(), "Hotel Check-Out Time:") != false()]/following-sibling::b[1]')->item(0);
        }

        if (!isset($hotelNameNode)) {
            return;
        }
        $result['HotelName'] = CleanXMLValue($hotelNameNode->nodeValue);
        $result['Address'] = $http->FindSingleNode('following-sibling::text()[1]', $hotelNameNode) . ', ' . $http->FindSingleNode('following-sibling::text()[2]', $hotelNameNode);
        $result['CheckInDate'] = strtotime($http->FindSingleNode('//*[(self::b or self::strong) and php:functionString("stripos", string(), "Check-In Date") != false()]/following-sibling::node()[1]/self::text()') . ' ' .
                                           $http->FindSingleNode('//*[(self::b or self::strong) and php:functionString("stripos", string(), "Hotel Check-In Time") != false()]/following-sibling::node()[1]/self::text()[not(contains(., "-"))]'));
        $result['CheckOutDate'] = strtotime($http->FindSingleNode('//*[(self::b or self::strong) and php:functionString("stripos", string(), "Check-Out Date") != false()]/following-sibling::node()[1]/self::text()') . ' ' .
                                           $http->FindSingleNode('//*[(self::b or self::strong) and php:functionString("stripos", string(), "Hotel Check-Out Time") != false()]/following-sibling::node()[1]/self::text()[not(contains(., "-"))]'));
        $result['Fax'] = $http->FindSingleNode('//*[contains(text(), "Fax:")]', null, false, '/Fax:\s*(.+)/ims');

        if (empty($result['Fax'])) {
            $result['Fax'] = $http->FindSingleNode('//text()[contains(., "Fax:")]', null, false, '/Fax:\s*(.+)/ims');
        }
        $result['Phone'] = $http->FindSingleNode('//*[contains(text(), "Tel:")]', null, false, '/Tel:\s*(.+)/ims');

        if (empty($result['Phone'])) {
            $result['Phone'] = $http->FindSingleNode('//text()[contains(., "Tel:")]', null, false, '/Tel:\s*(.+)/ims');
        }
        $result['GuestNames'] = beautifulName($http->FindSingleNode('//*[(self::b or self::strong) and contains(string(), "Guest Name")]/following-sibling::node()[string-length(php:functionString("CleanXMLValue", string())) > 0][1]'));
        $result['GuestNamesArray'] = array_map('trim', explode(',', $result['GuestNames']));

        if (($numberOfAdults = $http->FindSingleNode('//*[(self::b or self::strong) and contains(string(), "Number of Adults")]/following-sibling::node()[string-length(php:functionString("CleanXMLValue", string())) > 0][1]', null, false)) !== null) {
            $result['Guests'] = (int) $numberOfAdults;
        }

        if (($numberOfChildren = $http->FindSingleNode('//*[(self::b or self::strong) and contains(string(), "Number of Children")]/following-sibling::node()[string-length(php:functionString("CleanXMLValue", string())) > 0][1]', null, false)) !== null) {
            $result['Kids'] = (int) $numberOfChildren;
        }

        if (($numberOfRooms = $http->FindSingleNode('//*[(self::b or self::strong) and contains(string(), "Number of Rooms")]/following-sibling::node()[string-length(php:functionString("CleanXMLValue", string())) > 0][1]', null, false)) !== null) {
            $result['Rooms'] = (int) $numberOfRooms;
        }
        $roomTypes = [];
        $roomTypeNodes = $xpath->query("//*[(self::b or self::strong) and contains(string(), 'Room(s) Booked:')]/following-sibling::node()[not(./preceding-sibling::strong[contains(string(), 'Room Description')])]");

        foreach ($roomTypeNodes as $roomTypeNode) {
            // May 19, 2013 - May 20, 2013: GRAND KING
            // 2 QUEEN BEDS
            if (preg_match('/(([^:]+):)?(.*)/ims', CleanXMLValue($roomTypeNode->nodeValue), $matches)) {
                $roomTypes[] = $matches[3];
            }
        }

        $result['RoomType'] = implode(' | ', array_filter($roomTypes, 'strlen'));
        $result['RoomTypeDescription'] = implode(' | ', array_filter($http->FindNodes("//*[(self::b or self::strong) and contains(string(), 'Room Description:')]/following-sibling::node()[not(./preceding-sibling::strong[contains(string(), 'Nightly Rate per Room')])]"), 'strlen'));
        $result['CancellationPolicy'] = implode(' | ', array_filter($http->FindNodes('//*[contains(text(), "CANCELLATION POLICY:")]/following::node()[string-length(php:functionString("CleanXMLValue", string())) > 0][1]'), 'strlen'));

        $rateTypes = [];
        $rates = [];
        $currency = null;
        $rateNodes = $xpath->query("//*[(self::b or self::strong) and contains(string(), 'Nightly Rate per Room')]/following-sibling::node()");
        $this->parseRates($rateNodes, $rateTypes, $rates, $currency);

        if (!empty($currency)) {
            $result['Currency'] = $currency;
        }
        $result['Rate'] = implode(' | ', array_filter($rates, 'strlen'));
        $result['RateType'] = implode(' | ', array_filter($rateTypes, 'strlen'));
        $result['AccountNumbers'] = $http->FindSingleNode('//*[contains(text(), "Membership Number")]/following-sibling::node()[1]/self::text()', null, true, '/:?\s+?(.*)/ims');

        return $result;
    }

    public function ParseReservationCancellationDetails()
    {
        $result = ['Kind' => 'R'];
        $result['Cancelled'] = true;
        $result['ConfirmationNumber'] = $this->http->FindSingleNode('//strong[contains(text(), "Cancellation Number:")]/following-sibling::text()[1]');
        $result['HotelName'] = $this->http->FindSingleNode('//strong[contains(text(), "Cancellation Number:")]/following-sibling::strong[1]');
        $result['Address'] = implode(', ', $this->http->FindNodes('//strong[contains(text(), "Cancellation Number:")]/following-sibling::strong[1]/following-sibling::text()[position() < 3]'));
        $result['Phone'] = $this->http->FindSingleNode('//span[contains(text(), "Tel:")]/descendant::a');
        $result['Fax'] = $this->http->FindSingleNode('//span[contains(text(), "Fax:")]/descendant::a');
        $result['Guests'] = $this->http->FindSingleNode('//strong[contains(text(), "Number of Adults:")]/following-sibling::text()[1]');
        $result['Kids'] = $this->http->FindSingleNode('//strong[contains(text(), "Number of Children:")]/following-sibling::text()[1]');
        $result['Rooms'] = $this->http->FindSingleNode('//strong[contains(text(), "Number of Rooms:")]/following-sibling::text()[1]');
        $result['GuestNames'] = $this->http->FindSingleNode('//strong[contains(text(), "Guest Name")]/following-sibling::text()[1]');
        $result['RoomType'] = $this->http->FindSingleNode('//strong[contains(text(), "Room(s) Booked:")]/following-sibling::text()[1]');
        $result['RoomTypeDescription'] = $this->http->FindSingleNode('//strong[contains(text(), "Room Description:")]/following-sibling::text()[1]');
        $result['CheckInDate'] = strtotime($this->http->FindSingleNode('//strong[contains(text(), "Nightly Rate per Room:")]/following-sibling::b[1]', null, true, '/(.*?)\s+-/ims'));
        $result['CheckOutDate'] = strtotime($this->http->FindSingleNode('//strong[contains(text(), "Nightly Rate per Room:")]/following-sibling::b[1]', null, true, '/\s+-(.*)/ims'));
        $result['Rate'] = $this->http->FindSingleNode('//strong[contains(text(), "Nightly Rate per Room:")]/following-sibling::b[2]');
        $result['RateType'] = $this->http->FindSingleNode('//b[contains(text(), "Type of Rate:")]/following-sibling::text()[1]');
        $result['CancellationPolicy'] = $this->http->FindSingleNode('//b[contains(text(), "CANCELLATION POLICY:")]/following-sibling::text()[1]');
        $result['Status'] = 'Cancelled';

        return $result;
    }

    public function parseRates(\DOMNodeList $rateNodes, &$rateTypes, &$rates, &$currency)
    {
        $http = $this->http;
        $xpath = $http->XPath;

        foreach ($rateNodes as $rateNode) {
            if (isset($rateNode->tagName) && $rateNode->tagName == 'span') {
                $this->parseRates($rateNode->childNodes, $rateTypes, $rates, $currency);
            }
            $nodeValue = CleanXMLValue($rateNode->nodeValue);

            if (preg_match('/^((\d+.\d+|\d+)|\*C\*)\s+(.*)$/ims', $nodeValue, $matches)) {
                if ($matches[1] !== '*C*') {
                    $rates[] = $http->FindSingleNode('preceding-sibling::node()[1]/self::text()', $rateNode) . ', ' . $matches[2];
                }
                $currency = $matches[3];
            }

            if (preg_match('/Type of Rate:/ims', $nodeValue)) {
                $rateTypes[] = $http->FindSingleNode('following-sibling::node()[1]/self::text()', $rateNode);
            }
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->SetBody($parser->getHTMLBody(), true);
        $this->http->XPath->registerNamespace('php', 'http://php.net/xpath');
        $this->http->XPath->registerPhpFunctions(['stripos', 'CleanXMLValue']);
        $emailType = $this->getEmailType();

        switch ($emailType) {
            case "ReservationConfirmation":
                $result = $this->ParseReservationConfirmation();

            break;

            case "ReservationConfirmationDetails":
                $result = $this->ParseReservationConfirmationDetails();

            break;

            case "ReservationCancellationDetails":
                $result = $this->ParseReservationCancellationDetails();

                break;

            case "EConcierge":
                $result = $this->ParseEConcierge();

            break;

            default:
                $result = [];
        }
        /*           if($this->RefreshData && !empty($result['ConfirmationNumber']) && !empty($result['GuestNamesArray'][0])){
                       // get last name
                       $nameParts = explode(' ', $result['GuestNamesArray'][0]);
                       $fistName = strtoupper(current($nameParts));
                    	$lastName = strtoupper(end($nameParts));

                       $errorMsg = $this->CheckConfirmationNumberInternal([
                               'RecordLocator' => $result['ConfirmationNumber'],
                               'FirstName' => $fistName,
                               'LastName' => $lastName,
                           ], $freshData);

                       if($errorMsg === null && $this->checkItineraries($freshData)){
                           $result = $freshData;
                       }
                   }*/
        if (is_array($result)) {
            unset($result['GuestNamesArray']);
        }

        return [
            'parsedData' => [
                'Itineraries' => isset($result[0]) ? $result : [$result],
                'Properties'  => [],
            ],
            'emailType' => $emailType,
        ];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public function getEmailType()
    {
        if ($this->http->FindSingleNode("//*[
   		    contains(text(), 'RESERVATION') and (
   		        contains(php:functionString('CleanXMLValue', text()), 'RESERVATION CONFIRMATION') or
   		        contains(php:functionString('CleanXMLValue', text()), 'RESERVATION CHANGE') or
   		        contains(php:functionString('CleanXMLValue', text()), 'RESERVATION REMINDER')
               )]")) {
            return "ReservationConfirmation";
        }

        if ($this->http->XPath->query('//p[contains(., "Reservation") and php:functionString("CleanXMLValue", .) = "Reservation Details" and not(.//p)]/following-sibling::table[1]/descendant-or-self::*[tr][1][count(tr) > 6]')->length > 0) {
            return "ReservationConfirmationDetails";
        }

        if ($this->http->FindPreg('/E-Concierge/ims')) {
            return "EConcierge";
        }

        if ($this->http->FindPreg('/Cancellation Number/ims') && $this->http->FindPreg('/RESERVATION Cancellation/ims')) {
            return "ReservationCancellationDetails";
        }

        return "Undefined";
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['from']) && stripos($headers['from'], 'hyatt.com') !== false)
            || (isset($headers['subject']) && stripos($headers['subject'], 'Hyatt') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (isset($this->http->Response['body']) and $this->http->Response['body']) {
            $text = $this->http->Response['body'];
        } elseif ($parser->getPlainBody()) {
            $text = $parser->getPlainBody();
        } else {
            return false;
        }

        return stripos($text, "hyatt") !== false
                    or stripos($text, 'We are pleased to confirm your reservations at Hyatt')
                    or stripos($text, 'Thank you for using Hyatt');
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@](\w+\.)*hyatt\.com$/ims', $from);
    }
}
