<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleHasChanged extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-108240935.eml";
    public $subjects = [
        '/The schedule for your reservation [A-Z\d]+ has changed/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@united.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[contains(@href, 'united.com')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(), 'United Airlines. All rights reserved')]")->length === 0
        ) {
            return false;
        }

        if (
            $this->http->XPath->query("//text()[contains(normalize-space(), 'The schedule for your reservation') and contains(normalize-space(), 'has changed')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src, '_logo_United.png')]/following::text()[normalize-space()][1][normalize-space() = 'New itinerary']")->length > 0
            || $this->http->XPath->query("//img[contains(@src, '_logo_United.png')]/following::text()[normalize-space()][1][contains(normalize-space(), 'for reservation')]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]united\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[normalize-space()='Schedule changes']/preceding::text()[starts-with(normalize-space(), 'The schedule for your reservation')][1]", null, true, "/{$this->opt($this->t('The schedule for your reservation'))}\s*([A-Z\d]{5,})/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//img[contains(@src, '_logo_United.png')]/following::text()[normalize-space()][1][normalize-space() = 'New itinerary']");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//img[contains(@src, '_logo_United.png')]/following::text()[string-length()][1]", null, true, "/\s([A-Z\d]{6})\s*have/");
        }

        if (empty($conf)) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($conf);
        }
        $f->general()
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Traveler(s)']/ancestor::tr[1]/following-sibling::tr"), true);

        if ($this->http->XPath->query("//text()[normalize-space()='Schedule changes']")->length > 0) {
            $f->general()
                ->status('changed');
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'arrow-right')]/ancestor::tr[2]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//text()[normalize-space()='Depart']/ancestor::tr[2][contains(., 'Flight info')]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::text()[contains(normalize-space(), 'Depart')][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));
            /*Depart
            September 4, 2021
            1:40 p.m.
            Colorado Springs (COS)*/
            if (preg_match("/^Depart\s*(?<depDate>\w+\s*\d+\,\s*\d{4}\s*[\d\:]+\s*a?p?\.m\.)\s*(.+)\((?<depCode>[A-Z]{3})\)$/", $depInfo, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['depDate']))
                    ->code($m['depCode']);
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::text()[contains(normalize-space(), 'Arrival')][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));
            /*Arrival
            September 4, 2021
            2:30 p.m.
            Denver (DEN)*/

            if (preg_match("/^Arrival\s*(?<arrDate>\w+\s*\d+\,\s*\d{4}\s*[\d\:]+\s*a?p?\.m\.)\s*(.+)\((?<arrCode>[A-Z]{3})\)$/s", $arrInfo, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['arrDate']))
                    ->code($m['arrCode']);
            }

            $flightInfo = implode("\n", $this->http->FindNodes("./descendant::text()[contains(normalize-space(), 'Flight info')][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));
            /*Flight info
            Flight: UA 4999
            Fare class: United Economy  (T)
            Duration: 2h 4m
            Aircraft: Embraer RJ145*/

            if (preg_match("/^{$this->opt($this->t('Flight info'))}\n{$this->opt($this->t('Flight:'))}\s*(?<aName>[A-Z\d]{2})\s*(?<fNumber>\d{1,5})\n{$this->opt($this->t('Fare class:'))}\s*(?<cabin>\D+)\s\((?<bokingCode>\D+)\)\n{$this->opt($this->t('Duration:'))}\s*(?<duration>.+)\n{$this->opt($this->t('Aircraft:'))}\s*(?<aircraft>.+)$/u", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bokingCode'])
                    ->aircraft($m['aircraft'])
                    ->duration($m['duration']);
            }

            $operator = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'is operated by')][1]", $root, true, "/{$this->opt($this->t('is operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //September 4, 2021 1:40 p.m.
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+)\s*(a?p?)\.(m)\.$#u", // 19 Nov 2018 1800 hrs
        ];
        $out = [
            "$2 $1 $3, $4 $5$6",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }
}
