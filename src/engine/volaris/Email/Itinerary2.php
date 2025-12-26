<?php

namespace AwardWallet\Engine\volaris\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary2 extends \TAccountChecker
{
    public $mailFiles = "volaris/it-498275130.eml, volaris/it-502722102.eml, volaris/it-506756375.eml, volaris/it-646104987.eml, volaris/it-654227839.eml, volaris/it-655774878.eml, volaris/it-655875957.eml, volaris/it-656525085.eml";
    public $subjects = [
        'My Itinerary - Volaris reservations',
        'Mi itinerario Volaris',
    ];

    public $lang = 'en';
    public $date = 'en';

    public $detectLang = [
        "en" => ['Flight', 'DEPARTURE'],
        "es" => ['Vuelo', 'SALIDA'],
    ];

    public static $dictionary = [
        "en" => [
            'DEPARTURE'                      => ['DEPARTURE', 'ARRIVAL'],
            'Flight:'                        => ['Flight:', 'Flight'],
        ],
        "es" => [
            'THIS IS NOT YOUR BOARDING PASS' => 'ESTE NO ES TU PASE DE ABORDAR',
            'CHECK OUR SEAT AND BAGGAGE'     => 'EQUIPAJES Y ASIENTOS',
            'Passenger '                     => 'Pasajero ',
            'reservation code:'              => 'Código de reservación',
            'TOTAL:'                         => 'TOTAL:',
            'DEPARTURE'                      => ['LLEGADA', 'SALIDA'],
            'FARE:'                          => 'TARIFA:',
            'SEATS'                          => 'ASIENTOS',
            'Flight:'                        => 'Vuelo:',
            'Operated by:'                   => 'Operado por:',
            'Stops'                          => 'Escalas',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@volaris.com') !== false) {
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
        $this->assignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'volaris.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('THIS IS NOT YOUR BOARDING PASS'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('CHECK OUR SEAT AND BAGGAGE'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]volaris.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger '))}]/preceding::text()[normalize-space()][1]"));

        $confs = array_filter(array_unique($this->http->FindNodes("//text()[{$this->contains($this->t('reservation code:'))}]/following::text()[normalize-space()][not(contains(normalize-space(), 'Volaris'))][1]", null, "/^([A-Z\d]{6})$/")));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^\D+(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})$/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        if ($this->http->XPath->query("//img[contains(@src, 'Arrow')]/following::text()[normalize-space()][2][{$this->contains($this->t('Stops'))}]")->length > 0) {
            $this->ParseSegment1($f);
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('DEPARTURE'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('reservation code:'))}]/ancestor::tr[1]/following::tr[normalize-space()][2]/descendant::img[contains(@src, 'Arrow')]")) {
            $this->ParseSegment3($f);
        } else {
            //it-498275130.eml
            $this->ParseSegment2($f);
        }
    }

    public function ParseSegment1(Flight $f)
    {
        $this->logger->debug(__METHOD__);

        // Codes
        $segmentSeats = [];
        $seatsText = implode("\n", $this->http->FindNodes("(//text()[{$this->eq($this->t('SEATS'))}])[1]/following::tr[normalize-space()][1]//text()[normalize-space()]"));

        if (preg_match_all("/^\s*([A-Z]{3}) - ([A-Z]{3}):/m", $seatsText, $m)) {
            foreach ($m[0] as $i => $v) {
                $segmentSeats[] = ['d' => $m[1][$i], 'a' => $m[2][$i]];
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('DEPARTURE'))}]");

        if ($nodes->length !== count($segmentSeats)) {
            $segmentSeats = [];
        }

        // Codes
        $airportCodes = [];
        $codesNodes = $this->http->XPath->query("//img[contains(@src, 'Arrow') or contains(@src, 'WRD')]/ancestor::tr[count(*[normalize-space()]) > 1][1][*[2][.//img]][following::tr[1][count(*[normalize-space()]) = 2]]");

        foreach ($codesNodes as $cRoot) {
            $codes = $this->http->FindNodes("*[normalize-space()]", $cRoot);
            $names = $this->http->FindNodes("following::tr[1]/*[normalize-space()]/descendant::text()[normalize-space()][1]", $cRoot);

            if (count($codes) === 3 && preg_match("/^\s*\d\s*[[:alpha:]]+\s*$/", $codes[1])) {
                $codes[1] = $codes[2];
                unset($codes[2]);
            }

            if (count($codes) === 2 && count($names) === 2 && preg_match("/^\s*[A-Z]{3}\s*$/", $codes[0]) && preg_match("/^\s*[A-Z]{3}\s*$/", $codes[1])) {
                $airportCodes[$names[0]] = $codes[0];
                $airportCodes[$names[1]] = $codes[1];
            }
        }

        foreach ($nodes as $segI => $root) {
            $s = $f->addSegment();
            $airInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[{$this->contains($this->t('Flight:'))}][1]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Flight:'))}\s*[*]?{$this->opt($this->t('Operated by:'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])).+\s(?<fNumber>\d{1,4})/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $depName = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][2]/descendant::td[normalize-space()][1]", $root);
                $arrName = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][2]/descendant::td[normalize-space()][2]", $root);

                $depCode = null;
                $s->departure()
                    ->name($depName);

                if (!empty($depName) && !empty($airportCodes[$depName])) {
                    $depCode = $airportCodes[$depName];
                }

                $arrCode = null;
                $s->arrival()
                    ->name($arrName);

                if (!empty($arrName) && !empty($airportCodes[$arrName])) {
                    $arrCode = $airportCodes[$arrName];
                }

                if (!empty($depCode) && isset($segmentSeats[$segI]) && $segmentSeats[$segI]['d'] !== $depCode) {
                    $segmentSeats = [];
                }

                if (!empty($arrCode) && isset($segmentSeats[$segI]) && $segmentSeats[$segI]['a'] !== $arrCode) {
                    $segmentSeats = [];
                }

                if (empty($depCode)) {
                    $depCode = $segmentSeats[$segI]['d'];
                }

                if (empty($arrCode)) {
                    $arrCode = $segmentSeats[$segI]['a'];
                }

                if (!empty($depCode) && !empty($arrCode)) {
                    $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('SEATS'))}]/following::text()[{$this->eq($depCode . ' - ' . $arrCode . ':')}]/following::text()[normalize-space()][1]", null, "/^(\d+[A-Z])$/"));

                    if (empty($seats)) {
                        $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('SEATS'))}]/following::text()[{$this->starts($depCode . ' - ' . $arrCode . ':')}]",
                            null, "/^\s*{$depCode} - {$arrCode}:\s*(\d+[A-Z])\s*$/"));
                    }

                    if (!empty($seats)) {
                        foreach ($seats as $seat) {
                            $pax = $this->http->FindSingleNode("//text()[{$this->contains($depCode . ' - ' . $arrCode . ': ' . $seat)}]/preceding::text()[{$this->starts($this->t('Passenger '))}][1]/preceding::text()[normalize-space()][1]");

                            if (!empty($pax)) {
                                $s->addSeat($seat, true, true, $pax);
                            } else {
                                $s->addSeat($seat);
                            }
                        }
                    }
                }

                if (!empty($depCode)) {
                    $s->departure()
                        ->code($depCode);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (!empty($arrCode)) {
                    $s->arrival()
                        ->code($arrCode);
                } else {
                    $s->arrival()
                            ->noCode();
                }

                if (preg_match("/^\s*(.+?) (T[\dA-Z]{1,3})\s*$/", $s->getDepName(), $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->terminal($m[2]);
                }

                if (preg_match("/^\s*(.+?) (T[\dA-Z]{1,3})\s*$/", $s->getArrName(), $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->terminal($m[2]);
                }

                $depTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][1]", $root);
                $arrTime = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::td[normalize-space()][2]", $root);

                $dateTemp = $this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/\s(\d+\s*\w+\s*\d{4})/");

                if (!empty($dateTemp)) {
                    $this->date = $dateTemp;
                }

                if (!empty($this->date) && !empty($depTime)) {
                    $s->departure()
                        ->date($this->normalizeDate($this->date . ' ' . $depTime));
                }

                if (!empty($this->date) && !empty($arrTime)) {
                    $s->arrival()
                        ->date($this->normalizeDate($this->date . ' ' . $arrTime));
                }
            }

            if ($s->getDepCode() === 'TJX' || $s->getArrCode() === 'TJX') {
                $f->removeSegment($s);
            }
        }
    }

    public function ParseSegment2(Flight $f)
    {
        $this->logger->debug(__METHOD__);
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $seg = $this->http->FindSingleNode(".", $root);

            if (preg_match("/{$this->opt($this->t('DEPARTURE'))}\s*\w+\,\s*(?<date>\d+\s*\w+\s*\d{4})\s*\-\s*[\d\:]+\s*A?P?M?\D*(?<depTime>[\d\:]+\s*A?P?M)\s*(?<arrTime>[\d\:]+\s*A?P?M).+{$this->opt($this->t('Flight:'))}\s*{$this->opt($this->t('Operated by:'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])).+\D\s*(?<fNumber>\d{1,4})$/u", $seg, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->name($this->http->FindSingleNode("./descendant::img[1]/ancestor::tr[2]/following::tr[1]/descendant::td[normalize-space()][1]", $root))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['depTime']));

                $depCode = $this->http->FindSingleNode("./preceding::img[contains(@src, 'Arrow') or contains(@src, 'cid')][1]/preceding::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z]{3})\s*$/");

                if (!empty($depCode)) {
                    $s->departure()
                        ->code($depCode);
                } else {
                    $s->departure()
                        ->noCode();
                }

                $s->arrival()
                    ->name($this->http->FindSingleNode("./descendant::img[1]/ancestor::tr[2]/following::tr[1]/descendant::td[normalize-space()][2]", $root))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['arrTime']));

                $arrCode = $this->http->FindSingleNode("./preceding::img[contains(@src, 'Arrow') or contains(@src, 'cid')][1]/following::text()[normalize-space()][1]", $root, true, "/^\s*([A-Z]{3})\s*$/");

                if (!empty($arrCode)) {
                    $s->arrival()
                        ->code($arrCode);
                } else {
                    $s->arrival()
                        ->noCode();
                }

                $cabin = $this->http->FindSingleNode("//text()[{$this->starts($this->t('FARE:'))}]", null, true, "/{$this->opt($this->t('FARE:'))}\s*(.+)/");

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }

                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('SEATS'))}]/following::text()[{$this->eq($s->getDepCode() . ' - ' . $s->getArrCode() . ':')}]/following::text()[normalize-space()][1]", null, "/^(\d+[A-Z])$/"));

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }
            }
        }
    }

    public function ParseSegment3(Flight $f)
    {
        $this->logger->debug(__METHOD__);
        $xpath = "//text()[{$this->eq($this->t('Flight:'))}]/preceding::img[contains(@src, 'Arrow')][1]/ancestor::tr[normalize-space()][1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airInfo = implode(' ', $this->http->FindNodes("./following::tr[normalize-space()][position() < 7][{$this->starts($this->t('Flight:'))}][1]//text()[normalize-space()]", $root));

            if (preg_match("/{$this->opt($this->t('Flight:'))}\s*\*?{$this->opt($this->t('Operated by:'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\b.+\s(?<fNumber>\d{1,5})\s*$/u", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $depInfo = implode(" ", $this->http->FindNodes("td[1]/descendant::text()[normalize-space()]", $root));
                $name = null;

                if (preg_match("/^(?<depCode>[A-Z]{3})\b\s*(?<depName>.+)/", $depInfo, $m)) {
                    $s->departure()
                        ->code($m['depCode']);
                    $name = $m['depName'];
                } elseif (preg_match("/^(?<depCode>[A-Z]{3})\s*$/", $depInfo, $m)) {
                    $s->departure()
                        ->code($m['depCode']);
                    $name = implode(" ", $this->http->FindNodes("following-sibling::*[normalize-space()][1]/*[normalize-space()][1]//text()[normalize-space()]", $root));

                    if (preg_match("/\d+:\d{2}/", $name)) {
                        $name = null;
                    }
                }

                if (preg_match("/^\s*(.+?) (T[\dA-Z]{1,3})\s*$/", $name, $m)) {
                    $name = $m[1];
                    $s->departure()
                        ->terminal($m[2]);
                }

                if (!empty($name)) {
                    $s->departure()
                        ->name($name);
                }

                $arrInfo = implode(" ", $this->http->FindNodes("td[normalize-space()][2]/descendant::text()[normalize-space()]", $root));
                $name = null;

                if (preg_match("/^(?<arrCode>[A-Z]{3})\b\s*(?<arrName>.+)/", $arrInfo, $m)) {
                    $s->arrival()
                        ->code($m['arrCode']);
                    $name = $m['arrName'];
                } elseif (preg_match("/^(?<arrCode>[A-Z]{3})\s*$/", $arrInfo, $m)) {
                    $s->arrival()
                        ->code($m['arrCode']);
                    $name = implode(" ", $this->http->FindNodes("following-sibling::*[normalize-space()][1]/*[normalize-space()][2]//text()[normalize-space()]", $root));

                    if (preg_match("/\d+:\d{2}/", $name)) {
                        $name = null;
                    }
                }

                if (preg_match("/^\s*(.+?) (T[\dA-Z]{1,3})\s*$/", $name, $m)) {
                    $name = $m[1];
                    $s->arrival()
                        ->terminal($m[2]);
                }

                if (!empty($name)) {
                    $s->arrival()
                        ->name($name);
                }

                $depTime = $this->http->FindSingleNode("following::tr[normalize-space()][1]/td[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/i");
                $arrTime = $this->http->FindSingleNode("following::tr[normalize-space()][1]/td[normalize-space()][2]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/i");

                if (empty($depTime) && empty($arrTime)) {
                    $depTime = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2]/td[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/i");
                    $arrTime = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2]/td[normalize-space()][2]", $root, true, "/^\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/i");
                }

                $dateTemp = $this->http->FindSingleNode("preceding::tr[1]", $root, true, "/\s(\d+\s*\w+\s*\d{4})/");

                if (!empty($dateTemp)) {
                    $this->date = preg_replace("/(\s+)/", " ", $dateTemp);
                } elseif (empty($dateTemp) && !empty($this->http->FindSingleNode("preceding::tr[1]", $root, true, "/\b\d{4}\b/"))) {
                    $this->date = null;
                }

                if (!empty($this->date) && !empty($depTime)) {
                    $s->departure()
                        ->date($this->normalizeDate($this->date . ' ' . $depTime));
                }

                if (!empty($this->date) && !empty($arrTime)) {
                    $s->arrival()
                        ->date($this->normalizeDate($this->date . ' ' . $arrTime));
                }

                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('SEATS'))}]/following::text()[{$this->eq($s->getDepCode() . ' - ' . $s->getArrCode() . ':')}]/following::text()[normalize-space()][1]", null, "/^(\d+[A-Z])$/"));

                if (empty($seats)) {
                    $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('SEATS'))}]/following::text()[{$this->starts($s->getDepCode() . ' - ' . $s->getArrCode() . ':')}]", null, "/^\s*{$s->getDepCode()} - {$s->getArrCode()} *: *(\d{1,3}[A-Z])\s*$/"));
                }

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseFlight($email);

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

    public function assignLang()
    {
        foreach ($this->detectLang as $lang => $langArray) {
            foreach ($langArray as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str in  = '.print_r( $str,true));
        $in = [
            // Dimanche 27 août 2023
            "#^(\d{1,2})\s+([[:alpha:]]+)\.?\s*(\d{4})\s*\,\s*([\d\:]+\s*A?P?M?)$#su",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->debug('$str out = '.print_r( $str,true));

        return strtotime($str);
    }
}
