<?php

namespace AwardWallet\Engine\swagbucks\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

// emails from info@swagbucks.com
class AccountInfo extends \TAccountChecker
{
    public $mailFiles = "swagbucks/statements/it-80204627.eml, swagbucks/statements/it-79947215.eml, swagbucks/statements/it-80268826.eml, swagbucks/statements/it-80382295.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'hello' => ['Hi', 'Hey'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]swagbucks\.com$/', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHeader('from'), 'info@swagbucks.com') === false
            && $this->http->XPath->query('//a[contains(@href,".swagbucks.com/") or contains(@href,"www.swagbucks.com") or contains(@href,"help.swagbucks.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Welcome to Swagbucks") or contains(normalize-space(),"Thanks for shopping through Swagbucks") or contains(.,"www.swagbucks.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // James Koo    |    Jaik83
        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:]\d ]*[[:alpha:]\d]';

        $st = $email->add()->statement();

        $name = $login = null;

        // it-79947215.eml
        $helloHtml = $this->http->FindHTMLByXpath("//text()[starts-with(normalize-space(),'Congratulations!')]/ancestor::tr[1]");
        $helloText = $this->htmlToText($helloHtml);

        if (preg_match("/^\s*(?<name>{$patterns['travellerName']})[ ]*\([ ]*(?<login>[^\n)(]{3,})[ ]*\)[,;:!? ]*\n+[ ]*Congratulations!/iu", $helloText, $m)) {
            /*
                James Koo (Jaik83),
                Congratulations! You're getting cash back from Swagbucks on your recent purchase.
            */
            $name = $this->travellerNameFilter($m['name']);
            $login = $m['login'];
        }

        if (!$name) {
            $name = $this->travellerNameFilter($this->http->FindSingleNode("//text()[{$this->starts($this->t('hello'))}]", null, true, "/^{$this->opt($this->t('hello'))}\s+({$patterns['travellerName']})(?:[ ]*[,:;!?]|$)/u"));
        }

        if (!$name) {
            $name = $this->travellerNameFilter($this->http->FindSingleNode("//text()[{$this->eq($this->t('hello'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u"));
        }

        if (!$name) {
            $name = $this->travellerNameFilter($this->http->FindSingleNode("//text()[contains(normalize-space(),'congrats')]", null, true, "/^({$patterns['travellerName']})[ ]*,[ ]*congrats/iu"));
        }

        if (preg_match('/\d/', $name) && !preg_match('/\s/', $name)) {
            // Jaik83
            if (!$login) {
                $login = $name;
            }
            $name = null;
        }

        if (!$login) {
            // it-80268826.eml
            $login = $this->http->FindSingleNode("//text()[contains(normalize-space(),'New Email:')]/ancestor::*[1]", null, true, "/New Email:[ ]*(\S+@\S+)(?: |$)/i");
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($login) {
            $st->setLogin($login);
        }

        if ($name || $login) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
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
        return $this->http->FindSingleNode("//text()[contains(normalize-space(),'Welcome to Swagbucks and congrats on being a part of a special')]", null, true, "/Welcome to Swagbucks and congrats on being a part of a special .+ Signup Bonus/") !== null
            || $this->http->FindSingleNode("//text()[contains(normalize-space(),\"You've just earned\")]", null, true, "/You've just earned \d[,.\'\d ]*SB from/i") !== null
            || $this->http->FindSingleNode("//text()[starts-with(normalize-space(),\"You earned\")]", null, true, "/^You earned \d[,.\'\d ]*SB for/i") !== null
            || $this->http->XPath->query("//a[normalize-space()='Verify Your Email' or normalize-space()='Confirm Email Address' or normalize-space()='Activate My Swag Up Now']")->length > 0
            || $this->http->XPath->query("//tr[ *[normalize-space()='SB Credit Date'] and *[normalize-space()='Pending SB'] ]")->length > 0
            || $this->http->XPath->query("//text()[normalize-space()='Please sign-in to your account to view the deposit.' or normalize-space()='We received a request to change your password.' or contains(normalize-space(),'We see you made a transaction through Swagbucks for') or contains(normalize-space(),'Please verify your new profile by clicking here') or contains(normalize-space(),'your account has been awarded') or contains(normalize-space(),'This is to notify you that your Swagbucks profile has been updated.') or contains(normalize-space(),'signup bonus expires!')]")->length > 0;
    }

    private function travellerNameFilter(?string $name): ?string
    {
        if (preg_match("/^there$/", $name)) {
            return null;
        }

        return $name;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
