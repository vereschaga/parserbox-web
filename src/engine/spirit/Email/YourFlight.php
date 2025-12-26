<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "spirit/it-133933252.eml";
    public $subjects = [
        'is Fast Approaching! Get More Legroom Today',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Hi' => ['Dear', 'Hi'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@spirit.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Spirit Airlines')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'SeatBid Is Available On Your Flight')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('BID NOW'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('SeatBid Is As Easy As'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]spirit\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();
        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}][not(contains(normalize-space(), 'Guests'))]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\w+)\s*\,/u");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]/following::text()[starts-with(normalize-space(), 'Confirmation Number:')][1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]{6})/"));

        $nodes = $this->http->XPath->query("//img[contains(@src, 'arrow-ltr')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $text = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));
            //$this->logger->error($text);

            if (preg_match("/^(?<date>\d+\s*\w+\s*\d{4})\n\D+\s+\((?<depCode>[A-Z]{3})\)\n\D+\s\((?<arrCode>[A-Z]{3})\)\n(?<airline>[A-Z\d]{2})\s+(?<number>\d{2,4})[\-\s]+Operated by\s*(?<operator>.+)$/", $text, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['airline'])
                    ->number($m['number'])
                    ->operator($m['operator']);

                $s->departure()
                    ->noDate()
                    ->day(strtotime($m['date']))
                    ->code($m['depCode']);

                $s->arrival()
                    ->code($m['arrCode'])
                    ->noDate();
            }
        }

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
}
