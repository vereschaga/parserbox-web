<?php

namespace AwardWallet\Engine\velocity\Email;

use AwardWallet\Schema\Parser\Email\Email;

class LineAndDots extends \TAccountChecker
{
    public $mailFiles = "velocity/it-619772827.eml, velocity/it-622506765.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Booking ref:', 'Booking ref :'],
            'changedFlights' => ['Delayed flight', 'Cancelled flight', 'Canceled flight', 'Previously Scheduled Flight'],
            'statusPhrases'  => ['your flight has'],
            'statusVariants' => ['changed'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking confirmation for', 'Your flight has changed - Booking reference'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]virginaustralia\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".virginaustralia.com/") or contains(@href,"www.virginaustralia.com") or contains(@href,"check-in.virginaustralia.com") or contains(@href,"flightstatus.virginaustralia.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"© Virgin Australia Airlines")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('LineAndDots' . ucfirst($this->lang));

        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'date'          => '\b\d{1,2}[-.\s]+[[:alpha:]]+[-.\s]+\d{2,4}\b', // 23 Dec 2023
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}(?:\s+been)?[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], preg_replace('/^(.+?)[\s:：]*$/u', '$1', $m[1]));
        }

        $guestsVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests:'))}]/following::text()[normalize-space()][1]");
        $travellers = preg_split('/(\s*,\s*)+/', $guestsVal);

        foreach ($travellers as $t) {
            if (!preg_match("/^{$patterns['travellerName']}$/u", $t)) {
                $travellers = [];

                break;
            }
        }

        $f->general()->travellers($travellers, true);

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $codeDep = $this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space() and *[2]][2]/*[normalize-space()][1]", $root);
            $codeArr = $this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space() and *[2]][2]/*[normalize-space()][2]", $root);

            /*
            $cityDep = $this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space() and *[2]][1]/*[normalize-space()][1]", $root);
            $cityArr = $this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space() and *[2]][1]/*[normalize-space()][2]", $root);
            */

            $s->departure()->code($codeDep);
            $s->arrival()->code($codeArr);

            $timeDep = $this->http->FindSingleNode("*[1]", $root, true, "/^{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode("*[3]", $root, true, "/^{$patterns['time']}/");

            $date = strtotime($this->http->FindSingleNode("following::*[not(.//tr) and normalize-space()][1]", $root, true, "/^{$patterns['date']}$/u"));

            if ($date) {
                if ($timeDep) {
                    $s->departure()->date(strtotime($timeDep, $date));
                }

                if ($timeArr) {
                    $s->arrival()->date(strtotime($timeArr, $date));
                }
            }

            $flight = $this->http->FindSingleNode("following::text()[normalize-space()][position()<4][{$this->starts($this->t('Virgin Australia'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $cabinVal = $this->http->FindSingleNode("following::text()[normalize-space()][position()<8][{$this->eq($this->t('Cabin:'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", $root);

            if (preg_match('/^[(\s]*([A-Z]{1,2})[\s)]*$/', $cabinVal, $m)) {
                // (Y)
                $s->extra()->bookingCode($m[1]);
            } elseif (preg_match('/^(.{2,}?)[(\s]+([A-Z]{1,2})[\s)]*$/', $cabinVal, $m)) {
                // Economy Class (Y)
                $s->extra()->cabin($m[1])->bookingCode($m[2]);
            } else {
                // Economy Class
                $s->extra()->cabin($cabinVal, false, true);
            }

            $duration = $this->http->FindSingleNode("following::text()[normalize-space()][position()<8][{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space()][1]", $root, true, '/^\d.*/');
            $s->extra()->duration($duration, false, true);
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
        $xpathTime = '(starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';
        $xpathFilter = "count(preceding::h2[normalize-space()])=0 and count(following::h2[normalize-space()])=0 or following::*[{$this->eq($this->t('changedFlights'))}] or not(following::*[{$this->eq($this->t('changedFlights'))}]) and preceding::*[{$this->eq($this->t('Your new flight'))}]";

        return $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$xpathTime}] and *[3][{$xpathTime}] ][{$xpathFilter}]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
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
