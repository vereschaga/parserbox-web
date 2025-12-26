<?php

namespace AwardWallet\Engine\lufthansa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "lufthansa/statements/it-609172240.eml, lufthansa/statements/it-610902018-fr.eml, lufthansa/statements/it-613635090-zh.eml, lufthansa/statements/it-613264124-pt.eml, lufthansa/statements/it-613023784-de.eml, lufthansa/statements/it-612351470-es.eml, lufthansa/statements/it-610437454-ru.eml, lufthansa/statements/it-610859023-ja.eml, lufthansa/statements/it-609037006-tr.eml, lufthansa/statements/it-609547316-nl-brussels.eml, lufthansa/statements/it-606247908-pl-austrian.eml";

    public $lang = '';

    public static $dictionary = [
        'ru' => [
            'emailAddress'        => ['Адрес электронной почты'],
            'Service card number' => 'Номер карты',
            'Dear'                => 'Dear',
        ],
        'fr' => [
            'emailAddress'        => ['Adresse email'],
            'Service card number' => 'Numéro de carte de service',
            'Dear'                => ['Bonjour', 'Dear'],
        ],
        'pl' => [
            'emailAddress'        => ['Adres e-mail'],
            'Service card number' => 'Numer karty',
            'Dear'                => 'Dear',
        ],
        'zh' => [
            'emailAddress'        => ['电子邮件地址'],
            'Service card number' => '会员卡号',
            'Dear'                => 'Dear',
        ],
        'ja' => [
            'emailAddress'        => ['Eメールアドレス'],
            'Service card number' => 'お客様のサービスカード番号',
            'Dear'                => 'Dear',
        ],
        'tr' => [
            'emailAddress' => ['Elektronik posta adresi'],
            // 'Service card number' => '',
            'Dear' => 'Dear',
        ],
        'pt' => [
            'emailAddress'        => ['Endereço de e-mail'],
            'Service card number' => 'Número do cartão de serviço',
            'Dear'                => 'Dear',
        ],
        'es' => [
            'emailAddress'        => ['Dirección de e-mail'],
            'Service card number' => 'Número de ServiceCard',
            'Dear'                => 'Buenos días',
        ],
        'it' => [
            'emailAddress'        => ['Indirizzo e-mail'],
            'Service card number' => 'Numero della tessera',
            'Dear'                => 'Buongiorno',
        ],
        'de' => [
            'emailAddress'        => ['E-Mail-Adresse'],
            'Service card number' => 'Servicekartennummer',
            'Dear'                => 'Guten Tag',
        ],
        'nl' => [
            'emailAddress'        => ['E-mailadres'],
            'Service card number' => 'Servicekaartnummer',
            'Dear'                => 'Beste',
        ],
        'en' => [
            'emailAddress'        => ['Email address'],
            'Service card number' => 'Service card number',
            'Dear'                => 'Dear',
        ],
    ];

    private $subjects = [
        'ru' => ['Добро пожаловать в Travel ID'],
        'fr' => ['Bienvenue dans votre Travel ID'],
        // 'pl' => [''],
        'zh' => ['欢迎使用 Travel ID'],
        'ja' => ['Miles & Moreにようこそ - Travel ID'],
        'tr' => ['Travel ID hesabınıza hoş geldiniz'],
        'pt' => ['Bem-vindo ao seu Travel ID'],
        'es' => ['Bienvenido/a a su Travel ID'],
        'it' => ['Le diamo il benvenuto nel suo Travel ID'],
        'de' => ['Willkommen bei Ihrer Travel ID'],
        'nl' => ['Welkom bij je Travel ID'],
        'en' => ['Welcome to your Travel ID', 'Welcome to Miles & More - Travel ID'],
    ];

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@](?:lufthansa|lufthansagroup)\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language and Format
        return $this->assignLang() && $this->findRoot()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        if (empty($this->providerCode)) {
            $this->logger->debug("Can't determine a provider!");

            return $email;
        }
        $this->logger->debug('Provider: ' . $this->providerCode);

        // Detecting Language
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('WelcomeTo' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            return $email;
        }
        $root = $roots->item(0);

        if (in_array($this->providerCode, ['austrian', 'brussels', 'swissair'])) {
            $email->setIsJunk(true);

            return $email;
        }

        $st = $email->add()->statement();

        $name = $number = null;

        $travellerNames = array_filter($this->http->FindNodes("following::text()[{$this->starts($this->t('Dear'))}]", $root, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $name = array_shift($travellerNames);
        }

        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("*[normalize-space()][3]", $root, true, "/^(.{3,}?)\s*{$this->opt($this->t('Service card number'))}$/iu");

        if ($number) {
            $st->setNumber($number)->setLogin($number);
        }

        if ($name || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//node()[not(.//tr) and {$this->eq($this->t('emailAddress'))}]/ancestor::*[../self::tr and count(preceding-sibling::*[normalize-space()])=1][1][ count(following-sibling::*[normalize-space()])=0 or count(following-sibling::*[normalize-space()])=1 and following-sibling::*[descendant::node()[{$this->eq($this->t('Service card number'))}]] ]/..");
    }

    private function assignProvider($headers): bool
    {
        if (preg_match('/[.@](?:lufthansa|lufthansagroup)\.com$/i', rtrim($headers['from'], '> ')) > 0
            || $this->http->XPath->query('//a[normalize-space()="Lufthansa.com"]')->length > 0
        ) {
            $this->providerCode = 'lufthansa';

            return true;
        }

        if (preg_match('/[.@]austrian\.com$/i', rtrim($headers['from'], '> ')) > 0
            || $this->http->XPath->query('//a[normalize-space()="Austrian.com"]')->length > 0
        ) {
            $this->providerCode = 'austrian';

            return true;
        }

        if (preg_match('/[.@]brusselsairlines\.com$/i', rtrim($headers['from'], '> ')) > 0
            || $this->http->XPath->query('//a[normalize-space()="Brusselsairlines.com"]')->length > 0
        ) {
            $this->providerCode = 'brussels';

            return true;
        }

        // always last!
        if (preg_match('/[.@]swiss\.com$/i', rtrim($headers['from'], '> ')) > 0
            || $this->http->XPath->query('//a[normalize-space()="swiss.com"]')->length > 0
        ) {
            $this->providerCode = 'swissair';

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
            if (!is_string($lang) || empty($phrases['emailAddress'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->eq($phrases['emailAddress'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
