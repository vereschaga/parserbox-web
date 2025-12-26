<?php

namespace AwardWallet\Engine\hertz\Email;

use PlancakeEmailParser;

class It2479920 extends \TAccountCheckerExtended
{
    public $mailFiles = "hertz/it-2479920.eml";

    private $from = '/[@.]hertz[.]com/i';

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
                        return reni('Your Confirmation Number: (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return reni('
							Pick-up Location:
							(.+?)
							Pick-up Date
						');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Pick-up Date (.+)');
                        $dt = uberDateTime($info);

                        return totime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return get_or($it, 'PickupLocation');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return MISSING_DATE;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return reni('Hello (.+?) \n');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Your Confirmation Number')) {
                            return 'confirmed';
                        }
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return $this->http->FindSingleNode("//tr[normalize-space(.)='Vehicle:']/following-sibling::tr[1]");
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return 0 < $this->http->XPath->query("//a[contains(normalize-space(.), 'Here’s a friendly reminder regarding your reservation details') and contains(@href, 'hertz')] | //text()[contains(normalize-space(.), 'Here’s a friendly reminder regarding your reservation details')]")->length;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }
}
