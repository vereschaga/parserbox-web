<?php

namespace AwardWallet\Engine\vueling\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingChanged extends \TAccountChecker
{
    public $mailFiles = 'vueling/it-12233690.eml, vueling/it-5748894.eml, vueling/it-632199345.eml';

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'      => ['Booking code:', 'Booking Reference:', 'Booking number:'],
            'statusPhrases'   => ['your flight has'],
            'statusVariants'  => ['changed'],
            'newFlights'      => ['New schedule:', 'New flight:', 'New flights:', 'Your new flight itinerary is as follows:'],
            'originalFlights' => ['Original schedule', 'Original flight'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return strpos($headers['subject'], 'Notification of changes to your booking') !== false
            || preg_match('/your booking [A-Z\d]{5,} has changed/i', $headers['subject']) > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]vueling\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".vueling.com/") or contains(@href,"www.vueling.com") or contains(@href,"comms.vueling.com") or contains(@href,"tickets.vueling.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Best wishes and happy Vueling") or contains(normalize-space(),"Kind regards,Vueling Team")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('BookingChanged' . ucfirst($this->lang));

        $patterns = [
            'date'          => '\b\d{1,2}[-\/]\d{1,2}[-\/]\d{4}\b', // 26/02/2015    |    08-03-2022
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $f->general()->traveller($traveller);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}(?:\s+{$this->opt($this->t('been'))})?\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $xpathFilter = "not(following::*[{$this->starts($this->t('originalFlights'))}])";
        $segments = $this->http->XPath->query("//*/tr[normalize-space()][1][{$this->eq($this->t('newFlights'))}][{$xpathFilter}]/following-sibling::tr[contains(.,'/') and contains(.,'-') and contains(.,':')]");

        foreach ($segments as $row) {
            $s = $f->addSegment();

            $text = $this->http->FindSingleNode('.', $row);
            $this->logger->debug('Segment text: ' . $text);

            if (preg_match("/\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fNumber>\d+)\s+(?<date>{$patterns['date']})\s+(?<nameDep>.{2,}?)(?:\s+\(\s*(?<codeDep>[A-Z]{3})\s*\))?\s*-\s*(?<nameArr>.{2,}?)(?:\s+\(\s*(?<codeArr>[A-Z]{3})\s*\))?\s+(?<time1>{$patterns['time']})\s*-\s*(?<time2>{$patterns['time']})/", $text, $m)) {
                // it-12233690.eml, it-5748894.eml
                // VY8205 26/02/2015 Paris (Charles de Gaulle)(CDG)-Madrid (MAD) 20:30 - 22:35
                $this->logger->debug('Detect segment ver.1');
                $s->airline()->name($m['airline'])->number($m['fNumber']);

                $date = strtotime(str_replace('/', '.', $m['date']));

                if ($date) {
                    $s->departure()->date(strtotime($m['time1'], $date));
                    $s->arrival()->date(strtotime($m['time2'], $date));
                }

                $s->departure()->name($m['nameDep']);
                $s->arrival()->name($m['nameArr']);

                if (empty($m['codeDep'])) {
                    $s->departure()->noCode();
                } else {
                    $s->departure()->code($m['codeDep']);
                }

                if (empty($m['codeArr'])) {
                    $s->arrival()->noCode();
                } else {
                    $s->arrival()->code($m['codeArr']);
                }
            } elseif (preg_match("/\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fNumber>\d+)\s+(?<date>{$patterns['date']})\s+(?<codeDep>[A-Z]{3})\s*[-]+\s*(?<codeArr>[A-Z]{3})\s*[-]+\s*(?<time1>{$patterns['time']})\s*\/\s*(?<time2>{$patterns['time']})/", $text, $m)) {
                // it-632199345.eml
                // VY6304 13-04-2022 LGW-BIO - 08:30 / 11:30
                $this->logger->debug('Detect segment ver.2');
                $s->airline()->name($m['airline'])->number($m['fNumber']);

                $date = strtotime($m['date']);

                if ($date) {
                    $s->departure()->date(strtotime($m['time1'], $date));
                    $s->arrival()->date(strtotime($m['time2'], $date));
                }

                $s->departure()->code($m['codeDep']);
                $s->arrival()->code($m['codeArr']);
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

    public function IsEmailAggregator()
    {
        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['newFlights'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['newFlights'])}]")->length > 0
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
