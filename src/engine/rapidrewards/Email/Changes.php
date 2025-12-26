<?php

namespace AwardWallet\Engine\rapidrewards\Email;

// TODO: merge with parsers malaysia/FlightRetiming(object), aviancataca/Air(object), flyerbonus/TripReminder(object), thaiair/Cancellation(object), mabuhay/FlightChange(object), lotpair/FlightChange(object) (in favor of malaysia/FlightRetiming)

use AwardWallet\Schema\Parser\Email\Email;

class Changes extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-6090639.eml, rapidrewards/it-66993330.eml";

    public $reFrom = "southwest.com";

    public $reBody = [
        'en' => [
            ['Updated flight information', 'Your new itinerary'],
            ['Previous flight information', 'Itinerary information'],
        ],
    ];

    public $reSubject = [
        'Changes to your upcoming Southwest trip',
        'Some changes occurred to your flight',
    ];

    public $lang = 'en';

    public static $dict = [
        'en' => [
            'Your new itinerary' => ['Your new itinerary', 'Itinerary information', 'Itinerary information'],
            'Hello'              => ['Hello', 'Dear'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmail($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Southwest')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(),'SIMPLE RE-ACCOMODATION')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference'))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,}$/");
        $f->general()->confirmation($confirmation);

        $f->program()->accounts($this->http->FindNodes("//text()[contains(normalize-space(),'Frequent flyer')]/following::text()[normalize-space()][1]/ancestor::span[1]", null, "/\/\s+([-A-Z\d]{5,})$/"), false);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $helloTraveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference'))}]/following::text()[{$this->contains($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s+(.+?)[,:;!? ]*$/");
        $travellers = preg_split('/\s*,\s*/', $helloTraveller);

        if (empty($travellers) || count($travellers) == 1) {
            $travellers2 = array_filter($this->http->FindNodes("//text()[contains(normalize-space(),'Frequent flyer')]/following::text()[normalize-space()][1]/ancestor::span[1]", null, "/^(.+?)\s*\//"));

            if (count($travellers2)) {
                $travellers = $travellers2;
            }
        }

        if ($confirmation) {
            foreach ($travellers as $key => $tName) {
                $extraName = $this->http->FindSingleNode("//p[{$this->contains($tName)} and contains(normalize-space(),'{$confirmation}')]", null, true, "/^({$patterns['travellerName']})\s+{$confirmation}$/u");

                if ($extraName && mb_strlen($extraName) > mb_strlen($tName)) {
                    $travellers[$key] = $extraName;
                }
            }
        }
        $f->general()->travellers(array_unique($travellers));

        $xpath = "//text()[{$this->contains($this->t('Your new itinerary'))}]/ancestor::tr[1]/following-sibling::tr[contains(.,'From' ) and contains(.,'Departure')]/following-sibling::tr[./td[string-length(normalize-space(.))>0][3][contains(.,':')]]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug('$xpath = ' . print_r($xpath, true));

        foreach ($nodes as $root) {
            $s = $f->addSegment();
//            $date = $this->http->FindSingleNode("./following-sibling::tr[1]/td[string-length(normalize-space(.))>0][1]", $root);

            $node = $this->http->FindSingleNode("./td[string-length(normalize-space(.))>0][5]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $s->airline()->name($m[1])->number($m[2]);
            }

            $node = $this->http->FindSingleNode("./td[string-length(normalize-space(.))>0][1]", $root);

            if (preg_match("#(.+?)\s+([A-Z]{3})\s+(.+)#", $node, $m)) {
                $s->departure()->name($m[1] . ' ' . $m[3])->code($m[2]);
            } else {
                $s->departure()->name($node)->noCode();
            }

            $node = $this->http->FindSingleNode("./td[string-length(normalize-space(.))>0][2]", $root);

            if (preg_match("#(.+?)\s+([A-Z]{3})\s+(.+)#", $node, $m)) {
                $s->arrival()->name($m[1] . ' ' . $m[3])->code($m[2]);
            } else {
                $s->arrival()->name($node)->noCode();
            }

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[string-length(normalize-space(.))>0][1]", $root)));
            $s->departure()->date(strtotime($this->http->FindSingleNode("./td[string-length(normalize-space(.))>0][3]", $root, true, "#(\d+:\d+)#"), $date));

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/td[string-length(normalize-space(.))>0][2]", $root)));
            $s->arrival()->date(strtotime($this->http->FindSingleNode("./td[string-length(normalize-space(.))>0][4]", $root, true, "#(\d+:\d+)#"), $date));

            $s->extra()->cabin($this->http->FindSingleNode("./td[string-length(normalize-space(.))>0][6]", $root));
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\w+)\s+(\d+),\s+(\d+)#u',
        ];
        $out = [
            '$2 $1 $3',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
