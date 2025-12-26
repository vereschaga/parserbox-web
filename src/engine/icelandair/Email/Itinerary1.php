<?php

namespace AwardWallet\Engine\icelandair\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->checkMails($headers["from"]);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/@icelandair\.is/ims", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->checkMails($parser->getHTMLBody());
    }

    public function checkMails($input = '')
    {
        preg_match('/[\.@]icelandair\.is/ms', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function parsePDF(\PlancakeEmailParser $parser)
    {
        $it = ['Kind' => 'T'];
        $text = $this->toText($this->extractPDF($parser, '.'));

        $it['Passengers'] = preg_match("#\nName:\s*([^\n]+)#", $text, $m) ? $m[1] : null;
        $it['ReservationDate'] = preg_match("#\nDate of issue:\s*([^\n]+)#", $text, $m) ? strtotime($m[1]) : null;
        $it['RecordLocator'] = preg_match("#\nBooking reference:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

        $year = date('Y', $it['ReservationDate']);

        $it['TripSegments'] = [];

        if (preg_match("#Flight\s+From\s+To\s+Date\s+Class\s+Terminal\s+Dep Time\s+Arr Time\s+Seat\s+NVB\s+NVA\s+Status\s+Baggage\s+(.*?)Air fare#ims", $text, $m)) {
            preg_replace_callback("#([\w\d]+)\s+(\w{3})\s+(\w{3})\s+(\d+\w{3})\s+([^\s]+)\s+(\d+:\d+)\s+(\d+:\d+)\s+(\d+\w+)#",
                                  function ($m) use (&$it, $year) {
                                      $seg = [];

                                      $seg['FlightNumber'] = $m[1];

                                      $seg['DepCode'] = $seg['DepName'] = $m[2];
                                      $seg['ArrCode'] = $seg['ArrName'] = $m[3];

                                      $seg['BookingClass'] = $m[5];

                                      $seg['DepDate'] = strtotime($m[4] . $year . ', ' . $m[6]);
                                      $seg['ArrDate'] = strtotime($m[4] . $year . ', ' . $m[7]);

                                      $seg['Seats'] = $m[8];

                                      $it['TripSegments'][] = $seg;
                                  }, $m[1]);
        }

        if (preg_match("#\nTotal airfare:\s*(\w{3})\s+([.\d]+)#", $text, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = $m[2];
        }

        if (preg_match("#\nTaxes:\s*([^\n]+)#", $text, $m)) {
            $it['Tax'] = 0;

            foreach (explode(' ', preg_replace("#[^\d.]#", ' ', $m[1])) as $item) {
                $it['Tax'] += $item;
            }
        }

        return $it;
    }

    public function toText($html)
    {
        $nbsp = '&' . 'nbsp;';
        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#&\#160;#ums", " ", $html);
        $html = preg_replace("#$nbsp#ums", " ", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);

        $html = preg_replace("#[^\w\d\s:;,./\(\)\[\]\{\}\-\\\$]#", '', $html);

        return $html;
    }

    public function extractPDF(\PlancakeEmailParser $parser, $wildcard = null, $index = -1)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";
        $i = 0;

        foreach ($pdfs as $pdfo) {
            if (($index == -1 || $index == $i) && ($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
            $i++;
        }

        return $pdf;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindPreg("#ATTACHED IS YOUR E\-TICKET#")) {
            $it = $this->parsePDF($parser);

            return [
                "emailType"  => "reservation",
                "parsedData" => [
                    "Itineraries" => [$it],
                ],
            ];
        }
    }
}
