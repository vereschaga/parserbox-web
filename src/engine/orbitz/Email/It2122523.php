<?php

namespace AwardWallet\Engine\orbitz\Email;

class It2122523 extends \TAccountCheckerExtended
{
    public $mailFiles = "orbitz/it-2122523.eml, orbitz/it-2122531.eml";

    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@orbitz[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@orbitz[.]com#i";
    public $reProvider = "#[@.]orbitz[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $pdfRequired = "0";

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
                        return orval(
                            re_white('Confirmation code: (\w+)'),
                            re_white('Orbitz record locator: (\w+)')
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return between('Hotel name:', 'Address:');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Check-in date:', 'Check-out date:');
                        $dt = uberDateTime($dt);

                        return strtotime($dt);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $dt = between('Check-out date:', 'Total number of guests:');
                        $dt = uberDateTime($dt);

                        return strtotime($dt);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return between('Address:', 'Phone number:');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return between('Phone number:', 'Check-in date:');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [between('Guest name:', 'Hotel name:')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('Total number of guests: (\d+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re_white('Total number of rooms: (\d+)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re_white('Average rate per night: (.\d+[.]\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $pol = node("//*[contains(text(), 'Cancellation:')]/following::*[1]");

                        return nice($pol);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Total number of rooms: \d+ rooms?
							(?P<RoomType> .+?) (?:-|,) (?P<RoomTypeDescription> .+?)
							(?:Special Requests:|Rate description:)
						');

                        return re2dict($q, $text);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Taxes & fees : (.\d+[.]\d+)');

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Amount charged to your credit card: (.\d+[.]\d+)');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('This e-mail confirms your hotel reservation')) {
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
