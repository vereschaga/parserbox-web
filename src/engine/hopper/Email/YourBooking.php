<?php

namespace AwardWallet\Engine\hopper\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "hopper/it-239318078.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hopper.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Your Hopper Booking\s+[-A-Z\d]{5,}\s+is/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"Your Hopper booking with") or contains(normalize-space(),"Thanks for using Hopper") or contains(normalize-space(),"Thank you, Hopper Support") or contains(normalize-space(),"Hopper. All rights reserved")]')->length === 0) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';
        $email->setType('YourBooking' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        $xpathHeader = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Hopper Booking')] ]";

        $statusGeneral = $this->http->FindSingleNode($xpathHeader . "/preceding-sibling::tr[normalize-space()]", null, true, "/^(Confirmed)$/i");
        $f->general()->status($statusGeneral);

        $otaConfirmation = $this->http->FindSingleNode($xpathHeader . "/*[normalize-space()][1]");

        if (preg_match("/^(Hopper Booking)\s*\(\s*([-A-Z\d]{5,})\s*\)$/i", $otaConfirmation, $m)) {
            $f->ota()->confirmation($m[2], $m[1]);
            $f->general()->noConfirmation();
        }

        // HOQS39 (Delta), HKLC4K (United Airlines)
        $pnrByAirline = [];
        $confirmationVal = $this->http->FindSingleNode($xpathHeader . "/*[normalize-space()][2]");
        $confirmationNumbers = preg_split("/[)]+\s*[,]+\s*/", $confirmationVal);

        foreach ($confirmationNumbers as $confirmation) {
            if (preg_match("/^(?<pnr>[-A-Z\d]{5,})\s*\(\s*(?<airline>\S.*?)[\s)]*$/i", $confirmation, $m)) {
                if (array_key_exists($m['airline'], $pnrByAirline)) {
                    $this->logger->debug('Wrong PNR list!');
                    $pnrByAirline = [];

                    break;
                }
                $pnrByAirline[$m['airline']] = $m['pnr'];
            }
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Hi')]", null, "/^Hi[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $f->general()->traveller($traveller);
        }

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Flight segments is wrong!');

            return $email;
        }
        $root = $roots->item(0);

        $segments = $this->http->XPath->query("../self::thead/following-sibling::tbody/tr[normalize-space()]", $root);

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("following-sibling::tr[normalize-space()]", $root);
        }

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $date = strtotime($this->http->FindSingleNode("*[1]", $segment));

            $flight = $this->http->FindSingleNode("*[2]", $segment);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\D|$)/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            /*
                Chicago ORD
                O'Hare International Airport
            */
            $pattern = "/^\s*"
                . "(?<city>\S.*?)[ ]+(?<code>[A-Z]{3})[ ]*\n+"
                . "[ ]*(?<airport>.{3,}?)"
                . "\s*$/"
            ;

            $fromText = $this->htmlToText($this->http->FindHTMLByXpath("*[3]", null, $segment));

            if (preg_match($pattern, $fromText, $m)) {
                $s->departure()->code($m['code'])->name($m['airport'] . ' (' . $m['city'] . ')');
            }

            $toText = $this->htmlToText($this->http->FindHTMLByXpath("*[4]", null, $segment));

            if (preg_match($pattern, $toText, $m)) {
                $s->arrival()->code($m['code'])->name($m['airport'] . ' (' . $m['city'] . ')');
            }

            $carrierVal = implode(' ', $this->http->FindNodes("*[5]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^(.{2,}?)\s*(?:Operated by|$)/i", $carrierVal, $m)) {
                $s->airline()->carrierName($m[1]);

                if (array_key_exists($m[1], $pnrByAirline)) {
                    $s->airline()->carrierConfirmation($pnrByAirline[$m[1]]);
                }
            }

            if (preg_match("/Operated by\s+(.{2,})$/", $carrierVal, $m)) {
                $s->airline()->operator($m[1]);
            }

            $timeDep = $this->http->FindSingleNode("*[6]", $segment, true, "/^{$patterns['time']}/u");
            $timeArr = $this->http->FindSingleNode("*[7]", $segment, true, "/^{$patterns['time']}/u");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $status = $this->http->FindSingleNode("*[8]", $segment, true, "/^(Confirmed)$/i");
            $s->extra()->status($status);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ *[2][normalize-space()='Flight'] and *[5][normalize-space()='Carrier'] ]");
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
