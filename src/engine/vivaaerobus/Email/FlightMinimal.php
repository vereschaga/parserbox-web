<?php

namespace AwardWallet\Engine\vivaaerobus\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightMinimal extends \TAccountChecker
{
    public $mailFiles = "vivaaerobus/it-645670349.eml, vivaaerobus/it-644216004.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'departure'  => ['Origen'],
            'arrival'    => ['Destino'],
            'confNumber' => ['Código de reservación:', 'Código de reservación :'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]vivaaerobus\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".vivaaerobus.com/") or contains(@href,"info.vivaaerobus.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Enviado por Viva Aerobus")]')->length === 0
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
        $email->setType('FlightMinimal' . ucfirst($this->lang));

        $patterns = [
            'confNumber' => '[A-Z\d]{5,7}', // M5GPQK
            'date'       => '\b\d{4}-\d{1,2}-\d{1,2}\b', // 2022-04-21
            'time'       => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['confNumber']}$/");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('departure'))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('arrival'))}] ]");

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            if (!$confirmation && $i === 0) {
                // it-644216004.eml
                $confirmation = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root, true, "/^{$patterns['confNumber']}$/");
                $f->general()->confirmation($confirmation);
            }

            $departureText = implode("\n", $this->http->FindNodes("*[normalize-space()][1]/descendant::text()[normalize-space() and not({$this->eq($this->t('departure'))})]", $root));
            $arrivalText = implode("\n", $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space() and not({$this->eq($this->t('arrival'))})]", $root));

            /*
                ORD
                Chicago
            */
            $patternCodeName = "/^(?<code>[A-Z]{3})\n+(?<name>.{2,})$/s";

            /*
                ORD
                Chicago
                2022-04-21 00:05:00 hrs
            */

            if (preg_match($pattern = "/^(?<name>.{2,}(?:\n.+){0,1})\n+(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})/", $departureText, $m)) {
                if (preg_match($patternCodeName, $m['name'], $m2)) {
                    $s->departure()->code($m2['code']);
                    $m['name'] = $m2['name'];
                } else {
                    $s->departure()->noCode();
                }
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['name']))->date(strtotime($m['time'], strtotime($m['date'])));
            }

            if (preg_match($pattern, $arrivalText, $m)) {
                if (preg_match($patternCodeName, $m['name'], $m2)) {
                    $s->arrival()->code($m2['code']);
                    $m['name'] = $m2['name'];
                } else {
                    $s->arrival()->noCode();
                }
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['name']))->date(strtotime($m['time'], strtotime($m['date'])));
            }

            if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
                $s->airline()->noName()->noNumber();
            }
        }

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['departure']) || empty($phrases['arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->eq($phrases['departure'])}]/following::node()[{$this->eq($phrases['arrival'])}]")->length > 0) {
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
}
