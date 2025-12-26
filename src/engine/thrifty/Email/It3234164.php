<?php

namespace AwardWallet\Engine\thrifty\Email;

class It3234164 extends \TAccountCheckerExtended
{
    public $mailFiles = "thrifty/it-3234164.eml";

    public $reBody = "Thank you for booking with Thrifty!";
    public $reSubject = "THRIFTY RESERVATION ";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response['body']);

                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = re("#Your confirmation number is\s+(\w+)#i", $text);

                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(re("#Pickup\n.*?Date/Time:\s+([^\n]+)#msi", $text));

                // PickupLocation
                $it['PickupLocation'] = re("#Pickup\n.*?Location:\s+([^\n]+)#msi", $text);

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(re("#Return\n.*?Date/Time:\s+([^\n]+)#msi", $text));

                // DropoffLocation
                $it['DropoffLocation'] = re("#Return\n.*?Location:\s+([^\n]+)#msi", $text);

                // PickupPhone
                $it['PickupPhone'] = re("#Pickup\n.*?Phone Number:\s+([^\n]+)#msi", $text);

                // PickupFax
                // PickupHours
                $it['PickupHours'] = re("#Pickup\n.*?Location Hours:\s+([^\n]+)#msi", $text);

                // DropoffPhone
                $it['DropoffPhone'] = re("#Return\n.*?Phone Number:\s+([^\n]+)#msi", $text);

                // DropoffHours
                $it['DropoffHours'] = re("#Return\n.*?Location Hours:\s+([^\n]+)#msi", $text);

                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = re("#Vehicle:\s+([^\n]+)#i", $text);

                // CarModel
                // CarImageUrl
                // RenterName
                $it['RenterName'] = re("#Customer Name:\s+([^\n]+)#i", $text);

                // PromoCode
                // TotalCharge
                $it["TotalCharge"] = cost($this->http->FindSingleNode('//text()[contains(.,"APPROXIMATE RENTAL CHARGE:")]'));

                // Currency
                $it["Currency"] = currency($this->http->FindSingleNode('//text()[contains(.,"APPROXIMATE RENTAL CHARGE:")]'));

                // TotalTaxAmount
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // ServiceLevel
                // Cancelled
                // PricedEquips
                // Discount
                // Discounts
                // Fees
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], $this->reSubject) !== false;
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

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
