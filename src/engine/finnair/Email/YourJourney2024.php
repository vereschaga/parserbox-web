<?php

namespace AwardWallet\Engine\finnair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourJourney2024 extends \TAccountChecker
{
    public $mailFiles = "finnair/it-814188266.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Your booking reference'],
            'cabinValues' => ['Economy', 'Business'],
        ]
    ];

    private $xpath = [
        'time' => '(starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))',
        'noDisplay' => 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none")]',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]finnair\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return array_key_exists('subject', $headers)
            && preg_match('/ your journey to .{2,} is in \d/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".finnair.com/") or contains(@href,"www.finnair.com") or contains(@href,"email.finnair.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"© Finnair. All rights reserved")]')->length === 0
        ) {
            return false;
        }
        return $this->findSegments()->length > 0;
    }

    private function findSegments(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*[{$this->xpath['time']}])=2 and count(*[{$this->xpath['time']}])<4 ]");
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('YourJourney2024' . ucfirst($this->lang));

        $patterns = [
            'date' => '\b\d{1,2}[,. ]+[[:alpha:]]+[,. ]+\d{4}\b', // 22 December 2024
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        if (preg_match("/(?:^|:\s*)({$patterns['travellerName']})\s*,\s*your journey to/iu", $parser->getSubject(), $m)) {
            $f->general()->traveller($m[1]);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([A-Z\d]{5,10})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], preg_replace("/^{$this->opt($this->t('Your'))}\s+(\S.*)$/i", '$1', $m[1]));
        }

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            /*
                Departure 22 December 2024
                Business
            */
            $segHeader = implode("\n", $this->http->FindNodes("preceding::tr[not(.//tr) and normalize-space()][1]/descendant::text()[normalize-space() and not({$this->xpath['noDisplay']})]", $root));

            $this->logger->info('Segment header:');
            $this->logger->debug($segHeader);

            if (preg_match("/^[^\d\n]*({$patterns['date']})(?:\n|$)/u", $segHeader, $m)) {
                $date = strtotime($m[1]);
            } else {
                $date = null;
            }

            if (preg_match("/^(?<cabin>{$this->opt($this->t('cabinValues'))})[\s(]+(?<bookingCode>[A-Z]{1,2})[\s)]*$/im", $segHeader, $m)) {
                // Economy (I)
                $s->extra()->cabin($m['cabin'])->bookingCode($m['bookingCode']);
            } elseif (preg_match("/^(?<cabin>{$this->opt($this->t('cabinValues'))})(?:\s*\(|$)/im", $segHeader, $m)) {
                // Business
                $s->extra()->cabin($m['cabin']);
            }

            $timeDep = $timeArr = null;

            $departureText = implode("\n", $this->http->FindNodes("*[{$this->xpath['time']}][1]/descendant::text()[normalize-space() and not({$this->xpath['noDisplay']})]", $root));
            $arrivalText = implode("\n", $this->http->FindNodes("*[{$this->xpath['time']}][2]/descendant::text()[normalize-space() and not({$this->xpath['noDisplay']})]", $root));

            $this->logger->info('Departure:');
            $this->logger->debug($departureText);

            $this->logger->info('Arrival:');
            $this->logger->debug($arrivalText);

            /*
                22:30
                +1
                Helsinki
                Helsinki Vantaa HEL
            */
            $pattern1 = "/^(?<time>{$patterns['time']}).*(?:\n.+){0,2}\n(?<airport>.{3,})$/";

            // Helsinki Vantaa HEL
            $pattern2 = "/^(?<name>.{2,}?)[,(\s]+(?<code>[A-Z]{3})[\s)]*$/";

            if (preg_match($pattern1, $departureText, $m)) {
                $timeDep = $m['time'];

                if (preg_match($pattern2, $m['airport'], $m2)) {
                    $s->departure()->name($m2['name'])->code($m2['code']);
                } else {
                    $s->departure()->name($m['airport'])->noCode();
                }
            }

            if (preg_match($pattern1, $arrivalText, $m)) {
                $timeArr = $m['time'];

                if (preg_match($pattern2, $m['airport'], $m2)) {
                    $s->arrival()->name($m2['name'])->code($m2['code']);
                } else {
                    $s->arrival()->name($m['airport'])->noCode();
                }
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            /*
                AY19 Total duration: 12h 30 min
                    [OR]
                AY552 Operated by NORDIC REG FOR FINNAIR. Total duration: 1h 15 min
            */
            $flightInfo = $this->http->FindSingleNode("following::tr[not(.//tr) and normalize-space()][1]", $root);

            $this->logger->info('Flight info:');
            $this->logger->debug($flightInfo);

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s|$)/", $flightInfo, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/{$this->opt($this->t('Operated by'))}[:\s]+(.{2,}?)(?:[.\s]+{$this->opt($this->t('Total duration'))}|$)/i", $flightInfo, $m)) {
                $s->airline()->operator($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Total duration'))}[:\s]+((?:\d{1,3}[h min]+)+)/i", $flightInfo, $m)) {
                $s->extra()->duration($m[1]);
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
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['confNumber']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
