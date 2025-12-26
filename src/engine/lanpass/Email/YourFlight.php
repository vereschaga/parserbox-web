<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-30066176.eml";

    private $langDetectors = [
        'en' => ['You can find the details below:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@mail.latam.com") or contains(.,"www.latam.com") or contains(normalize-space(.),"LATAM AIRLINES GROUP S.A. - All rights reserved")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//mail.latam.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('YourFlight' . ucfirst($this->lang));

        return $email;
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

        // confirmation number
        $f->general()->noConfirmation();

        // traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\s+([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])(?:,|$)/m");
        $f->general()->traveller($traveller);

        // segments
        $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('New Flight Schedule'))}]/ancestor::tr/following::tr[ ./*[normalize-space(.)][1][./descendant::text()[{$this->eq($this->t('FLIGHT'))}]] and ./*[normalize-space(.)][position()>1][./descendant::text()[{$this->eq($this->t('DEPARTURE DATE'))}]] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('FLIGHT'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            // depCode
            $airportDep = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('FROM'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", $segment, true, '/^[A-Z]{3}$/');
            $s->departure()->code($airportDep);

            // arrCode
            $airportArr = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('TO'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", $segment, true, '/^[A-Z]{3}$/');
            $s->arrival()->code($airportArr);

            $dateDep = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE DATE'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", $segment);

            $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?'; // 4:19PM    |    2:00 p.m.

            // depDate
            $timeDep = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE TIME'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", $segment, true, "/^{$patterns['time']}$/");
            $s->departure()->date2($dateDep . ' ' . $timeDep);

            // arrDate
            $timeArr = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVAL TIME'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]", $segment, true, "/^{$patterns['time']}$/");
            $s->arrival()->date2($dateDep . ' ' . $timeArr);
        }
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
