<?php

namespace AwardWallet\Engine\mileageplus\Email;

class UnitedReservation extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#United\s+Reservation\s+\d+#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#mileageplus-carhotel@united\.com#i";
    public $reProvider = "#united\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
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
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation:\s+([\w\-]+)#i');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return cell('Pick-up location:', +1);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Pick-up:', +1));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return orval(cell('Drop-off location:', +1), $it['PickupLocation']);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(cell('Drop-off:', +1));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re('#\s*(.*)\s+Car\s+type#');
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#:\s+(Intermediate)\s+(.*)#i', cell('Car type'), $m)) {
                            return [
                                'CarType'  => $m[1],
                                'CarModel' => $m[2],
                            ];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#LEAD\s+TRAVELL?ER\s+(.*)#i');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $s = cell('Payments received', +1);

                        if (preg_match('#(?:([\d,]+\s+miles)\s+\+\s+)?(.*)#i', $s, $m)) {
                            return [
                                'SpentAwards' => $m[1] ? $m[1] : null,
                                'TotalCharge' => cost($m[2]),
                                'Currency'    => currency($m[2]),
                            ];
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Booking\s+Date\s+(\d+/\d+/\d+)#'));
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
