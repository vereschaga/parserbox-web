<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class Itinerary1 extends \TAccountChecker
{
    public $reFrom = "#HolidayInn@reservations.ihg.com|Reservations@InterContinental.com|HolidayInnExpress@reservations.ihg.com#";
    public $reProvider = "#[@\.](ihg|InterContinental).com#i";

    public $reSubject = "#Your Reservation Confirmation at Holiday Inn|Holiday Inn|Priority Club Rewards#i";
    public $reText = "#InterContinental Asiana Saigon|\d{4}\s+InterContinental Hotels Group|please contact American\s+Express#";
    public $reHtml = null;
    public $mailFiles = "ichotelsgroup/it-14.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $emailType = $this->getEmailType($parser);
        $result = "Undefined email type";

        switch ($emailType) {
            case "InterContinentalPDF":
                $result = $this->ParseInterContinentalPDF($parser);

                break;

            case "AmexTravel":
                $this->http->SetBody($parser->getHTMLBody(), true);
                $result = $this->ParseAmexTravelEmail($parser);

                break;

            case "ReservationConfirmationAttachment":
                $result = $this->ParseEmailConfirmationAttach($parser);

                break;
        }

        return [
            'parsedData' => $result,
            'emailType'  => $emailType,
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public function toText($html)
    {
        $nbsp = '&' . 'nbsp;';
        $html = preg_replace("#[^\w\d\t\r\n :;,./\(\)\[\]\{\}\-\\\$+=_<>&\#%^&!]#", ' ', $html);

        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#&\#160;#ums", " ", $html);
        $html = preg_replace("#$nbsp#ums", " ", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);

        return $html;
    }

    public function extractPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        if (preg_match('/(Reservation\s+)Confirmation(\s?#)?\s+\d+/i', $parser->getSubject())) {
            if ($parser->countAttachments()) {
                return "ReservationConfirmationAttachment";
            }
        }

        if ($this->http->FindPreg("/we would like to send you the room booking/")) {
            return "InterContinentalPDF";
        }

        if ($this->http->FindPreg("/please contact American\s+Express/i")) {
            return "AmexTravel";
        }

        return "Undefined";
    }

    public function ParseAmexTravelEmail($parser)
    {
        $it = ['Kind' => 'R'];
        $text = $this->toText($parser->getHtmlBody());

        $it['GuestNames'] = preg_match("#\nTraveler Name:\s*([^\n]+)#", $text, $m) ? $m[1] : null;
        $it['HotelName'] = preg_match("#\nHotel name:\s*([^\n]+)#", $text, $m) ? $m[1] : null;
        $it['ConfirmationNumber'] = preg_match("#Confirmation Number:\s*([\d]+)#", $text, $m) ? $m[1] : null;

        $it['CheckInDate'] = preg_match("#\nCheck in:\s*([^\n]+)#", $text, $m) ? strtotime($m[1]) : null;
        $it['CheckOutDate'] = preg_match("#\nCheck out:\s*([^\n]+)#", $text, $m) ? strtotime($m[1]) : null;

        $it['Guests'] = preg_match("#\nNumber of persons:\s*([\d]+)#", $text, $m) ? $m[1] : null;
        $it['Rate'] = preg_match("#\nRoom price:\s*([\d.,]+)#", $text, $m) ? $m[1] : null;
        $it['Address'] = preg_match("#\nAddress:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

        if ($phfax = preg_match("#\nPhone/Fax:\s*([^\n]+)#", $text, $m) ? $m[1] : null) {
            [$it['Phone'], $it['Fax']] = explode("/", $phfax);
        }

        if (preg_match("#Estimated Total Room Price\s+\([^\)]+\):\s*([\d.A-Z ]+)#", $text, $m)) {
            $it['Total'] = preg_replace("#[^\d.,]+#", '', $m[1]);
            $it['Currency'] = preg_match("#\b([A-Z]{3})\b#", $m[1], $m) ? $m[1] : null;
        }

        return ["Itineraries" => [$it], "Properties" => []];
    }

    public function ParseEmailConfirmationAttach(\PlancakeEmailParser $parser)
    {
        global $sPath;
        $result = [];

        for ($i = 0; $i <= $parser->countAttachments(); $i++) {
            if (preg_match('/^\s*([\w-]+)\/([\w-]+)\s*(;.*)?$/i', $parser->getAttachmentHeader($i, 'Content-Type'), $ar)) {
                if (in_array(strtolower($ar[1]), ['file', 'application']) and $ar[2] == 'pdf') {
                    $pdf = new \PDFConvert($parser->getAttachmentBody($i));
                    $body = $pdf->textBlocks();
                    $body = $body[0]; // get first page
                    $block = reset($body); // additional information in the first block
                    $flag = 0;

                    foreach ($block as $line) {
                        switch ($flag) {
                            case 1:
                                $result['ConfirmationNumber'] = $line;
                                $flag = -1;

                                break;

                            case 2:
                                $result["Address"][] = $line;

                                if (count($result['Address']) == 2) {
                                    $flag = 3;
                                }

                                break;

                            case 3:
                                $result["Phone"] = $line;
                                $flag = -1;

                                break;

                            case -1:$flag = 0;

                                break;
                        }

                        if ($flag !== 0) {
                            continue;
                        }

                        if (preg_match('/Confirmation\s+number\s+is/i', $line)) {
                            $flag = 1;
                        }

                        if (preg_match('/^\s*Holiday\s+Inn\s+Express(?:\s+(.+?))?\s*$/i', $line, $ar)) {
                            if (isset($ar[1])) {
                                $result['HotelName'] = $ar[1];
                            } else {
                                $flag = 2;
                            }
                        }
                    }
                    $block = $body[3];
                    $ar = [];
                    $append = false;
                    $last = '';

                    foreach ($block as $line) {
                        if ($append) {
                            $last .= $line;
                            $append = false;

                            continue;
                        } elseif (preg_match('/^\s+$/s', $line)) {
                            if (isset($last)) {
                                $last .= $line;
                                $append = true;
                            }

                            continue;
                        } else {
                            if (isset($last)) {
                                unset($last);
                            }
                            $last = $line;
                            $ar[] = &$last;
                        }
                    }

                    if (preg_match('/(\+?(?:\d+\.)?\d+)(?:\s+([a-z]{3,3}))?/i', $ar[0], $a)) {
                        $result['Cost'] = $a[1];

                        if (!empty($a[2])) {
                            $result['Currency'] = $a[2];
                        }
                    }
                    $result['RoomType'] = $ar[1];

                    if (preg_match('/([0-2]?[0-9])-([0-2]?[0-9]|3[0-1])-(\d{2,4})/', $ar[3], $a)) {
                        $result['CheckInDate'] = mktime(0, 0, 0, $a[1], $a[2], $a[3] < 100 ? substr(date("Y"), 0, 2) . $a[3] : $a[3]);
                    }

                    if (preg_match('/([0-2]?[0-9])-([0-2]?[0-9]|3[0-1])-(\d{2,4})/', $ar[2], $a)) {
                        $result['CheckOutDate'] = mktime(0, 0, 0, $a[1], $a[2], $a[3] < 100 ? substr(date("Y"), 0, 2) . $a[3] : $a[3]);
                    }
                    //$result['ar']=&$ar;
                    //$result['pdf']=&$body;
                    $result["Kind"] = "R";

                    return ["Itineraries" => [$result], "Properties" => []];
                }
            }
        }

        return "Unparsed email body\n";
    }

    public function ParseInterContinentalPDF($parser)
    {
        $text = $this->toText($this->extractPDF($parser));
        $it["Kind"] = "R";

        if (preg_match("#\(By VND\)\s+(.*?)\s+Method#ims", $text, $m)) {
            $info = $m[1];

            if (preg_match("#\n(\d{6,})\s+([^\n]+)\s+(\d+)\s+([\dx]+)\s+(\d+\s+\w{3}\s+\d+)\s+(\d+\s+\w{3}\s+\d+)\s+(.*?)\sOn\s+\d+#", $info, $m)) {
                $it['ConfirmationNumber'] = $m[1];
                $it['GuestNames'] = $m[2];
                $it['Guests'] = intval($m[3]);
                $it['Kids'] = intval($m[4]);
                $it['CheckInDate'] = strtotime($m[5]);
                $it['CheckOutDate'] = strtotime($m[6]);
                $it['RoomType'] = $m[7];
            }

            if (preg_match("#Reservations Officer\.*\s+([^\n]+)\s+([^\n]+)\s+Main Line:\s+(.*?)\s+Fax:\s+([^\n]+)#", $text, $m)) {
                $it['HotelName'] = $m[1];
                $it['Address'] = $m[2];
                $it['Phone'] = $m[3];
                $it['Fax'] = $m[4];
            }
        }

        return ["Itineraries" => [$it], "Properties" => []];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return ((isset($this->reFrom) && isset($headers['from'])) ? preg_match($this->reFrom, $headers["from"]) : false)
                || ((isset($this->reSubject) && isset($headers['subject'])) ? preg_match($this->reSubject, $headers["subject"]) : false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $parser->getPlainBody()) : false)
                || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }
}
