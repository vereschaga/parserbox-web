<?php

namespace AwardWallet\Engine\tiger\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationOfBooking2019 extends \TAccountChecker
{
    public $mailFiles = "tiger/it-37289038.eml, tiger/it-37387536.eml, tiger/it-62682151.eml";

    public $reFrom = ["tigerair.com.au"];
    public $reBody = [
        'en'  => ['Flight Details', 'flight confirmation'],
        'en2' => ['flight details', 'booking reference'],
    ];
    public $reSubject = [
        'Tigerair - Confirmation of booking',
        'Trip Reminder for booking',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Departs'           => ['Departs', 'depart'],
            'booking reference' => 'booking reference',
            'Passenger'         => ['Passenger', 'passenger'],
        ],
    ];
    private $keywordProv = 'Tigerair';

    private $date = null;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getHeader('date'));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Acknowledge Changes'] | //a[contains(@href,'.tigerair.com.au')]")->length > 0
            && $this->detectBody($this->http->Response['body'])
        ) {
            $this->logger->warning('YES1');

            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('booking reference'))}]/following::text()[normalize-space()!=''][1]"),
                $this->t('booking reference'))
            ->travellers($this->http->FindNodes("//text()[({$this->starts($this->t('Passenger'))}) and not({$this->contains($this->t('Passenger Details'))})]/following::text()[normalize-space()!=''][1][not(contains(normalize-space(), 'passenger'))]"),
                true);

        $xpath = "//p[({$this->starts($this->t('Date'))}) and ({$this->contains($this->t('Departs'))})]";

        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH 1]: " . $xpath);

        if ($nodes->count() > 0) {
            $this->parseSegment1($nodes, $f);
        }

        if ($nodes->count() === 0) {
            $xpath = "//text()[starts-with(normalize-space(), 'depart')]/ancestor::tr[1]";
            $nodes = $this->http->XPath->query($xpath);
            $this->logger->debug("[XPATH 2]: " . $xpath);

            if ($nodes->count() > 0) {
                $this->parseSegment2($nodes, $f);
            }
        }

        return true;
    }

    private function parseSegment1($nodes, $f)
    {
        $this->logger->notice(__METHOD__);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][2]", $root);

            if (preg_match("#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$#", $node, $m)) {
                $flight = $m[1] . $m[2];
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $seats = $this->http->FindNodes("//text()[translate(normalize-space(),' ','')='{$flight}'][1]/ancestor::table[{$this->contains($this->t('seat'))}][1][{$this->contains($this->t('meals'))}][not(.//table)]/descendant::text()[{$this->eq($this->t('seat'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][not({$this->eq($this->t('seat'))})]", null, '/^\d+[A-Z]$/');
                $seats = array_filter($seats);

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
                $meals = $this->http->FindNodes("//text()[translate(normalize-space(),' ','')='{$flight}'][1]/ancestor::table[{$this->contains($this->t('seat'))}][1][{$this->contains($this->t('meals'))}]/descendant::text()[{$this->eq($this->t('meals'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][not({$this->eq($this->t('meals'))})]");

                if (!empty($meals)) {
                    $s->extra()->meal(implode(";", array_unique($meals)));
                }
            }

            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#(.+?)\s+{$this->opt($this->t('to'))}\s+(.+)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->noCode();
                $s->arrival()
                    ->name($m[2])
                    ->noCode();
            }

            $date = $this->normalizeDate(
                $this->http->FindSingleNode(
                    "./descendant::text()[{$this->contains($this->t('Date'))}]/following::text()[normalize-space()!=''][1]",
                    $root
                ));

            if (!empty($date)) {
                $s->departure()
                    ->date(strtotime($this->http->FindSingleNode("./descendant::text()[contains(.,'Departs')]/following::text()[normalize-space()!=''][1]",
                        $root), $date));
                $s->arrival()
                    ->date(strtotime($this->http->FindSingleNode("./descendant::text()[contains(.,'Arrives')]/following::text()[normalize-space()!=''][1]",
                        $root), $date));
            }

            if (empty($date) && empty($s->getDepDate()) && empty($s->getArrDate())) {
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Date'))}][normalize-space()!=''][1]", $root);

                if (preg_match('/^Date \w+ (?<date>\d{1,2} \w+ \d{2,4}) \- Departs (?<dtime>\d{1,2}\:\d{2} [AP]M) \- Arrives (?<atime>\d{1,2}\:\d{2} [AP]M)$/', $node, $m)) {
                    $s->departure()
                        ->date(strtotime($m['date'] . ', ' . $m['dtime']));
                    $s->arrival()
                        ->date(strtotime($m['date'] . ', ' . $m['atime']));
                }
            }
        }
    }

    private function parseSegment2($nodes, Flight $f)
    {
        $this->logger->notice(__METHOD__);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = $this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][2]", $root);

            if (preg_match("#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$#", $node, $m)) {
                $flight = $m[1] . $m[2];
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $seats = $this->http->FindNodes("//text()[translate(normalize-space(),' ','')='{$flight}'][1]/ancestor::table[{$this->contains($this->t('seat'))}][1][{$this->contains($this->t('meals'))}][not(.//table)]/descendant::text()[{$this->eq($this->t('seat'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][not({$this->eq($this->t('seat'))})]", null, '/^\d+[A-Z]$/');
                $seats = array_filter($seats);

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
                $meals = $this->http->FindNodes("//text()[translate(normalize-space(),' ','')='{$flight}'][1]/ancestor::table[{$this->contains($this->t('seat'))}][1][{$this->contains($this->t('meals'))}]/descendant::text()[{$this->eq($this->t('meals'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][not({$this->eq($this->t('meals'))})]");

                if (!empty($meals)) {
                    $s->extra()->meal(implode(";", array_unique($meals)));
                }
            }

            $node = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'depart')]", $root);
            $reg = "#^depart\s+(?<name>\D+)\((?<code>[A-Z]{3})\)\s+"
                . "(?<time>[\d+:]+(?:\s*[AP]M))\s+•\s+(?<date>.+?)\s+•\s+Terminal\s*(?<terminal>\w{0,5})$#";

            if (preg_match($reg, $node, $m)) {
                $depDate = $this->normalizeDate($m['date'] . ', ' . $m['time']);
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($depDate)
                    ->terminal($m['terminal'], true, false);
            }

            $node = $this->http->FindSingleNode("./descendant::td[starts-with(normalize-space(), 'arrive')]", $root);
            $reg = "#^arrive\s+(?<name>\D+)\((?<code>[A-Z]{3})\)\s+"
                . "(?<time>[\d+:]+(?:\s*[AP]M))\s+•\s+(?<date>.+?)\s+•\s+Terminal\s*(?<terminal>\w{0,5})$#";

            if (preg_match($reg, $node, $m)) {
                $depDate = $this->normalizeDate($m['date'] . ', ' . $m['time']);
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($depDate)
                    ->terminal($m['terminal'], true, false);
            }
        }
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
        $in = [
            // Wed 01 May 2019
            '#^(\w+)\s+(\d+)\s+(\w+)\s+(\d{4})$#u',
            //Mon 30 Mar, 10:30 AM
            '#^(\w+)\s+(\d+)\s+(\w+)[,]\s+([\d\:]+\s+(?:AM|PM))$#u',
        ];
        $out = [
            '$2 $3 $4',
            "$2 $3 $year, $4",
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Departs'], $words['booking reference'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departs'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['booking reference'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
