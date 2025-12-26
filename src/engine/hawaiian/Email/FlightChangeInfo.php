<?php

namespace AwardWallet\Engine\hawaiian\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChangeInfo extends \TAccountChecker
{
    public $mailFiles = "hawaiian/it-11540853.eml, hawaiian/it-11602443.eml, hawaiian/it-64974794.eml";

    public $reSubject = [
        'en' => 'Important Flight Change Information', 'Important flight change notification',
        'ja' => 'フライト変更に関する重要なお知らせ',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'ARRIVES' => ['ARRIVES'],
            'ROUTE'   => ['ROUTE'],
        ],
        'ja' => [
            'Confirmation Code' => '該当するご予約番号をご入力ください',
            'ARRIVES'           => 'ご到着時刻',
            'ROUTE'             => 'ご旅程',
            'PASSENGER'         => 'ご搭乗者名',
            'FLIGHT'            => '便名',
            // 'Operated by'       => '',
            'DEPARTS'           => 'ご出発時刻',
            'SEAT'              => 'お座席',
            'to'                => '発',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".hawaiianairlines.com/") or contains(@href,"flightnotifications.hawaiianairlines.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"for choosing Hawaiian Airlines") or contains(.,"@FlightNotifications.HawaiianAirlines.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from']) === true && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flightnotifications.hawaiianairlines.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Code'))}]", null, true, "#{$this->opt($this->t('Confirmation Code'))}[\s:]+([A-Z\d]{5,})#");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Code'))}]/following::text()[normalize-space()][1]", null, true, "#^([A-Z\d]{5,})\.?$#");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//img[contains(@alt, 'Confirmation Code')]/@alt", null, true, "#^\s*Confirmation Code\:\s*([A-Z\d]{5,})\.?$#");
        }

        if (empty($confirmation) && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight schedule has changed'))}]")->length > 0) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()->confirmation($confirmation);
        }

        $pax = [];
        $xpath = "//text()[{$this->eq($this->t('DEPARTS'))}]/ancestor::tr[{$this->contains($this->t('PASSENGER'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $operator = $this->http->FindSingleNode("preceding::text()[string-length(normalize-space())>3][1]", $root);
            $flight = $this->http->FindSingleNode("preceding::text()[string-length(normalize-space())>3][position()<3][not({$this->contains($this->t('Operated by'))})]", $root)
                ?? $operator;

            if (preg_match("#{$this->opt($this->t('FLIGHT'))}\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)#", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            } elseif (preg_match("#{$this->opt($this->t('FLIGHT'))}\s+\#\s*(\d+)#", $flight, $m)) {
                $s->airline()
                    ->name('HA')
                    ->number($m[1]);
            } elseif (preg_match("#\b([A-Z]{2})\s*(\d+)\s*:#", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("/{$this->opt($this->t('Operated by'))}\s+(.{2,})/", $operator, $m)) {
                $s->airline()->operator($m[1]);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DATE'))}]/ancestor::td[1]", $root, true, "#{$this->opt($this->t('DATE'))}\s+(.+)#"));

            if ($date === false) {
                $s->departure()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTS'))}]/ancestor::td[1]", $root, true, "#{$this->opt($this->t('DEPARTS'))}\s*(.+)#")));
                $s->arrival()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVES'))}]/ancestor::td[1]", $root, true, "#{$this->opt($this->t('ARRIVES'))}\s*(.+)#")));
            } else {
                $s->departure()
                    ->date(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTS'))}]/ancestor::td[1]", $root, true, "#{$this->opt($this->t('DEPARTS'))}\s*(.+)#"), $date));
                $s->arrival()
                    ->date(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVES'))}]/ancestor::td[1]", $root, true, "#{$this->opt($this->t('ARRIVES'))}\s*(.+)#"), $date));
            }

            $paxRoots = $this->http->XPath->query("./descendant::text()[{$this->eq($this->t('PASSENGER'))}]/ancestor::td[1]/descendant::text()[not({$this->contains($this->t('PASSENGER'))}) and normalize-space()!='']/ancestor::div[1][normalize-space()!='']", $root);

            foreach ($paxRoots as $paxRoot) {
                $pax[] = implode(' ', $this->http->FindNodes(".//text()[normalize-space(.)!='']", $paxRoot));
            }

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ROUTE'))}]/ancestor::td[1]", $root, true, "#{$this->opt($this->t('ROUTE'))}\s*(.+)#");

            if (preg_match("#([A-Z]{3})\s*{$this->opt($this->t('to'))}\s*([A-Z]{3})#", $node, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[2]);
            }

            $seatsValue = implode("\n", $this->http->FindNodes("descendant::text()[{$this->eq($this->t('SEAT'))}]/ancestor::td[1]/descendant::text()[not({$this->contains($this->t('SEAT'))}) and normalize-space()]", $root));

            if ($seatsValue) {
                // 2F    |    2E 2E
                $seats = array_values(array_unique(preg_split('/\s+/', $seatsValue)));
                $s->extra()->seats($seats);
            }
        }

        if (count($pax) > 0) {
            $f->general()->travellers(array_values(array_unique(array_filter($pax))));
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //8:31 AM 29 April 2018
            '#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*(\d+)\s+(\w+)\s+(\d{4})\s*$#iu',
            //20 June 2017
            '#^\s*(\d+)\s+(\w+)\s+(\d{4})\s*$#i',
            //8:45 PM 2020 年 10 月 29 日
            '#^([\d\:]+\s*A?P?M)\s*(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日$#',
        ];
        $out = [
            '$2 $3 $4 $1',
            '$1 $2 $3',
            '$4.$3.$2, $1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['ARRIVES']) || empty($phrases['ROUTE'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['ARRIVES'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['ROUTE'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
