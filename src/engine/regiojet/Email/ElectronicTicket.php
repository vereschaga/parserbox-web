<?php

namespace AwardWallet\Engine\regiojet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ElectronicTicket extends \TAccountChecker
{
    public $mailFiles = "regiojet/it-489364155.eml, regiojet/it-492480692.eml, regiojet/it-498162943.eml, regiojet/it-499465443.eml, regiojet/it-502169262.eml, regiojet/it-505556201.eml, regiojet/it-516729610.eml";

    public $operators = [
        'train' => [
            'RJ - RegioJet a.s.',
            'Ukz - Ukrzaliznycja',
            'OBB - Österreichische Bundesbahnen',
        ],
        'bus' => [
            'SA - STUDENT AGENCY k.s.',
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Electronic ticket' => 'Electronic ticket',
            // 'Passengers:' => '',
            'Station/Transfer' => 'Station/Transfer',
            // 'Connection' => '',
            // 'Price:' => '',
            // 'Operators:' => '',
        ],
        'cs' => [
            'Electronic ticket' => 'Elektronická jízdenka č.',
            'Passengers:'       => 'Cestující:',
            'Station/Transfer'  => 'Zastávka/Přestup',
            'Connection'        => 'Spoj',
            'Price:'            => 'Cena:',
            'Operators:'        => 'Dopravci:',
        ],
        'de' => [
            'Electronic ticket' => 'Elektronischer Beförderungsausweis',
            'Passengers:'       => 'Fahrgäste:',
            'Station/Transfer'  => 'Haltestelle/Transfer',
            'Connection'        => 'Bus/Zug',
            'Price:'            => 'Preis:',
            'Operators:'        => 'Beförderer:',
        ],
        'sk' => [
            'Electronic ticket' => 'Elektronický lístok',
            // 'Passengers:' => ':',
            'Station/Transfer' => 'Zastávka/Prestup',
            'Connection'       => 'Spoj',
            'Price:'           => 'Cena:',
            'Operators:'       => 'Dopravcovia:',
        ],
        'uk' => [
            'Electronic ticket' => 'Електронний квиток №',
            'Passengers:'       => 'Пасажири:',
            'Station/Transfer'  => 'Зупинка/пересадка',
            'Connection'        => 'Автобус/потяг',
            'Price:'            => 'Ціна:',
            'Operators:'        => 'Перевізники:',
        ],
    ];

    private $detectFrom = "@regiojet.cz";
    private $detectSubject = [
        // en
        'RegioJet: Electronic ticket',
        // cs
        'RegioJet: Elektronická jízdenka',
        // de
        'RegioJet: Elektronischer Beförderungsausweis',
        // sk
        'RegioJet: Elektronický lístok',
        // uk
        'RegioJet: Електронний квиток',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]@regiojet\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'RegioJet') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['/regiojet.com/', '/regiojet.cz/'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['www.regiojet.cz'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Electronic ticket"], $dict["Station/Transfer"])) {
                if ($this->http->XPath->query("//*[{$this->starts($dict['Electronic ticket'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->eq($dict['Station/Transfer'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Segments
        $allOperators = $this->http->FindNodes("//text()[{$this->eq($this->t('Operators:'))}]/following::text()[normalize-space()][1]/ancestor::ul/li",
            null, "/^\s*(.+?), /");
        $operators = [];

        foreach ($allOperators as $name) {
            if (in_array($name, $this->operators['train'])) {
                $operators[trim(strstr($name, '-', true))] = 'train';
            } elseif (in_array($name, $this->operators['bus'])) {
                $operators[trim(strstr($name, '-', true))] = 'bus';
            }
        }
        $segments = [];

        $xpath = "//tr[*[2][{$this->eq($this->t('Station/Transfer'))}] and *[6][{$this->eq($this->t('Connection'))}]]/following-sibling::tr[normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $table = $this->http->FindNodes("*", $root);

            if (!empty($table[2])) {
                if (isset($seg) && empty($seg['arrTime'])) {
                    $seg = array_merge($seg, [
                        'arrDate'    => empty($table[0]) ? null : $table[0],
                        'arrStation' => $table[1],
                        'arrTime'    => $table[2],
                    ]);
                } else {
                    $seg = [];
                }
                $segments[] = $seg;
                $seg = [];
            }

            if (!empty($table[3])) {
                $seg = [
                    'depDate'    => $table[0],
                    'depStation' => $table[1],
                    'depTime'    => $table[3],
                    'arrDate'    => null,
                    'arrStation' => null,
                    'arrTime'    => null,
                    'info'       => $table[5],
                    'seats'      => $table[6],
                    'type'       => null,
                ];

                // Type
                $opCode = $this->re("/\(([[:alpha:]]+)\s*(?:,|\))/", $seg['info']);

                if (isset($operators[$opCode])) {
                    $seg['type'] = $operators[$opCode];
                } elseif (preg_match("/^\s*\d+\s*\\/\s*\d+\s*(,|$)/", $seg['seats'])) {
                    $seg['type'] = 'train';
                } elseif (preg_match("/^\s*\d+\s*(,|$)/", $seg['seats'])) {
                    $seg['type'] = 'bus';
                }
            }
        }

        $currentDate = null;

        foreach ($segments as $i => $seg) {
            if (empty($seg['type'])) {
                $this->logger->debug('not detect Segment Type');
                $email->add()->flight();

                break;
            } elseif ($seg['type'] == 'train') {
                if (!isset($trains)) {
                    $trains = $email->add()->train();
                }
                $s = $trains->addSegment();

                // Extra
                $seats = array_filter(preg_split("/\s*,\s*/", $seg['seats']));
                $car = $seat = [];

                foreach ($seats as $stext) {
                    $car[] = $this->re('/^\s*(\d+)\s*\\/\s*\d+\s*$/', $stext);
                    $seat[] = $this->re('/^\s*\d+\s*\\/\s*(\d+)\s*$/', $stext);
                }

                if (!empty(array_filter($car)) && !empty(array_filter($seat))) {
                    $s->extra()
                        ->car(implode(', ', array_unique($car)))
                        ->seats($seat);
                }
            } elseif ($seg['type'] == 'bus') {
                if (!isset($buses)) {
                    $buses = $email->add()->bus();
                }
                $s = $buses->addSegment();

                // Extra
                $seats = array_filter(preg_split("/\s*,\s*/", $seg['seats']));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            if (empty($seg['depDate'])) {
                $seg['depDate'] = $currentDate;
            }

            // Departure
            $s->departure()
                ->name($seg['depStation'])
                ->geoTip('europe')
                ->date(!empty($seg['depDate'] && !empty($seg['depTime'])) ? $this->normalizeDate($seg['depDate'] . ', ' . $seg['depTime']) : null)
            ;

            $seg['arrDate'] = $seg['arrDate'] ?? $seg['depDate'];

            // Arrival
            $s->arrival()
                ->name($seg['arrStation'])
                ->geoTip('europe')
                ->date(!empty($seg['depDate'] && !empty($seg['depTime'])) ? $this->normalizeDate($seg['arrDate'] . ', ' . $seg['arrTime']) : null)
            ;

            if (!empty($s->getArrDate())) {
                $currentDate = date('D d.m.Y', $s->getArrDate());
            } else {
                $currentDate = null;
            }

            // Extra
            $number = $this->re("/\([[:alpha:]]+\s*,\s*[[:alpha:]]+\s+(\d.+)\)/", $seg['info']);

            if (!empty($number)) {
                $s->extra()
                    ->number($number);
            } elseif ($seg['type'] == 'train') {
                $s->extra()
                    ->noNumber();
            }
        }

        // General
        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Electronic ticket'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");
        $confName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Electronic ticket'))}]");
        $travellers = array_filter(preg_split('/\s*,\s*/', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers:'))}]/following::text()[normalize-space()][1]")));

        if (isset($trains)) {
            $trains->general()
                ->confirmation($confNo, $confName);

            if (!empty($travellers)) {
                $trains->general()
                    ->travellers($travellers, true);
            }
        }

        if (isset($buses)) {
            $buses->general()
                ->confirmation($confNo, $confName);

            if (!empty($travellers)) {
                $buses->general()
                    ->travellers($travellers, true);
            }
        }

        // Price
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $price, $m)
        ) {
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $email->price()
                ->total($m['amount'])
                ->currency($m['currency'])
            ;
        } else {
            $email->price()
                ->total(null);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // Sat 8/19/23, 8:30 PM
            '/^\s*[[:alpha:]]+\s+(\d{1,2})\\/(\d{1,2})\\/(\d{2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // pá 01.09.23, 6:12
            // Di. 05.09.23, 06:12
            '/^\s*[[:alpha:]]+[.]?\s+(\d{1,2})\. ?(\d{1,2})\. ?(\d{2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // po 11. 9. 2023, 5:57
            '/^\s*[[:alpha:]]+[.]?\s+(\d{1,2})\. ?(\d{1,2})\. ?(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2.$1.20$3, $4',
            '$1.$2.20$3, $4',
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
