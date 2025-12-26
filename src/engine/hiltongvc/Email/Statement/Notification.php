<?php

namespace AwardWallet\Engine\hiltongvc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Notification extends \TAccountChecker
{
    public $mailFiles = "hiltongvc/statements/it-105353194.eml, hiltongvc/statements/it-108091813.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'requested to reset your password' => ['requested to reset your password', 'Click the button below to reset your Club account password', 'Welcome to'],
        ],
    ];

    private $subjects = [
        'en' => ['Your Trip Dates are now open for booking', 'HGV Password Reset', 'Reset your HGV Concierge Password', 'Welcome to Hilton Grand Vacations Club'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hgvc.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".hiltongrandvacations.com/") or contains(@href,"club.hiltongrandvacations.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello')]", null, true, "/^\s*Hello\s*(\w+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
            $st->setNoBalance(true);
        } elseif ($this->detectEmailByBody($parser) == true) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("*[ descendant::h1[normalize-space()='Your Booking Window is Now Open'] and descendant::*[contains(normalize-space(),'You asked us to notify')] ]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('requested to reset your password'))}]")->length > 0;
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
}
