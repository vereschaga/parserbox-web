<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LatestSearch extends \TAccountChecker
{
    public $mailFiles = "wizz/it-10122196.eml, wizz/it-10183414.eml, wizz/it-10288165.eml, wizz/it-11438823.eml, wizz/it-11589292.eml, wizz/it-11618449.eml, wizz/it-15155464.eml, wizz/it-15216471.eml, wizz/it-9785469.eml, wizz/it-49635479.eml, wizz/it-60117425.eml";
    public static $dict = [
        'pt' => [
            'We\'ve saved your latest search to help you book your flight.' => 'Guardámos a sua pesquisa mais recente para o ajudar a reservar o seu voo.',
            'Dear' => 'Caro(a)',
            //            'Total' => '',
            //            'From' => '',
            //            'To' => '',
            'confirmation code' => 'código de confirmação',
            'Departure'         => 'Partida',
            'Flight number'     => 'Número do voo',
        ],
        'pl' => [
            'We\'ve saved your latest search to help you book your flight.' => 'Zapisaliśmy Twoją ostatnio wyszukiwaną trasę, by ułatwić Ci rezerwację lotu.',
            'Dear'  => 'Witaj',
            'Total' => 'Razem',
            'From'  => 'Od',
            'To'    => 'Do',
            //            'confirmation code' => '',
            //            'Departure' => '',
            //            'Flight number' => '',
        ],
        'es' => [
            'We\'ve saved your latest search to help you book your flight.' => 'Hemos guardado su última búsqueda para ayudarle a reservar su vuelo.',
            'Dear' => 'Estimado/a',
            //            'Total' => '',
            //            'From' => '',
            //            'To' => '',
            'confirmation code' => 'código de confirmación',
            'Departure'         => 'Salida',
            'Flight number'     => 'Número de vuelo',
        ],
        'nl' => [
            'We\'ve saved your latest search to help you book your flight.' => 'We hebben uw laatste zoekopdracht opgeslagen om u te helpen bij het boeken.',
            'Dear'              => ['Geachte', 'Beste'],
            'Total'             => 'Totaal',
            'From'              => 'Van',
            'To'                => 'Naar',
            'confirmation code' => 'bevestigingscode',
            'Departure'         => 'Vertrek',
            'Flight number'     => 'Vluchtnummer',
        ],
        'de' => [
            'We\'ve saved your latest search to help you book your flight.' => 'Wir haben Ihre letzte Suchanfrage gespeichert, um Ihnen die Flugbuchung zu erleichtern.',
            'Dear'              => ['Sehr geehrte(r)', 'Hallo'],
            'Total'             => 'Gesamt',
            'From'              => 'Von',
            'To'                => 'Nach',
            'confirmation code' => 'Bestätigungscode',
            'Departure'         => ['Abflug', 'Abreise'],
            'Flight number'     => 'Flugnummer',
        ],
        'hu' => [
//            'We\'ve saved your latest search to help you book your flight.' => '',
            'Dear' => 'Kedves',
            //            'Total' => '',
            //            'From' => '',
            //            'To' => '',
            'confirmation code' => 'visszaigazolási kód',
            'Departure'         => 'Indulás',
            'Flight number'     => 'Repülési szám',
        ],
        'ro' => [
            'We\'ve saved your latest search to help you book your flight.' => 'V-am salvat cea mai recentă căutare pentru a vă ajuta să vă rezervați zborul.',
            'Dear' => 'Stimate pasager',
            //            'Total' => '',
            //            'From' => '',
            //            'To' => '',
            'confirmation code' => 'codului de confirmare',
            'Departure'         => 'Plecare',
            'Flight number'     => 'Numărul zborului',
        ],
        'bg' => [
//            'We\'ve saved your latest search to help you book your flight.' => '',
            'Dear' => 'Уважаеми/а',
            //            'Total' => '',
            //            'From' => '',
            //            'To' => '',
            'confirmation code' => 'код за потвърждение',
            'Departure'         => 'Заминаване',
            'Flight number'     => 'Номер на полет',
        ],
        'he' => [
            'We\'ve saved your latest search to help you book your flight.' => 'שמרנו את החיפוש האחרון שביצעת כדי לעזור לך בהזמנת הטיסה.',
            'Dear' => 'שלום',
            //            'Total' => '',
                        'From' => 'מוצא',
                        'To' => 'יעד',
//            'confirmation code' => 'код за потвърждение',
//            'Departure'         => 'Заминаване',
//            'Flight number'     => 'Номер на полет',
        ],
        'cs' => [
            'We\'ve saved your latest search to help you book your flight.' => 'Abychom vám pomohli s rezervací letu, uložili jsme vaše poslední vyhledávání.',
//            'Dear' => 'שלום',
            //            'Total' => '',
//                        'From' => 'מוצא',
//                        'To' => 'יעד',
//            'confirmation code' => 'код за потвърждение',
//            'Departure'         => 'Заминаване',
//            'Flight number'     => 'Номер на полет',
        ],
        'it' => [
            'We\'ve saved your latest search to help you book your flight.' => 'Abbiamo salvato la tua ultima ricerca per aiutarti a prenotare il tuo volo.',
//            'Dear' => 'שלום',
            //            'Total' => '',
//                        'From' => 'מוצא',
//                        'To' => 'יעד',
//            'confirmation code' => 'код за потвърждение',
//            'Departure'         => 'Заминаване',
//            'Flight number'     => 'Номер на полет',
        ],
        'en' => [
//            'We\'ve saved your latest search to help you book your flight.' => '',
        ],
    ];

    private $reBody = [
        'en'  => ['should not be construed as an offer of Wizz Air', 'Complete your booking'],
        'en2' => ['This message is from Wizz Air Hungary Ltd', 'Wizz Air Customer Service'],
        'en3' => ['there has been a schedule change that ', 'Wizz Air Customer Service'],
        'pt'  => ['Esta mensagem é da Wizz Air Hungary Ltd', 'Serviço de apoio ao cliente da Wizz Air'],
        'pt2'  => ['te e parece que saiu do Web site da Wizz Air sem concluir a sua reserva', 'Concluir a sua reserva'],
        'pl'  => ['Ta wiadomość została wysłana przez Wizz Air', 'Dokończ rezerwację'],
        'es'  => ['Lamentamos informarle de que se ha producido un cambio', 'wizzair.com'],
        'es2'  => ['Hemos guardado su última búsqueda para ayudarle a reservar su vuelo.', 'wizzair.com'],
        'nl'  => ['We hebben uw laatste zoekopdracht opgeslagen', 'wizzair.com'],
        'nl2' => ['wijziging in het vluchtschema', 'wizzair.com'],
        'de'  => ['Wir haben Ihre letzte Suchanfrage gespeichert', 'wizzair.com'],
        'de2' => ['Flugplanänderungen gegeben hat', 'wizzair.com'],
        'de3' => ['Ihr Wizz Air-Kundendienst', 'ist von einer Flugplanänderung betroffen'],
        'hu'  => ['Wizz Air ügyfélszolgálat', 'menetrendváltozás történt'],
        'ro'  => ['Acest mesaj este trimis de Wizz Air Hungary', 'produs o modificare de program care vă afectează zborurile aferente'],
        'ro2'  => ['că ați ieșit de pe site-ul web Wizz Air fără a finaliza rezervarea', 'Finalizați rezervarea'],
        'bg'  => ['Отдел Обслужване на клиенти на Wizz Air', 'че е наложена промяна на полетния план'],
        'bg2'  => ['Екипът за работа с клиенти на Wizz Air', 'Бихме искали да Ви уведомим'],
        'he'  => ['כרטיסים לטיסות שלנו אוזלים במהירות, ונראה שיצאת מהאתר של Wizz Air בלי לסיים את הזמנתך!', 'השלם את ההזמנה שלך'],
        'cs'  => ['zdá se, že jste Wizz Air opustili, aniž byste svoji rezervaci dokončili', 'Dokončete svoji rezervaci'],
        'it'  => ['Sembra che tu sia uscito da Wizz Air senza terminare la prenotazione', 'Completa la prenotazione'],
    ];
    private $reSubject = [
        'en'  => 'Complete your booking to',
        'en2' => 'Schedule change notification (confirmation code:',// +ro
        'pl'  => 'Dokończ swoją rezerwację',
        'nl'  => 'Wijziging in tijdschema (bevestigingscode:',
        'de'  => 'Ihre Buchung mit dem Ziel',
        'de2' => 'Benachrichtigung über Flugplanänderung (Bestätigungsnummer:',
        'bg' => 'Съобщение за промяна в разписанието (код за потвърждение:',
        'he' => 'השלם את ההזמנה שלך',
        'cs' => 'Dokončete svoji rezervaci',
    ];
    private $lang = '';
    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->http->XPath->query("//*[{$this->contains($this->t('We\'ve saved your latest search to help you book your flight.'))}]")->length > 0) {
            $email->setIsJunk(true);
            return $email;
        }

        if ($this->http->XPath->query("//table[{$this->contains($this->t('Departure'))} and not(.//table)]")->length > 0) {
            $this->parseEmail2($email);
        } else {
            $this->parseEmail1($email);
        }


        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'wizzair.com')]")->length === 0
            && $this->http->XPath->query("//a[contains(@href,'etraveligroup.com')]")->length === 0
            && $this->http->XPath->query("//img[@alt = 'Wizzair']")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Wizz Air Customer Service') !== false
            || stripos($from, 'wizzair.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail1(Email $email)
    {
        $this->logger->debug('parseEmail1');
        // it's not a reservation
        return false;

        $patterns = [
            'nameTerminal' => '/^(?<name>.+?)\s*-\s*Terminal\s+(?<terminal>[A-Z\d\s]+)$/', // Budapest - Terminal 2
        ];

        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation()
            ->status('Latest search')
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "#{$this->opt($this->t('Dear'))}\s+(.+?)\s*(?:,|$)#"));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]"));

        if ($tot['Total'] !== null) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $xpath = "//text()[{$this->eq($this->t('From'))}]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space(.)]", $root));

            if (preg_match("#{$this->opt($this->t('From'))}\s+(.+)\s+\(([A-Z]{3})\)\s+(.+)#", $node, $matches)) {
                if (preg_match($patterns['nameTerminal'], $matches[1], $m)) {
                    $seg->departure()
                        ->name($m[1])
                        ->terminal($m[2]);
                } else {
                    $seg->departure()
                        ->name($matches[1]);
                }
                $seg->departure()
                    ->code($matches[2])
                    ->date($this->normalizeDate($matches[3]));
            } else {
                $seg->departure()
                    ->code($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][2]", $root))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][last()]", $root)));
            }

            $node = implode("\n", $this->http->FindNodes("./following::td[normalize-space(.)][1]//text()[normalize-space(.)]", $root));

            if (preg_match("#{$this->opt($this->t('To'))}\s+(.+)\s+\(([A-Z]{3})\)\s+(.+)#", $node, $matches)) {
                if (preg_match($patterns['nameTerminal'], $matches[1], $m)) {
                    $seg->arrival()
                        ->name($m[1])
                        ->terminal($m[2]);
                } else {
                    $seg->arrival()
                        ->name($matches[1]);
                }
                $seg->arrival()
                    ->code($matches[2])
                    ->date($this->normalizeDate($matches[3]));
            } else {
                $seg->arrival()
                    ->code($this->http->FindSingleNode("./following::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][2]", $root))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./following::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][last()]", $root)));
            }

            if (!empty($seg->getDepDate()) && !empty($seg->getArrDate())) {
                $seg->airline()
                    ->name('W6') // Wizz Air (Discount Club)
                    ->noNumber();
            }
        }

        return true;
    }

    private function parseEmail2(Email $email)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $f = $email->add()->flight();

        $xpathConfNum = "descendant::text()[{$this->eq($this->t('Departure'))}][1]/preceding::text()[{$this->contains($this->t('confirmation code'))}][1]/ancestor::tr[1]";

        $f->general()
            ->traveller($this->http->FindSingleNode($xpathConfNum . "/preceding::tr[{$this->starts($this->t('Dear'))}][1]", null, true, "#{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:[,;!?]|$)#u"));

        $confirmationCode = $this->http->FindSingleNode($xpathConfNum);

        if (preg_match("#{$this->opt($this->t('confirmation code'))}\s+(?-i)([A-Z\d]{5,8})(?:\W|$)#i", $confirmationCode, $m)
            || preg_match("#(?:^|\W)([A-Z\d]{5,8})\s+(?i){$this->opt($this->t('confirmation code'))}#", $confirmationCode, $m)
        ) {
            $f->general()
                ->confirmation($m[1]);
        }

        $xpath = "//table[{$this->starts($this->t('Departure'))} and not(.//table)]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $s = $f->addSegment();

            if (preg_match('/(.+)\s*\(([A-Z]{3})\)\s*(.+)\s*\(([A-Z]{3})\)/', $this->http->FindSingleNode('descendant::tr[2]', $root), $m)) {
                $s->departure()
                    ->name(trim($m[1]))
                    ->code($m[2]);
                $s->arrival()
                    ->name(trim($m[3]))
                    ->code($m[4]);
            }

            $dDate = 0;
            $aDate = 0;
            $dates = implode(' ', $this->http->FindNodes('descendant::tr[normalize-space()][3]/descendant::text()[normalize-space()]', $root));

            if (preg_match('/^(\d{1,2}\s*\/\s*\w{3,}\.?\s*\/\s*\d{2,4})\s+(\d{1,2}\s*\/\s*\w{3,}\.?\s*\/\s*\d{2,4})$/u', $dates, $m)) {
                // 28 / december / 2019    28/Dec/2019
                $dDate = strtotime(str_replace('/', ' ', $this->dateStringToEnglish($m[1])));
                $aDate = strtotime(str_replace('/', ' ', $this->dateStringToEnglish($m[2])));
            }

            $times = implode(' ', $this->http->FindNodes('descendant::tr[normalize-space()][4]/descendant::text()[normalize-space()]', $root));

            if (!empty($dDate) && !empty($aDate) && preg_match('/^(\d+:\d+)\s+(\d+:\d+)$/', $times, $m)) {
                // 20:30    23:50
                $s->departure()
                    ->date(strtotime($m[1], $dDate));
                $s->arrival()
                    ->date(strtotime($m[2], $aDate));
            }

            if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
                $s->airline()->name('W6');
            }

            if (preg_match("/{$this->t('Flight number')}\s*:\s*(\d+)/", $this->http->FindSingleNode('preceding-sibling::table[1]', $root), $m)) {
                $s->airline()->number($m[1]);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^(\w+),?\s+(\d+)\s+(\w+)[\s\-]+(\d+:\d+)$#',
            '#^(\d+)-(\d+)-(\d+)T(\d+:\d+):\d+$#', // 2018-07-03T00:20:00
        ];
        $out = [
            '$2 $3 ' . $year . '$4',
            '$3.$2.$1 $4',
        ];
        $outWeek = [
            '$1',
            '',
        ];
        $weeknum = WeekTranslate::number1(WeekTranslate::translate(preg_replace($in, $outWeek, $date), $this->lang));

        if (!empty($weeknum)) {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);

            return $str;
        }
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (
                    $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } else {
                foreach (array_unique(array_merge(array_keys(self::$dict), ['lt' , 'he'])) as $lang) {
                    if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                        return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
                    }
                }
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
