<?php

namespace AwardWallet\Engine\onpeak\Email;

class It1 extends \TAccountCheckerExtended
{
    public $rePlain = "#onPeak - the official housing provider|\n[>\s*]*From\s*:[^\n]*?onpeak#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]onpeak\.#i";
    public $reProvider = "[@.]#onpeak\.#i";
    public $caseReference = "9015";
    public $xPath = "";
    public $mailFiles = "onpeak/it-1.eml";
    public $pdfRequired = "0";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return
            $this->http->XPath->query("//img[contains(@alt, 'onPeak') or contains(@src,'onpeak')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(.),'onPeak - the official housing provider')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(text()),'From')]/ancestor::*[contains(normalize-space(.),'onpeak')][1]")->length > 0;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        //$node = node("//*[contains(text(), 'onPeak ID')]/ancestor-or-self::div[1]/following-sibling::p");

                        $node = re("#\n\s*Hotel Confirmation Number\s*:\s*([0-9]+)#");

                        if ($node == null) {
                            return CONFNO_UNKNOWN;
                        }
                        $node = trim($node);

                        return $node;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $node = nodes("//text()[starts-with(normalize-space(.), 'onPeak ID')]/following::text()[normalize-space(.)!=''][1]");

                        return $node;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Check-in')])[1]/ancestor-or-self::td[1]/preceding-sibling::td[2]//strong");

                        if (empty($node)) {
                            $node = node("(//*[contains(text(), 'Check-in')])[1]/ancestor-or-self::tr[1]/preceding-sibling::tr[1]/td[normalize-space()][1]//strong");
                        }

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Check-in')])[1]/following::text()[normalize-space()][1]");
                        $node = uberDateTime($node);

                        return totime($node);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Check-out')])[1]/following::text()[normalize-space()][1]");
                        $node = uberDateTime($node);

                        return totime($node);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("((//*[contains(text(), 'Check-in')])[1]/ancestor-or-self::td[1]/preceding-sibling::td[2]//span)[2]");

                        if (empty($node)) {
                            $node = implode("\n", nodes("((//*[contains(text(), 'Check-in')])[1]/ancestor-or-self::tr[1]/preceding-sibling::tr[1]/td[normalize-space()][1]//span)[2]//text()"));

                            if (preg_match("#(.+?)\n[^\n]+miles to.*?(?:\n+([\d\-\+\(\) ]{5,}))?$#s", $node, $m)) {
                                return ["Address" => preg_replace("#\s+#", ' ', $m[1]), "Phone" => $m[2]];
                            }
                        }

                        if ($node == null) {
                            $strong = node("(//*[contains(text(), 'Check-in')])[1]/ancestor-or-self::td[1]/preceding-sibling::td[2]//strong");
                            $node = node("(//*[contains(text(), 'Check-in')])[1]/ancestor-or-self::td[1]/preceding-sibling::td[2]");
                            $node = str_replace($strong, "", $node);
                        }
                        $node = preg_replace("#\s+([\d\.]+\s*miles to Event Location)#", '', $node);

                        return $node;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $node =
                            [
                                re("#Occupant Name\s*([^\n]+)#"),
                            ];
                        $roommates = nodes("//*[contains(text(), 'Roommate')]/ancestor-or-self::div[1]/following-sibling::div/p");

                        $roommates = array_merge($node, $roommates);
                        $count = count($roommates);

                        return [
                            'Guests'     => $count,
                            'GuestNames' => $roommates,
                        ];
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return node("(//*[contains(text(), 'Avg nightly')])[1]");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cancellation\s*Policy\n\s*([^\n]+)#");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Taxes", +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Estimated Total", +1), "Total");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $node = re("#\n\s*Subject\s*:\s*CONFIRMATION#");

                        if ($node != null) {
                            return "confirmed";
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
