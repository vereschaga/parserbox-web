<?php

namespace AwardWallet\Engine\rentacar\Email;

class It1925591 extends \TAccountCheckerExtended
{
    public $rePlain = "#From\s*:\s*Enterprise\s*Rent-A-Car\s*Reservation#i";
    public $rePlainRange = "";
    public $reHtml = "#Enterprise\s*Rent-A-Car#i";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Enterprise\s*Rent-A-Car\s*Reservation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@enterprise[.]com#i";
    public $reProvider = "#[@.]enterprise[.]com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "rentacar/it-1.eml, rentacar/it-1620860.eml, rentacar/it-1925591.eml, rentacar/it-1938319.eml, rentacar/it-1955156.eml, rentacar/it-2.eml, rentacar/it-3.eml, rentacar/it-3079809.eml, rentacar/it-9015018.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // echo $text;
                    return [text($text)];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation\s*Number:\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(orval(
                            re("#and\s*Phone\s*Number\s*:\s*(.+?)\s*Tel[.]?\s*:#is"),
                            re("#Car Located at\s*:\s*([^\n]+)#")
                        ));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = re("#Pick\s*Up\s*Date:\s*(.+)#i");
                        $dt = str_replace(' at ', ' ', $dt);

                        return strtotime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        // same as pick up
                        return nice(orval(
                            re("#and\s*Phone\s*Number\s*:\s*(.+?)\s*Tel[.]?\s*:#is"),
                            re("#Car Located at\s*:\s*([^\n]+)#")
                        ));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = re("#Drop\s*Off\s*Date:\s*(.+)#i");
                        $dt = str_replace(' at ', ' ', $dt);

                        return strtotime($dt);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $phone = re("#Tel[.]?:\s*([\d\(\) \-\+]{5,})\s*(Pick\s*Up\s*Location|Car Located at)#is");

                        if (empty($phone)) {
                            $phone = re("#and\s*Phone\s*Number\s*:\s*.+?\s*Tel[.]?:[ ]*([\d\(\) \-\+]{5,})\b#is");

                            return ["PickupPhone" => $phone, "DropoffPhone" => $phone];
                        }

                        return nice($phone);
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pick Up Location Hours[^\n]+\s+(.*?)\n{2,}#ims");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car Provided by\s*:\s*([^\n]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $type = re("#Car\s*and\s*Rate\s*Information:\s*(.+?)\s*$#im");

                        return nice($type);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        // next line after type
                        $model = re("#Car\s*and\s*Rate\s*Information:\s*.+?\s*$\s*(.+?)\s*$#im");

                        return nice($model);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = re("#Name:\s*(.+)#i");

                        return nice($name);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $tot = re("#Total\s*charges\s*(.+?)\s*Additional#is");

                        return total($tot);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#Member\s*Number:\s*([\w-]+)#i");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#Subject:\s*Confirmed:#i")) {
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
}
