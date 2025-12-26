<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "spirit/it-77113530.eml, spirit/it-77680935.eml";

    private $detectFrom = [
        '@fly.spirit-airlines.com',
    ];
    private $detectSubject = [
        'It’s Almost Go Time!',
        'Spirit Airlines Email Boarding Pass:',
        'Add Bags Now To Save',
    ];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);
        $this->parseStatement($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->detectSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.spirit-airlines.com')] | //text()[contains(., 'Spirit Airlines')]")->length === 0) {
            return false;
        }

        if (
            $this->http->XPath->query("//a[contains(@href, '.spirit-airlines.com') and starts-with(normalize-space(), 'MODIFY ITINERARY')]")->length > 0
            || $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Your mobile boarding pass is below')]")->length > 0) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $confs = array_unique($this->http->FindNodes("//text()[normalize-space(.) = 'Confirmation Number:']/following::text()[normalize-space()][1]"));

        foreach ($confs as $conf) {
            $f->general()->confirmation(
                $conf,
                'Confirmation Number'
            );
        }

        $travellersNodes = $this->http->XPath->query("//td[starts-with(normalize-space(), 'SEQ:')]/preceding::td[normalize-space()][2][starts-with(normalize-space(), 'FLIGHT')]/preceding::td[normalize-space()][1]");

        foreach ($travellersNodes as $tRoot) {
            $names[] = implode(" ", $this->http->FindNodes(".//text()[normalize-space()]", $tRoot));
        }

        if (!empty($names)) {
            $f->general()
                ->travellers(array_unique($names), true);
        }
        $segmentsText = [];
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Departing')]/ancestor::tr[contains(normalize-space(), 'Arriving')][1]");

        foreach ($nodes as $root) {
            if (in_array($root->nodeValue, $segmentsText)) {
                continue;
            }
            $segmentsText[] = $root->nodeValue;

            $s = $f->addSegment();

            // Departure
            $node = implode("\n", $this->http->FindNodes(".//text()[starts-with(., 'Departing')]/ancestor::td[1]//text()[normalize-space()]", $root));

            if (preg_match("/^\w+ (?<code>[A-Z]{3})\s+(?<name>.+)\s+(?<date>\d{1,2}\/.+)/", $node, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['date']))
                ;
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes(".//text()[starts-with(., 'Arriving')]/ancestor::td[1]//text()[normalize-space()]", $root));

            if (preg_match("/^\w+ (?<code>[A-Z]{3})\s+(?<name>.+)\s+(?<date>\d{1,2}\/.+)/", $node, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['date']))
                ;
            }

            // Airline
            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $flight = $this->http->FindSingleNode(".//following::text()[starts-with(normalize-space(), 'FLIGHT')][1]" .
                    "[preceding::text()[contains(., 'Departing')][1][contains(., '" . $s->getDepCode() . "')] and preceding::text()[contains(., 'Arriving')][1][contains(., '" . $s->getArrCode() . "')]]", $root,
                    true, "/^\s*FLIGHT\s*(\d{1,})\s*$/");

                if (!empty($flight)) {
                    $s->airline()
                        ->name('NK')
                        ->number($flight)
                    ;
                } else {
                    $s->airline()
                        ->name('NK')
                        ->noNumber()
                    ;
                }
                // Extra
                $seats = $this->http->FindNodes("//following::text()[starts-with(normalize-space(), 'SEAT ')][1]" .
                    "[preceding::text()[contains(., 'Departing')][1][contains(., '" . $s->getDepCode() . "')] and preceding::text()[contains(., 'Arriving')][1][contains(., '" . $s->getArrCode() . "')]]", null,
                    "/^\s*SEAT\s*(\d{1,3}[A-Z])\s*$/");

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            /*if ($this->http->XPath->query("//text()[normalize-space()='Barcode will not scan if printed']")->length > 0){
                foreach ($f->getTravellers() as $traveller){
                    $bp = $email->add()->bpass();
                    $bp->setFlightNumber('NK '.$s->getFlightNumber());
                    $bp->setDepDate($s->getDepDate());
                    $bp->setDepCode($s->getDepCode());
                    $bp->setUrl($this->http->FindSingleNode("//text()[normalize-space()='Barcode will not scan if printed']/following::text()[starts-with(normalize-space(), 'Departing {$s->getDepCode()}')][1]/following::td[contains(normalize-space(), '{$traveller[0]}')][1]/descendant::text()[starts-with(normalize-space(), 'FLIGHT')][1]/following::img[contains(@src, 'base')][1]/@src"));
                    $bp->setTraveller($traveller[0]);
                }
            }*/
        }
    }

    private function parseStatement(Email $email)
    {
        $info = $this->http->FindSingleNode("//text()[contains(., 'Points')]/ancestor::*[contains(., '#') and count(.//text()[contains(., '|')]) >= 2 and descendant::text()[normalize-space()][1][not(contains(., 'Points'))]][1]");

        if (preg_match("/^ *([[:alpha:]][[:alpha:]\- ]+) *\| *(\d[\d, ]*) *Points *\| *\#(\d{5,}) *(?:$|\|)/", $info, $m)) {
            $st = $email->add()->statement();

            $st
                ->setLogin($m[3])
                ->setNumber($m[3])
                ->setBalance(str_replace([',', ' '], '', $m[2]))
            ;

            if (!preg_match("/(\bguest|spirit)/iu", $m[1])) {
                $st
                    ->addProperty("Name", $m[1]);
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

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
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
