<?php

namespace AwardWallet\Engine\venetian\Email;

class It3486902 extends \TAccountCheckerExtended
{
    public $mailFiles = "venetian/it-3486902.eml";
    public $reBody = "The Venetian";
    public $reBody2 = "This is confirmation that your reservation has been successfully modified";
    public $reFrom = "groupcampaigns@pkghlrss.com";
    public $reSubject = "Your reservation has been modified!";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);

                if (!($web = $this->http->FindSingleNode("(//a[contains(@href, 'http://manage.passkey.com/Tracking/track.do')])[1]/@href"))) {
                    return;
                }

                $http2 = clone $this->http;

                $http2->getUrl($web);

                if (!($web = $http2->FindSingleNode("(//a[contains(., 'this link')])[1]/@href"))) {
                    return;
                }

                $http2->getUrl($web);

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $http2->FindSingleNode("//*[contains(text(), 'Acknowledgement number')][1]", null, true, "#Acknowledgement\s+number\s*:\s*(\w+)#");

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = $http2->FindSingleNode("//*[@class='desc']/*[@class='title']");

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Arrival:"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Departure:"));

                // Address
                $it['Address'] = $http2->FindSingleNode("//*[@class='desc']/*[@class='address']");

                // DetailedAddress
                // Phone
                // Fax
                // GuestNames
                $it['GuestNames'] = [$this->getField("Guest:")];

                // Guests
                $it['Guests'] = $http2->FindSingleNode("//*[@class='info']/*[@class='dates']", null, true, "#(\d+)\s+adult#");

                // Kids
                $it['Kids'] = $http2->FindSingleNode("//*[@class='info']/*[@class='dates']", null, true, "#(\d+)\s+children#");

                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("//*[contains(text(), 'Cancel Policy:')]/following::p[1]");

                // RoomType
                $it['RoomType'] = $this->getField("Suite:");

                // RoomTypeDescription
                // Cost
                $it['Cost'] = cost($http2->FindSingleNode("//*[normalize-space(text())='Rates:']/following-sibling::*[1]"));

                // Taxes
                $it['Taxes'] = cost($http2->FindSingleNode("//*[normalize-space(text())='Taxes:']/following-sibling::*[1]"));

                // Total
                $it['Total'] = cost($http2->FindSingleNode("//*[normalize-space(text())='Total:']/following-sibling::*[1]"));

                // Currency
                $it['Currency'] = currency($http2->FindSingleNode("//*[normalize-space(text())='Total:']/following-sibling::*[1]"));

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false && strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }
        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function getField($str)
    {
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$str}']/following::text()[normalize-space(.)][1]");
    }
}
