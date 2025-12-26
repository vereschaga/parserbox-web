<?php

namespace AwardWallet\Engine\supershuttle\Email;

class It2356878 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]supershuttle[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]supershuttle[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]supershuttle[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.06.2015, 10:45";
    public $crDate = "15.01.2015, 10:02";
    public $xPath = "";
    public $mailFiles = "supershuttle/it-1.eml, supershuttle/it-1696002.eml, supershuttle/it-1721150.eml, supershuttle/it-1910146.eml, supershuttle/it-1915612.eml, supershuttle/it-1920855.eml, supershuttle/it-2356878.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $address = reni('(?:
							Guest Information:
							Address
						)  (.+? , \s+ \w{2} \s+ \d+)
					');
                    $this->home = $address;
                    $this->name = reni('Dear (.+?),');

                    return xpath("//*[contains(text(), 'Confirmation Number:')]/ancestor::tr[2]");
                },

                "#.*?#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        $this->to_airport = reni('Pickup Date/Time:');
                        $this->from_airport = !$this->to_airport;

                        return reni('Confirmation Number:  (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        if ($this->to_airport) {
                            return $this->home;
                        }

                        return nice(cell('Airport', +1));
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        if ($this->to_airport) {
                            return totime(uberDateTime(1));
                        }

                        return totime(cell('Flight Date/Time', +1));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        if ($this->from_airport) {
                            return $this->home;
                        }

                        return nice(cell('Airport', +1));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return MISSING_DATE;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return $this->name;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Total', +1);

                        return total($x);
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        $x = cell('DISCOUNT', +1);

                        return cost($x);
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
