<?php

namespace AwardWallet\Engine\spg\Email;

class It1728486 extends \TAccountCheckerExtended
{
    public $mailFiles = "spg/it-1728486.eml"; // +1 bcdtravel(pdf)[en]

    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?spg#i";
    public $rePlainRange = "";
    public $reHtml = "#.*?#";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#starwoodhotels#i";
    public $reProvider = "#starwoodhotels#i";
    public $xPath = "";
    public $pdfRequired = "1";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detected = parent::detectEmailByBody($parser);

        if ($detected) {
            $this->parser = $parser;
            $pdf = $this->getDocument('application/pdf', 'text');

            return stripos($pdf, 'Starwood hotels') !== false;
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("application/pdf", "text");

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation(?: Number)?\s*:\s*([-\dA-Z]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $result = [
                            'HotelName' => trim(re("#([^\n]+)\s+([^\n]+)\s+Phone\s*:\s*([\(\)+\-\d ]+)\s+Fax\s*:\s*([\(\)+\-\d ]+)\s+Contact\s+Us#ims")),
                            'Address'   => re(2),
                            'Phone'     => re(3),
                            'Fax'       => re(4),
                        ];

                        if (empty($result['HotelName']) && re('/We\'re pleased to confirm your upcoming stay at the (\w[^,.]+)\./')) {
                            $result['HotelName'] = re(1);
                        }

                        if (empty($result['Address']) && empty($result['Phone']) && empty($result['Fax'])) {
                            $pattern = '/'
                                . 'Visa and passport information is required for all foreign nationals and non-residents holding foreign passports\.'
                                . '\s+(?<address>.+?)'
                                . '\s+Telephone[: ]+(?<phone>[^\n]+)' // Telephone : + 91 20 26050505
                                . '\s+Facsimile[: ]+(?<fax>[^\n]+)' // Facsimile : + 9120 26050506
                                . '/s';

                            if (preg_match($pattern, $text, $matches)) {
                                $result['Address'] = preg_replace('/\s*\n+\s*/', ', ', $matches['address']);
                                $result['Phone'] = $matches['phone'];
                                $result['Fax'] = $matches['fax'];
                            }
                        }

                        return $result;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        $patterns = [
                            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
                        ];

                        $result['CheckInDate'] = totime(uberDateTime(re("/\n\s*Check In[\s:]+([^\n]+(?:\s+{$patterns['time']})?)/i")));
                        $result['CheckOutDate'] = totime(uberDateTime(re("/\n\s*Check Out[\s:]+([^\n]+(?:\s+{$patterns['time']})?)/i")));

                        return $result;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = [];
                        re("#Guest Name[\s:]+([^\n]+)#", function ($m) use (&$names) {
                            $names[] = $m[1];
                        }, $text);

                        return $names;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $num = 0;
                        re("#Number of Adults[\s:]+(\d+)#", function ($m) use (&$num) {
                            $num += $m[1];
                        }, $text);

                        return $num;
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        $num = 0;
                        re("#Number of Children[\s:]+(\d+)#", function ($m) use (&$num) {
                            $num += $m[1];
                        }, $text);

                        return $num;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Number of Rooms[\s:]*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room Rate\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('/\n\s*(?:Modify and Cancel Information|Cancel Informations:)\s+(.*?)\s+Guarantee Rules/is'));
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        $type = [];
                        $desc = [];
                        re("#\n\s*Room Description\s+([^\n]+)\s+(.*?)\n\s*Remarks#ims", function ($m) use (&$type, &$desc) {
                            $type[] = $m[1];
                            $desc[] = nice($m[2]);
                        }, $text);

                        if (count($type) && count($desc)) {
                            $result['RoomType'] = implode("|", $type);
                            $result['RoomTypeDescription'] = implode("|", $desc);
                        }

                        if (empty($result['RoomType']) && re('/\n\s*Category of Room[\s:]*([^\n]+)/')) {
                            $result['RoomType'] = re(1);
                        }

                        if (empty($result['RoomTypeDescription']) && re('/\n\s*Room Description[\s:]*([^:]+?)\s+Guest Name[\s:]+/')) {
                            $result['RoomTypeDescription'] = str_replace("\n", ' ', re(1));
                        }

                        return $result;
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Estimated Total[*:\s]+[A-Z]{3}\s+[\d.]+\s+([A-Z]{3}\s+[\d.]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#reservation is (\w+)#");
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
        return ['en'];
    }
}
