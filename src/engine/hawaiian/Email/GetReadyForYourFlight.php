<?php

namespace AwardWallet\Engine\hawaiian\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GetReadyForYourFlight extends \TAccountChecker
{
    public $mailFiles = "hawaiian/it-259332214.eml, hawaiian/it-264109125.eml, hawaiian/it-264151106.eml";

    private $detectSubject = [
        // en
        'Get ready for your flight to',
    ];

    private $langDetectors = [
        'en' => ['FLIGHT INFORMATION'],
    ];

    private $lang = '';

    private static $dict = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '.hawaiianairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".hawaiianairlines.com/")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("(//*/@src[{$this->contains('&PNR_LOC=')}])[1]", null, true, "#&PNR_LOC=([A-Z\d]{5,7})&#"))
            ->travellers(array_values(array_unique($this->http->FindNodes("//*[{$this->eq($this->t("Traveler"))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1]"))));

        $nodes = $this->http->XPath->query($xpath = "//text()[{$this->eq($this->t('FLIGHT INFORMATION'))}]");
        $countNodes = $nodes->length;

        foreach ($nodes as $i => $root) {
            $pos = $i + 1;
            $s = $f->addSegment();

            $pXpath = "[count(preceding::text()[{$this->eq($this->t('FLIGHT INFORMATION'))}]) = {$pos} and count(following::text()[{$this->eq($this->t('FLIGHT INFORMATION'))}]) = " . ($countNodes - $pos) . "]";

            $info = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t("Operated by Flight"))}]{$pXpath}"));

            if (preg_match("/^\s*Operated by Flight\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $info, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $info = implode("\n", $this->http->FindNodes("//*[{$this->eq($this->t("Depart"))}]{$pXpath}/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^\s*(?<dt>\d{1,2}:\d{2}.*\s+.*\d{4}.*)\s*\n\s*(?<name>.+)/", $info, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['dt']));
            }
            $info = implode("\n", $this->http->FindNodes("//*[{$this->eq($this->t("Depart"))}]{$pXpath}/preceding::tr[normalize-space()][1]"));

            if (preg_match("/^\s*([A-Z]{3})\W+([A-Z]{3})\s*$/", $info, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[2]);
            }
            $info = implode("\n", $this->http->FindNodes("//*[{$this->eq($this->t("Arrive"))}]{$pXpath}/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^\s*(?<dt>\d{1,2}:\d{2}.*\s+.*\d{4}.*)\s*\n\s*(?<name>.+)/", $info, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['dt']));
            }

            $infos = $this->http->XPath->query("//*[{$this->eq($this->t("Traveler"))}]{$pXpath}/following-sibling::*[normalize-space()][1]");
            $cabins = [];
            $seats = [];

            foreach ($infos as $row) {
                $text = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()]", $row));

                if (preg_match("/^\s*.+\n(.+)\s*\|\s*Seat\s*(\d{1,3}[A-Z])\s*$/", $text, $m)) {
                    $cabins[] = $m[1];
                    $seats[] = $m[2];
                } elseif (preg_match("/^\s*.+\n(?:\s*{$this->opt($this->t("Seat selection may be available at check-in"))}\n)?(.+)\s*$/", $text, $m)) {
                    $cabins[] = $m[1];
                }
            }

            $cabins = array_unique($cabins);

            if (count($cabins) == 1) {
                $s->extra()
                    ->cabin(array_shift($cabins));
            }

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }
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

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            // 06:54 AM       //01/18/2023
            "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+(\d{2}\\/\d{2}\\/\d{4})\s*$/iu",
        ];
        $out = [
            "$2, $1",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug('$str = ' . print_r($str, true));

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return str_replace(' ', '\s?', preg_quote($s, '/')); }, $field)) . ')';
    }
}
