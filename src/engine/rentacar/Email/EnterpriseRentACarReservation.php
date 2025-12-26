<?php

namespace AwardWallet\Engine\rentacar\Email;

class EnterpriseRentACarReservation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Visit enterprise.co.uk#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = "";
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "22.06.2016, 10:21";
    public $crDate = "22.06.2016, 10:04";
    public $xPath = "";
    public $mailFiles = "rentacar/it-3952756.eml";
    public $re_catcher = "#.*?#";
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
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+reservation\s+number\s+is\s+(\d+)#');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= 'PICK-UP DETAILS\s+LOCATION\s+(.*)\s+';
                        $r .= 'DATE & TIME\s+(.*)\s+';
                        $r .= 'ADDRESS\s+((?s).*?)\s+';
                        $r .= 'PHONE\s+(.*)\s+';
                        $r .= 'HOURS\s+(.*)';
                        $r .= '#i';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'PickupLocation' => $m[1] . ', ' . nice($m[3], ','),
                                'PickupDatetime' => strtotime(str_replace(' @', ',', $m[2])),
                                'PickupPhone'    => $m[4],
                                'PickupHours'    => $m[5],
                            ];
                        }
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= 'RETURN DETAILS\s+LOCATION\s+(.*)\s+';
                        $r .= 'DATE & TIME\s+(.*)\s+';
                        $r .= 'ADDRESS\s+((?s).*?)\s+';
                        $r .= 'PHONE\s+(.*)\s+';
                        $r .= 'HOURS\s+(.*)';
                        $r .= '#i';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'DropoffLocation' => $m[1] . ', ' . nice($m[3], ','),
                                'DropoffDatetime' => strtotime(str_replace(' @', ',', $m[2])),
                                'DropoffPhone'    => $m[4],
                                'DropoffHours'    => $m[5],
                            ];
                        }
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#VEHICLE\s+CLASS\s+(.*)\s+(.*\s+or\s+similar)#i', $text, $m)) {
                            return [
                                'CarType'  => $m[1],
                                'CarModel' => $m[2],
                            ];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#RENTER\s+DETAILS\s+NAME\s+(.*)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#ESTIMATED\s+TOTAL\s+(.*)#'));
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
