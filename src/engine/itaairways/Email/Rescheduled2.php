<?php

namespace AwardWallet\Engine\itaairways\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Rescheduled2 extends \TAccountChecker
{
    public $mailFiles = "itaairways/it-688682178.eml, itaairways/it-693506387-pt.eml, itaairways/it-692437552-it.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'langDetect' => [
                'Todos os horários são indicados na hora local',
                'A seguir você pode encontrar o horário atualizado',
            ],
            'confNumber' => ['PNR:'],
        ],
        'it' => [
            'langDetect' => [
                "Tutti gli orari sono indicati nell'ora locale", 'Tutti gli orari sono indicati nell’ora locale',
                "Qui di seguito trovi l'orario aggiornato", 'Qui di seguito trovi l’orario aggiornato',
            ],
            'confNumber' => ['Codice di prenotazione:'],
        ],
        'en' => [
            'langDetect' => [
                'All times are shown in local time',
                'The updated arrival time is given below',
                'Please note that, for operational reasons, we had to change your flight',
            ],
            'confNumber' => ['Reservation code:'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]ita-airways\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers) && strpos($headers['subject'], 'ITA Airways info PNR ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".ita-airways.com/") or contains(@href,"www.ita-airways.com") or contains(@href,"enews.ita-airways.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"- www.ita-airways.com") or contains(normalize-space(),"- www.ita-airways.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('Rescheduled2' . ucfirst($this->lang));

        $patterns = [
            'date' => '\b\d{1,2}\/\d{1,2}\/\d{4}\b', // 26/07/2024
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^(.{2,}?)[:\s]*([A-Z\d]{5,10})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $nameDep = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]/*[1]", $root);
            $nameArr = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]/*[3]", $root);

            if (preg_match($pattern = "/^(?<name>.{2,}?)[(\s]+(?<code>[A-Z]{3})[\s)]*$/", $nameDep, $m)) {
                $s->departure()->name($m['name'])->code($m['code']);
            } elseif (preg_match("/^[(\s]*(?<code>[A-Z]{3})[\s)]*$/", $nameDep, $m)) {
                $s->departure()->code($m['code']);
            } else {
                $s->departure()->name($nameDep);
            }

            if (preg_match($pattern, $nameArr, $m)) {
                $s->arrival()->name($m['name'])->code($m['code']);
            } elseif (preg_match("/^[(\s]*(?<code>[A-Z]{3})[\s)]*$/", $nameArr, $m)) {
                $s->arrival()->code($m['code']);
            } else {
                $s->arrival()->name($nameArr);
            }

            $flight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]/*[2]", $root);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[1]", $root, true, "/^{$patterns['date']}/")));
            $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[3]", $root, true, "/^{$patterns['date']}/")));

            $timeDep = $this->http->FindSingleNode("*[1]", $root, true, "/^{$patterns['time']}/u");
            $timeArr = $this->http->FindSingleNode("*[3]", $root, true, "/^{$patterns['time']}/u");

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
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

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        return $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$xpathTime}] and *[3][{$xpathTime}] ]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['langDetect'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['langDetect'])}]")->length > 0) {
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 26/07/2024
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
