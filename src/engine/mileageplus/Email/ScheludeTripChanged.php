<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheludeTripChanged extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-142976939.eml, mileageplus/it-145562270.eml, mileageplus/it-207424580.eml, mileageplus/it-264646307.eml";
    public $subjects = [
        'The schedule for your trip to', 'Some of the flight numbers for your trip to',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'statusPhrases'  => ['The schedule for your trip to', 'Some of the flight numbers for your trip to'],
            'has'            => ['has', 'have'],
            'statusVariants' => 'changed',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@united.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'United Airlines')] | //text()[contains(normalize-space(),'United Airlines')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('statusPhrases'))}]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(),'Flight to')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]united\.com$/', $from) > 0;
    }

    public function ParseSegment(\AwardWallet\Schema\Parser\Common\Flight $flight, $root): void
    {
        $this->logger->debug(__METHOD__);
        $s = $flight->addSegment();

        $depInfo = implode(' ', $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)?\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*(?<depDate>\w+\s+\d{1,2},\s*\d{4})(?:\s+[\d:]+\s*a?p?\.m\.)?\s+(?<depTime>[\d:]+\s*a?p?\.m\.)\s+(?<depName>.{2,})\s*\(\s*(?<depCode>[A-Z]{3})\s*\)$/u", $depInfo, $m)
        ) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['number'])
            ;
            $operator = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::text()[contains(normalize-space(),'is operated by')]", $root, true, "/{$m['airline']}\s*{$m['number']}\s+{$this->opt($this->t('is operated by'))}\s*(.+)/");
            $s->airline()->operator($operator, false, true);

            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
                ->date(strtotime($m['depDate'] . ' ' . $m['depTime']));
        }

        $durationInfo = implode(' ', $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/Duration:\s*(?<duration>[\d\sa-z]+)\s+\D+\s+\(\s*(?<booking>[A-Z]{1,2})\s*\)/", $durationInfo, $m)) {
            $s->extra()
                ->duration($m['duration'])
                ->bookingCode($m['booking']);
        }

        $arrInfo = implode(' ', $this->http->FindNodes("*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^(?<arrDate>\w+\s+\d{1,2},\s*\d{4})(?:\s+[\d:]+\s*a?p?\.m\.)?\s+(?<arrTime>[\d:]+\s*a?p?\.m\.)\s+(?<arrName>.{2,}?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)$/", $arrInfo, $m)) {
            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->date(strtotime($m['arrDate'] . ' ' . $m['arrTime']));
        }

        if (empty($s->getAirlineName()) && empty($s->getDuration())) {
            $flight->removeSegment($s);

            return;
        }

        foreach ($flight->getSegments() as $segment) {
            if ($segment->getId() !== $s->getId()) {
                if (($segment->getFlightNumber() === $s->getFlightNumber())
                    && ($segment->getDepDate() === $s->getDepDate())
                    && ($segment->getDepCode() === $segment->getDepCode())
                    && ($segment->getArrCode() === $segment->getArrCode())
                ) {
                    if (!empty($s->getSeats())) {
                        $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                    }
                    $flight->removeSegment($s);

                    break;
                }
            }
        }
    }

    public function ParseSegment2(\AwardWallet\Schema\Parser\Common\Flight $flight, $root): void
    {
        $this->logger->debug(__METHOD__);
        $s = $flight->addSegment();

        $depInfo = implode(' ', $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));
        $this->logger->debug($depInfo);

        if (preg_match("/^\s*(?<depDate>\w+\s+\d{1,2},\s*\d{4})(?:\s+[\d:]+\s*a?p?\.m\.)?\s+(?<depTime>[\d:]+\s*a?p?\.m\.)\s+(?<depName>.{2,})\s*\(\s*(?<depCode>[A-Z]{3})\s*\)$/u", $depInfo, $m)
        ) {
            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
                ->date(strtotime($m['depDate'] . ' ' . $m['depTime']));
        }

        $durationInfo = implode(' ', $this->http->FindNodes("*[normalize-space()][3]/descendant::text()[normalize-space()]", $root));
        $this->logger->debug($durationInfo);

        if (preg_match("/^(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)?\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*Duration:\s*(?<duration>[\d\sa-z]+)\s+\D*\s*\(\s*(?<booking>[A-Z]{1,2})\s*\)/", $durationInfo, $m)) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['number'])
            ;
            $operator = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::text()[contains(normalize-space(),'is operated by')]", $root, true, "/{$m['airline']}\s*{$m['number']}\s+{$this->opt($this->t('is operated by'))}\s*(.+)/");
            $s->airline()->operator($operator, false, true);

            $s->extra()
                ->duration($m['duration'])
                ->bookingCode($m['booking']);
        }

        $arrInfo = implode(' ', $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

        if (preg_match("/^(?<arrDate>\w+\s+\d{1,2},\s*\d{4})(?:\s+[\d:]+\s*a?p?\.m\.)?\s+(?<arrTime>[\d:]+\s*a?p?\.m\.)\s+(?<arrName>.{2,}?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)$/", $arrInfo, $m)) {
            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->date(strtotime($m['arrDate'] . ' ' . $m['arrTime']));
        }

        if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
            $route = $s->getDepCode() . '-' . $s->getArrCode();
            $seats = explode(",", $this->http->FindSingleNode("//text()[normalize-space()='Seat Information']/following::tr[*[1][normalize-space()='{$route}']]/*[normalize-space()][last()]",
                null, true, "/^\s*(\d{1,3}[A-Z](?:\s*,\s*\d{1,3}[A-Z])*)\s*$/"));

            if (!empty($seats)) {
                $s->extra()
                    ->seats(preg_replace("/\s+/", '', $seats));
            }
        }

        if (empty($s->getAirlineName()) && empty($s->getDuration())) {
            $flight->removeSegment($s);

            return;
        }

        foreach ($flight->getSegments() as $segment) {
            if ($segment->getId() !== $s->getId()) {
                if (($segment->getFlightNumber() === $s->getFlightNumber())
                    && ($segment->getDepDate() === $s->getDepDate())
                    && ($segment->getDepCode() === $segment->getDepCode())
                    && ($segment->getArrCode() === $segment->getArrCode())
                ) {
                    if (!empty($s->getSeats())) {
                        $segment->extra()->seats(array_unique(array_merge($segment->getSeats(), $s->getSeats())));
                    }
                    $flight->removeSegment($s);

                    break;
                }
            }
        }
    }

    public function ParseEmail(Email $email): void
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd") or starts-with(translate(normalize-space(),"0123456789：.","dddddddddd::"),"dd:dd"))';

        $f = $email->add()->flight();

        $statusValues = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}.+?{$this->opt($this->t('has'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/"));

        if (count(array_unique($statusValues)) === 1) {
            $f->general()->status(array_shift($statusValues));
        }

        $confirmation = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Confirmation number:')]", null, true, "/{$this->opt($this->t('Confirmation number:'))}\s*([A-Z\d]{5,})$/");

        if (empty($confirmation)) {
            $conf = array_unique($this->http->FindNodes("//text()[contains(normalize-space(),'Confirmation number:')]", null, "/{$this->opt($this->t('Confirmation number:'))}\s*([A-Z\d]{5,})$/"));

            if (count($conf) == 1) {
                $confirmation = array_shift($conf);
            }
        }

        $f->general()
           ->confirmation($confirmation)
        ;
        $travellersRows = $this->http->FindNodes("//text()[normalize-space()='Travelers']/ancestor::tr[1]/following-sibling::tr[normalize-space()]", null, "/^[[:alpha:]][-.\'’[:alpha:] ,]*[[:alpha:]]$/u");
        $travellers = [];

        foreach ($travellersRows as $row) {
            $travellers = array_merge($travellers, explode(",", $row));
        }
        $travellers = array_map('trim', $travellers);
        $f->general()
            ->travellers($travellers, true);

        $xpath = "//tr[ count(*[normalize-space()])=3 and *[normalize-space()][1]/descendant::text()[{$xpathTime}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->starts($this->t('Duration'))} and not(ancestor::*[contains(@style,'line-through')]) and not(ancestor::s)]  and *[normalize-space()][3]/descendant::text()[{$xpathTime}] ]";
        $segments = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath 1 = '.print_r( $xpath,true));

        foreach ($segments as $root) {
            $this->ParseSegment($f, $root);
        }

        if ($segments->length === 0) {
            $xpath = "//tr[ count(*[normalize-space()])=3"
                . " and *[normalize-space()][1]/descendant::text()[{$xpathTime}]"
                . " and *[normalize-space()][2]/descendant::text()[{$xpathTime}]"
                . " and *[normalize-space()][3]/descendant::text()[{$this->contains($this->t('Duration'))} and not(ancestor::*[contains(@style,'line-through')]) and not(ancestor::s)]"
                . "]";
            $segments = $this->http->XPath->query($xpath);
//            $this->logger->debug('$xpath 2 = '.print_r( $xpath,true));

            foreach ($segments as $root) {
                $this->ParseSegment2($f, $root);
            }
        }

        /* WTF?
        $nodes = $this->http->XPath->query("//text()[normalize-space()='New itinerary']/following::div[starts-with(normalize-space(), 'Duration')][not(contains(@style, 'line'))][1]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $this->ParseSegment($f, $root);

            if ($this->http->XPath->query("./following::tr[1][contains(normalize-space(), 'CONNECTION')]/following::table[1]", $root)->length == 1) {
                for ($i = 1; $i <= 10; $i++) {
                    $newRoot = $this->http->XPath->query("./following::tr[1][contains(normalize-space(), 'CONNECTION')]/following::table[1]", $root);

                    if ($newRoot->length > 0) {
                        foreach ($newRoot as $root) {
                            $this->ParseSegment($f, $root);
                        }
                    }
                }
            }
        }
        */
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
