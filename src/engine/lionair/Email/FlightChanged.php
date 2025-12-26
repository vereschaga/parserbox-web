<?php

namespace AwardWallet\Engine\lionair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "lionair/it-778204522.eml";

    private $subjects = [
        'en' => ['Schedule Message - Flight Replacement/Time Change']
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'timeDep' => ['New departure time from'],
            'timeArr' => ['New arrival time in'],
            'statusPhrases' => ['has been'],
            'statusVariants' => ['reschedule'],
        ]
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]lionairthai\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], '[Thai Lion Air]') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array)$phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                    return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider( rtrim($parser->getHeader('from'), '> ') ) !== true
            && $this->http->XPath->query('//a[contains(@href,".lionairthai.com/") or contains(@href,"www.lionairthai.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thai Lion Air would like to inform") or contains(normalize-space(),"contact us page at www.lionairthai.com")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if ( empty($this->lang) ) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('FlightChanged' . ucfirst($this->lang));

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();
        $s = $f->addSegment();

        $introText = $this->http->FindSingleNode("//text()[contains(normalize-space(),'scheduled to depart from')]/ancestor::p[1]");
        $this->logger->debug($introText);

        if (preg_match("/scheduled to depart from\s+(?<nameDep>.{2,}?)\s*\(\s*(?<codeDep>[A-Z]{3})\s*\)\s*to\s+(?<nameArr>.{2,}?)\s*\(\s*(?<codeArr>[A-Z]{3})\s*\)\s*on\s/", $introText ?? '', $m)) {
            $s->departure()->name($m['nameDep'])->code($m['codeDep']);
            $s->arrival()->name($m['nameArr'])->code($m['codeArr']);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s+{$this->opt($this->t('to'))}\s|\s*[,.;:!?]|$)/i"));
        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('PNR'))}]");
        if (preg_match("/^({$this->opt($this->t('PNR'))})[:\s]+([A-Z\d]{5,10})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $travellers = [];
        $passengersVal = $this->http->FindSingleNode("//*/*[normalize-space()][1][{$this->eq($this->t('Passenger Detail'), "translate(.,':','')")}]/following-sibling::*[string-length(normalize-space())>3][1]");
        $passengersList = preg_split('/(?:\s*,\s*)+/', $passengersVal ?? '');

        foreach ($passengersList as $pItem) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $pItem)) {
                $travellers[] = $pItem;
            } else {
                $this->logger->debug('Wrong Passenger Detail!');
                $travellers = [];

                break;
            }
        }

        $f->general()->travellers($travellers, true);

        $flight = $this->http->FindSingleNode("//*/*[normalize-space()][1][{$this->eq($this->t('New flight'), "translate(.,':','')")}]/following-sibling::*[string-length(normalize-space())>3][1]");

        if ( preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m) ) {
            $s->airline()->name($m['name'])->number($m['number']);
        }

        $dateDep = strtotime($this->http->FindSingleNode("//*/*[normalize-space()][1][{$this->eq($this->t('New departure date'), "translate(.,':','')")}]/following-sibling::*[string-length(normalize-space())>3][1]", null, true, "/^.{4,}?\b\d{4}\b/"));
        $timeDep = $this->http->FindSingleNode("//*/*[normalize-space()][1][{$this->starts($this->t('timeDep'))} and not(.//tr[normalize-space()])]/following-sibling::*[string-length(normalize-space())>3][1]", null, true, "/^{$patterns['time']}/");
        $timeArr = $this->http->FindSingleNode("//*/*[normalize-space()][1][{$this->starts($this->t('timeArr'))} and not(.//tr[normalize-space()])]/following-sibling::*[string-length(normalize-space())>3][1]", null, true, "/^{$patterns['time']}/");

        if ($dateDep && $timeDep) {
            $s->departure()->date(strtotime($timeDep, $dateDep));
        }

        if ($dateDep && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $dateDep));
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
            if ( !is_string($lang) || empty($phrases['timeDep']) || empty($phrases['timeArr']) ) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($phrases['timeDep'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['timeArr'])}]")->length > 0
            ) {
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

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
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
