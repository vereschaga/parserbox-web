<?php

namespace AwardWallet\Engine\triprewards\Email;

class It3580061 extends \TAccountCheckerExtended
{
    public $mailFiles = "triprewards/it-3580061.eml, triprewards/it-6102288.eml";
    public $reBody = "manage.passkey.com";
    public $reBody2 = "Thank you for making your";
    public $reFrom = "groupcampaigns@pkghlrss.com";
    public $reSubject = "Hotel Reservation Acknowledgement";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("(//*[contains(text(), 'Number:')]/ancestor-or-self::td[1]/following-sibling::td[1])[1]");

                // Hotel Name
                $it['HotelName'] = $this->getField(["Your hotel:", 'Your Hotel:', 'Hotel Name:']);

                // CheckInDate
                $it['CheckInDate'] = strtotime($this->getField("Check-in:"));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->getField("Check-out:"));

                // Address
                $it['Address'] = join(', ', $this->http->FindNodes("//*[contains(normalize-space(text()), 'Address:')]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()]"));

                if (empty($it['Address'])) {
                    $node = join(', ', $this->http->FindNodes("//*[contains(normalize-space(text()), 'Address and Phone:')]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()]"));

                    if (preg_match("#(.+),\s*([\d\-\(\) \+]+)\s*$#s", $node, $m)) {
                        $it['Address'] = trim($m[1]);
                        $it['Phone'] = trim($m[2]);
                    } else {
                        $it['Address'] = trim($node);
                    }
                }

                // GuestNames
                $it['GuestNames'] = preg_split('/,\s*/', $this->http->FindSingleNode("//*[contains(normalize-space(text()), 'Guest name:') or contains(normalize-space(text()), 'Guest Name:')]/ancestor::td[1]/following-sibling::td[1]"));

                // Guests
                $it['Guests'] = $this->getField(["Guests per room:", 'Guests Per Room:', 'Number of Guests:']);

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->getField("Cancellation Policy:");

                // RoomType
                $it['RoomType'] = $this->getField(["Room type:", "Room type:*", 'Room Type:']);

                $it['Rooms'] = $this->getField("Number of Rooms:");

                $it['Total'] = cost($this->http->FindSingleNode("(//*[starts-with(normalize-space(text()), 'Total Room Charge')]/ancestor-or-self::td[1]/following-sibling::td[1]//text()[normalize-space(.)])[1]"));

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["from"], $this->reFrom) !== false || strpos($headers["subject"], $this->reSubject) !== false;
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
        return $this->http->FindSingleNode("(//*[{$this->eq($str)}]/ancestor-or-self::td[1]/following-sibling::td[1])[1]");
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(text())=\"{$s}\""; }, $field));
    }
}
