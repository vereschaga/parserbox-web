<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class It1728485 extends \TAccountCheckerExtended
{
    public $reFrom = "#ichotelsgroup#i";
    public $reProvider = "#ichotelsgroup#i";
    public $rePlain = "";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-1728485.eml";
    public $rePDF = "#From:\s*(?:<[^>]+>)*\s+.*?ihg.com#i";
    public $rePDFRange = "/1";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $pdfs = $this->parser->searchAttachmentByName('.*pdf');
                    $reservations = [];

                    foreach ($pdfs as $p) {
                        $plainText = $this->getDocument('application/pdf', 'text', $p);
                        $text = $this->getDocument('application/pdf', 'simpletable', $p);
                        $regex = '#Your\s+Reservation\s+with\s+(?:Crowne\s+Plaza|InterContinental)#i';

                        if (preg_match($regex, $plainText)) {
                            $reservations[] = $text;
                            $this->plainTexts[] = $plainText;
                        }
                    }

                    return $reservations;
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        if (!isset($this->reservationIndex)) {
                            $this->reservationIndex = 0;
                        } else {
                            $this->reservationIndex++;
                        }
                        $this->currentResPlainText = $this->plainTexts[$this->reservationIndex];

                        return re('#Your\s+Confirmation\s+Number\s+is\s+([^.]+)\.#', $this->currentResPlainText);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#See\s+Dining\s+Options\s+(.*)\s+((?s).*?)\s+Front\s+Desk:\s+(.*)#';

                        if (preg_match($regex, text($text), $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice(trim($m[2]), ','),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-In:\s+\w+\s+(.*)#', $this->plainTexts[$this->reservationIndex]));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-Out:\s+\w+\s+(.*)#', $this->plainTexts[$this->reservationIndex]));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Guests:\s+(\d+)#', $this->plainTexts[$this->reservationIndex]);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Rooms\s*:\s+(\d+)#i', $this->currentResPlainText);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Cancellation\s+Policy:\s+(.*?)\s+See\s+Dining\s+Options#s';
                        $subj = re($regex, text($text));

                        if ($subj) {
                            return nice($subj);
                        }
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+Type:\s+(.*)#', $this->plainTexts[$this->reservationIndex]);
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
