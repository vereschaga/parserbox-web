<?php

namespace AwardWallet\Engine\wanderlog\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CheckOutReservationJunk extends \TAccountChecker
{
    public $mailFiles = "wanderlog/it-65456861.eml";

    private $detectFrom = ['no-reply@wanderlog.com'];
    private $detectSubject = [
        // en
        'Check out your reservation on Wanderlog for',
    ];

    private $detectText = [
        'en' => [
            'We\'ve added your reservations!',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        if (self::detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains('.wanderlog.com', '@href') . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectText as $lang => $detectText) {
            if ($this->http->XPath->query("//*[" . $this->eq($detectText) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if (empty($headers["subject"])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function eq($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'normalize-space(' . $text . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
