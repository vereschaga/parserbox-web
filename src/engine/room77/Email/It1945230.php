<?php

namespace AwardWallet\Engine\room77\Email;

class It1945230 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?room77#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "3";
    public $reFrom = "#room77#i";
    public $reProvider = "#room77#i";
    public $caseReference = "8998";
    public $xPath = "";
    public $mailFiles = "";
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
                        $node = re("#\n\s*Booking Confirmation\s*([^\n]+)#");

                        if ($node != null) {
                            return $node;
                        } else {
                            $node = re("#\n\s*Trip Itinerary\s*:\s*([^\n]+)#");
                        }

                        if ($node != null) {
                            return $node;
                        } else {
                            $node = re("#\s*Itinerary Number\s*:\s*([^\n]+)#");
                        }

                        return $node;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Reservation Summary')]/following-sibling::div[1]//a");

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Dates')]/ancestor-or-self::div[1]//a");
                        }

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = re("#\n\s*Check-in\s*:\s*([^\n]+)#");
                        $time = "00:00";
                        $timenode = re("#\s*Check In Time:([^\n]+)#");

                        if ($timenode != null) {
                            $timenode = trim($timenode);
                            $time = $timenode;
                        } else {
                            $timenode = re("#\s*Check In: ([0-9][0-9][0-9][0-9])#");

                            if ($timenode != null) {
                                $timenode = trim($timenode);
                                $time = $timenode;
                            }
                        }
                        $date = \DateTime::createFromFormat($time !== "00:00" ? "m-d-Y Hi" : "m-d-Y H:i", $node . '  ' . $time);

                        return $date ? $date->getTimestamp() : null;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $node = re("#\n\s*Check-out\s*:\s*([^\n]+)#");
                        $time = "00:00";
                        $timenode = re("#\s*Check Out Time:([^\n]+)#");

                        if ($timenode != null) {
                            $timenode = trim($timenode);
                            $time = $timenode;
                        } else {
                            $timenode = re("#\s*Check Out: ([0-9][0-9][0-9][0-9])#");

                            if ($timenode != null) {
                                $timenode = trim($timenode);
                                $time = $timenode;
                            }
                        }

                        $node = $node . "  " . $time;

                        if ($time != "00:00") {
                            $date = \DateTime::createFromFormat("m-d-Y Hi", $node);
                        } else {
                            $date = \DateTime::createFromFormat("m-d-Y H:i", $node);
                        }
                        $date = $date->getTimestamp();

                        return $date;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Reservation Summary')]/following-sibling::div[1]//p[2]");

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Dates')]/ancestor-or-self::div[1]//p[2]");
                        }

                        return $node;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Reservation Summary')]/following-sibling::div[1]/table[1]//b");

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Dates')]/ancestor-or-self::div[1]/table[1]//b");
                        }

                        return $node;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $node = re("#- ([^\n]+) -\s*[0-9]\s*adult\(s\)#");

                        return nice($node);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $node = re("#\s*([0-9])\s*adult\(s\)#");

                        return $node;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell("Number of Rooms:", +1);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return cell("Daily Rate:", +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Cancellation Policy:", +1);

                        return $node;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Room Type:", +1);
                        $node2 = explode("/", $node);

                        if (!isset($node2[1])) {
                            $node = explode("-", $node);
                            $type = $node[0];
                            unset($node[0]);
                            $node = implode("-", $node);
                        } else {
                            $type = $node2[0];
                            unset($node2[0]);
                            $node = implode("/", $node2);
                        }

                        return [
                            'RoomType'            => $type,
                            'RoomTypeDescription' => $node,
                        ];
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Subtotal:", +1);

                        return total($node, "Cost");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Tax", +1);

                        return cost($node);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Total", +1);

                        return cost($node);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Your reservation is cancelled.#");

                        if ($node != null) {
                            return "cancelled";
                        }
                        $node = re("#We confirmed with #");

                        if ($node != null) {
                            return "confirmed";
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Your reservation is cancelled.#");

                        if ($node != null) {
                            return true;
                        }
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
