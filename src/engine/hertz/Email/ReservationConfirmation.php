<?php

namespace AwardWallet\Engine\hertz\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?hertz#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Hertz.*WebRES Reservation / Confirmation#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]hertz#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]hertz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.06.2016, 10:18";
    public $crDate = "30.06.2016, 08:33";
    public $xPath = "";
    public $mailFiles = "hertz/it-11236499.eml, hertz/it-3936210.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $textPdf = $this->getDocument('application/pdf', 'text');

                    if (strpos($text, 'Pickup Date/Time') === false && strpos($textPdf, 'Pickup Date/Time') !== false) {
                        $text = $this->setDocument('application/pdf', 'complex');
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number:\s+(\w+)#');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = [];

                        foreach (['Pickup' => 'Pickup', 'Dropoff' => 'Return'] as $key => $value) {
                            $s = cell($value . ' Date/Time');

                            if (empty($s)) {
                                $s = re("/($value Date\/Time[\s\S]+?)\s*(?:Return Date\/Time|Your Vehicle)/");
                            }

                            $s = preg_replace('/\s+/', ' ', $s);

                            if (preg_match('#Date/Time\s*(\d.*at\s+\d+:\d+)\s+(.*)#i', $s, $m)) {
                                $res[$key . 'Datetime'] = strtotime(str_replace('/', '-', str_replace(' at ', ', ', $m[1])));
                                $res[$key . 'Location'] = $m[2];
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re('#Class\s+Code:\s+(.*)#');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $res = re('#Your Vehicle\s+(.*)#');

                        if (isset($it['CarType']) && !empty($it['CarType'])) {
                            return $res;
                        }

                        if (preg_match("#(CATEGORY\s+.+?)\s*-\s*(.+)#i", $res, $m)) {
                            return ['CarType' => $m[1], 'CarModel' => $m[2]];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Renters\s+Name:\s+(.*)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        // TODO: add parsing from PDF (it-3936210.eml)
                        return cell(['Rental Rate', 'Total'], +2);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return cell(['Rental Rate', 'Total'], +1);
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
