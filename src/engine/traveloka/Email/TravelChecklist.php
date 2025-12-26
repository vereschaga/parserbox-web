<?php

namespace AwardWallet\Engine\traveloka\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TravelChecklist extends \TAccountChecker
{
    public $mailFiles = "traveloka/it-33872715.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'headers'    => ['RE-CHECK YOUR FLIGHT DETAILS', 'Re-check Your Flight Details'],
            'confNumber' => ['Booking Code (PNR):', 'Booking Code (PNR) :'],
        ],
    ];

    private $subjects = [
        'en' => ['travel checklist'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@traveloka.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//www.traveloka.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Download Traveloka App") or contains(normalize-space(),"you have made a reservation through Traveloka") or contains(normalize-space(),"Traveloka. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->ota(); // because Traveloka is not airline

        $this->parseEmail($email);
        $email->setType('TravelChecklist' . ucfirst($this->lang));

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

    private function parseEmail(Email $email)
    {
        $traveller = $this->http->FindSingleNode("//h1/preceding::text()[{$this->starts($this->t('Hello '))}]", null, true, "/{$this->opt($this->t('Hello '))}\s*([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])(?:[,!]|$)/");

        $flights = $this->http->XPath->query("//text()[{$this->eq($this->t('headers'))}]/ancestor::*[ (self::td or self::th) and descendant::h2[normalize-space()] ][1]");

        foreach ($flights as $root) {
            $f = $email->add()->flight();

            $f->general()->traveller($traveller);

            $confirmationNumberTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('confNumber'))}]", $root);
            $confirmationNumber = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", $root, true, '/^[A-Z\d]{5,}$/');
            $f->general()->confirmation($confirmationNumber, preg_replace('/\s*:+\s*$/', '', $confirmationNumberTitle));

            $segments = $this->http->XPath->query("descendant::*[*[normalize-space()][1][contains(.,'(') and contains(.,')')] and count(*[normalize-space()])=3]/ancestor::*[count(*)=2][1]", $root);

            foreach ($segments as $segment) {
                $s = $f->addSegment();

                $flight = $this->http->FindSingleNode("preceding-sibling::*[string-length(normalize-space())>1][1]/descendant::text()[normalize-space()][1][ following::*[1][descendant-or-self::img] ]", $segment);
                $s->airline()
                    ->name($flight)
                    ->noNumber() // on <img>
                ;

                $xpathFragment1 = "descendant::*[count(*[normalize-space()])=3]";

                $depCode = $this->http->FindSingleNode($xpathFragment1 . "[1]/*[normalize-space()][1]", $segment, true, "/\(\s*([A-Z]{3})\s*\)$/");
                $s->departure()->code($depCode);

                $depDate = $this->http->FindSingleNode($xpathFragment1 . "[1]/*[normalize-space()][2]", $segment);
                $s->departure()->date2($this->normalizeDate($depDate));

                $arrCode = $this->http->FindSingleNode($xpathFragment1 . "[2]/*[normalize-space()][1]", $segment, true, "/\(\s*([A-Z]{3})\s*\)$/");
                $s->arrival()->code($arrCode);

                $arrDate = $this->http->FindSingleNode($xpathFragment1 . "[2]/*[normalize-space()][2]", $segment);
                $s->arrival()->date2($this->normalizeDate($arrDate));
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['headers']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['headers'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function normalizeDate($text)
    {
        return str_replace('•', '', $text);
    }
}
