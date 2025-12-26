<?php

namespace AwardWallet\Engine\pia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Air extends \TAccountChecker
{
    public $mailFiles = "pia/it-27070951.eml";

    public static $dictionary = [
        'en' => [
        ],
    ];
    private $lang = '';
    private $reFrom = ['piac.aero'];
    private $reProvider = ['PIA'];
    private $reSubject = [
        'PIA Web Ticket Purchase confirmation for reservation:',
        'PIA E-Ticket Purchase confirmation for reservation:',
    ];
    private $reBody = [
        'en' => [
            ['Thank you for choosing Pakistan International Airlines', 'FLIGHT NUMBER'],
            ['Thank you for choosing PIA. You may click on the following link', 'FLIGHT NUMBER:'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        if ($conf = $this->http->FindSingleNode("//td[normalize-space(.)='Reservation Code']/following-sibling::td[normalize-space(.)][1]", null, true, '/([A-Z\d]{5,9})/')) {
            $f->general()
                ->confirmation($conf);
        }

        if ($paxs = array_filter(array_unique($this->http->FindNodes("//td[normalize-space(.)='Passenger Detail']/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]")))) {
            foreach ($paxs as $pax) {
                $f->addTraveller($pax);
            }
        }

        if ($tn = array_filter(array_unique($this->http->FindNodes("//td[normalize-space(.)='Passenger Detail']/following-sibling::td[normalize-space(.)][2]/descendant::text()[normalize-space(.)]")))) {
            foreach ($tn as $t) {
                $f->addTicketNumber($t, false);
            }
        }

        $xpath = "//text()[starts-with(normalize-space(.), 'Flight Details')]/ancestor::tr[2]/following-sibling::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("descendant::td[starts-with(normalize-space(.), 'FLIGHT NUMBER')]", $root);

            if (preg_match('/\:\s*([A-Z\d]{2})\s*(\d+)/', $airInfo, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if ($cabin = $this->http->FindSingleNode("descendant::td[starts-with(normalize-space(.), 'FLIGHT NUMBER')]/following-sibling::td[1]", $root)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $day = $this->http->FindSingleNode('descendant::node()[count(tr)>=5]/tr[1]/td[1]', $root);
            $monthYear = implode(' ', $this->http->FindNodes("descendant::tr[contains(normalize-space(.), 'Departure Time') and not(.//tr)]/td[1]/descendant::text()[normalize-space(.)]", $root));

            $depTime = $this->http->FindSingleNode("descendant::td[normalize-space(.)='Departure Time:']/following-sibling::td[1]", $root, true, '/(\d{1,2}:\d{2})/');

            if ($day && $monthYear && $depTime) {
                $s->departure()
                    ->date(strtotime($day . ' ' . $monthYear . ', ' . $depTime));
            }

            $arrTime = $this->http->FindSingleNode("descendant::td[normalize-space(.)='Arrival Time']/following-sibling::td[1]", $root, true, '/(\d{1,2}:\d{2})/');

            if ($day && $monthYear && $arrTime) {
                $s->arrival()
                    ->date(strtotime($day . ' ' . $monthYear . ', ' . $arrTime));
            }

            if ($dep = $this->http->FindSingleNode("descendant::tr[contains(normalize-space(.), 'Departure Time') and not(.//tr)]/following-sibling::tr[1]/td[2]", $root)) {
                $s->departure()
                    ->name($dep)
                    ->noCode();
            }

            if ($arr = $this->http->FindSingleNode("descendant::tr[contains(normalize-space(.), 'Departure Time') and not(.//tr)]/following-sibling::tr[1]/td[3]", $root)) {
                $s->arrival()
                    ->name($arr)
                    ->noCode();
            }
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
