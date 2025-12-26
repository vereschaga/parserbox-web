<?php

namespace AwardWallet\Engine\rentacar\Email;

class ReservationInformationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank you for choosing Enterprise Rent-A-Car#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#NO_REPLY@enterprise\.com#i";
    public $reProvider = "#enterprise\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "rentacar/it-1931240.eml, rentacar/it-1961102.eml, rentacar/it-2077261.eml, rentacar/it-2219231.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $subj = $this->parser->getPlainBody();
                    $subj = preg_replace('#\n\s*>#', "\n", $subj);
                    $text = $subj;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Your\s+confirmation\s+number\s+is\s+([\w\-]+)\.#i'),
                            re_white('Your new confirmation number is (\w+)')
                        );
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							Pickup Branch:                  Return Branch:
							(.+? \s+)
							Car Information
						');
                        //var_dump($info);

                        $q = white('^ (\w.+?) \s{3,} (\w.+) $');

                        if (!preg_match_all("/$q/im", $info, $m)) {
                            return;
                        }
                        //var_dump($m);
                        $loc1 = implode(',', $m[1]);
                        $loc2 = implode(',', $m[2]);

                        return [
                            'PickupLocation'  => nice($loc1),
                            'DropoffLocation' => nice($loc2),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pickup', 'Dropoff' => 'Return'] as $key => $value) {
                            $regex = '#' . $value . '\s+date:\s+(.*)\s+at\s+(.*)\s+\(Office\s+([^\)]+)\)#i';

                            if (preg_match($regex, $text, $m)) {
                                $res[$key . 'Datetime'] = strtotime($m[1] . ' ' . $m[2]);
                                $res[$key . 'Hours'] = nice(clear('/^\s*hours:\s*/', $m[3]));
                            }
                        }

                        return $res;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							(?P<PickupPhone> \( \d+ \) [\d -]+) \s{3,}
							(?P<DropoffPhone> \( \d+ \) [\d -]+)
							Car Information
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re('#Type\s+of\s+Car:\s+(.*)#i');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re('#Examples:\s+(.*)#i');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Total\s+charges\s+=\s+(.*)#i'));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#\(all\s+rates\s+in\s+(.*)\)#i');

                        switch ($subj) {
                            case 'U.S. DOLLARS':
                                $subj = 'USD';

                                break;
                        }

                        return $subj;
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
