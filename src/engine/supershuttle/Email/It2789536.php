<?php

namespace AwardWallet\Engine\supershuttle\Email;

class It2789536 extends \TAccountCheckerExtended
{
    public $mailFiles = "supershuttle/it-2454320.eml, supershuttle/it-2789536.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->name = reni('Dear (.+?),');

                    return xpath("//*[contains(text(), 'Confirmation Number:')]/ancestor::tr[2]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        $this->to_airport = reni('Pickup:');
                        $this->from_airport = !$this->to_airport;

                        return reni('Confirmation Number:  (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        if ($this->from_airport) {
                            return nice(cell('Airport', +1));
                        }

                        $info = cell('Pickup:', +1);
                        $q = white('
							(?P<PickupLocation> .+? , [A-Z]{2} \d+)
							(?P<PickupPhone> [\d\s()-]+)
						');
                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        if ($this->to_airport) {
                            return totime(uberDateTime(1));
                        }

                        return totime(cell('Flight Date/Time', +1));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        if ($this->to_airport) {
                            return nice(cell('Airport', +1));
                        }

                        $info = cell('Drop Off:', +1);
                        $q = white('
							(?P<DropoffLocation> .+? , [A-Z]{2} \d+)
							(?P<DropoffPhone> [\d\s()-]+)
						');
                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return MISSING_DATE;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return nice(cell('Service Type:', +1));
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return $this->name;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Total:', +1);

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('summary of your confirmed service')) {
                            return 'confirmed';
                        }
                    },
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
                && stripos($headers['from'], 'reservations@SuperShuttle.com') !== false
                && stripos($headers['subject'], 'SuperShuttle Reservation Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'No additional action is necessary.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@SuperShuttle.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
