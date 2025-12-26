<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "spirit/it-61082335.eml";
    private $reFrom = [
        '@fly.spirit-airlines.com',
    ];
    private $reSubject = [
        'Spirit Airlines Email Boarding Pass:',
    ];
    private $reProvider = ['Spirit Airlines'];
    private $detectLang = [
        'en' => [
            'This is your boarding pass. Use this on a mobile device at the airport.',
        ],
    ];
    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            //            "Reservation Credit ID" => "",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $xpath = "//text()[(contains(normalize-space(), 'hour') and contains(normalize-space(), 'minute')) or contains(normalize-space(), 'hour')]/ancestor::table[2]";
        $node = $this->http->XPath->query($xpath);

        foreach ($node as $root) {
            $flight = $email->add()->flight();
            $confirmation = $this->re("/:\s*([A-Z\d]{5,6})/", $parser->getSubject());

            if (!empty($confirmation)) {
                $flight->general()
                    ->confirmation($confirmation);
            }

            $seg = $flight->addSegment();

            $airlineText = $this->http->FindSingleNode("./descendant::tr[1]", $root);
            $dateDep = '';

            if (preg_match("/^([A-Z]{2})(\d{2,4})\s*(\d+\s*\w+\s*\d{4})/", $airlineText, $m)) {
                $seg->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $dateDep = $m[3];
            }

            $timeText = $this->http->FindSingleNode("./descendant::tr[normalize-space()][last()]", $root);

            if (preg_match("/^([\d\:]+\s*A?P?M)\s*([\d\:]+\s*A?P?M)$/", $timeText, $m)) {
                $timeDep = $m[1];
                $timeArr = $m[2];

                $seg->departure()
                    ->date(strtotime($dateDep . ', ' . $timeDep));

                $seg->arrival()
                    ->date(strtotime($dateDep . ', ' . $timeArr));
            }

            $duration = $this->http->FindSingleNode("./descendant::tr[normalize-space()][last()]/preceding::tr[contains(normalize-space(), 'hours')][1]", $root);

            if (!empty($duration)) {
                $seg->extra()
                    ->duration($duration);
            }

            $codeText = $this->http->FindSingleNode("./descendant::tr[normalize-space()][last()]/preceding::tr[contains(normalize-space(), 'hour')][1]/preceding::tr[normalize-space()][1]", $root);

            if (preg_match("/^([A-Z]{3})\s*([A-Z]{3})$/", $codeText, $m)) {
                $seg->departure()
                    ->code($m[1]);

                $seg->arrival()
                    ->code($m[2]);
            }

            $traveller = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'ADT') or starts-with(normalize-space(), 'CHD')][1]/preceding::text()[normalize-space()][1]", $root);

            if (!empty($traveller)) {
                $flight->general()
                    ->traveller($traveller, true);
            }

            $seat = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'SEAT')][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($seat)) {
                $seg->extra()
                    ->seat($seat);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
