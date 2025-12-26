<?php

namespace AwardWallet\Engine\perfectdrive\Email;

class Confirmation2 extends \TAccountCheckerExtended
{
    public $mailFiles = "perfectdrive/it-1710936.eml, perfectdrive/it-1710937.eml, perfectdrive/it-2863178.eml, perfectdrive/it-3925905.eml";

    public static $dictionary = [
        'ru' => [
            'Return Location'                => ['Локация возврата авто'],
            'Opening hours on day of return' => ['Время работы в день возврата авто'],
            'Car Group'                      => 'Группа авто',
            'e.g.'                           => 'напр.',
            //            'or similar' => '',
        ],
        'en' => [
            'Return Location'                => ['Return Location'],
            'Opening hours on day of return' => ['Opening hours on day of return'],
            'Car Group'                      => ['Car Group', 'Vehicle Group'],
        ],
    ];

    private $subjects = [
        'ru' => ['Подтверждение резервации'],
        'en' => ['Booking confirmation', 'Pay on Arrival Booking'],
    ];

    private $lang = "en";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return trim(nice(re('#(?:Номер\s+Вашей\s+резервации|your\s+reference\s+is|Reservation\s+No):?\s+(.*)[\.]*#')), " .");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        $arr = [
                            'ru' => ['Pickup' => 'Локация проката:', 'Dropoff' => 'Локация возврата авто:'],
                            'en' => ['Pickup' => 'Rental Location:', 'Dropoff' => 'Return Location:'],
                        ];

                        foreach ($arr[$this->lang] as $key => $value) {
                            $subj = cell($value, +1);

                            if (cell($value, 0, +1) === '') {
                                $subj .= "\n" . cell($value, +1, +1);
                            }

                            if (preg_match('/^\s*(.{3,}?)\s*(?:Телефон|Telephone)[:\s]+\(?\s*([+\d][-. \d)(]{5,}[\d])\s*(?:\)|$)/', $subj, $m)) {
                                $res["${key}Location"] = nice($m[1]);
                                $res["${key}Phone"] = nice($m[2]);
                            }
                        }

                        return $res;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $months = [
                            'Январь'   => 'January',
                            'Февраль'  => 'February',
                            'Март'     => 'March',
                            'Апрель'   => 'April',
                            'Май'      => 'May',
                            'Июнь'     => 'June',
                            'Июль'     => 'July',
                            'Август'   => 'August',
                            'Сентябрь' => 'September',
                            'Октябрь'  => 'October',
                            'Ноябрь'   => 'November',
                            'Декабрь'  => 'December',
                        ];

                        $arr = [
                            'ru' => [
                                'Pickup'  => ['Дата проката:', 'Время работы в день выдачи авто:'],
                                'Dropoff' => ['Дата возврата авто:', 'Время работы в день возврата авто:'],
                            ],
                            'en' => [
                                'Pickup'  => [['Date of Rental:', 'Date Of Rental:'], 'Opening hours on day of pickup:'],
                                'Dropoff' => [['Date of Return:', 'Date Of Return:'], 'Opening hours on day of return:'],
                            ],
                        ];

                        foreach ($arr[$this->lang] as $key => $value) {
                            $subj = cell($value[0], +1);

                            if (preg_match('#(\d+)\s+(\w+)\s+(\d+)\s+(\d+:\d+)#u', $subj, $m)) {
                                $res["${key}Datetime"] = strtotime($m[1] . ' ' . strtr($m[2], $months) . ' ' . $m[3] . ', ' . $m[4]);
                            }

                            $res["${key}Hours"] = nice(trim(cell($value[1], +1), '.'));
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $carGroup = cell($this->t('Car Group'), +1);

                        if (preg_match("/^(?<type>.+?)\s+\({$this->opt($this->t('e.g.'))}\s*(?<model>[^)(]+)\)$/", $carGroup, $m)
                            || preg_match("/^(?<type>.+?)\s+-\s+{$this->opt($this->t('e.g.'))}\s*(?<model>.+)$/", $carGroup, $m)
                            || preg_match("/^(?<type>.+?)\s+\(\s*(?<model>.+?)\s+{$this->opt($this->t('or similar'))}\s*\)$/", $carGroup, $m)
                            || preg_match("/^(?<model>.+\s+{$this->opt($this->t('or similar'))})[.\s]*\((?<type>[^)(]+)\)$/", $carGroup, $m)
                        ) {
                            // Group B (e.g. Ford Fiesta)    |    Group O - e.g. Hyundai i20 Auto or similar    |    Group B (Nissan Sunny or similar)    |    Kia Sportage or similar... (Car group F)
                            return ['CarType' => $m['type'], 'CarModel' => $m['model']];
                        }

                        return null;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $arr = [
                            'ru' => 'Имя:',
                            'en' => 'Name:',
                        ];

                        return cell($arr[$this->lang], +1);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $arr = [
                            'ru' => 'Сумма:',
                            'en' => 'Amount:',
                        ];
                        $subj = re("#{$arr[$this->lang]}\s+(.*)#");

                        if ($subj) {
                            return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                        }
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Budget Rent-a-Car') !== false
            || preg_match('/@budget[^@]*\.[a-z]{2,}/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a['
                . 'contains(@href,"www.budget.ua/")'
                . ' or contains(@href,"www.budgetinternational.com/")'
                . ' or contains(@href,"www.budget.is/")'
                . ']')->length === 0
            && $this->http->XPath->query('//node()['
                . 'contains(normalize-space(),"Thank you for renting a car with Budget Car Rental")'
                . ' or contains(normalize-space(),"Thank you for choosing Budget")'
                . ' or contains(normalize-space(),"Спасибо, что зарезервировали авто с Budget")'
                . ' or contains(normalize-space(),"Спасибо, что выбрали Budget")'
                . ' or contains(.,"www.budget-international.com")'
                . ' or contains(.,"@budget")'
                . ']')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $result = parent::ParsePlanEmail($parser);
        $result['emailType'] = 'BookingConfirmation' . ucfirst($this->lang);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["ru", "en"];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Return Location']) || empty($phrases['Opening hours on day of return'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Return Location'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Opening hours on day of return'])}]")->length > 0
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
