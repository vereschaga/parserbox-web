<?php

namespace AwardWallet\Engine\hotwire\Email;

class It1979321 extends \TAccountCheckerExtended
{
    public $rePlain = "#trademarks\s+or\s+trademarks\s+of\s+Hotwire#i";
    public $rePlainRange = "-500";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@hotwire[.]com#i";
    public $reProvider = "#[@.]hotwire[.]com#i";
    public $caseReference = "6934";
    public $xPath = "";
    public $mailFiles = "hotwire/it-1979321.eml, hotwire/it-1982896.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // to stop parsing it-3 and it-7 early, they take time.
                    $q = whiten('This is confirmation of your booked trip\.');

                    if (!re("#$q#i")) {
                        return;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('Hotel confirmation: ([\w-]+)');

                        return re("#$q#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'Hotwire Customer Care')]/ancestor::*[1]/div[2]");
                        $addr = between($name, 'Phone :');

                        return [
                            'HotelName' => nice($name),
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dates = node("//*[contains(text(), 'Dates')]/following::tr[1]/td[1]");

                        if (!preg_match('/(.+?)\s*-\s*(.+)/', $dates, $ms)) {
                            return;
                        }
                        $date1 = nice($ms[1]);
                        $date2 = nice($ms[2]);

                        return [
                            'CheckInDate'  => strtotime($date1),
                            'CheckOutDate' => strtotime($date2),
                        ];
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return between('Phone :', 'Hotel confirmation:');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(text(), 'Dates')]/following::li[1]");
                        $q = whiten('(.+?) must be present');
                        $name = re("/$q/i", $info);

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $n = node("//*[contains(text(), 'Dates')]/following::tr[1]/td[2]");

                        return re("#(\d+)#", $n);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        $n = node("//*[contains(text(), 'Dates')]/following::tr[1]/td[3]");

                        return re("#(\d+)#", $n);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $n = node("//*[contains(text(), 'Dates')]/following::tr[1]/td[4]");

                        return re("#(\d+)#", $n);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = node("//*[contains(text(), '/night') or contains(text(), '/ night')]");

                        return nice($rate);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between(
                            'Hotel cancellation policy',
                            'Hotwire Low Price Guarantee'
                        );
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Subtotal', +1);

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Tax recovery charges & fees:', +1);

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Total trip price', +1);

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $q = whiten('This is confirmation of your booked trip\.');

                        if (re("#$q#i")) {
                            return 'confirmed';
                        }
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
