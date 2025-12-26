<?php

namespace AwardWallet\Engine\shangrila\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RestaurantReservation extends \TAccountChecker
{
    public $mailFiles = "shangrila/it-57531255.eml, shangrila/it-57657321.eml, shangrila/it-58519709.eml";

    public static $dictionary = [
        'en' => [
            'Confirmation number:' => ['Confirmation number:', 'Confirmation Number:'],
            'Restaurant:'          => 'Restaurant:',
        ],
    ];
    public $lang = '';

    private $detectSubject = [
        "en" => " - Reservation Confirmation", // Shanghai Pavilion - Reservation Confirmation (7 March 2020 #H06325)
    ];

    private $providerCode = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@shangri-la.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['ichotelsgroup', 'shangrila'];
    }

    private function parseHtml(Email $email): void
    {
        $ev = $email->add()->event();

        // General
        $ev->general()
            ->confirmation($this->nextTd($this->t('Confirmation number:')));

        $last = $this->nextTd($this->t('Last name:'));
        $first = $this->nextTd($this->t('First name:'));

        if (!empty($last) && !empty($first)) {
            $ev->general()
                ->traveller(implode(' ', [$first, $last]), true);
        }
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Dear ')) . "][1]", null, true,
            "#" . $this->preg_implode($this->t('Dear ')) . "\s*(?:(?:Mr|Ms)\.\s+)?(.+)#");

        if (!empty($traveller)) {
            $ev->general()
                ->traveller($traveller, false);
        }

        // Program
        $account = $this->nextTd($this->t('Golden Circle Membership No.:'));

        if (!empty($account)) {
            $ev->program()->account($account, false);
        }

        // Place
        $ev->place()
            ->type(Event::TYPE_RESTAURANT)
            ->name($this->nextTd($this->t('Restaurant:')))
            ->address($this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Greetings from')) . "and " . $this->contains($this->t('!')) . "])[1]", null, true,
                "#" . $this->preg_implode($this->t('Greetings from')) . "\s+(.+)!#"))
        ;

        // Booked
        $date = $this->nextTd($this->t('Date:'));
        $time = $this->nextTd($this->t('Time:'));

        if (!empty($date) && !empty($time)) {
            $ev->booked()
                ->start(strtotime($date . ', ' . $time))
                ->noEnd();
        }

        $ev->booked()
            ->guests($this->nextTd($this->t('Number of adults:')));
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@ihg.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".intercontinental.com/") or contains(@href,"www.ihg.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Greetings from InterContinental") or contains(.,"@ihg.com")]')->length > 0
        ) {
            $this->providerCode = 'ichotelsgroup';

            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) === true
            || $this->http->XPath->query('//a[contains(@href,".shangri-la.com/") or contains(@href,"www.shangri-la.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Greetings from Shangri-La") or contains(normalize-space(),"welcoming you at Shangri-La") or contains(.,"@shangri-la.com")]')->length > 0
        ) {
            $this->providerCode = 'shangrila';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Confirmation number:']) || empty($phrases['Restaurant:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation number:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Restaurant:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function nextTd($field, $root = null, $regexp = null)
    {
        return $this->http->FindSingleNode(".//td[{$this->eq($field)} and not(.//td)]/following-sibling::td[1]", $root, true, $regexp);
    }
}
