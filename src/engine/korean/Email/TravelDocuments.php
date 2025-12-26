<?php

namespace AwardWallet\Engine\korean\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelDocuments extends \TAccountChecker
{
    public $mailFiles = "korean/it-784342847.eml, korean/it-784727717.eml";
    public $subjects = [
        '[Korean Air] Travel Documents and Requirements',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@koreanair.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Download Korean Air app')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Travel Documents and Requirements'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('K-ETA'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]koreanair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Flight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Reference'))}[\:\s]*([A-Z\d]{6})$/");
        $segConf = '';

        if (empty($confirmation) && preg_match("/^(?:Booking\s+Reference)[\:\s]*(?<confF>[\d\-]+)\((?<confS>[A-Z\d]{6})\)$/",
                $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Reference')]/ancestor::tr[1]"), $m)) {
            $f->general()
                ->confirmation($m['confF'], 'Booking Reference');
            $segConf = $m['confS'];
        } else {
            $f->general()
                ->confirmation($confirmation);
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'icon-flight-bottom')]/ancestor::table[normalize-space()][1]");

        if (!preg_match("/^[A-Z]{2}/", $this->http->FindSingleNode(".", $nodes[0]))) {
            $nodes = $this->http->XPath->query("//img[contains(@src, 'icon-flight-bottom')]/ancestor::table[normalize-space()][4]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $segmentText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            /*
             YYZ
             ICN
             KE 074
             2024.11.09 11:40
            */

            if (
                preg_match("/^(?<depCode>[A-Z]{3})\n(?<arrCode>[A-Z]{3})\n(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\n(?<depDate>\d{4}\.\d+\.\d+\s*\d+\:\d+)$/", $segmentText, $m)
                || preg_match("/^(?<depCode>[A-Z]{3})\n(?<depDate>\d{4}\.\d+\.\d+\s*\d+\:\d+)\n(?:.+\n)?(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\n(?:.+\n){0,5}(?<arrCode>[A-Z]{3})$/", $segmentText, $m)
            ) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate']));

                $s->arrival()
                    ->code($m['arrCode'])
                    ->noDate();
            }

            if (!empty($segConf)) {
                $s->setConfirmation($segConf);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            // 2024.11.09 11:40
            "#^(\d{4})\.(\d+)\.(\d+)\s*(\d+\:\d+)$#u",
        ];
        $out = [
            "$3.$2.$1, $4",
        ];
        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }
}
