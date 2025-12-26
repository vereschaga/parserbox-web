<?php

namespace AwardWallet\Engine\hertz\Email;

class ReservationText extends \TAccountCheckerExtended
{
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $rePlain = "#Thanks\s+for\s+Traveling\s+at\s+the\s+Speed\s+of\s+Hertz#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#My\s+Hertz\s+Reservation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("text/plain");

                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number:\s*([\w\d-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['Pickup' => 'Pickup(?:\s+and\s+Return)?', 'Dropoff' => 'Return'] as $key => $value) {
                            $regex = '#';
                            $regex .= "\n\s*${value}\s+Location:\s+(?P<${key}Location>(?s).*?)";
                            $regex .= 'Location\s+Type:\s+.*\s+';
                            $regex .= "Hours\s+of\s+Operation:\s+(?P<${key}Hours>.*)\s+";
                            $regex .= "Phone\s+Number:\s+(?P<${key}Phone>.*)\s+";
                            $regex .= "Fax\s+Number:\s+(?P<${key}Fax>.*)\s+";
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                $keys = ["${key}Location", "${key}Phone", "${key}Fax", "${key}Hours"];
                                copyArrayValues($res, $m, $keys);
                                $res["${key}Location"] = nice(glue($res["${key}Location"]));

                                if (preg_match('#Pickup\s+and\s+Return#', $m[0])) {
                                    foreach ($keys as $k) {
                                        $newk = str_replace('Pickup', 'Dropoff', $k);
                                        $res[$newk] = $res[$k];
                                    }
                                }
                            }
                            //	$regex = "#\n\s*${value}\s+Location:\s+(.*?)\s+Location Type:#ms";
                        //	$res["${key}Location"] = nice(glue(re($regex)));
                        //	$res["${key}Phone"] = re("#\n\s*${value}\s+Location:.*?\n\s*Phone\s+Number:\s*([^\n]+)#ms");
                        //	$res["${key}Fax"] = re("#\n\s*${value}\s+Location:.*?\n\s*Fax\s+Number:\s*([^\n]+)#ms");
                        //	$res["${key}Hours"] = re("#\n\s*${value}Location:.*?\n\s*Hours\s+of\s+Operation:\s*([^\n]+)#ms");
                        }

                        return $res;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#\s+at\s+#", re("#Pickup Time:\s*([^\n]+)#")));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#\s+at\s+#", re("#Return Time:\s*([^\n]+)#")));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return [
                            'RentalCompany' => re("#Thanks for Traveling at the Speed of\s+([\w\s]+),\s*(.*)#"),
                            'RenterName'    => re(2),
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarModel' => nice(re("#\n\s*Your Vehicle\s+([\w\s]+)\s+([^\n]+)#")),
                            'CarType'  => re(2),
                        ];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Total Approximate Charge\s+([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#Total Approximate Charge\s+([^\n]+)#"));
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Service Type:\s*([^\n]+)#");
                    },

                    "Discounts" => function ($text = '', $node = null, $it = null) {
                        //$subj = re('#Discounts\s+(.*?)\n\s*\n#s');
                        //if (preg_match_all('#(.*):\s+(.*)#', $subj, $m, PREG_SET_ORDER)) {
                        //}
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
