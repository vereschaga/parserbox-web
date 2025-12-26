<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class CarRentalReservationConfirmationDetails extends \TAccountCheckerExtended
{
    public $reFrom = "#reservations@budget.ie#i";
    public $reProvider = "#budget.ie#i";
    public $rePlain = "#Thank\s+you\s+for\s+booking\s+your\s+car\s+rental\s+on\s+www\.Budget\.ie#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1805953.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');

                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Booking\s+Number:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick Up', 'Dropoff' => 'Return'] as $key => $value) {
                            $regex = '#' . $value . '\s+Date\s*/\s*Time:\s+';
                            $regex .= '\w+,\s+(?P<Day>\d+)\w+\s+(?P<Month>\w+)\s+(?P<Year>\d+),\s+';
                            $regex .= '(?P<Time>\d+:\d+\s*(?:am|pm)?)';
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                $s = $m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ', ' . $m['Time'];
                                $res[$key . 'Datetime'] = strtotime($s);
                            }

                            $res[$key . 'Location'] = re('#' . $value . '\s+Location:\s+(.*)#i');
                        }

                        return $res;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Reserved\s+for:\s+(.*)#i');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Rental\s+Price\s+(.*)#i'));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#We\s+confirm\s+that\s+a\s+reservation\s+has\s+been\s+created#i', $text)) {
                            return 'Confirmed';
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
