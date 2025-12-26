<?php

namespace AwardWallet\Engine\fareharbor\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AdventureOnJunk extends \TAccountChecker
{
    public $mailFiles = "fareharbor/it-886355382.eml, fareharbor/it-886378008.eml";
    public $subjects = [
        '/(?:Confirmation|Reminder).+(?:for|on)\s+\w+\,\s+(?:\d+\s*\w+\s*\d{4}|\w+\s*\d+\,?\s+\d{4})\s*$/',
    ];

    public $lang = 'en';

    public $reBody = [
        'en' => ['powered by FareHarbor', 'your FareHarbor settings'],
        'fr' => ['optimisés par FareHarbor'],
    ];

    public static $dictionary = [
        "en" => [
            'Booking #' => 'Booking #',
        ],

        "fr" => [
            'Booking #' => 'Booking #',
        ],

        "it" => [
            'Booking #' => 'Prenotazione #',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fareharbor.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains('Affiliate:')}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains('Voucher:')}]")->length > 0) {
            return false;
        }

        if ($this->http->XPath->query("//img[contains(@src, '.fareharbor.com')] | //a[contains(@href, '.fareharbor.com') or contains(@href, '/fareharbor.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0
                    || $this->http->XPath->query("//a[contains(@href, 'messages.fareharbor.com')]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fareharbor\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true
            && self::detectEmailByHeaders($parser->getHeaders()) === true
        ) {
            $email->setIsJunk(true);
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

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (!empty($words['Booking #']) && $this->http->XPath->query("//text()[{$this->contains($words['Booking #'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
