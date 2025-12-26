<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MilesAndMore extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-12692183.eml, lufthansa/it-6714941.eml";
    public $reFrom = ["no-reply@miles-and-more.com", "lufthansa.com"];
    public $reBody = [
        'en' => ['Details of your booking', 'Booking'],
        'de' => ['Details zu Ihrer Buchung', 'Buchung'],
        'nl' => ['Details van uw boeking', 'Boeking'],
    ];
    public $detectBody = [
        'en'  => 'We herewith confirm your booking via Miles & More',
        'en2' => 'These will be credited to your Miles & More account within two weeks after check-out/drop-off',
        'de'  => 'hiermit bestätigen wir Ihre Buchung über Miles & More',
        'nl'  => 'Wij bevestigen hiermee uw boeking via Miles & More',
    ];
    public $reSubject = [
        'Booking #',
        'Buchung #',
        'Boeking #',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Guests'              => ['Guests', 'Adults'],
            'Cancellation policy' => ['Cancellation policy', 'Cancellation Policy'],
            'Collected Miles'     => ['Collected Miles', 'Miles earned'],
        ],
        'de' => [
            'Booking' => 'Buchung',
            //for hotels
            'Room'                => 'Zimmer',
            'Guests'              => 'Gäste',
            'Collected Miles'     => 'Gesammelte Meilen',
            'Costs'               => 'Kosten',
            'Taxes & Fees'        => 'Steuern & Gebühren',
            'Total Cost'          => 'Gesamtkosten',
            'Cancellation policy' => 'Stornierungsbedingungen',
            //for cars
            'Pick-up date'      => 'Anmietdatum',
            'Pick-up location'  => 'Anmietstation',
            'Drop-off date'     => 'Rückgabedatum',
            'Drop-off location' => 'Rückgabestation',
            'Operation Hours'   => 'Öffnungszeiten',
            'Car Company'       => 'Name der Firma',
            'Car type'          => 'Fahrzeugtyp',
            'CarImages'         => 'CarImages',
            'Name'              => 'Name:',
            'Cash'              => 'Geldbetrag',
            'Miles spent'       => 'Eingelöste Meilen',
        ],
        'nl' => [
            'Booking' => 'Boeking',
            //for hotels
            'Check-in'            => 'Inchecken',
            'Check-out'           => 'Uitchecken',
            'Name'                => 'Naam',
            'Room'                => 'Kamer',
            'Guests'              => 'Volwassenen',
            'Collected Miles'     => 'Verzamelde mijlen',
            'Costs'               => 'Kosten',
            'Taxes & Fees'        => 'Belastingen en kosten',
            'Total Cost'          => 'Totale kosten',
            'Cancellation policy' => 'Annuleringsvoorwaarden',
            //for cars
            //			'Pick-up date'=>'',
            //			'Pick-up location'=>'',
            //			'Drop-off date'=>'',
            //			'Drop-off location'=>'',
            //			'Operation Hours'=>'',
            //			'Car Company'=>'',
            //			'Car type'=>'',
            //			'CarImages'=>'',
            //			'Name'=>'',
            //			'Cash'=>'',
            //			'Miles spent' => '',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $type = '';

        if (!empty($this->getNode($this->t('Check-in')))) {
            $type = 'Hotel';
            $this->parseEmailHotel($email);
        } elseif (!empty($this->getNode($this->t('Pick-up date')))) {
            $type = 'Car';
            $this->parseEmailCar($email);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        $flag = false;

        foreach ($this->reFrom as $f) {
            if (stripos($headers['from'], $f) !== false) {
                $flag = true;

                break;
            }
        }

        if ($flag) {
            foreach ($this->reSubject as $re) {
                if (stripos($headers['subject'], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $f) {
            if (stripos($from, $f) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->detectBody as $detect) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(.), '{$detect}')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailCar(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()->confirmation($this->getNode($this->t('Booking')));

        $r->pickup()
            ->date($this->normalizeDate($this->getNode($this->t('Pick-up date'))))
            ->location($this->getNode($this->t('Pick-up location')))
            ->openingHours($this->http->FindSingleNode("(//span[contains(., '{$this->t('Operation Hours')}')])[1]",
            null, true, '/:\s+(.+)/'));

        $r->dropoff()
            ->date($this->normalizeDate($this->getNode($this->t('Drop-off date'))))
            ->location($this->getNode($this->t('Drop-off location')))
            ->openingHours($this->http->FindSingleNode("(//span[contains(., '{$this->t('Operation Hours')}')])[2]",
            null, true, '/:\s+(.+)/'));
        $r->setCompany($this->getNode($this->t('Car Company')));

        $r->car()
            ->type($this->getNode($this->t('Car type')))
            ->model($this->http->FindSingleNode("//img[contains(@src, '{$this->t('CarImages')}')]/ancestor::tr[1]/following-sibling::tr[1]"))
//            ->model($this->getNode('Car class'))
            ->image($this->http->FindSingleNode("//img[contains(@src, '{$this->t('CarImages')}')]/@src"));

        $r->general()->traveller($this->getNode($this->t('Name')), true);
        $total = $this->getTotalCurrency($this->getNode($this->t('Cash')));

        if (!empty($total['Total'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
        $r->price()->spentAwards($this->getNode($this->t('Miles spent')));
    }

    private function parseEmailHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->getNode($this->t('Booking')))
            ->traveller($this->getNode($this->t('Name')), true);
        $h->booked()
            ->checkIn($this->normalizeDate($this->getNode($this->t('Check-in'))))
            ->checkOut($this->normalizeDate($this->getNode($this->t('Check-out'))))
            ->guests($this->getNode($this->t('Guests')))
            ->kids($this->getNode($this->t('Children'), '/^[ ]*(\d{1,2})[ ]*/'), true, true)
        ;
        $h->general()
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation policy'))}]/following::text()[normalize-space(.)!=''][1]"));
        $h->hotel()
            ->name($this->http->FindSingleNode("//img[contains(@src,'/hotels/')]/ancestor::tr[1]/following-sibling::tr[1]"))
            ->address($this->http->FindSingleNode("//img[contains(@src,'/hotels/')]/ancestor::tr[1]/following-sibling::tr[2]"));
        $h->addRoom()
            ->setType($this->getNode($this->t('Room')));

        $total = $this->getTotalCurrency($this->getNode($this->t('Taxes & Fees')));

        if (!empty($total['Total'])) {
            $h->price()
                ->tax($total['Total'])
                ->currency($total['Currency']);
        }
        $total = $this->getTotalCurrency($this->getNode($this->t('Costs')));

        if (!empty($total['Total'])) {
            $h->price()
                ->cost($total['Total'])
                ->currency($total['Currency']);
        }
        $total = $this->getTotalCurrency($this->getNode($this->t('Total Cost')));

        if (!empty($total['Total'])) {
            $h->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
        $h->program()
            ->earnedAwards($this->getNode($this->t('Collected Miles')));
    }

    private function getNode($str, ?string $re = null)
    {
        return $this->http->FindSingleNode("//td[{$this->starts($str)}]/following-sibling::td[1]", null, true, $re);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function normalizeDate($date)
    {
        if ($this->lang == "de") {
            $date = str_replace(["NACHMITTAGS", "VORMITTAG"], ["PM", "AM"], $date); // check VORMITTAG
        }
        $in = [
            '#\w+\s+(\w+)\s+(\d{1,2})[,.]?\s+(\d{4})\s*(\d+:\d+(\s*[AP]M)?)#u',
            '#(\d+)\.?\s+(\w+)\s+(\d+)#',
        ];
        $out = [
            '$2 $1 $3 $4',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
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
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
