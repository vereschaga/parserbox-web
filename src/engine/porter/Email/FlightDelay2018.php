<?php

namespace AwardWallet\Engine\porter\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightDelay2018 extends \TAccountChecker
{
    public $mailFiles = "porter/it-30546600.eml, porter/it-683247278.eml, porter/it-683247893.eml, porter/it-763538876.eml";

    public $reFrom = ["flyporter.com"];
    public $reBody = [
        'en'  => ['Flight Delay Notification', 'Departure times are subject to change'],
        'en2' => ['Flight Cancellation Notification', 'has been cancelled'],
        'en3' => ['Flight Change Notification', "You’ve been rebooked on the next available flight"],
        'fr'  => ['Avis de retard de vol', "Les heures de départ peuvent être modifiées"],
    ];
    public $reSubject = [
        'IMPORTANT - Flight Delay Notification',
        'IMPORTANT - Avis de retard de vol',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            // 'Confirmation number' => '',
            // 'Departure date' => '',
            // 'Departure time' => '',
            // 'Passenger name(s)' => '',
            // 'Porter flight' => '',
            // 'Rebooked' => '',
            // 'Status' => '',
            // 'Your Porter flight' => '',
            // 'Your new flight details' => '',
            // 'has been cancelled' => '',
            // 'has been delayed' => '',
        ],
        'fr' => [
            'Confirmation number' => 'Numéro de confirmation',
            'Departure date'      => 'Date de départ',
            'Departure time'      => 'Heure de départ',
            'Passenger name(s)'   => 'Passagers Nom(s)',
            // 'Porter flight' => '',
            // 'Rebooked' => '',
            'Status'             => 'État',
            'Your Porter flight' => 'Votre prochain vol',
            // 'Your new flight details' => '',
            // 'has been cancelled' => '',
            'has been delayed' => 'a été retardé',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your new flight details'))}]/following::text()[normalize-space()][2][{$this->eq($this->t('Rebooked'))}]/preceding::text()[normalize-space()][1]")->length > 0) {
            $this->parseEmail2($email);
        } else {
            $this->parseEmail($email);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.flyporter.com/')] | //a[contains(@href,'.flyporter.com/')]")->length > 0) {
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)) {
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

    private function parseEmail(Email $email)
    {
        if ($this->http->XPath->query("//tr[contains(., 'Your revised departure time') or contains(., 'Your new flight details')]")->length > 0
        && $this->http->XPath->query("//text()[normalize-space()='Your new flight details']/following::text()[normalize-space()][2][normalize-space()='Rebooked']")->length === 0) {
            return false;
        }

        $r = $email->add()->flight();

        // if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0
        //     && $this->http->XPath->query("//text()[normalize-space()='Your new flight details']/following::text()[normalize-space()][2][normalize-space()='Rebooked']")->length === 0) {
        // move to segment
        //     $r->general()
        //         ->cancelled();
        // }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger name(s)'))}]/ancestor::tr[1][{$this->contains($this->t('Status'))}]/td[3]/descendant::text()[normalize-space()!=''][position()>1]"));

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//img[contains(@src, 'Logo')]/following::text()[{$this->contains($this->t('Passenger name(s)'))}][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        }

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number'))}]/ancestor::tr[1][{$this->contains($this->t('Status'))}]/td[1]/descendant::text()[normalize-space()!=''][2]")
                ?? $this->http->FindSingleNode("//img[contains(@src, 'Logo')]/following::text()[{$this->eq($this->t('Confirmation number'))}][1]/following::text()[normalize-space()][1]"));

        if (!empty($travellers)) {
            $r->general()
                ->travellers($travellers);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number'))}]/ancestor::tr[1][{$this->contains($this->t('Status'))}]/td[2]/descendant::text()[normalize-space()!=''][2]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//img[contains(@src, 'Logo')]/following::text()[{$this->contains($this->t('Status'))}][1]/following::text()[normalize-space()][1]");
        }

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        $node = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Your Porter flight'))}) and ({$this->contains($this->t('has been delayed'))})]");

        if (preg_match("/Porter flight (\d+) to (.+) has been delayed/i", $node, $m)
            || preg_match("/Votre prochain vol (\d+) à destination (.+) a été retardé/iu", $node, $m)
        ) {
            $s = $r->addSegment();

            $s->airline()
                //https://en.wikipedia.org/wiki/Porter_Airlines
                ->name('PD')
                ->number($m[1]);

            $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure date'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][last()][not({$this->contains($this->t('Departure date'))})]");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure date'))}]/ancestor::tr[1][{$this->eq($this->t('Departure date'))}]/following-sibling::tr[last()][count(descendant::text()[normalize-space()!='']) = 1]");
            }

            if (empty($date)) {
                $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure date'))}]/ancestor::td[1]/following::td[1][count(descendant::text()[normalize-space()!='']) > 1]/descendant::text()[normalize-space()!=''][last()]");
            }

            $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure time'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][last()][not({$this->contains($this->t('Departure time'))})]");

            if (empty($time)) {
                $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure time'))}]/ancestor::tr[1][{$this->eq($this->t('Departure time'))}]/following-sibling::tr[last()][count(descendant::text()[normalize-space()!='']) = 1]");
            }

            if (empty($time)) {
                $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure time'))}]/ancestor::td[1]/following::td[1][count(descendant::text()[normalize-space()!='']) > 1]/descendant::text()[normalize-space()!=''][last()]");
            }

            $s->departure()
                ->noCode()
                ->name($m[2])
                ->date(strtotime($time, strtotime($date)));

            $s->arrival()
                ->noCode()
                ->noDate();

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0
                && $this->http->XPath->query("//text()[normalize-space()='Your new flight details']/following::text()[normalize-space()][2][normalize-space()='Rebooked']")->length === 0) {
                $s->extra()
                    ->cancelled();
            }
        }
        $node = $this->http->FindSingleNode("//text()[({$this->contains($this->t('Porter flight'))}) and ({$this->contains($this->t('has been cancelled'))})]");

        if (preg_match("/Porter flight (\d+) to (.+) - (.+) has been cancelled/i", $node, $m)) {
            $s = $r->addSegment();

            $s->airline()
                //https://en.wikipedia.org/wiki/Porter_Airlines
                ->name('PD')
                ->number($m[1]);

            $s->departure()
                ->noCode()
                ->name($m[2])
                ->noDate();

            $s->arrival()
                ->noCode()
                ->name($m[3])
                ->noDate();

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('has been cancelled'))}]")->length > 0
                && $this->http->XPath->query("//text()[normalize-space()='Your new flight details']/following::text()[normalize-space()][2][normalize-space()='Rebooked']")->length === 0) {
                $s->extra()
                    ->cancelled();
            }
        }

        return true;
    }

    private function parseEmail2(Email $email)
    {
        $r = $email->add()->flight();

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger name(s)'))}]/ancestor::tr[1][{$this->contains($this->t('Status'))}]/td[3]/descendant::text()[normalize-space()!=''][position()>1]"));

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//img[contains(@src, 'Logo')]/following::text()[{$this->contains($this->t('Passenger name(s)'))}][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));
        }

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number'))}]/ancestor::tr[1][{$this->contains($this->t('Status'))}]/td[1]/descendant::text()[normalize-space()!=''][2]")
                ?? $this->http->FindSingleNode("//img[contains(@src, 'Logo')]/following::text()[{$this->eq($this->t('Confirmation number'))}][1]/following::text()[normalize-space()][1]"));

        if (!empty($travellers)) {
            $r->general()
                ->travellers($travellers);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number'))}]/ancestor::tr[1][{$this->contains($this->t('Status'))}]/td[2]/descendant::text()[normalize-space()!=''][2]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//img[contains(@src, 'Logo')]/following::text()[{$this->contains($this->t('Status'))}][1]/following::text()[normalize-space()][1]");
        }

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Your new flight details']/following::text()[normalize-space()][2][normalize-space()='Rebooked']/preceding::text()[normalize-space()][1]");

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            $date = $this->http->FindSingleNode(".", $root);

            $flightNumber = $this->http->FindSingleNode("./ancestor::tr[1]/following::table[1]/descendant::text()[contains(normalize-space(), 'Flight number')]/following::td[1]", $root, true, "/^(\d{1,4})$/");

            if (!empty($flightNumber)) {
                $s->airline()
                    ->number($flightNumber)
                    ->name('PD');
            }

            $depInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following::table[1]/descendant::text()[contains(normalize-space(), 'Departs')]/following::td[1]", $root);

            if (preg_match("/^(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s*(?<depTime>[\d\:]+\s*a?p?m)$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($date . ', ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following::table[1]/descendant::text()[contains(normalize-space(), 'Arrives')]/following::td[1]", $root);

            if (preg_match("/^(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s*(?<arrTime>[\d\:]+\s*a?p?m)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
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
