<?php

namespace AwardWallet\Engine\slh\Email;

class YourBookingConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "slh/it-1591388.eml, slh/it-1593009.eml, slh/it-2756619.eml";

    private $from = 'slh.com';

    private $detects = [
        'Your Booking Confirmation',
        'Your Small Luxury Hotels of the World reservation has now been cancelled',
    ];

    private $provider = 'Small Luxury Hotels';

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
                        return re("#Your\s+(?:confirmation|cancellation)\s+number\s+is\s+([A-Z\d]+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return BeautifulName(node('//*[contains(text(), \'Check in times:\')]/ancestor::tr[1]/preceding-sibling::tr[last()]'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $checkInDate = node("//tr[contains(., 'Guest Name') and contains(., 'Adults') and not(.//tr)]/following-sibling::tr[2]/td[2]", null, true, '/^(.+)\s+to/i');
                        $checkInTime = re('/Check-in\s+after\s+(.+M)/i');

                        return strtotime($checkInDate . ' ' . $checkInTime);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $checkOutDate = node("//tr[contains(., 'Guest Name') and contains(., 'Adults') and not(.//tr)]/following-sibling::tr[2]/td[2]", null, true, '/to\s+(.+)$/i');
                        $checkOutTime = re('/Check-out\s+before\s+(.+M)/i');

                        return strtotime($checkOutDate . ' ' . $checkOutTime);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Check in times:')]/ancestor::tr[1]/preceding-sibling::tr[2]", null, true, "/^(.*)Tel:/i");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Check in times:')]/ancestor::tr[1]/preceding-sibling::tr[2]", null, true, "/Tel:\s*(.+)$/i");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return beautifulName(node("//tr[contains(., 'Guest Name') and contains(., 'Adults') and not(.//tr)]/following-sibling::tr[2]/td[1]/descendant::td[1]"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return node("//tr[contains(., 'Guest Name') and contains(., 'Adults') and not(.//tr)]/following-sibling::tr[2]/td[5]");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return node("//tr[contains(., 'Guest Name') and contains(., 'Adults') and not(.//tr)]/following-sibling::tr[2]/td[6]/div[1]");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell("Cancellation policy", +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node("//tr[contains(., 'Guest Name') and contains(., 'Adults') and not(.//tr)]/following-sibling::tr[2]/td[4]", null, true, "#^(.+)\s*\(#i");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return cell('Room details', +1);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Total price', +1), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (node("//font[contains(., 'Your confirmation number is') and not(.//font)]")) {
                            return 'confirmed';
                        } elseif (node("//font[contains(., 'Your cancellation number is')]")) {
                            return 'canceled';
                        }

                        return '';
                    },
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false && strpos($body, $this->provider) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }
}
