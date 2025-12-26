<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: lufthansa/ChangeOfReservation2, lufthansa/CheckIn3

class UpcomingTrip extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-153837393.eml, lufthansa/it-154150823.eml, lufthansa/it-154337289.eml, lufthansa/it-356262993.eml, lufthansa/it-358958607.eml";
    public $subjects = [
        'Preparations for your upcoming trip to',
    ];

    public $lang = 'en';
    public $provCode;

    public $detectLang = [
        'en'  => ['Your booking code'],
        'de'  => ['Ihr Buchungscode'],
        'fr'  => ['Vos vols'],
        'it'  => ['I suoi voli'],
    ];

    public $providers = [
        'lufthansa' => [
            'from' => '@notifications.business.lufthansagroup.com',

            'body' => [
                'en' => [
                    'your flight with Lufthansa',
                    'Your journey starts soon',
                    'Your booking code',
                ],
            ],
        ],

        'swissair' => [
            'from' => '@noti.swiss.com',

            'body' => [
                'en' => [
                    'We look forward to welcoming you on board SWISS soon',
                    'To start your journey well-prepared',
                    'Your booking code',
                ],
            ],
        ],

        'austrian' => [
            'from' => '@notifications.austrian.com',

            'body' => [
                'en' => [
                    'Your Austrian Airlines Team',
                    'Austrian Airlines App',
                    'Your flights',
                ],
                'de' => [
                    'Ihr Austrian Airlines Team',
                    'Austrian Airlines App',
                    'Ihre Flüge',
                ],
            ],
        ],

        'brussels' => [
            'from' => '@notification.brusselsairlines.com',

            'body' => [
                'fr' => [
                    'Votre équipe Brussels Airlines',
                    'Votre code de réservation',
                    'Brusselairlines.com',
                ],

                'en' => [
                    'Your Brussels Airlines Team',
                    'Your booking code',
                    'Please do not reply to this email.',
                ],

                'it' => [
                    'Brussels Airlines',
                    'Il suo codice di prenotazione',
                    'I suoi voli',
                ],
            ],
        ],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "de" => [
            'Your booking code' => 'Ihr Buchungscode',
            'Dear'              => 'Sehr geehrter',
            'Passenger'         => 'Passagier',
        ],

        "fr" => [
            'Your booking code' => 'Votre code de réservation',
            'Dear'              => 'Chère/cher',
            //'Passenger'         => '',
        ],
        "it" => [
            'Your booking code' => 'Il suo codice di prenotazione',
            'Dear'              => 'Gentile',
            //'Passenger'         => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->providers as $key => $provider) {
            if (isset($headers['from']) && stripos($headers['from'], $provider['from']) !== false) {
                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        foreach ($this->providers as $key => $provider) {
            $this->provCode = $key;

            if (isset($provider['body'][$this->lang][0])) {
                if ($this->http->XPath->query("//text()[{$this->contains($provider['body'][$this->lang][0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($provider['body'][$this->lang][1])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($provider['body'][$this->lang][2])}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->providers as $key => $provider) {
            if (preg_match("/{$this->opt($this->t($provider['from']))}$/u", $from)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $f = $email->add()->flight();

        if (!empty($this->provCode)) {
            $f->setProviderCode($this->provCode);
        }

        $confs = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Your booking code'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, "/^[A-Z\d]{5,10}$/")));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $travellers = array_filter(array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('Dear'))}]", null, "/{$this->opt($this->t('Dear'))}\s+(.+),/")));

        foreach ($travellers as $traveller) {
            if (stripos($traveller, $this->t('Passenger')) == false && $traveller !== $this->t('Passenger') && $traveller !== $this->t('passenger') && $traveller !== $this->t('guest') && $traveller !== $this->t('passeggero')) {
                $f->general()
                    ->traveller($traveller);
            }
        }

        $xpath = "//img[contains(@src, 'airplane-outbound')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::tr[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{2})\s*\d{2,4}/"))
                ->number($this->http->FindSingleNode("./preceding::tr[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

            $date = $this->http->FindSingleNode("./preceding::tr[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root, true, "/\d+\.\d+\.\d{4}/");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./preceding::tr[normalize-space()][2]/preceding::text()[normalize-space()][1]", $root, true, "/\d+\.\d+\.\d{4}/");
            }

            $depTime = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, true, "/\d+\:\d+/");

            if (empty($depTime)) {
                $depTime = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), ':')][1]/descendant::text()[normalize-space()][1]", $root, true, "/\d+\:\d+/");
            }

            $arrTime = $this->http->FindSingleNode("./following::tr[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root, true, "/\d+\:\d+/");

            if (empty($arrTime)) {
                $arrTime = $this->http->FindSingleNode("./following::tr[contains(normalize-space(), ':')][1]/descendant::text()[normalize-space()][2]", $root, true, "/\d+\:\d+/");
            }

            $depCode = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z]{3})\s*$/");
            $arrCode = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root, true, "/^\s*([A-Z]{3})\s*$/");

            if (!empty($depCode) && !empty($arrCode)) {
                $s->departure()
                    ->code($depCode);

                $s->arrival()
                    ->code($arrCode);
            } else {
                $depName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);
                $arrName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root);

                $s->departure()
                    ->name($depName)
                    ->noCode();

                $s->arrival()
                    ->name($arrName)
                    ->noCode();
            }
            $s->departure()
                ->date(strtotime($date . ', ' . $depTime));

            $arrDate = strtotime($date . ', ' . $arrTime);

            // if ($arrDate < $s->getDepDate()) {
            //     $s->arrival()
            //         ->date(strtotime('+1 day', $arrDate));
            // } else {
            $s->arrival()
                    ->date($arrDate);
            // }
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

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
