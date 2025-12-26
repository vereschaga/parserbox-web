<?php

namespace AwardWallet\Engine\viking\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class DiningJunk extends \TAccountChecker
{
    public $mailFiles = "viking/it-215468372.eml, viking/it-218339638.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private static $detectProviders = [
        'viking' => [
            'from' => '@vikingcruises.com',
            'subjectUniqueWords' => 'Viking',
            'bodyUrl' => ['vikingcruises.com'],
        ],
        'oceania' => [
            'from' => '@oceaniacruises.com',
            'subjectUniqueWords' => 'Oceania Cruises',
            'bodyUrl' => ['oceaniacruises.com', 'OceaniaCruises.com'],
        ],
        'regentcruises' => [
            'from' => '@RSSC.com',
            'subjectUniqueWords' => 'Regent Seven Seas Cruises',
            'bodyUrl' => ['.rssc.com/'],
        ]
    ];
    private $detectSubject = [
        '| Your Dining Reservation Confirmation',
    ];
    private $detectBody = [
        'invited you to join them for dinner aboard',
        'confirming your dining reservation onboard',
        'confirming your dining reservation aboard',
    ];

    private $lang = 'en';
    private $providerCode = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$detectProviders as $code => $detects) {
            if (empty($detects['bodyUrl'])
                || $this->http->XPath->query("//a[{$this->contains($detects['bodyUrl'], '@href')}]")->length === 0
            ) {
                continue;
            }
            if (
                $this->http->XPath->query("//node()[{$this->contains($this->detectBody)}]")->length > 0
                && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Name:')]/following::text()[normalize-space()][position() = 1 or position() = 2][starts-with(normalize-space(), 'Dining Party:')]/following::text()[normalize-space()][position() = 1 or position() = 2][starts-with(normalize-space(), 'Restaurant:')]")->length > 0
            ) {
                $this->providerCode = $code;
                $email->setIsJunk(true);
            }
        }

        $class = explode('\\', __CLASS__);

        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@vikingcruises.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectProviders as $code => $detects) {
//            if (empty($detects['from']) || stripos(implode(' ', $parser->getFrom()), $detects['from']) === false) {
//                continue;
//            }
            if (empty($detects['subjectUniqueWords']) || stripos($parser->getSubject(), $detects['subjectUniqueWords']) === false) {
                continue;
            }

            $detectedSubject = false;
            foreach ($this->detectSubject as $subject) {
                if (stripos($parser->getSubject(), $subject) !== false) {
                    $detectedSubject = true;
                    break;
                }
            }
            if ($detectedSubject === false) {
                continue;
            }
//
            if (empty($detects['bodyUrl'])
                || $this->http->XPath->query("//a[{$this->contains($detects['bodyUrl'], '@href')}]")->length === 0
            ) {
                continue;
            }
            if ( $this->http->XPath->query("//node()[{$this->contains($this->detectBody)}]")->length > 0
                && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Name:')]/following::text()[normalize-space()][position() = 1 or position() = 2][starts-with(normalize-space(), 'Dining Party:')]/following::text()[normalize-space()][position() = 1 or position() = 2][starts-with(normalize-space(), 'Restaurant:')]")->length > 0
            ) {
                $this->providerCode = $code;
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProviders);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text){
            return "contains(".$text.", \"{$s}\")";
        }, $field));
    }
}
