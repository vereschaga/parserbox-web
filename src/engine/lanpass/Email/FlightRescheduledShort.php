<?php

namespace AwardWallet\Engine\lanpass\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightRescheduledShort extends \TAccountChecker
{
    public $mailFiles = "lanpass/it-169208784.eml, lanpass/it-172110485-es.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'newSchedule'    => ['Nuevo Horario'],
            'timeDep'        => ['nueva hora de salida es'],
            'statusPhrases'  => ['Por tu seguridad, ya estás'],
            'statusVariants' => ['confirmado'],
        ],
        'en' => [
            'newSchedule'    => ['New schedule'],
            'timeDep'        => ['new departure time is'],
            'statusPhrases'  => ['You are already'],
            'statusVariants' => ['confirmed'],
        ],
        'pt' => [
            'newSchedule'    => ['Voo sugerido'],
            //            'timeDep'        => ['new departure time is'],
            //            'statusPhrases'  => ['You are already'],
            //            'statusVariants' => ['confirmed'],
        ],
    ];

    private $subjects = [
        'es' => ['Tu vuelo ha sido reprogramado'],
        'en' => ['Your flight has been rescheduled'],
    ];

    private $detectors = [
        'es' => ['reprogramado'],
        'en' => ['rescheduled'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@info.latam.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".latam.com/") or contains(@href,"info.latam.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"LATAM AIRLINES GROUP S.A. All rights reserved") or contains(.,"latam@info.latam.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FlightRescheduledShort' . ucfirst($this->lang));

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $segments = $this->http->XPath->query("//tr[{$this->eq($this->t('newSchedule'))}]/ancestor::tr[ *[normalize-space()][2] ][1]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('newSchedule'))}]/following-sibling::tr[normalize-space()]", $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $xpathRightTd = "*[normalize-space()][last()]/descendant::*[count(tr[normalize-space()])=2][1]";

            $dateVal = $this->http->FindSingleNode($xpathRightTd . "/tr[normalize-space()][1]", $root, true, '/^.*\d.*$/');
            $dateNormal = $this->normalizeDate($dateVal);
            $airportsVal = implode(' ', $this->http->FindNodes($xpathRightTd . "/tr[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<time>\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)\s+(?<codeDep>[A-Z]{3})\s+(?<codeArr>[A-Z]{3})$/", $airportsVal, $m)) {
                // 19:55 AEP LIM
                $s->departure()->code($m['codeDep']);
                $s->arrival()->code($m['codeArr']);

                if ($dateNormal) {
                    $s->departure()->date(strtotime($m['time'], strtotime($dateNormal)));
                    $s->arrival()->noDate();
                }
            }
        }

        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}[ ]+({$this->opt($this->t('statusVariants'))})(?:[ ,.;:!?]|$)/");
        $f->general()->status($status);

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

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['newSchedule']) || empty($phrases['timeDep'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['newSchedule'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['timeDep'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[-\s]*([[:alpha:]]+)[-\s]*(\d{4})$/u', $text, $m)) {
            // 21-July-2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
