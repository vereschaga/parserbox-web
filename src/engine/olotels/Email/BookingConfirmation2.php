<?php

namespace AwardWallet\Engine\olotels\Email;

class BookingConfirmation2 extends \TAccountCheckerExtended
{
    public $reFrom = "#olotels#i";
    public $reProvider = "#olotels#i";
    public $rePlain = "#Following\s+this\s+confirmation\s+email,\s+you\s+will\s+receive\s+a\s+voucher.*?reservation\s+on\s+the\s+olotels.com#is";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Your\s+booking\s+confirmation\s+with\s+Olotels\.com#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "olotels/it-1747097.eml, olotels/it-1747225.eml, olotels/it-1747230.eml, olotels/it-1747232.eml";
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
                        return cell('Booking n°', +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Check-in")]/following-sibling::tr[1]/td[1]//text()';
                        $subj = implode("\n", array_values(array_filter(nodes($xpath))));

                        if (preg_match('#(.*)\s+((?s).*)\s+(\d+)\s+Adult\(s\),\s+(\d+)\s+Child#i', $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Guests'    => $m[3],
                                'Kids'      => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $xpath = '//tr[contains(., "Check-in")]/following-sibling::tr[1]/td[position() >= 2]';
                        $dateNodes = nodes($xpath);

                        if (count($dateNodes) == 2) {
                            foreach (['CheckIn' => 0, 'CheckOut' => 1] as $key => $value) {
                                $res[$key . 'Date'] = strtotime(str_replace('/', '.', $dateNodes[$value]));
                            }

                            return $res;
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [cell('Client name', +1)];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $subj = node('//tr[contains(., "Room(s)")]/following-sibling::tr[1]/td[1]');

                        if (preg_match_all('#(\d+)\s*x\s*(.*)#', $text, $matches, PREG_SET_ORDER)) {
                            $roomsCount = 0;
                            $rooms = null;
                            $descriptions = null;

                            foreach ($matches as $m) {
                                $roomsCount += $m[1];

                                if (preg_match('#(.*)\s*-\s*(Breakfast.*)#', $m[2], $m2)) {
                                    $rooms[] = nice($m2[1]);
                                    $descriptions[] = nice($m2[2]);
                                } else {
                                    $rooms[] = $m[2];
                                    $descriptions[] = null;
                                }
                            }

                            return [
                                'Rooms'               => $roomsCount,
                                'RoomType'            => implode('|', $rooms),
                                'RoomTypeDescription' => implode('|', $descriptions),
                            ];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $regex = '#.*if\s+you\s+cancel\s+your\s+booking.*|.*not\s+refundable\s+in\s+case\s+of\s+cancellation.*#';

                        if (preg_match_all($regex, $text, $m)) {
                            return implode('. ', nice($m[0]));
                        }
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node('//tr[contains(., "Room(s)") or contains(., "Total room(s) without tax")]/following-sibling::tr[1]/td[3]'));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node('//tr[contains(., "Bank card fees") or contains(., "Taxes and fees")]/following-sibling::tr[1]/td[3]'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = node('//tr[contains(., "Total to be paid") or contains(., "Total amount to be paid") or contains(., "Total price")]/following-sibling::tr[1]/td[last()]');

                        if ($subj) {
                            return [
                                'Total'    => cost($subj),
                                'Currency' => currency($subj),
                            ];
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return cell('Client number', +1);
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
