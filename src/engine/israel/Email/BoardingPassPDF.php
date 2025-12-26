<?php

namespace AwardWallet\Engine\israel\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "israel/it-1905849.eml, israel/it-1912899.eml, israel/it-1912905.eml, israel/it-7100380.eml, israel/it-7100385.eml, israel/it-7156116.eml, israel/it-7212072.eml";

    public $reProvider = "elal.";
    public $reFrom = "#(WebCheckin|MobileCheckin)@elal\.co\.il#i";
    public $reBody = ['BOARDING PASS'];
    public $reSubject = ['Boarding Pass'];
    public $lang = '';
    public $attachName;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName("BoardingPasses\.pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

                $file = $parser->getAttachment($pdf);
                $this->attachName = $this->re('/name\=(.+)/ui', $file['headers']['content-type']);

                $this->pdf->SetBody($body);

                $this->parseEmail($email, $body);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('BoardingPasses\.pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $reBody) {
                if (strpos($text, $reBody) !== false && stripos($text, $this->reProvider) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match($this->reFrom, $headers["from"]) == 0) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function parseEmail(Email $email, $plainText)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        $plainText = str_replace(['‫‪', '‬‬'], ['', ''], $plainText);
        $plainText = str_replace([html_entity_decode("&#8234;"), html_entity_decode("&#8235;"), html_entity_decode("&#8236;")], '', $plainText);
        $this->logger->debug($plainText);

        $segTexts = $this->splitText("#(BOARDING PASS\n)#", $plainText);

        $travellers = [];

        foreach ($segTexts as $text) {
            $s = $f->addSegment();

            if (preg_match('#Flight:\s+(\w{2})(\d{1,4})\s*\n#i', $text, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#Date:\s+(\d+)\/(\d+)\/(\d{4})\s*(\d+\:\d+)\s*\n#", $text, $m)) {
                $s->departure()
                    ->date(strtotime($m[1] . '.' . $m[2] . '.' . $m[3] . ' ' . $m[4]));
            }

            if (preg_match('#From:\s+(\w{3})\s*(\((Terminal\s*([\w]{1,3}))\))?\s+to\s+(\w{3})\s*(\((Terminal\s*([\w]{1,3}))\))?\s*\n#i', $text, $m)) {
                $s->departure()
                    ->code($m[1]);

                $s->arrival()
                    ->code($m[5])
                    ->noDate();

                if (!empty($m[4])) {
                    $s->departure()
                        ->terminal($m[4]);
                }

                if (!empty($m[8])) {
                    $s->arrival()
                        ->terminal($m[8]);
                }
            }

            $traveller = '';

            if (preg_match('#BOARDING\s+PASS\s*([A-Z\s\-\/]+)\s*Passenger\s+Name:\s+([A-Z\s\-\/]+)(?:\s*\n|\s{2,}\D*\n)#i', $text, $m)) {
                $traveller = empty(trim($m[1])) ? trim($m[2]) : trim($m[1]) . ' ' . trim($m[2]);
                $traveller = preg_replace("/(?:^(INF\s)|(\sCHD)$)/", "", $traveller);
                $travellers[] = $traveller;
            }

            if (preg_match('#FQTV:\s+([A-Z\d]+)\s*\n#i', $text, $m)) {
                $f->program()
                    ->account($m[1], false);
            }

            if (preg_match('#Class:\s+(\w)\s#i', $text, $m)) {
                $s->extra()
                    ->bookingCode($m[1]);
            }

            if (preg_match('#SEAT\b.*\n\s*(\w{1,3})?.+(\d{3}[A-Z])\s*#', $text, $m)) {
                $s->extra()
                    ->seat($m[2]);
            }

            // Added Boarding Pass
            $bp = $email->add()->bpass();
            $bp->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber());
            $bp->setTraveller($traveller);
            $bp->setDepDate($s->getDepDate());
            $bp->setDepCode($s->getDepCode());
            $bp->setAttachmentName($this->attachName);

            // Removing Doubled Segments
            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }

        $f->general()
            ->travellers(array_unique($travellers));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
