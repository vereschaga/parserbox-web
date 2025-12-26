<?php

namespace AwardWallet\Engine\jetsetter\Email;

class It extends \TAccountCheckerExtended
{
    public $reFrom = "#@jetsetter#i";
    public $reProvider = "#@jetsetter#i";
    public $rePlain = "#Jetsetter will send you a separate Itinerary Email with a confirmation number from the property.#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "jetsetter/it.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Jetsetter Reservation \#([\w-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Jetsetter Reservation')]/ancestor-or-self::tr[1]/following-sibling::tr[2]//td[2]";

                        $res = [];
                        $info = text(xpath($xpath));
                        $info = re('#(.+)(?:[ ]*\n[ ]*\n|$)#s', $info); // remove duplicate if present

                        $name = re('#(.+)\n#', $info);
                        $addr = re('#\n(.+)http#s', $info);
                        $addr = preg_replace('#\s+#', ' ', $addr);
                        $res['HotelName'] = trim($name);
                        $res['Address'] = trim($addr);

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Check In/Check Out')]/ancestor-or-self::tr[1]/following-sibling::tr[2]//td[3]";
                        $dates = nodes($xpath)[0];

                        if (preg_match('#(.+)/(.+)#', $dates, $ms)) {
                            return [
                                'CheckInDate'  => totime(uberDateTime($ms[1])),
                                'CheckOutDate' => totime(uberDateTime($ms[2])),
                            ];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#(cancellations.+)\s*this\s*info#is"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//*[contains(text(), 'Room Type')]/ancestor-or-self::tr[1]/following-sibling::tr[2]//td[2]";
                        $info = text(xpath($xpath));

                        $type = re('#(.+?)(?:\s{2,}|$)#', $info);

                        if (preg_match('#\s*(.+)\s*-\s*(.+)\s*#', $type, $ms)) {
                            return [
                                'RoomType'            => $ms[1],
                                'RoomTypeDescription' => $ms[2],
                            ];
                        } else {
                            return trim($type);
                        } // no desc. found
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $taxes = cell('Subtotal', +1);

                        return cost($taxes);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $taxes = cell('Taxes & Fees', +1);

                        return cost($taxes);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $total = cell('Total Cost', +1);

                        return [
                            'Total'    => cost($total),
                            'Currency' => currency($total),
                        ];
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
