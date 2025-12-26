<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckInHtml2016En extends \TAccountChecker
{
    public $mailFiles = "spirit/it-39945826.eml, spirit/it-4877583.eml, spirit/it-5208354.eml, spirit/it-77840449.eml";

    public $reBody = [
        'en' => ['It\'s time to check-in!', 'Spirit Airlines'],
    ];
    public $reSubject = [
        "Check-In\s+for\s+Tomorrow\'s\s+Flight",
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);
        $email->setType("CheckInHtml2016En");

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if ($this->http->XPath->query("//a[contains(@href,'spiritairlines.com') or contains(@href,'spirit-airlines.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match('/' . $reSubject . '/ui', $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@t.spiritairlines.com") !== false || stripos($from, "@fly.spirit-airlines.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
        ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'CONFIRMATION')]/following::text()[normalize-space(.)!=''][1]", null, true, "#^\s*([A-Z\d]+)\s*$#"));

        if ($passengers = $this->http->FindNodes("//text()[normalize-space()='WHO:']/following::td[normalize-space(.)!=''][1]//text()[normalize-space()]")) {
            $r->general()
                ->travellers($passengers, true);
        } else {
            $passenger = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hi ')]", null, true, "#^\s*Hi ([^,a-z]+?|(?!There)[^,]*?),[\s,]*$#"); // Hi NATASHA, , ; Hi There, ;

            if (!empty($passenger)) {
                $r->general()->traveller($passenger, false);
            }
        }

        if ($this->http->XPath->query("//img[normalize-space(@alt)='spirit'] | //a[contains(@href,'save.spirit-airlines.com') or contains(@href,'l.spiritairlines.com')]")->length > 0) {
            $airlineName = 'Spirit Airlines';
        }
        // Segments
        $xPath = "//text()[normalize-space() = 'WHEN:']/ancestor::td[1]";
        $segments = $this->http->XPath->query($xPath);

        foreach ($segments as $root) {
            $s = $r->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("./preceding::td[normalize-space()][position() < 5][normalize-space()='WHAT:']/following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#Flight (\d{1,5})\b#", $flight, $m)) {
                $s->airline()
                    ->number($m[1]);
            } elseif (empty($flight)) {
                $s->airline()
                    ->noNumber();
            }

            if (!empty($airlineName)) {
                $s->airline()
                    ->name($airlineName);
            } else {
                $s->airline()
                    ->noName();
            }

            $dateDep = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#([^\d]+)\s+(\d+),?\s+(\d+)\s+(\d+:\d+\s*[ap]m)#i", $dateDep, $m)) {
                $date = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3]);
                $s->departure()->date(strtotime($m[4], $date));
                $timeArr = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][contains(.,'Arrive')][1]",
                    $root, true, "#Arrive\s*:\s+(\d+:\d+\s*[ap]m)#i");
                $s->arrival()->date(strtotime($timeArr, $date));
            } else {
                $date = strtotime($dateDep);
                $timeDep = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][contains(.,'Arrive')][1]",
                    $root, true, "#Depart\s*:\s+(\d+:\d+\s*[ap]m)#i");
                $s->departure()->date(strtotime($timeDep, $date));
                $timeArr = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][contains(.,'Arrive')][1]",
                    $root, true, "#Arrive\s*:\s+(\d+:\d+\s*[ap]m)#i");
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $node = $this->http->FindSingleNode("./following::td[normalize-space()][position() < 5][normalize-space() ='WHERE:'][1]/following::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(.+?)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }
            $node = $this->http->FindSingleNode("./following::td[normalize-space()][position() < 5][normalize-space() ='WHERE:'][1]/following::text()[normalize-space(.)!=''][2]", $root);

            if (preg_match("#(.+?)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            }
            $terminal = $this->http->FindSingleNode("./following::td[normalize-space()][position() < 5][normalize-space() ='WHERE:'][1]/following::text()[normalize-space(.)!=''][position() < 5][contains(.,'Terminal')]",
                $root, true, "#Terminal\s*:\s+(.+)#");

            if (!empty($terminal)) {
                $s->departure()->terminal($terminal);
            }
        }
    }
}
