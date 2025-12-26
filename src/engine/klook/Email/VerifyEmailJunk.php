<?php

namespace AwardWallet\Engine\klook\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerifyEmailJunk extends \TAccountChecker
{
    public $mailFiles = "klook/it-466943869.eml";
    public $subjects = [
        'Klook - Please verify your email address',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];
    private $detectFrom = "support-noreply@klook.com";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->detectFrom) !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Klook Team'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Welcome to Klook!'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Verify Email'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('If the button above does not work, please copy and paste the link provided into your browser.'))}]")->length > 0;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }
}
