<?php

namespace AwardWallet\Engine\ninja\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketsCanceled extends \TAccountChecker
{
    public $mailFiles = "ninja/it-739286860.eml";
    public $subjects = [
        'Your tickets have been canceled',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your ticket order has been canceled' => 'Your ticket order has been canceled',
            'Order ID:'                           => 'Order ID:',
            'Travel Date :'                       => 'Travel Date :',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rail.ninja') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'rail.ninja')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your ticket order has been canceled']) && !empty($dict['Travel Date :'])
                && $this->http->XPath->query("//text()[{$this->starts($dict['Your ticket order has been canceled'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['Travel Date :'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rail\.ninja$/', $from) > 0;
    }

    public function ParseRail(Email $email): void
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//a[contains(normalize-space(), 'My account')]/following::text()[contains(normalize-space(), 'Order ID:')][1]", null, true, "/{$this->opt($this->t('Order ID:'))}\s*(RN[\d\-]+)/"), 'Order ID');

        // Trains
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation()
            ->cancelled()
            ->status('Canceled')
        ;
        $traveller = $this->http->FindSingleNode("//a[contains(normalize-space(), 'My account')]/following::text()[contains(normalize-space(), 'Order ID:')][1]", null, true, "/^(\D+)\s+\|\s*{$this->opt($this->t('Order ID:'))}/");

        if (empty($traveller) && !empty($this->http->FindSingleNode("//a[contains(normalize-space(), 'My account')]/following::text()[contains(normalize-space(), 'Order ID:')][1]", null, true, "/^(\s*\D+\S+@\S+)+\s+\|\s*{$this->opt($this->t('Order ID:'))}/"))) {
        } else {
            $t->general()
                ->traveller($traveller, true);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseRail($email);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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
}
