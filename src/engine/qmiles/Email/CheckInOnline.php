<?php

namespace AwardWallet\Engine\qmiles\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CheckInOnline extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-444491846.eml, qmiles/it-442640199.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Booking reference', 'Booking Reference'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]qatarairways\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'check in online before your flight to') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".qatarairways.com/") or contains(@href,"qr.qatarairways.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Get the Qatar Airways App")] | //text()[contains(normalize-space(),"All rights reserved ©") and contains(normalize-space(),"Qatar Airways")]')->length === 0
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
        $email->setType('CheckInOnline' . ucfirst($this->lang));

        $xpathTime = '(starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t(', you are only one day away from your upcoming trip to'))}]/preceding::text()[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $f->general()->traveller($traveller);
        }

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->http->XPath->query("//tr[count(*[normalize-space()])=2 and count(*[{$xpathTime}])=2]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateDep = strtotime($this->http->FindSingleNode("preceding::tr[*[2] and normalize-space()][1]/*[1]", $root, true, "/^.*\d.*$/"));
            $dateArr = strtotime($this->http->FindSingleNode("preceding::tr[*[2] and normalize-space()][1]/*[2]", $root, true, "/^.*\d.*$/"));

            if (empty($dateArr) && $this->http->XPath->query("preceding::tr[*[2] and normalize-space()][1]/*[2][normalize-space()='']", $root)->length === 1 && !empty($dateDep)) {
                // it-444491846.eml
                $dateArr = $dateDep;
            }

            $timeDep = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/^{$patterns['time']}/");

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            $s->departure()->code($this->http->FindSingleNode("following::tr[*[3] and normalize-space()][1]/*[1]", $root, true, "/^[A-Z]{3}$/"));
            $s->arrival()->code($this->http->FindSingleNode("following::tr[*[3] and normalize-space()][1]/*[3]", $root, true, "/^[A-Z]{3}$/"));

            $extra = $this->http->FindSingleNode("following::tr[*[3] and normalize-space()][1]/*[2]", $root);

            if (preg_match("/^(\d[hm \d]+?)(?:[ ]*,|$)/i", $extra, $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match("/\b(\d{1,3})\s+{$this->opt($this->t('stop'))}/i", $extra, $m)) {
                $s->extra()->stops($m[1]);
            }

            $operator = $this->http->FindSingleNode("following::tr[*[3] and normalize-space()][1]/following::tr[not(.//tr) and normalize-space()][position()<3][ count(*)=2 and *[1][{$this->eq($this->t('Operated by'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", $root);
            $s->airline()->operator($operator, false, true);

            if (!empty($s->getArrDate())) {
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
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr/*[{$this->eq($phrases['confNumber'])}]")->length > 0) {
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
