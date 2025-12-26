<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class It2667537 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['InterContinental Hotels Group#i', 'blank', '-1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]ihg[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.04.2015, 10:59";
    public $crDate = "28.04.2015, 10:19";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $x = xpath("
						//*[normalize-space(text()) = 'Check In:']
						/ancestor::table[contains(., 'confirmation number')][1]
					");

                    if ($x->length > 1) {
                        return [$x->item(1)];
                    }
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('confirmation number is : (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node(".//*[
							contains(normalize-space(text()), 'View Map')
						]/preceding::a[2]");
                        $addr = between($name, 'View Map');

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = uberDate(1);
                        $time = uberTime(1);

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = uberDate(2);
                        $time = uberTime(2);

                        $dt = strtotime($date);
                        $dt = strtotime($time, $dt);

                        return $dt;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Hotel Front Desk: ([\d-\s]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Name: (.+?) \n');

                        return [$name];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Adults:', 0, +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Rooms:', 0, +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('Cancellation Policy: (.+?) Rate Description:');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(node('.//*[contains(text(), "Rate Type:")]/preceding::span[1]'));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Taxes:', +1);

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Total Price:', +1);

                        return total($x, 'Total');
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return reni('Member \#: (\d+)');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Reservation Confirmed')) {
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
