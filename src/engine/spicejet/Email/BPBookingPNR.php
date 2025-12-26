<?php

namespace AwardWallet\Engine\spicejet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BPBookingPNR extends \TAccountChecker
{
    public $mailFiles = "spicejet/it-75023727.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) === true
            && isset($headers['subject']) && stripos($headers['subject'], 'Boarding pass for SpiceJet Booking PNR') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing SpiceJet")]')->length === 0) {
            return false;
        }

        return $this->http->XPath->query('//tr[ *[normalize-space()="Travel Date"] ]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@spicejet.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (preg_match("/\n.+ BOARDING TIME[ ]*:/", $textPdf) > 0) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        $bpTexts = $this->splitText($textPdfFull, "/(.*PNR[ ]*:[\s\S]+?\n.+ BOARDING TIME[ ]*:)/", true);

        $it = $email->add()->flight();

        $pnr = null;

        if (preg_match("/Boarding pass for SpiceJet (Booking PNR)[:\s]+([-A-Z\d]{5,})(?:\W|$)/i", $parser->getSubject(), $m)) {
            $pnr = $m[2];
            $it->general()->confirmation($m[2], $m[1]);
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes('//tr/*[not(.//tr) and starts-with(normalize-space(),"Dear")]', null, "/^Dear[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $it->general()->traveller($traveller, true);

        $tickets = [];

        $segments = $this->http->XPath->query('//tr[ *[normalize-space()="Travel Date"] ]/following-sibling::tr[normalize-space()]');

        foreach ($segments as $root) {
            $seg = $it->addSegment();

            $row = [];
            $row['date'] = $this->http->FindSingleNode('*[1]', $root);
            $row['depName'] = $this->http->FindSingleNode('*[3]', $root);
            $row['arrName'] = $this->http->FindSingleNode('*[4]', $root);
            $row['depTime'] = $this->http->FindSingleNode('*[5]', $root);
            $row['arrTime'] = $this->http->FindSingleNode('*[6]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $this->http->FindSingleNode('*[2]', $root), $m)) {
                $seg->airline()->name($m['name'])->number($m['number']);

                foreach ($bpTexts as $bpT) {
                    if (preg_match("/.+ Flight[ ]*[:]+[ ]*{$m['name']}[ ]*{$m['number']}(?:[ ]{2}| [[:alpha:]]|\n)/i", $bpT) > 0) {
                        if (preg_match("/.+ Seat No\.[ ]*[:]+[ ]*(\d+[A-Z])\n/i", $bpT, $m2)) {
                            $seg->extra()->seat($m2[1]);
                        }

                        if ($pnr && preg_match("/.+ PNR[ ]*[:]+[ ]*{$pnr}(?:\W|\n)/i", $bpT)
                            && preg_match("/^[ ]*E[ ]*[-‐][ ]*ticket no\.[ ]*[:]+[ ]*(\d{3}(?: | ?- ?)?\d{4,}(?: | ?- ?)?\d{1,3})(?:[ ]{2}| [[:alpha:]]|$)/imu", $bpT, $m2)
                        ) {
                            $tickets[] = $m2[1];
                        }
                    }
                }
            }

            // Delhi (T1D)
            $pattern = '/^(?<name>.{3,}?)\s*\(\s*T(?<terminal>\d[A-Z\d]*)\s*\)$/i';

            if (preg_match($pattern, $row['depName'], $m) > 0) {
                $row['depName'] = $m['name'];
                $seg->departure()->terminal($m['terminal']);
            }

            if (preg_match('/^([-[:alpha:]]{3})[,.\s]+(\d{1,2})[-,.\s]+([[:alpha:]]{3})[-,.\s]+(\d{2,4})$/u', $row['date'], $m) > 0) {
                $row['date'] = $m[1] . ' ' . $m[3] . ' ' . $m[2] . ', ' . $m[4];
            }
            $seg->departure()
                ->name($row['depName'])
                ->noCode()
                ->date(strtotime(str_replace(' hrs', '', $row['depTime']), strtotime($row['date'])));

            if (preg_match($pattern, $row['arrName'], $m) > 0) {
                $row['arrName'] = $m['name'];
                $seg->arrival()->terminal($m['terminal']);
            }
            $seg->arrival()
                ->noCode()
                ->name($row['arrName'])
                ->date(strtotime(str_replace(' hrs', '', $row['arrTime']), strtotime($row['date'])));
        }

        if (count($tickets) > 0) {
            $it->issued()->tickets(array_unique($tickets), false);
        }

        // TODO: add parsing Boarding Pass from $bpTexts

        return $email;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
