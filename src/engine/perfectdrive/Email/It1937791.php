<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class It1937791 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Your reservation is confirmed for.*?Budget Rent a Car#is', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Budget Reservation Confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#budget(?:group)?\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#budget(?:group)?\.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "05.08.2015, 22:30";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "perfectdrive/it-1937791.eml, perfectdrive/it-2941685.eml, perfectdrive/it-2955098.eml";
    public $re_catcher = "#.*?#";
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
                        return re('#Reservation\s+Number\s*([\w\-]+)#i');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['PickupLocation'])) {
                            return preg_replace("#\s+Suburb\s*:\s*([^\n]+)#", ", \\1", re("#\n\s*Pickup\s+Date\s*:.+?\n\s*Location\s*:\s*(.+?)\s*\n\s*Phone\s*:#s"));
                        }

                        return $it['PickupLocation'];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        $key = 'Pickup';
                        $value = ['PICKUP', 'Pickup'];

                        $subj = cell($value, 0, 0, '//text()');
                        $regex = '#';
                        $regex .= '(?:' . implode('|', $value) . ')\s+';
                        $regex .= '(.*)\s+';
                        $regex .= '((?s).*)\s*';
                        $regex .= '\n\s*([\d -\(\)+]+)\s*\n';
                        $regex .= '#i';

                        if (preg_match($regex, $subj, $matches)) {
                            $res[$key . 'Datetime'] = strtotime($matches[1]);
                            $res[$key . 'Location'] = nice($matches[2], ',');
                            $res[$key . 'Phone'] = $matches[3];
                        }

                        if (!isset($res)) {
                            return timestamp_from_format(nice(re("#\n\s*Pickup\s+Date\s*:\s*([^\n]+\d+\s+\d+:\d+\s+\w+)\s*\n#")), "d/m/Y H:i A");
                        }

                        return $res;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        $key = 'Dropoff';
                        $value = ['RETURN', 'Return'];

                        $subj = cell($value, 0, 0, '//text()');
                        $regex = '#';
                        $regex .= '(?:' . implode('|', $value) . ')\s+';
                        $regex .= '(.*)\s+';
                        $regex .= '((?s).*)\s*';
                        $regex .= '\n\s*([\d -\(\)+]+)\s*\n';
                        $regex .= '#i';

                        if (preg_match($regex, $subj, $matches)) {
                            $res[$key . 'Datetime'] = strtotime($matches[1]);
                            $res[$key . 'Location'] = nice($matches[2], ',');
                            $res[$key . 'Phone'] = $matches[3];
                        }

                        if (!isset($res)) {
                            return preg_replace("#\s+Suburb\s*:\s*([^\n]+)#", ", \\1", re("#\n\s*Return\s+Date\s*:.+?\n\s*Location\s*:\s*(.+?)\s*\n\s*Phone\s*:#s"));
                        }

                        return $res;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['DropoffDatetime'])) {
                            return timestamp_from_format(nice(re("#\n\s*Return\s+Date\s*:\s*([^\n]+\d+\s+\d+:\d+\s+\w+)\s*\n#")), "d/m/Y H:i A");
                        }

                        return $it['DropoffDatetime'];
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['PickupPhone'])) {
                            return re("#\n\s*Pickup\s+Date\s*:.+?\n\s*Phone\s*:\s*(.+?)\s*\n#s");
                        }

                        return $it['PickupPhone'];
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['PickupHours'])) {
                            return nice(re("#\n\s*Pickup\s+Date\s*:.+?\n\s*Opening\s+Hours\s*:\s*(.+?)\s*\n\s*(?>\[view\s+map\]|Return|VEHICLE)#si"), " ");
                        }

                        return $it['PickupHours'];
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['PickupPhone'])) {
                            return re("#\n\s*Return\s+Date\s*:.+?\n\s*Phone\s*:\s*(.+?)\s*\n#s");
                        }

                        return $it['PickupPhone'];
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['DropoffHours'])) {
                            return nice(re("#\n\s*Return\s+Date\s*:.+?\n\s*Opening\s+Hours\s*:\s*(.+?)\s*\n\s*(?>\[view\s+map\]|Return|VEHICLE)#si"), " ");
                        }

                        return $it['DropoffHours'];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Budget Rent a Car#i");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['CarType'])) {
                            return nice(re("#\n\s*VEHICLE[\s:]+.+?or\s+similar\s*\n\s*(.+?)\s+RENTAL\s+COST#si"));
                        }

                        return $it['CarType'];
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        if (!isset($it['CarModel'])) {
                            return nice(re("#\n\s*VEHICLE[\s:]+(.+?or\s+similar)\s*\n#si"));
                        }

                        return $it['CarModel'];
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//img[contains(@src, '/Vehicle')]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Driver\s*:\s*([^\n]*?)\s*-\s#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Estimated\s+Total\s+(.*)#'));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+reservation\s+is\s+(confirmed)\s+for#i');
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
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
