<?php

namespace AwardWallet\Engine\israel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkYouCanNow extends \TAccountChecker
{
    public $mailFiles = "israel/it-38300407.eml, israel/it-38729199.eml";

    public $reFrom = ["no-replyelal@elal.co.il"];
    public $reBody = [
        'en' => ['Boarding from terminal:'],
    ];
    public $reSubject = [
        'You can now perform Check-in for your upcoming flight.',
        'You can now perform quick Check-in for your upcoming flight.',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flight ticket no:' => 'Flight ticket no:',
            'Route:'            => 'Route:',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $subject = $parser->getSubject();

        if (empty($subject)) {
            return $email;
        }

        if (false === $this->stripos($subject, $this->reSubject)) {
            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[(@alt='the new') or contains(@src,'.elal.com')] | //a[contains(@href,'.elal.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->reFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"]) && $this->stripos($headers["subject"], $this->reSubject)) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email)
    {
        $condition1 = $this->http->XPath->query("//text()[{$this->starts($this->t('Flight ticket no:'))}]
            /following::text()[normalize-space()!=''][1][{$this->starts('Order number:')}]
            /following::text()[string-length(normalize-space())>2][1][{$this->starts('Route:')}]
            /following::text()[string-length(normalize-space())>2][1][{$this->starts('Boarding from terminal:')}]");

        $condition2 = $this->http->XPath->query("//img[contains(@src,'www.elal.com/SiteCollectionImages/Newsletter/')]");
        $condition3 = $this->http->XPath->query("//img[@alt='the new']");

        if (($condition1->length == 1) && ($condition2->length > 0) && ($condition3->length > 0)) {
            $email->setIsJunk(true);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Flight ticket no:'], $words['Route:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight ticket no:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Route:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
