<?php

namespace AwardWallet\Engine\iberia\Email;

class It2212768 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]iberia[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]iberia[.]com#i";
    public $reProvider = "#[@.]iberia[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "iberia/it-2212768.eml, iberia/it-44605458.eml"; // +1 bcdtravel(html)[en]
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!re_white('We are sorry to inform you that your flight has been cancelled')
                        && !re_white('Your fight time has changed.')) {
                        return false;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('Reservation code: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (?:Sir\.|Mr/Ms)\s*(.+?)\s*:');

                        return [nice($name)];
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return true;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re_white('We are sorry to inform you that your flight has been cancelled') ? 'cancelled' : null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text()) = 'Cancelled']/ancestor::tr[1]|//*[contains(text(),'fight time has changed.')]/following-sibling::table[1]//td[1][normalize-space(text()) = 'New']/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('./td[2]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $x = node('./td[4]');

                            return nice($x);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('./td[6]');
                            $time = node('./td[7]');
                            $dt = uberDateTime("$date $time");

                            return totime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $x = node('./td[5]');

                            return nice($x);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },
                    ],
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(.), 'We are sorry to inform you that your flight has been cancelled')]")->length
            || $this->http->XPath->query("//text()[contains(normalize-space(.), 'fight time has changed.')]")->length) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["subject"], 'Changes in your reservation') !== false
            || strpos($headers["subject"], 'Changes in your booking') !== false) {
            return true;
        }

        return false;
    }
}
