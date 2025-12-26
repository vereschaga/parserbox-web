<?php

namespace AwardWallet\Engine\tway\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "tway/it-714591601.eml, tway/it-725964554.eml, tway/it-726512894.eml";

    public $subjects = [
        '[T’way Air] Booking No.',
        "[T'way Air] Reservation Confirmation Form (E-Ticket)",
        //zh
        "[T'way Air] E-ticket",
    ];

    public $reBody = [
        'en'    => ['Manage my booking', 'Segment1'],
        'zh'    => ['My予約へ', '区間1'],
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
        ],
        'zh' => [
            'T’way Air Co.'     => 'T’way Air Co.',
            'Segment1'          => '区間1',
            'Manage my booking' => 'My予約へ',
            'Reservation No.'   => '予約番号',
            'Reservation Date'  => '予約日',
            'Flight No.'        => '便名',
            'Departure'         => '出発',
            'Arrival'           => '到着',
            'Status'            => '状態',
            'Confirmed'         => '状態',
            'Passenger'         => '搭乗客',
        ],
    ];

    public function detectEmailByHeaders(array $headers): bool
    {
        if (isset($headers['from']) && stripos($headers['from'], '@twayair.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser): bool
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('T’way Air Co.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Segment1'))}]")->length > 0
            && $this->http->XPath->query("//a[{$this->eq($this->t('Manage my booking'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from): bool
    {
        return preg_match('/[@.]twayair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation No.'))}]/ancestor::tr", null, true, "/^{$this->opt($this->t('Reservation No.'))}\s*([A-Z\d]{6})$/"), $this->t('Reservation No.'))
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Date'))}]/ancestor::tr", null, true, "/^{$this->opt($this->t('Reservation Date'))}\s*(\d{4}\-\d+\-\d+)\(/")));

        $segmentNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight No.'))}]/ancestor::tbody[1]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight No.'))}]/ancestor::tr[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Flight No.'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s*(?<duration>(?:\d{1,2}h)?\s*\d{1,2}m)$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->setDuration($m['duration']);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Departure'))}\s*(?<depPoint>.+)\s*\((?<depCode>[A-Z]{3})\)\s*(?<depDate>\d{4}\-\d+\-\d+)\(\S{1,3}\)\s*(?<depTime>\d{1,2}:\d{2})$/", $depInfo, $m)) {
                if (preg_match("/^(?<depName>.+?)\s*(?:T(?<depTerminal>\s*\S+))?\s*$/", $m['depPoint'], $m2)) {
                    $s->departure()
                        ->name($m2['depName']);

                    if (isset($m2['depTerminal']) && !empty($m2['depTerminal'])) {
                        $s->departure()
                            ->terminal($m2['depTerminal']);
                    }
                }

                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ' ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Arrival'))}\s*(?<arrPoint>.+)\s*\((?<arrCode>[A-Z]{3})\)\s*(?<arrDate>\d{4}\-\d+\-\d+)\(\S{1,3}\)\s*(?<arrTime>\d{1,2}:\d{2})$/", $arrInfo, $m)) {
                if (preg_match("/^(?<arrName>.+?)\s*(?:T(?<arrTerminal>\s*\S+))?\s*$/", $m['arrPoint'], $m2)) {
                    $s->arrival()
                        ->name($m2['arrName']);

                    if (isset($m2['arrTerminal']) && !empty($m2['arrTerminal'])) {
                        $s->arrival()
                            ->terminal($m2['arrTerminal']);
                    }
                }

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ' ' . $m['arrTime']));
            }

            $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status'))}]/ancestor::tr[1]", $root, true, "/^{$this->opt($this->t('Status'))}\s*(?<status>\w+)$/");

            if (!empty($status)) {
                $s->setStatus($status);
            }
        }

        $passNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::table[1]");

        foreach ($passNodes as $root) {
            $passInfo = $this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]", $root, false);

            if (preg_match("/^{$this->opt($this->t('Passenger'))}\s*(?<passName>\D+)\s+\S+\(/", $passInfo, $m)) {
                $f->addTraveller($m['passName'], false);
            }
        }
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
        foreach ($this->reBody as $lang => $words) {
            if ($this->http->XPath->query("//*[{$this->contains($words[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($words[1])}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
