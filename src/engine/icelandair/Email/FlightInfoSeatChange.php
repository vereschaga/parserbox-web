<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightInfoSeatChange extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-31269412.eml";

    private $langDetectors = [
        'en' => ['Previous Seat'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"THANK YOU FOR FLYINGWITH ICELANDAIR") or contains(normalize-space(.),"Regards Icelandair")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//book.icelandair.is")]')->length === 0;

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
        $email->setType('FlightInfoSeatChange' . ucfirst($this->lang));

        if (stripos($parser->getHeaders()['from'], '@amadeus.com') !== false) {
            $email->setProviderCode('amadeus');
        }

        $this->parseEmail($email);

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

    public static function getEmailProviders()
    {
        return ['icelandair', 'amadeus'];
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $s = $f->addSegment();

        if ($email->getProviderCode() !== null && $email->getProviderCode() !== 'icelandair') {
            $f->program()->code('icelandair');
        }

        $flightInfoCells = $this->http->FindNodes("//tr[not(.//tr) and {$this->contains($this->t('Your itinerary Date Previous Seat New Seat'))}]/following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]");

        if (count($flightInfoCells) !== 4) {
            $this->logger->alert('Wrong seat information table!');

            return false;
        }

        $intro = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Please note that your seat request for flight'))}]");

        // airlineName
        // flightNumber
        if (preg_match("/{$this->opt($this->t('Please note that your seat request for flight'))}\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)\b/", $intro, $m)) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['flightNumber'])
            ;
        }

        // depName
        // depCode
        // arrName
        // arrCode
        if (preg_match('/^(?<depName>.+?)\s*\(\s*(?<depCode>[A-Z]{3})\s*\)\s+-\s+(?<arrName>.+?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)$/', $flightInfoCells[0], $m)) {
            // Boston (BOS) - Reykjavik (KEF)
            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
            ;
            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
            ;
        } elseif (preg_match('/^(?<depName>.+?)\s+-\s+(?<arrName>.+?)$/', $flightInfoCells[0], $m)) {
            // Boston - Reykjavik
            $s->departure()->name($m['depName']);
            $s->arrival()->name($m['arrName']);
        }

        // depDay
        if (preg_match('/^.{6,}$/', $flightInfoCells[1])) {
            $s->departure()
                ->day2($flightInfoCells[1])
                ->noDate()
            ;
            $s->arrival()->noDate();
        }

        // seats
        if (preg_match('/^\d+[A-Z]$/', $flightInfoCells[3])) {
            $s->extra()->seat($flightInfoCells[3]);
        }

        // confirmation number
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your booking reference:'))}]");

        if (preg_match("/({$this->opt($this->t('Your booking reference:'))})\s*([A-Z\d]{5,})$/", $confirmationNumber, $m)) {
            $f->general()->confirmation($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        return true;
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

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
