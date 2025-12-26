<?php

namespace AwardWallet\Engine\milleniumnclc\Email;

class It2430826 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#(?:We\s+are\s+holding\s+a\s+reservation\s+for\s+you|We\s+are\s+pleased\s+to\s+confirm\s+your\s+reservation)\s+at\s+the\s+Millennium\s+\w+#i', 'blank', '2000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Millennium(\s+\w+)*\s+confirmation\s+\|\s+Confirmation\s+number#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]millenniumhotels\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]millenniumhotels\.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "18.06.2015, 14:05";
    public $crDate = "16.06.2015, 15:35";
    public $xPath = "";
    public $mailFiles = "milleniumnclc/it-2430826.eml, milleniumnclc/it-2596665.eml, milleniumnclc/it-2674535.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->resrvRawData = re("/\n[\t ]*([[:alpha:]]+[ ]+\d{1,2},[ ]+\d{4}[\t ]+[[:alpha:]]+[ ]+\d{1,2},[\s\S]{15,}?)\n+[\t ]*(?:Modify|\S.*\S[ ]+with[ ]+\S.*\S|.*View King.*)\n/iu");
                    $this->resrvRawPrices = re("/\n\s*Summary\n\s*(.+?)\n\s*Copyright\s/uis");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("/Your\s+Cart\s+ID\s+Number\s+is\s+(\w+)\s/ui");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("/^.+\n+[\t ]+(.{2,})\n/", $this->resrvRawData);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dateRaw = re("/(.+?\d{4})\s+\w+\s+\d+,/ui", re("/^\s*([^\n]+)?\s*\n/ui", $this->resrvRawData));

                        return timestamp_from_format($dateRaw . "T00:00", "F d, Y\\TH:i");
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $dateRaw = re("/\d{4}\s+(\w+\s+\d+,\s+\d+)/ui", re("/^\s*([^\n]+)?\s*\n/ui", $this->resrvRawData));

                        return timestamp_from_format($dateRaw . "T00:00", "F d, Y\\TH:i");
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addrRaw = re("/^(?:[^\n]+\s+){2}((?:[^\n]+\s+){3})/ui", $this->resrvRawData);
                        $addrRaw = preg_replace("/\n\s*(.+?)(?:\s+\-\s+MAP)?\s*$/u", "\n\\1", $addrRaw);

                        return preg_replace("/\n+\s*/u", ", ", $addrRaw);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re("/\s\-\s+MAP\s+(\d+(-\d+)+)/u", $this->resrvRawData);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return preg_split("/\s*(?:\n|,)\s*/u", re("/Reservation Name\s+(.+?)\s+\w+\s+\d+,\s*\d+/ui"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $guestCount = re("/\sOccupancy\s+(\d{1,2})\s+Adults,\s*(\d{1,2})\s+Children/i");
                        $kidsCount = re(2);

                        return [
                            'Guests' => $guestCount !== null ? intval($guestCount) : null,
                            'Kids'   => $kidsCount !== null ? intval($kidsCount) : null,
                        ];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        $roomCount = re("/\s-\s+MAP\s+(?:.+\n+){0,2}[\t ]*\b(\d{1,3})[ ]+Room/", $this->resrvRawData);

                        return $roomCount !== null ? intval($roomCount) : null;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $roomType = re("/\n[\t ]*Modify\n+[\t ]*(.{2,}?)\n/i")
                            ?? re("/\n[\t ]*(\S.*\S[ ]+with[ ]+\S.*\S)\n/")
                            ?? re("/\n[\t ]*(.*View King.*)\n/i");

                        return $roomType && $this->http->XPath->query("//text()[{$this->eq($roomType)}]")->length > 0 ? $roomType : null;
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re("/Rooms\s+([^\n]+)\n/uis", $this->resrvRawPrices));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $taxRaw = re("/Tax\s+&\s+Fees\s+([^\n]+)\n/uis", $this->resrvRawPrices);

                        return [
                            'Taxes'    => cost($taxRaw),
                            'Currency' => re("/\s+(\w+)/u", $taxRaw),
                        ];
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("/\n\s*Total\n\s*(\d+(?:,\d+)*(?:\.\d+)?)\s+\w+/ui", $this->resrvRawPrices));
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
