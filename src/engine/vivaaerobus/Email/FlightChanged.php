<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-79525980.eml, vivaaerobus/it-93853368.eml"; // +1 bcdtravel(html)[es]

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'confNumber-starts'       => ['Clave de reservación:', 'Clave de reservación :'],
            'confNumber-contains'     => 'clave de reservación',
            'Departs'                 => ['Salida'],
            'Arrives'                 => 'Llegada',
            'PREVIOUS FLIGHT DETAILS' => 'DETALLE VUELO ANTERIOR',
            'Flight'                  => 'Vuelo',
            'Operated by'             => 'Operado por',
            'Dear'                    => ['Estimado(a)', 'Dear'], // + en
            //'UPDATED FLIGHT DETAILS' => ''
        ],
        'en' => [
            'confNumber-starts'      => ['Booking reference:', 'Booking reference :'],
            'confNumber-contains'    => ['booking number', 'clave de reservación'], // + es
            'Departs'                => ['Departs'],
            'Dear'                   => ['Dear', 'Estimado(a)'], // + es
            'UPDATED FLIGHT DETAILS' => ['UPDATED FLIGHT DETAILS', 'Updated Flight Details'],
        ],
    ];

    private $subjects = [
        'es' => ['vuelo con Viva Aerobus ha cambiado'],
        'en' => ['flight has changed', 'Important information about your flight'],
    ];

    private $detectors = [
        'es' => ['DETALLES DE VUELO ACTUALIZADOS', 'Información actualizada de vuelo'],
        'en' => ['UPDATED FLIGHT DETAILS', 'Updated Flight Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]vivaaerobus\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Viva Aerobus') === false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".vivaaerobus.com/") or contains(@href,"notifications.vivaaerobus.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@notifications.vivaaerobus.com")]')->length === 0
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
        $email->setType('FlightChanged' . ucfirst($this->lang));

        $this->parseFlight($email);

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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = null;

        // it-79525980.eml
        $phrases = (array) $this->t('confNumber-starts');

        foreach ($phrases as $phrase) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($phrase)}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('confNumber-starts'))}\s*[A-Z\d]{5,}\b/");

            if ($confirmation) {
                break;
            }
        }

        if ($confirmation === null) {
            // it-93853368.eml
            $phrases = (array) $this->t('confNumber-contains');

            foreach ($phrases as $phrase) {
                $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($phrase)}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('confNumber-contains'))}\s*[A-Z\d]{5,}(?:\b|\,)/");

                if ($confirmation) {
                    break;
                }
            }
        }

        if ($confirmation && preg_match("/({$this->opt($this->t('confNumber-starts'))}|{$this->opt($this->t('confNumber-contains'))})\s*([A-Z\d]{5,})\b/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ' :：'));
        } elseif (empty($confirmation) && $this->http->XPath->query("//text()[contains(normalize-space(), 'booking number')]/ancestor::td[1]/descendant::text()[contains(normalize-space(), 'has been canceled')]")->length > 0) {
            $f->general()
                ->noConfirmation();
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//*[not(.//tr) and not(.//p) and {$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $f->general()->traveller($traveller);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('UPDATED FLIGHT DETAILS'))}]")->length > 0) {
            $f->general()
                ->status('Updated');
        }

        // Departs CJS 11:35
        $pattern = "/\s(?<code>[A-Z]{3})\s+(?<time>\d{1,2}[:：]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)/";

        $segments = $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$this->starts($this->t('Departs'))}] and *[2][descendant::img and normalize-space()=''] and *[3][{$this->starts($this->t('Arrives'))}] ][not(preceding::tr[{$this->eq($this->t('PREVIOUS FLIGHT DETAILS'))}])]");

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//text()[{$this->eq($this->t('PREVIOUS FLIGHT DETAILS'))}]/following::text()[{$this->eq($this->t('UPDATED FLIGHT DETAILS'))}]/following::tr[ count(*)=3 and *[1][{$this->starts($this->t('Departs'))}] and *[2][descendant::img and normalize-space()=''] and *[3][{$this->starts($this->t('Arrives'))}]]");
        }

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $xpath = "ancestor::*[ preceding-sibling::*[normalize-space()] ][1]";

            $dateNormal = $this->normalizeDate($this->http->FindSingleNode($xpath . "/preceding-sibling::*[normalize-space()][4]", $segment, true, "/^.*\d.*$/"));
            $date = strtotime($dateNormal);

            $flight = $this->http->FindSingleNode($xpath . "/preceding-sibling::*[normalize-space()][position()<4][{$this->starts($this->t('Flight'))}]", $segment);

            if (preg_match("/^{$this->opt($this->t('Flight'))}[#\s]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                // Flight#   VB 2217
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $operator = $this->http->FindSingleNode($xpath . "/preceding-sibling::*[normalize-space()][position()<4][{$this->starts($this->t('Operated by'))}]", $segment, true, "/^{$this->opt($this->t('Operated by'))}\s+(.+)$/");
            $s->airline()->operator($operator);

            $departsText = implode(' ', $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($pattern, $departsText, $m)) {
                $s->departure()->code($m['code']);

                if ($date) {
                    $s->departure()->date(strtotime($m['time'], $date));
                }
            }

            $arrivesText = implode(' ', $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($pattern, $arrivesText, $m)) {
                $s->arrival()->code($m['code']);

                if ($date) {
                    $s->arrival()->date(strtotime($m['time'], $date));
                }
            }
        }
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
            if (!is_string($lang) || empty($phrases['Departs'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Departs'])}]")->length > 0
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
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})$/u', $text, $m)) {
            // 20 February 2021
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
