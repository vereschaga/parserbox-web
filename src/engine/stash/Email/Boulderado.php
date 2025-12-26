<?php

namespace AwardWallet\Engine\stash\Email;

class Boulderado extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@boulderado.com#i";
    public $reProvider = "#boulderado.com#i";
    public $rePlain = "#We look forward to your stay at the Historic Hotel Boulderado.#i";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Hotel Boulderado: Your Reservation Confirmation#i";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "stash/it-1589985.eml";

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
                        $xpath = "//*[contains(text(), 'Confirmation Number')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#We look forward to your stay at the ([^\.]+)\.#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Arrival Date')]/ancestor::td[1]/following-sibling::td[1]";
                        $datetimeStr = str_replace(',', '', node($xpath));
                        $xpath = "//*[contains(text(), 'Check-In Time')]/ancestor::td[1]/following-sibling::td[1]";
                        $datetimeStr .= ', ' . node($xpath);

                        return strtotime($datetimeStr);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Departure Date')]/ancestor::td[1]/following-sibling::td[1]";
                        $datetimeStr = str_replace(',', '', node($xpath));
                        $xpath = "//*[contains(text(), 'Check-Out Time')]/ancestor::td[1]/following-sibling::td[1]";
                        $datetimeStr .= ', ' . node($xpath);

                        return strtotime($datetimeStr);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Room Type')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Nightly Rate')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Cancellation Policy')]/ancestor::td[1]/following-sibling::td[1]";

                        return node($xpath);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $xpath = "/descendant::table[1]/tbody/tr[last()]//text()";
                        $subj = join(' ', nodes($xpath));
                        $subj = str_replace('•', ' ', $subj);

                        $res['Address'] = trim(re('#\s+(.*)\s+Direct:#', $subj));
                        $res['Phone'] = trim(re('#Direct:\s+([\d\s\(\)\-]+)\s+#'));

                        $regex = '#(?P<Address>.*),\s+(?P<State>\w+)\s+(?P<PCode>\d+)#';

                        if (preg_match($regex, $res['Address'], $m)) {
                            $da['AddressLine'] = $m['Address'];
                            $da['StateProv'] = $m['State'];
                            $da['PostalCode'] = $m['PCode'];
                            $res['DetailedAddress'] = $da;
                        }

                        return $res;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Guest Name')]/ancestor::td[1]/following-sibling::td[1]";

                        return [node($xpath)];
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
