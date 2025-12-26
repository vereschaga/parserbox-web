<?php

namespace AwardWallet\Engine\national\Email;

class It1855443 extends \TAccountCheckerExtended
{
    public $reFrom = "#@nationalcar[.]com#i";
    public $reProvider = "#[@.]nationalcar[.]com#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]nationalcar[.]com#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "national/it-1855443.eml, national/it-1857914.eml, national/it-5.eml, national/it-6.eml";
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
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation\s*[\#]\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = node("//*[contains(text(), 'Pickup Information')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[3]");

                        return nice($loc);
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = node("//*[contains(text(), 'Pickup Information')]/ancestor-or-self::tr[1]/following-sibling::tr[2]/td[3]");

                        return totime(uberDateTime($dt));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $loc = node("//*[contains(text(), 'Dropoff Information')]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[3]");

                        return nice($loc);
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = node("//*[contains(text(), 'Dropoff Information')]/ancestor-or-self::tr[1]/following-sibling::tr[2]/td[3]");

                        return totime(uberDateTime($dt));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Phone:', +2));
                    },

                    "PickupFax" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Fax:', +2));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Hours:', +2));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $car = cell('Vehicle Type:', +2);

                        if (preg_match('/(.*)\s*-\s*(.*or\s*similar)/i', $car, $ms)) {
                            return [
                                'CarType'  => nice($ms[1]),
                                'CarModel' => nice($ms[2]),
                            ];
                        }

                        return nice($car); // if no model found
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = cell('Name:', +2);

                        return nice($name);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $tot = re('/Total\s*Estimate[.]*(.\d+[.]\d+)/iu');

                        return total($tot);
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $taxes = re('/Taxes:.*?Total\s*Estimate/is');

                        if (preg_match_all('/[^(\s\d](\d+[.]\d+)/', $taxes, $ms)) {
                            $res = 0;

                            foreach ($ms[1] as $x) {
                                $res += floatval($x);
                            }

                            return $res;
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Status:\s*(\w+)#i");
                    },

                    "Fees" => function ($text = '', $node = null, $it = null) {
                        $fees = re('/Surcharges:(.*?)Taxes:/is');

                        if (preg_match_all('/^\s*(.*?)\s*.(\d+[.]\d+)\s*$/im', $fees, $ms)) {
                            $names = $ms[1];
                            $charges = $ms[2];

                            $res = [];

                            for ($i = 0; $i < sizeof($names); $i++) {
                                $res[] = [
                                    'Name'   => $names[$i],
                                    'Charge' => floatval($charges[$i]),
                                ];
                            }

                            return $res;
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
