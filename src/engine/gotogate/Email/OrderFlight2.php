<?php

namespace AwardWallet\Engine\gotogate\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderFlight2 extends \TAccountChecker
{
    public $mailFiles = "gotogate/it-703467981.eml";
    public $subjects = [
        '/Order\s+\d+\-\d+/',
        '/Your booking under\s+\d+\-\d+ has changed/',
    ];

    public static $providers = [
        "gotogate" => [
            "from"       => "gotogate.",
            "detectBody" => ['Gotogate Pty Ltd', 'Gotogate.'],
        ],

        "trip" => [
            "from"       => ".mytrip.com",
            "detectBody" => [
                'Mytrip. ', 'Mytrip / ', ],
        ],
        "fnt" => [
            "from"       => "flightnetwork.com",
            "detectBody" => [
                'Flight Network.',
            ],
        ],
        "flybillet" => [
            "from"       => "@support.flybillet.dk",
            "detectBody" => ["Flybillet."],
        ],
        "supersaver" => [
            "from"       => "@supersaver.nl",
            "detectBody" => ['Supersaver.'],
        ],
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'confFromSubjectRe' => [
                '/Order\s+(?<conf>[A-Z\d\-]{5,})\s+$/i',
                '/Your booking under (?<conf>[A-Z\d\-]{5,})\s*has changed/iu',
            ],
            'As per the below details, the total fee including the fees mentioned above and any tax difference' => [
                'As per the below details, the total fee including the fees mentioned above and any tax difference',
                'Here is what\'s changed in your booking:', ],
            'Follow this link to get to the payment page for your order:' => [
                'Follow this link to get to the payment page for your order:',
                'Click here to request a rebooking with the proposed option', ],
            'Hi '                           => 'Hi ',
            'Regarding your booking number' => ['Regarding your booking number', 'your flight with Customer Reference'],
            '. All Rights Reserved'         => '. All Rights Reserved',
            'Departing:'                    => 'Departing:',
            'at'                            => 'at',
            'Arriving:'                     => 'Arriving:',
        ],
        "de" => [
            'confFromSubjectRe' => [
                '/Order\s+(?<conf>[A-Z\d\-]{5,})\s*$/i',
                '/Your booking under (?<conf>[A-Z\d\-]{5,})\s*has changed/iu',
            ],
            'As per the below details, the total fee including the fees mentioned above and any tax difference'
                => 'Folgendes hat sich an Ihrer Buchung geändert',
            'Follow this link to get to the payment page for your order:'
                                            => 'Klicken Sie hier, um eine Umbuchung mit der vorgeschlagenen Option anzufordern',
            'Hi '                           => 'Hallo ',
            'Regarding your booking number' => ['dass Ihr Flug mit der Kundenreferenz', 'dass Ihr Flug mit der Auftragsnummer'],
            '. All Rights Reserved'         => '. Alle Rechte vorbehalten',
            'Departing:'                    => 'Departing:',
            'at'                            => 'at',
            'Arriving:'                     => 'Arriving:',
        ],
        "fr" => [
            'confFromSubjectRe' => [
                '/Order\s+(?<conf>[A-Z\d\-]{5,})\s+$/i',
                '/Your booking under (?<conf>[A-Z\d\-]{5,})\s*has changed/iu',
            ],
            'As per the below details, the total fee including the fees mentioned above and any tax difference'
                => 'Voici les modifications de votre réservation',
            'Follow this link to get to the payment page for your order:'
                                            => 'Cliquez ici pour demander une modification de votre réservation en utilisant l’option proposée',
            'Hi '                           => 'Bonjour ',
            'Regarding your booking number' => 'vol associé au numéro de commande',
            '. All Rights Reserved'         => '. Tous droits réservés',
            'Departing:'                    => 'Departing:',
            'at'                            => 'at',
            'Arriving:'                     => 'Arriving:',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $provider) {
            if (isset($headers['from']) && stripos($headers['from'], $provider['from']) !== false) {
                foreach ($this->subjects as $subject) {
                    if (preg_match($subject, $headers['subject'])) {
                        return true;
                    }
                }
            }
        }

        // if (isset($headers['from']) && stripos($headers['from'], '@flightsonbooking.gotogate.support') !== false) {
        //     foreach ($this->subjects as $subject) {
        //         if (preg_match($subject, $headers['subject'])) {
        //             return true;
        //         }
        //     }
        // }

        return false;
    }

    public function detectProvider()
    {
        foreach (self::$providers as $prov => $provider) {
            foreach ($provider['detectBody'] as $provKey) {
                if ($this->http->XPath->query("//node()[{$this->contains($provKey)}]")->length > 0) {
                    return $prov;
                }
            }
        }

        return null;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (!$this->detectProvider()) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['As per the below details, the total fee including the fees mentioned above and any tax difference'])
                && !empty($dict['Follow this link to get to the payment page for your order:'])
                && !empty($dict['Departing:'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['As per the below details, the total fee including the fees mentioned above and any tax difference'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Follow this link to get to the payment page for your order:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Departing:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flightsonbooking\.gotogate\.support$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['As per the below details, the total fee including the fees mentioned above and any tax difference'])
                && !empty($dict['Follow this link to get to the payment page for your order:'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['As per the below details, the total fee including the fees mentioned above and any tax difference'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Follow this link to get to the payment page for your order:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        // Travel Agency
        $email->obtainTravelAgency();

        foreach ((array) $this->t('confFromSubjectRe') as $re) {
            if (strpos($re, '/') === 0 && preg_match($re, $parser->getSubject(), $m)
                && !empty($m['conf'])
            ) {
                $email->ota()
                    ->confirmation($m['conf']);
            }
        }
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Regarding your booking number'))}]",
            null, true, "/{$this->opt($this->t('Regarding your booking number'))}\s*([A-Z\d\-]{6,})\b/");

        if (in_array($conf, array_column($email->obtainTravelAgency()->getConfirmationNumbers(), 0))) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($conf);
        }

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]",
                null, true, "/{$this->opt($this->t('Hi '))}\s*(\D+)(?:\.|\,|\!)/"));

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Departing:'))}]");
        $year = $this->http->FindSingleNode("//text()[{$this->contains($this->t('. All Rights Reserved'))}]", null, true, "/\s(20\d{2})\s+\D*{$this->opt($this->t('. All Rights Reserved'))}/");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $dateFlight = '';

            $flightInfo = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<date>\w+\,\s*\d+\s*\w+)[\s\-]*(?<airlineName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<flightNumber>\d{1,4})$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);

                $dateFlight = $m['date'];
            }

            // Departure
            $depInfo = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Departing:'))}\s*(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s*{$this->opt($this->t('at'))}\s*(?<depTime>[\d\:]+)\s*$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                if (!empty($dateFlight) && !empty($year)) {
                    $s->departure()
                        ->date($this->normalizeDate($dateFlight . ' ' . $year . ', ' . $m['depTime']));
                }
            }

            // Arrival
            $arrInfo = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Arriving:'))}\s*(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s*{$this->opt($this->t('at'))}\s*(?<arrTime>[\d\:]+)\s*$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);

                if (!empty($dateFlight) && !empty($year)) {
                    $s->arrival()
                        ->date($this->normalizeDate($dateFlight . ' ' . $year . ', ' . $m['arrTime']));
                }
            }
        }

        if ($nodes->length === 0) {
            $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Departing:'))}]");
            $segments = $this->split("/ (\w+, \w+ \w+ - [A-Z\d]{3,6})/", "\n\n " . $text);

            foreach ($segments as $sText) {
                $s = $f->addSegment();

                $flightInfo = $depInfo = $arrInfo = null;

                if (preg_match("/^\s*(\S.+?)\s*({$this->opt($this->t('Departing:'))}.+?)\s*({$this->opt($this->t('Arriving:'))}.+?)\s*$/s", $sText, $m)) {
                    $flightInfo = $m[1];
                    $depInfo = $m[2];
                    $arrInfo = $m[3];
                }

                $dateFlight = '';

                if (preg_match("/^\s*(?<date>\w+\,\s*\d+\s*\w+)[\s\-]*(?<airlineName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<flightNumber>\d{1,4})\s*$/", $flightInfo, $m)) {
                    $s->airline()
                        ->name($m['airlineName'])
                        ->number($m['flightNumber']);

                    $dateFlight = $m['date'];
                }

                // Departure
                if (preg_match("/^\s*{$this->opt($this->t('Departing:'))}\s*(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s*{$this->opt($this->t('at'))}\s*(?<depTime>[\d\:]+)\s*$/", $depInfo, $m)) {
                    $s->departure()
                        ->name($m['depName'])
                        ->code($m['depCode']);

                    if (!empty($dateFlight) && !empty($year)) {
                        $s->departure()
                            ->date($this->normalizeDate($dateFlight . ' ' . $year . ', ' . $m['depTime']));
                    }
                }

                // Arrival
                if (preg_match("/^\s*{$this->opt($this->t('Arriving:'))}\s*(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s*{$this->opt($this->t('at'))}\s*(?<arrTime>[\d\:]+)\s*$/", $arrInfo, $m)) {
                    $s->arrival()
                        ->name($m['arrName'])
                        ->code($m['arrCode']);

                    if (!empty($dateFlight) && !empty($year)) {
                        $s->arrival()
                            ->date($this->normalizeDate($dateFlight . ' ' . $year . ', ' . $m['arrTime']));
                    }
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Fri, 31 Jan 2025, 09:50
            "/^\s*([[:alpha:]]+)\s*,\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/u",
        ];
        $out = [
            "$1, $2 $3 $4, $5",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'en'));

            if ($weeknum === null) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            }
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
