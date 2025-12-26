<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-205064952-es.eml, vivaaerobus/it-477046010-es.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            // 'Hola' => '',
            'confNumber' => ['Itinerario', 'Itinerario de vuelo'],
            'route'      => ['Vuelo de ida', 'Vuelo de regreso', 'Vuelo de salida'],
            // 'Total por' => '',
        ],
        'en' => [
            'Hola'       => 'Hi',
            'confNumber' => ['Flight itinerary'],
            'route'      => ['Departure Flight', 'Return Flight'],
            'Total por'  => 'Total amount for',
        ],
    ];

    private $subjects = [
        // en, es
        'es' => ['Te han compartido un itinerario', 'Tu pase de abordar a'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vivaaerobus.com') !== false;
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//*[' . $this->contains(['Enviado por VivaAerobus', 'Sent by VivaAerobus']) . ']')->length === 0
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
        $email->setType('Itinerary' . ucfirst($this->lang));

        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hola'))}]", null, "/^{$this->opt($this->t('Hola'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if ($traveller !== null) {
            $f->general()->traveller($traveller);
        }

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $f->general()->confirmation($confirmation);
        } else if ($confirmation == null){
            $f->general()->noConfirmation();
        }

        $segments = $this->http->XPath->query("//tr[ *[1]/descendant::text()[{$xpathTime}] and *[2]/descendant::img and *[3]/descendant::text()[{$xpathTime}] ]");
        $this->logger->debug("//tr[ *[1]/descendant::text()[{$xpathTime}] and *[2]/descendant::img and *[3]/descendant::text()[{$xpathTime}] ]");
        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateDep = 0;

            /*
                Vie. 16 Dic. 2022
                Ciudad de México
                MEX
                1:15 PM

                [OR]

                VB1373
                Ciudad de México
                MEX
                1:15 PM

                [OR]

                Ciudad de México
                MEX
                1:15 PM
            */
            $pattern = "/^(?:(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)\n+|(?<date>.*\d.*)\n+)?(?<name>.{2,})\n+(?<code>[A-Z]{3})\n+(?<time>{$patterns['time']})/";

            $xpathPreRow = "preceding::text()[normalize-space()][1]/ancestor::tr[count(*[normalize-space()])=2][1]";
            $departureText = implode("\n", $this->http->FindNodes("*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $departureText, $m)) {
                $s->departure()->name($m['name'])->code($m['code']);

                if (empty($m['date'])) {
                    $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode($xpathPreRow . "/*[normalize-space()][1]", $root)));
                } else {
                    $dateDep = strtotime($this->normalizeDate($m['date']));
                }

                if (empty($dateDep)){
                    $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[normalize-space()][count(./descendant::text()[normalize-space()]) = 2][1]/descendant::text()[normalize-space()][1]", $root)));
                }

                if ($dateDep) {
                    $s->departure()->date(strtotime($m['time'], $dateDep));
                }
            }

            $duration = $this->http->FindSingleNode('*[2]', $root, true, '/^\d[\d hrsmin]*$/i');
            $s->extra()->duration($duration);

            $arrivalText = implode("\n", $this->http->FindNodes("*[3]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()]", $root));

            if (preg_match($pattern, $arrivalText, $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);

                if ($dateDep) {
                    $s->arrival()->date(strtotime($m['time'], $dateDep));
                }

                if (!empty($m['airline'])) {
                    $s->airline()->name($m['airline'])->number($m['number']);
                } elseif (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)$/', $this->http->FindSingleNode($xpathPreRow . "/*[normalize-space()][2]", $root), $m2)) {
                    $s->airline()->name($m2['airline'])->number($m2['number']);
                } elseif (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)$/', $this->http->FindSingleNode("./preceding::table[normalize-space()][count(./descendant::text()[normalize-space()]) = 2][1]/descendant::text()[normalize-space()][2]", $root), $m2)) {
                    $s->airline()->name($m2['airline'])->number($m2['number']);
                } else {
                    $s->airline()->noName()->noNumber();
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Total por'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        $totalPrice2 = '';

        if (preg_match("/^\s*([^+]+)\s*\+(.+)$/", $totalPrice, $m)) {
            $totalPrice = $m[1];
            $totalPrice2 = $m[2];
        }

        if (
            preg_match('/^\s*[^\-\d)(]{0,1}\s*(?<amount>\d[,.\'\d ]*)\s*(?<currency>[A-Z]{3})\s*$/', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
        ) {
            // $9,984
            // $3,277 MXN
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()
                ->currency($matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            if (!empty($totalPrice2)) {
                $total2 = explode('+', $totalPrice2);

                foreach ($total2 as $t2) {
                    $f->price()
                        ->total($f->getPrice()->getTotal()
                            + PriceHelper::parse(preg_replace("/^\D*(\d[\d., ]*?)\D*$/", '$1', $t2), $currencyCode));
                }
            }
        } /*else {
            $f->price()
                ->total(null);
        }*/

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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})\s+([[:alpha:]]+)[.\s]+(\d{4})$/u', $text, $m)) {
            // Dom. 25 Dic. 2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $m)) {
            // 02/22/2024
            if (strtotime($text) !== null){
                $day = $m[2];
                $month = $m[1];
                $year = $m[3];
            }
            // 22/02/2024
            else {
                $day = $m[1];
                $month = $m[2];
                $year = $m[3];
            }
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        $assignLanguages = array_keys(self::$dictionary);

        foreach ($assignLanguages as $i => $lang) {
            if (!is_string($lang) || empty(self::$dictionary[$lang]['confNumber'])
                || $this->http->XPath->query("//*[{$this->contains(self::$dictionary[$lang]['confNumber'])}]")->length === 0
            ) {
                unset($assignLanguages[$i]);
            }
        }

        if (count($assignLanguages) > 1) {
            foreach ($assignLanguages as $i => $lang) {
                if (!is_string($lang) || empty(self::$dictionary[$lang]['route'])
                    || $this->http->XPath->query("//tr/*[{$this->eq(self::$dictionary[$lang]['route'])}]")->length === 0
                ) {
                    unset($assignLanguages[$i]);
                }
            }
        }

        if (count($assignLanguages) === 1) {
            $this->lang = array_shift($assignLanguages);

            return true;
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
}
