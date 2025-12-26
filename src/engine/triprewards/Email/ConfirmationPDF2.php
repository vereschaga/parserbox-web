<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationPDF2 extends \TAccountChecker
{
    public $reFrom = ["reservations@nyhotel.com"];
    public $reSubject = [
        "#Wyndham.*?Confirmation#i",
        "#Confirmation.+?Wyndham#i",
        "#wyndham_guest_conf#i",
    ];

    public $mailFiles = "triprewards/it-1803703.eml, triprewards/it-804018646.eml";
    private $keywordProv = 'Wyndham';
    /** @var \HttpBrowser */
    private $pdf;
    private $pdfNamePattern = ".*pdf";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $cnt = count($pdfs);

        for ($i = 0; $i < $cnt; $i++) {
            $this->date = strtotime($parser->getDate());

            if ($this->tablePdf($parser, $i)) {
                if (!$this->parseEmailPdf($email)) {
                    return null;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Wyndham') !== false || stripos($text, 'Ramada') !== false)
                && (stripos($text, 'Thank you for') !== false)
                && ((stripos($text, 'Nightly Rate') !== false) || (stripos($text, 'Daily Rate') !== false))
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function parseEmailPdf(Email $email)
    {
        $text = text($this->pdf->Response['body']);
        $r = $email->add()->hotel();

        $confNo = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(),'Your Confirmation Number is')]",
            null, false, "#Your Confirmation Number is ([\w\-]+)#");

        if (empty($confNo)) {
            $confNo = $this->pdf->FindSingleNode("//td[starts-with(normalize-space(),'Confirmation Number')]/following-sibling::td[1]");
        }

        if (empty($confNo)) {
            $confNo = $this->pdf->FindSingleNode("//td[starts-with(normalize-space(),'Confirmation')][not(following-sibling::td)]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]");
            $format2 = true;
            // bcd
            // FE table:
            //Arrival Day, Date	        Departure Date	            Daily Rate	        Confirmation
            //Tuesday, July 23 2019	    Wednesday, July 24 2019	    135.99	            11103978
        }

        $r->general()
            ->confirmation($confNo, 'Confirmation Number')
        ;

        if ($traveller = trim($this->pdf->FindSingleNode("//tr[starts-with(.,'Thank you for')]/preceding::td[1][./preceding-sibling::td[contains(.,'Dear')] or not(./preceding-sibling::td)]/descendant::text()[normalize-space()][1]"),
            ' ,')
        ) {
            if (preg_match("/^(\w+ \w+).*\d/", $traveller, $m)) {
                $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '{$m[1]}')]");
            }
            $r->general()->traveller($traveller, true);
        }

        $dateStr = $this->pdf->FindSingleNode("(//tr[starts-with(.,'Tel')]/following-sibling::tr[position()<3][not(contains(.,'Confirmation Number')) and contains(translate(.,'0123456789','dddddddddd'),'dd')])[1]");

        if (!empty($dateStr) && ($date = strtotime($dateStr))) {
            $r->general()
                ->date($date);
        }

        if ($node = $this->pdf->FindSingleNode("//text()[contains(.,'please inform us')]")) {
            $r->general()->cancellation($node);
        } elseif ($node = $this->pdf->FindSingleNode("//text()[starts-with(.,'Cancellation Policy:')]", null, false, "#Cancellation Policy:\s*(.+)#")) {
            $r->general()->cancellation($node);
        }

        if (preg_match('#(.*)\s+((?s).*)\s+Tel:\s+(.*?)(?:\s+Fax:\s+(.*))?\n#', $text, $m)) {
            $r->hotel()
                ->name($m[1])
                ->address(trim(preg_replace("#\s+#", ' ', $m[2])))
                ->phone($m[3]);

            if (isset($m[4]) && !empty($m[4])) {
                $r->hotel()->fax($m[4]);
            }
        }
        $nodes = $this->pdf->FindNodes("//tr[./td[1][contains(., 'Arrival Date') or contains(., 'Arrival Day, Date')] and ./td[3][contains(., 'Nightly Rate') or contains(., 'Daily Rate')] ]/following-sibling::tr[1]/td");

        if (count($nodes) !== 4) {
            $this->logger->debug('other format');

            return false;
        }

        $r->booked()
            ->checkIn($this->normalizeDate($nodes[0]))
            ->checkOut($this->normalizeDate($nodes[1]));

        if ($r->getCheckInDate()
            && $time = $this->pdf->FindSingleNode("//text()[contains(.,'check-in time is') or contains(.,'Check-in time is')]", null,
                false, "#check-in time is\s+(\d+:\d+ [ap]m)#i")
        ) {
            $r->booked()->checkIn(strtotime($time, $r->getCheckInDate()));
        }

        if ($r->getCheckOutDate()
            && $time = $this->pdf->FindSingleNode("//text()[contains(.,'check-out time is') or contains(.,'Check-out time is')]", null,
                false, "#check-out time is\s+(\d+:\d+ [ap]m)#i")
        ) {
            $r->booked()->checkOut(strtotime($time, $r->getCheckOutDate()));
        }

        $room = $r->addRoom();
        $room->setRate($nodes[2]);

        if (!isset($format2)) {
            $room->setType($nodes[3]);
        }

        $this->detectDeadLine($r);

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/please inform us by (?<time>\d+:\d+\s*(?:[ap]m)?) [A-Z]+ on your arrival date to receive full refund of your stay/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 day', $m['time']);
        } elseif (
            preg_match("/please inform us at least (?<hours>\d+) hours? prior to your arrival date to avoid a no-show charge/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['hours'] . ' hours');
        } elseif (
            preg_match("/Reservations must be cancelled by (?<time>\d+:\d+ ?[ap]\.?m\.?) the day prior to your arrival date to avoid/i", $cancellationText, $m)
            || preg_match("/to cancel or change plans, please inform us by (?<time>\d+:\d+ ?[ap]m) 24 hours prior to your arrival to avoid any applicable charges. /i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('1 days', str_replace(".", '', $m['time']));
        }
    }

    private function normalizeDate($date)
    {
        $regex = '#(?P<Month>\d+)-(?P<Day>\d+)-(?P<Year>\d+)#i';

        if (preg_match($regex, $date, $m)) {
            $dateStr = $m['Day'] . '.' . $m['Month'] . '.' . ((strlen($m['Year']) == 2) ? '20' . $m['Year'] : $m['Year']);

            return strtotime($dateStr);
        }

        return strtotime($date);
    }

    private function tablePdf(PlancakeEmailParser $parser, $num = 0)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[$num])) {
            return false;
        }
        $pdf = $pdfs[$num];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->http->SetBody($html);
        $this->pdf->SetBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            $prevTop = null;

            foreach ($nodes as $node) {
                $text = implode(' ', $this->pdf->FindNodes(".//text()", $node));

                if (empty(trim($text))) {
                    continue;
                }
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");

                if (isset($prevTop) && abs($prevTop - $top) < 4) {
                    $top = $prevTop;
                } else {
                    $prevTop = $top;
                }
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row => $c) {
                ksort($c);
                $html .= "<tr>";

                foreach ($c as $col) {
                    $html .= "<td>" . $col . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->setBody($html);

        return true;
    }
}
