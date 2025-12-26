<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-66481724.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code:'))}]", null, true, "/{$this->opt($this->t('Confirmation code:'))}\s+([A-Z\d]{6})$/"), 'Confirmation code')
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Passengers:'))}]", null, true, "/{$this->opt($this->t('Passengers:'))}\s+(\D+)/"), true);

        $s = $f->addSegment();

        $airlineText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'flight')]");

        if (preg_match("/^(?<name>.+)\s+flight\s+(?<number>\d{2,4})\s\((?<duration>.+)\)/u", $airlineText, $m)) {
            $s->airline()
                ->name($m['name'])
                ->number($m['number']);

            $s->extra()
                ->duration($m['duration']);
        }

        $departText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departs')]/ancestor::div[1]");

        if (preg_match("/^Departs\s*at\s*(?<depTime>[\d\:]+\sA?P?M)\s+on\s+(?<depDate>\w+\,\s+\w+\s+\d+\,\s+\d{4})\s+from\s+(?<depName>\w+)\s+\((?<depCode>\D{3})\)$/", $departText, $m)) {
            $s->departure()
                ->name($m['depName'])
                ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
                ->code($m['depCode']);
        }

        $arrivesText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrives')]/ancestor::div[1]");

        if (preg_match("/^Arrives\s*at\s*(?<arrTime>[\d\:]+\sA?P?M)\s+on\s+(?<arrDate>\w+\,\s+\w+\s+\d+\,\s+\d{4})\s+in\s+(?<arrName>\w+)\s+\((?<arrCode>\D{3})\)$/", $arrivesText, $m)) {
            $s->arrival()
                ->name($m['arrName'])
                ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']))
                ->code($m['arrCode']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'This event was automatically added to your calendar from email by Outlook')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Check in online'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation code:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Passengers:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('flight'))}]")->count() > 0
        ;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flightview\.com$/', $from) > 0;
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
