<?php

namespace AwardWallet\Engine\triprewards\Email;

class It2119262 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@wyndham[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "#www[.]wyndham[.]com#";
    public $rePDFRange = "-1000";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@wyndham[.]com#i";
    public $reProvider = "#[@.]wyndham[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "triprewards/it-2.eml, triprewards/it-2119262.eml, triprewards/it-4.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re_white('Confirmation No\.  (\d+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = between('choosing the', 'for your upcoming visit');
                        $addr = nice(orval(
                            re("#{$name}\s+(.{0,100})Tel:#ms", $text),
                            $name
                        ));

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Arrival Date:  ([^\n]+)');
                        $date = preg_replace("#^(\w+)\s+(\d+)\w+\s+(\d{4})$#", "$2 $1 $3", $date);
                        $date = preg_replace("#^(\d{1,2})\-(\d{1,2})\-(\d{2,4})$#", "$3-$1-$2", $date);

                        return strtotime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Departure Date:  ([^\n]+)');
                        $date = preg_replace("#^(\w+)\s+(\d+)\w+\s+(\d{4})$#", "$2 $1 $3", $date);
                        $date = preg_replace("#^(\d{1,2})\-(\d{1,2})\-(\d{2,4})$#", "$3-$1-$2", $date);

                        return strtotime($date);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = between('guest reservation for:', 'Reservation Information:');

                        return [$name];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('Adults/Children:  (\d+) / (?:\d+)');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re_white('Adults/Children:  (?:\d+) / (\d+)');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re_white('No\. of Rooms:  (\d+)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re_white('Room Rate .+?  (\d+[.]\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re_white('
							Cancellation / Deposit  Information:
							(?: .+?)
							Cancellation  (.+?)
							Information/Policy:
						');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = between('Room Description:', 'Arrival Room');
                        $info = clear('/Wyndham\s+Rewards/i', $info);

                        $q = white('(.+? [.]) (.+)');

                        if (preg_match("/$q/isu", $info, $ms)) {
                            return [
                                'RoomType'            => nice(re("#(.*?)(?:,|$)#", $ms[1])),
                                'RoomTypeDescription' => nice($ms[2]),
                            ];
                        }

                        return $info;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return re_white('Total cost .+?  (\d+[.]\d+)');
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re_white('Room Rate .+? information in (\w+) :');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Reservation Confirmation')) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('(\d+ - \d+ - \d+) Thank you for choosing');
                        $date = \DateTime::createFromFormat('m-d-y|', $date);
                        $date = $date ? $date->getTimestamp() : null;

                        return $date;
                    },
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }
}
