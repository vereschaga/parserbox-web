<?php

namespace AwardWallet\Engine\esky\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "esky/it-753391767.eml, esky/it-804290504.eml";
    public $subjects = [
        // en
        'is complete. Here\'s your ticket',
        // es
        'está completa. Aquí está tu pasaje',
        'está completa. Aquí está tu tiquete',
        // pl
        'jest gotowa. Oto Twój bilet',
    ];

    public $detectBody = [
        'en' => [
            'Great to have you with us! This email is your e-ticket.',
            'Great to have you with us! This message contains all the documents you need.',
        ],
        'es' => [
            'Encantados de tenerte con nosotros! Este mensaje es tu pasaje electrónico',
            'Encantados de tenerte con nosotros! Este mensaje contiene todos los documentos que necesitas.',
        ],
        'pl' => [
            'Super, że jesteś z nami! Ten e-mail jest Twoim biletem elektronicznym.',
        ],
    ];

    public $lang = 'en';
    public $providerCode;
    public static $detectProvider = [
        'edestinos'      => [
            'from'       => '',
            'detectBody' => 'eDestinos',
        ],
        'lucky'      => [
            'from'       => '',
            'detectBody' => 'lucky2go',
        ],
        // last
        'esky'      => [
            'from'       => '@esky.',
            'detectBody' => ['eSky.', '@esky.'],
        ],
    ];

    public static $dictionary = [
        "en" => [
            // 'View details' => '',
            // 'Flight booking number' => '',
            'Travel time:' => 'Travel time:',
            // 'Airline:' => '',
            'Flight number:' => 'Flight number:',
            // 'Seat ' => '',
            // 'Total price' => '',
        ],
        "es" => [
            'View details'          => 'Ver detalles',
            'Flight booking number' => 'Número de la reserva de vuelo',
            'Travel time:'          => 'Duración del viaje:',
            'Airline:'              => 'Aerolínea:',
            'Flight number:'        => 'Número de vuelo:',
            // 'Seat ' => '',
            'Total price' => 'Precio total',
        ],
        "pl" => [
            'View details'          => 'Zobacz szczegóły',
            'Flight booking number' => 'Numer rezerwacji lotniczej',
            'Travel time:'          => 'Czas podróży:',
            'Airline:'              => 'Linia lotnicza:',
            'Flight number:'        => 'Numer lotu:',
            // 'Seat ' => '',
            'Total price' => 'Cena całkowita',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        $detectedFrom = false;

        foreach (self::$detectProvider as $code => $dProvider) {
            if (!empty($dProvider['from']) && stripos($headers['from'], $dProvider['from']) !== false) {
                $this->providerCode = $code;
                $detectedFrom = true;

                break;
            }
        }

        if ($detectedFrom === false) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedBodyProv = false;

        foreach (self::$detectProvider as $code => $dProvider) {
            if (!empty($dProvider['detectBody']) && $this->http->XPath->query("//text()[{$this->contains($dProvider['detectBody'])}]")->length > 0) {
                $this->providerCode = $code;
                $detectedBodyProv = true;

                break;
            }
        }

        if ($detectedBodyProv === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]esky(\.[a-z]{2,3}){1,2}$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Travel time:']) && $this->http->XPath->query("//text()[{$this->contains($dict['Travel time:'])}]")->length > 0
                && !empty($dict['Flight number:']) && $this->http->XPath->query("//text()[{$this->contains($dict['Flight number:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }
        $this->Flight($email);

        $email->setProviderCode($this->providerCode);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        // Travel Agency
        $otaConf = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('View details'))}])[1]/preceding::text()[normalize-space()][1]", null, true, "/^(\d{5,})$/");
        $otaConfTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View details'))}]/preceding::text()[normalize-space()][2]");

        $email->ota()
            ->confirmation($otaConf, $otaConfTitle);

        // Flights
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->niceTravellers($this->http->FindNodes("//img[contains(@src, 'icon-men') or contains(@src, 'icon-women') or @alt = 'traveler']/following::text()[normalize-space()][1]")));

        // Price
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<total>\d[\d\.\,\']*)\s*(?<currency>[A-Z]{3})$/", $price, $m)
            || preg_match("/^(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,\']*)\s*$/", $price, $m)
        ) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Travel time:'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            unset($s2);
            $s = $f->addSegment();

            // Airline
            $row = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Airline:'))}\s+(?<aName>.+)\s+{$this->opt($this->t('Flight number:'))}\s+(?<fNumber>\d{1,4}(?:\s*,\s*\d{1,4})*)\s*$/", $row, $m)) {
                $airlines = preg_split('/\s*,\s*/', $m['aName']);
                $flights = preg_split('/\s*,\s*/', $m['fNumber']);

                if (count($airlines) === count($flights)) {
                    if (count($flights) >= 1) {
                        $s->airline()
                            ->name($airlines[0])
                            ->number($flights[0]);
                    }

                    if (count($flights) === 2) {
                        $s2 = $f->addSegment();

                        $s2->airline()
                            ->name($airlines[1])
                            ->number($flights[1]);
                    } elseif (count($flights) > 2) {
                        $f->addSegment();
                        $this->logger->info('2 or more stops');
                    }
                }
            }

            $conf = $this->http->FindSingleNode("following-sibling::tr[2]", $root, true, "/{$this->opt($this->t('Flight booking number'))}\s*([A-Z\d]{5,})$/u");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("preceding-sibling::tr[2]", $root, true,
                    "/{$this->opt($this->t('Flight booking number'))}\s*([A-Z\d]{5,})$/u");
            }
            $s->airline()
                ->confirmation($conf);

            if (isset($s2)) {
                $s2->airline()
                    ->confirmation($conf);
            }

            $row = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root);

            if (preg_match("/^(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+\-\s+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)(?:\s*[[:alpha:]]+:\s*(?<via>.+))?\s*$/u", $row, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                if (!empty($m['via'])) {
                    $vias = preg_split('/\s*,\s+/', $m['via']);

                    if (count($vias) > 2) {
                        $f->addSegment();
                        $this->logger->info('2 or more stops');
                    }
                    $s2 = $s2 ?? $f->addSegment();

                    if (preg_match("/^\s*[A-Z]{3}\s*$/", $m['via'])) {
                        $s->arrival()
                            ->code($m['via']);

                        $s2->departure()
                            ->code($m['via']);
                    } else {
                        $s->arrival()
                            ->noCode()
                            ->name($m['via']);

                        $s2->departure()
                            ->noCode()
                            ->name($m['via']);
                    }

                    $s2->arrival()
                        ->name($m['arrName'])
                        ->code($m['arrCode']);
                } else {
                    $s->arrival()
                        ->name($m['arrName'])
                        ->code($m['arrCode']);
                }
            }

            $row = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(?<depTime>\d+\:\d+)\,\s+(?<depDate>\d+\s*\w+\.?\s*\d{4})\s+(?<arrTime>\d+\:\d+)\,\s+(?<arrDate>\d+\s*\w+\.?\s*\d{4})\s+{$this->opt($this->t('Travel time:'))}\s*(?<duration>\d+(?:h|m).+)$/", $row, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));

                if (isset($s2)) {
                    $s->arrival()
                        ->noDate();

                    $s2->departure()
                        ->noDate();

                    $s2->arrival()
                        ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
                } else {
                    $s->arrival()
                        ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
                    $s->extra()
                        ->duration($m['duration']);
                }
            }

            if ($nodes->length === 1 && !isset($s2)) {
                $seats = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Seat '))}]", null, "/{$this->opt($this->t('Seat '))}\s*(\d+[A-Z])$/"));

                if (count($seats) > 0) {
                    foreach ($seats as $seat) {
                        $pax = $this->niceTravellers($this->http->FindSingleNode("//text()[{$this->starts(preg_replace('/(.+)/', '${1}' . $seat, $this->t('Seat ')))}]/ancestor::table[1]/preceding::text()[normalize-space()][2]"));
                        $s->extra()
                            ->seat($seat, true, true, $pax);
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 29 dic. 2024, 22:05
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\.?\s+(\d{4})\s*[, ]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
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

    private function niceTravellers($names)
    {
        return preg_replace("/^\s*(?:Mrs|Mr|Sr|Sra|Pan|Pani)\.?\s+/", "", $names);
    }
}
