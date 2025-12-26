<?php

namespace AwardWallet\Engine\jalhotels\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $reBody = "www.nikko-jalcity.com";
    public $reBody2 = "www.jalhotels.com";
    public $reBody3 = "RESERVATION CONFIRMATION:";
    public $reSubject = "JAL Hotels - Reservation Confirmation";
    public $reFrom = "jalhotels.com";
    public $reProvider = [
        ['#[@.]jalhotels#i', 'us', ''],
    ];
    public $mailFiles = "jalhotels/it-2518337.eml, jalhotels/it-2685820.eml, jalhotels/it-3435109.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return cell("Confirmation Number:", +1, 0);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= '\w+,\s*\w+\s+\d+,\s*\d{4}\s+to\s+\w+,\s*\w+\s*\d+,\s*\d{4}\s+';
                        $r .= '([^\n]+)\s+';
                        $r .= '(.*?)';
                        $r .= '\n\s*Tel:\s*([\d\-\(\)+ ]+)\s*';
                        $r .= '\n\s*Fax\s*:\s*([\d\-\(\)+ ]+)';
                        $r .= '#s';
                        $s = preg_replace('#\n\s*>#', "\n", $this->getDocument('plain'));

                        if (preg_match($r, $s, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2]),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Arrival Date:", +1, 0) . ',' . cell('Arrival Flight/Time', +1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Departure Date:", +1, 0) . ',' . cell('Departure Flight/Time', +1));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell("Guest Name:", +1, 0);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell("Number of guests:", +1, 0);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell("Number of Rooms:", +1, 0);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#^[^\n]*?([A-Z]{3}\s*[\d.,]+)#", cell("Daily Room Rate:", +1, 0, "//text()"));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell("Cancellation Policy:", +1, 0);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell("Room Type:", +1, 0);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return cell("Room Description:", +1, 0);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cell("Tax/Service Charge", +1, 0);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total Room Cost:", +1, 0), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#we are pleased to (\w+) the following#ix");
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return (strpos($body, $this->reBody) !== false || strpos($body, $this->reBody2) !== false) && strpos($body, $this->reBody3) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
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
