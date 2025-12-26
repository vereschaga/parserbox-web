<?php

namespace AwardWallet\Engine\onetwotrip\Email;

class Hotel extends \TAccountCheckerExtended
{
    public $mailFiles = "onetwotrip/it-3327920.eml";
    public $reBody = "onetwotrip";
    public $reBody2 = "Отель:";
    public $reFrom = "#noreply@onetwotrip.com#";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = re("#Информация\s+по\s+заказу\s+(\w+)#", $text);

                // TripNumber
                // ConfirmationNumbers

                // Hotel Name
                $it['HotelName'] = re("#Отель\s*:\s*([^\n]+)#", $text);

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(re("#Заезд\s*:\s*(\d{4}-\d+-\d+)#", $text));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(re("#Выезд\s*:\s*(\d{4}-\d+-\d+)#", $text));

                // Address
                $it['Address'] = re("#Адрес\s*:\s*([^\n]+)#", $text);

                // DetailedAddress

                // Phone

                // Fax
                // GuestNames
                $it['GuestNames'] = [re("#Бронирование\s+оформлено\s+на\s+(.*?)\.#", $text)];

                // Guests
                // Kids
                // Rooms
                // Rate
                // RateType

                // CancellationPolicy
                // RoomType
                $it['RoomType'] = re("#Номер\s*:\s*([^\n]+)#", $text);

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                // Currency
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
        return preg_match($this->reFrom, $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($this->reFrom) && isset($headers['from']) && preg_match($this->reFrom, $headers["from"]);
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
        return ["ru"];
    }
}
