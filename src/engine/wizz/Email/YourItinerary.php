<?php

namespace AwardWallet\Engine\wizz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary extends \TAccountCheckerExtended
{
    public $mailFiles = "wizz/it-11613421.eml, wizz/it-243148459.eml, wizz/it-249738410.eml, wizz/it-2673296.eml, wizz/it-3047321.eml, wizz/it-4149155.eml, wizz/it-4192396.eml, wizz/it-4232750.eml, wizz/it-4247437.eml, wizz/it-4329950.eml, wizz/it-4406792.eml, wizz/it-4445512.eml, wizz/it-4558662.eml, wizz/it-4573495.eml, wizz/it-53073622.eml, wizz/it-6736883.eml, wizz/it-6737403.eml"; // +1 bcdtravel(html)[bg]

    public $reSubject = [
        // en, lv, nl, pl, fr, ru, no
        "Your itinerary:",
        "Your travel itinerary:",
        // it
        "Il tuo itinerario di viaggio:",
        // bg
        "Вашият пътен план:",
        // he
        'הנסיעה שלך:',
        // cs
        'Váš cestovní plán:',
        // es
        'Itinerario de su viaje:',
        // de
        'Ihre Reiseroute:',
        // pt
        'O itinerário da sua viagem:',
        // pl
        'Twój plan podróży:',
        // ro
        'Itinerarul călătoriei:',
        // ru
        'Ваша маршрутная квитанция:',
        // fr
        'Votre itinéraire de voyage:',
        // uk
        'Ваш маршрут подорожі:',
        // hu
        'Foglalás visszaigazolása:',
        // sv
        'Din resplan:',
        // no
        'Reiseplanen din:',
        // ar
        'دليل خط سير رحلتك:',
        // lt
        'Jūsų kelionės maršrutas:',
    ];
    public $reBody = 'Wizz Air';
    public $reBody2 = [
        "en" => "Flight confirmation code:",
        "nl" => "Vluchtbevestigingscode:",
        "pl" => "Kod potwierdzenia lotu:",
        "de" => "Flugbestätigungscode:",
        "es" => "Código de confirmación del vuelo:",
        "pt" => "Código de confirmação do voo:",
        "it" => "Codice di conferma volo:",
        "fr" => "Code de confirmation du vol",
        "ru" => "Код подтверждения рейса:",
        "no" => "Bekreftelseskode:",
        'sv' => 'Flygbekräftelsekod:',
        'hu' => 'Járat visszaigazoló kódja:',
        'hu2' => 'Foglalás visszaigazolása:',
        'uk' => 'Код підтвердження замовлення рейсу:',
        //		"lv" => "",
        "ro" => "Codul de confirmare a zborului",
        "bg" => "Код за потвърждение на полет:",
        "cs" => "Váš cestovní plán:",
        "cs2" => "Podrobnosti o letu",
        "he" => "קוד אישור טיסה:",
        "ar" => "رمز تأكيد رحلة الطيران:",
        "lt" => "Skrydžio patvirtinimo kodas:",
    ];

    private $lang = "en";
    private static $dictionary = [
        'en' => [
            'RecordLocator' => 'Flight confirmation code:',
            'Passengers Name' => ['Title', 'First name'],
            'Passengers Route' => ['Route', 'Last name'],
            'FlightNumber' => 'Flight Number',
            'Departs from:' => 'Departs from:',
            'Terminal' => 'Terminal',
            'Date' => 'Booking date:',
            'Description' => 'Description', // price column name
            'Total' => 'Total', // price column name
            'Fare price' => 'Fare price',
            'Discount' => ['Discount'],
            'TotalCharge' => 'Grand total',
        ],
        'nl' => [
            'RecordLocator' => 'Vluchtbevestigingscode:',
            'Passengers Name' => ['Aanspreektitel', 'Titel'],
            'Passengers Route' => 'Route',
            'FlightNumber' => 'Vluchtnummer',
            'Departs from:' => 'Vertrekt vanaf:',
//            'Terminal'   => '',
            'Date' => 'Boekingsdatum:',
            'Description' => 'Omschrijving', // price column name
            'Total' => 'Totaal', // price column name
            'Fare price' => 'Fare price',
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Algemeen totaal',
        ],
        'pl' => [
            'RecordLocator' => 'Kod potwierdzenia lotu:',
            'Passengers Name' => ['Zwrot grzecznościowy', 'Tytuł'],
            'Passengers Route' => ['Route', 'Trasa'],
            'FlightNumber' => 'Numer lotu',
            'Departs from:' => 'Wylot z:',
            'Terminal'   => 'Terminal',
            'Date' => 'Data rezerwacji',
            'Description' => 'Opis', // price column name
            'Total' => 'Suma', // price column name
            'Fare price' => ['Fare price', 'Cena'],
            'Discount' => ['Discount', 'Rabat'],
            'TotalCharge' => 'Suma całkowita',
        ],
        'de' => [
            'RecordLocator' => 'Flugbestätigungscode:',
            'Passengers Name' => ['Titel', 'Anrede'],
            'Passengers Route' => ['Route', 'Strecke'],
            'FlightNumber' => 'Flugnummer',
            'Departs from:' => 'Abflug von:',
            'Terminal'   => 'Terminal',
            'Date' => 'Buchungsdatum:',
            'Description' => 'Beschreibung', // price column name
            'Total' => 'Summe', // price column name
            'Fare price' => ['Fare price', 'Ticketpreis'],
            'Discount' => ['Discount', 'Rabatt'],
            'TotalCharge' => 'Gesamtsumme',
        ],
        'es' => [
            'RecordLocator' => 'Código de confirmación del vuelo:',
            'Passengers Name' => 'Tratamiento',
            'Passengers Route' => ['Ruta', 'Route'],
            'FlightNumber' => 'N.º de vuelo',
            'Departs from:' => 'Sale de:',
            'Terminal' => 'Terminal',
            'Date' => 'Fecha de reserva:',
            'Description' => 'Descripción', // price column name
            'Total' => 'Total', // price column name
            'Fare price' => ['Fare price', 'Precio del billete'],
            'Discount' => ['Discount', 'Descuento'],
            'TotalCharge' => 'Importe total',
        ],
        'pt' => [
            'RecordLocator' => 'Código de confirmação do voo:',
            'Passengers Name' => 'Título',
            'Passengers Route' => 'Rota',
            'FlightNumber' => 'Número do voo',
            'Departs from:' => 'Parte de:',
            'Terminal'   => 'Terminal',
            'Date' => 'Data de reserva:',
            'Description' => 'Descrição', // price column name
            'Total' => 'Total', // price column name
            'Fare price' => ['Fare price', 'Valor da tarifa'],
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Total geral',
        ],
        'it' => [
            'RecordLocator' => 'Codice di conferma volo:',
            'Passengers Name' => 'Titolo',
            'Passengers Route' => ['Route', 'Rotta'],
            'FlightNumber' => 'Numero di volo',
            'Departs from:' => 'Partenza da:',
            'Terminal' => 'Terminal',
            'Date' => 'Data di prenotazione:',
            'Description' => 'Descrizione', // price column name
            'Total' => 'Totale', // price column name
            'Fare price' => ['Fare price', 'Tariffa'],
            'Discount' => ['Discount', 'Sconto'],
            'TotalCharge' => 'Totale complessivo',
        ],
        'fr' => [
            'RecordLocator' => 'Code de confirmation du vol :',
            'Passengers Name' => ['Civilité', 'Titre'],
            'Passengers Route' => ['Itinéraire', 'Route'],
            'FlightNumber' => 'Numéro de vol',
            'Departs from:' => 'Part de',
            'Terminal' => 'Terminal',
            'Date' => 'Date de réservation :',
            'Description' => 'Description', // price column name
            'Total' => 'Total', // price column name
            'Fare price' => ['Fare price', 'Tarif'],
            'Discount' => ['Discount', 'Réduction'],
            'TotalCharge' => 'Total général',
        ],
        'ru' => [
            'RecordLocator' => 'Код подтверждения рейса:',
            'Passengers Name' => 'Обращение',
            'Passengers Route' => ['Route', 'Маршрут'],
            'FlightNumber' => 'Номер рейса',
            'Departs from:' => 'Аэропорт отправления:',
            'Terminal' => 'Терминал',
            'Date' => 'Дата бронирования:',
            'Description' => 'Описание', // price column name
            'Total' => 'Всего', // price column name
            'Fare price' => ['Fare price', 'Стоимость тарифа'],
            'Discount' => ['Discount', 'Скидка'],
            'TotalCharge' => 'Общая сумма',
        ],
        'no' => [
            'RecordLocator' => 'Bekreftelseskode:',
            'Passengers Name' => 'Tittel',
            'Passengers Route' => ['Route', 'Rute'],
            'FlightNumber' => 'Flynummer',
            'Departs from:' => 'Avgang fra',
            'Terminal'   => 'Terminal',
            'Date' => 'Bestillingsdato:',
            'Description' => 'Beskrivelse', // price column name
            'Total' => 'Sum', // price column name
            'Fare price' => ['Fare price', 'Pris'],
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Sum totalt',
        ],
        'lv' => [
            'RecordLocator' => 'Lidojuma apstiprinājuma kods:',
            'Passengers Name' => 'Uzruna',
            'Passengers Route' => 'Route',
            'FlightNumber' => 'Lidojuma numurs',
            'Departs from:' => 'Izlidošanas vieta',
//            'Terminal'   => '',
            'Date' => 'Rezervējuma datums:',
            'Description' => 'Apraksts', // price column name
            'Total' => 'Kopā', // price column name
            'Fare price' => 'Fare price',
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Kopsumma',
        ],
        'sv' => [
            'RecordLocator' => 'Flygbekräftelsekod:',
            'Passengers Name' => 'Titel',
            'Passengers Route' => 'Rutt',
            'FlightNumber' => 'Flygnummer',
            'Departs from:' => 'Avgår från',
            'Terminal'   => 'Terminal',
            'Date' => 'Bokningsdatum:',
            'Description'    => 'Beskrivning', // price column name
            'Total'    => 'Summa', // price column name
            'Fare price'    => 'Biljettpris',
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Totalt',
        ],
        'hu' => [
            'RecordLocator' => 'Járat visszaigazoló kódja:',
            'Passengers Name' => 'Megszólítás',
            'Passengers Route' => 'Útvonal',
            'FlightNumber' => 'Járat száma',
            'Departs from:' => 'Indulási hely:',
            'Terminal'   => 'terminál',
            'Date' => 'Foglalás dátuma:',
            'Description'    => 'Leírás', // price column name
            'Total'    => 'Összesen', // price column name
            'Fare price'    => 'Viteldíj',
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Mindösszesen',
        ],
        'uk' => [
            'RecordLocator' => 'Код підтвердження замовлення рейсу:',
            'Passengers Name' => 'Звертання',
            'Passengers Route' => 'Маршрут',
            'FlightNumber' => 'Номер рейсу',
            'Departs from:' => 'Аеропорт відправлення:',
            'Terminal' => 'Термінал',
            'Date' => 'Дата бронювання:',
            'Description' => 'Опис', // price column name
            'Total' => 'Усього', // price column name
            'Fare price' => 'Тарифна ціна',
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Загальна сума',
        ],
        'ro' => [
            'RecordLocator' => 'Codul de confirmare a zborului:',
            'Passengers Name' => 'Titlu',
            'Passengers Route' => 'Rută',
            'FlightNumber' => 'Numărul zborului',
            'Departs from:' => 'Pleacă din:',
            'Terminal' => 'Terminalul',
            'Date' => 'Data rezervării:',
            'Description' => 'Descriere', // price column name
            'Total' => 'Total', // price column name
            'Fare price' => ['Preț zbor', 'Fare price'],
            'Discount' => ['Discount', 'Reducere'],
            'TotalCharge' => 'Total general',
        ],
        'bg' => [
            'RecordLocator' => 'Код за потвърждение на полет:',
            'Passengers Name' => 'Обръщение',
            'Passengers Route' => 'Маршрут',
            'FlightNumber' => 'Номер на полет',
            'Departs from:' => 'Тръгва от:',
//            'Terminal'   => '',
            'Date' => 'Дата на резервиране:',
            'Description'    => 'Описание', // price column name
            'Total'    => 'Сума', // price column name
            'Fare price'    => 'Добра цена',
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Обща сума',
        ],
        'cs' => [
            'RecordLocator' => 'Kód potvrzení letu:',
            'Passengers Name' => 'Jméno',
            'Passengers Route' => 'Příjmení',
            'FlightNumber' => 'Číslo letu',
            'Departs from:' => 'Odlet:',
//            'Terminal'   => '',
            'Date' => 'Datum rezervace:',
            'Description' => 'Popis', // price column name
            'Total' => 'Celkem', // price column name
            'Fare price' => ['Fare price', 'Cena tarifu'],
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Celková cena',
        ],
        'he' => [
            'RecordLocator' => ['קוד אישור טיסה:', 'קוד אישור:'],
            'Passengers Name' => 'תואר',
            'Passengers Route' => 'מסלול',
            'FlightNumber' => 'מספר טיסה',
            'Departs from:' => 'מקום המראה:',
            'Terminal' => 'טרמינל',
            'Date' => 'תאריך הזמנה:',
            'Description' => 'תיאור', // price column name
            'Total' => 'סכום', // price column name
            'Fare price' => 'תעריף',
            'Discount' => ['Discount', 'הנחה'],
            'TotalCharge' => 'סך הכול',
        ],
        'ar' => [
            'RecordLocator' => 'رمز تأكيد رحلة الطيران:',
            'Passengers Name' => 'اللقب',
            'Passengers Route' => 'المسار',
            'FlightNumber' => 'رقم رحلة الطيران',
            'Departs from:' => 'وصول إلى:',
            'Terminal'   => 'Terminal',
            'Date' => 'تاريخ الحجز:',
            'Description' => 'תיאור', // price column name
            'Total' => 'الإجمالي', // price column name
            'Fare price' => ['Fare price', 'رسوم المناولة الجماعية'],
//            'Discount' => ['Discount', ''],
            'TotalCharge' => 'الإجمالي الكلي',
        ],
        'lt' => [
            'RecordLocator' => 'Skrydžio patvirtinimo kodas:',
            'Passengers Name' => 'Kreipinys',
            'Passengers Route' => 'Maršrutas',
            'FlightNumber' => 'Skrydžio numeris',
            'Departs from:' => 'Išvyksta iš:',
            'Terminal'   => 'terminalas',
            'Date' => 'Užsakymo data:',
            'Description'    => 'Aprašas', // price column name
            'Total'    => 'Iš viso', // price column name
            'Fare price'    => 'Bilieto kaina',
//            'Discount' => ['Discount',],
            'TotalCharge' => 'Bendroji suma',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@wizzair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query('//img[contains(@alt,"//wizzair.com")]')->length === 0
            && strpos($body, $this->reBody) === false
        ) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//node()[{$this->contains($re)}]")->length > 0
                || strpos($body, $re) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['RecordLocator'])
                && $this->http->XPath->query("descendant::text()[{$this->eq($dict['RecordLocator'])}][1]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $body = str_replace("Â", "", $this->http->Response['body']);
        $this->http->SetBody($body);

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('RecordLocator'))}][1]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]",
                null, true, '/^\s*([\w-]+)\s*$/'));
        $x = "//tr[{$this->contains($this->t('Passengers Name'))} and {$this->contains($this->t('Passengers Route'))} and not(.//tr)]/following-sibling::tr[count(./td)=7]";
        $this->logger->debug('$x = '.print_r( $x,true));
        $passInfoNodes = $this->http->XPath->query($x);
        if ($passInfoNodes->length === 0) {
            $x = "//tr[{$this->contains($this->t('Passengers Name'))} and {$this->contains($this->t('Passengers Route'))} and not(.//tr)]/following-sibling::tr[count(./td)>=3]";
            $passInfoNodes = $this->http->XPath->query($x);
        }
        $passengers = [];
        foreach ($passInfoNodes as $n) {
            $passengers[] = str_replace(">", '',
                $this->http->FindSingleNode('./td[2]', $n) . ' ' . $this->http->FindSingleNode('./td[3]', $n));
        }
        $f->general()
            ->travellers($passengers, true);

        $date = $this->normalizeDate(str_replace("/", ".",
            $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Date')) . "]/following::text()[normalize-space(.)][1]")));
        if (!empty($date)) {
            $f->general()
                ->date($date);
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('Departs from:'))}]/ancestor::*[ following-sibling::*[normalize-space()][2] and not(.//tr) ][1]";
//        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);
        foreach ($nodes as $flightIndex => $root) {

            $s = $f->addSegment();

            $node = $this->http->FindSingleNode('preceding::text()[normalize-space() and not(.//tr)][1]', $root);
            $re = '/' . $this->opt($this->t('FlightNumber')) . '\s*:\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<fn>\d+)\s*$/iu';
            if (preg_match($re, $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            // Departure
            $pointInfo = $this->http->FindSingleNode('following-sibling::*[1]/td[normalize-space()][1]', $root)
                ?? $this->http->FindSingleNode('following-sibling::node()[normalize-space()][contains(.,"(") and contains(.,")")][1]',
                    $root);

            if (preg_match('/(.*)\(([A-Z]{3})\)/', $pointInfo, $m)) {
                $s->departure()
                    ->code($m[2]);

                $name = trim($m[1]);
                if (preg_match("#^(.+?) [-–] ((?:[\w\-]+\s+)?(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})(?:\s+[\w\-]*)?)\s*$#iu", $m[1], $ms)
                    || preg_match("#^(.+?)\(((?:[\w\-]+\s+)?(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})(?:\s+[\w\-]*)?)\)\s*$#iu", $m[1], $ms)
                ) {
                    $name = trim($ms[1]);

                    $s->departure()
                        ->terminal(trim(preg_replace("/\s*(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})\s*/u",
                            '', $ms[2])));
                }
                if (!empty($name)) {
                    $s->departure()
                        ->name($name);
                }
            }

            $d = $this->http->FindSingleNode('following-sibling::*[2]/td[normalize-space()][1]', $root)
                ?? $this->http->FindSingleNode('following-sibling::node()[normalize-space()][contains(.,"(") and contains(.,")")][1]/following-sibling::node()[normalize-space()][2]',
                    $root);
            if (!empty($d)) {
                $d = str_replace('/', '.', $d);
                $s->departure()
                    ->date($this->normalizeDate($d));
            }


            // Arrival
            $pointInfo = $this->http->FindSingleNode('following-sibling::*[1]/td[normalize-space()][2]', $root)
                ?? $this->http->FindSingleNode('following-sibling::node()[normalize-space()][contains(.,"(") and contains(.,")")][2]',
                    $root);

            if (preg_match('/(.*)\(([A-Z]{3})\)/', $pointInfo, $m)) {
                $s->arrival()
                    ->code($m[2]);

                $name = trim($m[1]);
                if (preg_match("#^(.+?) [-–] ((?:[\w\-]+\s+)?(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})(?:\s+[\w\-]*)?)\s*$#iu", $m[1], $ms)
                    || preg_match("#^(.+?)\(((?:[\w\-]+\s+)?(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})(?:\s+[\w\-]*)?)\)\s*$#iu", $m[1], $ms)
                ) {
                    $name = trim($ms[1]);

                    $s->arrival()
                        ->terminal(trim(preg_replace("/\s*(?:termin\S{1,2}l|{$this->opt($this->t("Terminal"))})\s*/u",
                            '', $ms[2])));
                }
                if (!empty($name)) {
                    $s->arrival()
                        ->name($name);
                }
            }

            $d = $this->http->FindSingleNode('following-sibling::*[2]/td[normalize-space()][2]', $root)
                ?? $this->http->FindSingleNode('following-sibling::node()[normalize-space()][contains(.,"(") and contains(.,")")][2]/following-sibling::node()[normalize-space()][2]',
                    $root);
            if (!empty($d)) {
                $d = str_replace('/', '.', $d);
                $s->arrival()
                    ->date($this->normalizeDate($d));
            }

            // Seats
            $seek = $s->getDepCode() . '-' . $s->getArrCode();
            if (strlen($seek) == 7) {
                $rows = $this->http->FindNodes("//text()[normalize-space(.)='{$seek}']/ancestor::td[1]//text()[normalize-space()!='']");
                $k = array_search($seek, $rows);
                if ($k !== false && is_numeric($k)) {
                    $seats = $this->http->FindNodes("//tr[(contains(normalize-space(.), '{$seek}')) and not(.//tr)]/td[last()]/descendant::text()[normalize-space(.)][$k+1]",
                        null);
                    $seats = array_filter($seats, function ($s) {
                        return preg_match("#^\d+[A-z]$#", $s) > 0;
                    });

                    if (!empty($seats)) {
                        $s->extra()
                            ->seats($seats);
                    }
                }
            }
        }

        // Price
        $currency = null;
        $total = $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("TotalCharge"))}]]/*[normalize-space()][2]");
        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/u", $total, $m)
        ) {
            $currency = $m['currency'];
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency']);


            $pXpath = "//tr[*[normalize-space()][1][{$this->eq($this->t('Description'))}] and *[normalize-space()][2][{$this->eq($this->t('Total'))}]]/following-sibling::tr[normalize-space()]";
            $pNodes = $this->http->XPath->query($pXpath);
            $prices = [];
            $cost = 0.0;
            $discount = 0.0;
            foreach ($pNodes as $pRoot) {
                $name = trim($this->http->FindSingleNode('*[normalize-space()][1]', $pRoot));
                $value = $this->http->FindSingleNode('*[normalize-space()][2]', $pRoot);
                if (preg_match("/^\s*" . preg_quote($currency) . "\s*(?<discount>-\s*)?(?<amount>\d[\d\., ]*)\s*$/u",
                        $value, $m)
                    || preg_match("/^\s*(?<discount>-\s*)?(?<amount>\d[\d\., ]*)\s*" . preg_quote($currency) . "\s*$/u",
                        $value, $m)
                ) {
                    if (preg_match("/^\s*{$this->opt($this->t("TotalCharge"))}/u", $name)) {
                        break;
                    } elseif (preg_match("/^\s*{$this->opt($this->t("Fare price"))}/u", $name)) {
                        $cost += PriceHelper::parse($m['amount'], $currency);
                    } elseif (!empty($m['discount']) || preg_match("/^\s*{$this->opt($this->t("Discount"))}/u", $name)) {
                        $discount += PriceHelper::parse($m['amount'], $currency);
                    } else {
                        if (isset($prices[$name])) {
                            $prices[$name] += PriceHelper::parse($m['amount'], $currency);
                        } else {
                            $prices[$name] = PriceHelper::parse($m['amount'], $currency);
                        }
                    }
                } else {
                    $prices = [];
                    unset($cost, $discount);
                    break;
                }
            }
            if (!empty($cost)) {
                $f->price()
                    ->cost($cost);
            }
            if (!empty($discount)) {
                $f->price()
                    ->discount($discount);
            }
            foreach ($prices as $name => $amount) {
                $f->price()
                    ->fee($name, $amount);
            }
        } else {
            $f->price()
                ->total(null);
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate(?string $str)
    {
//        $this->logger->debug($str);
        $in = [
            '/^\s*(\d{4})\.\s*(\d{1,2})\.\s*(\d{1,2})\s*\.\s*(\d{1,2}:\d{2})\s*$/u', //2018.11.27. 6:10
            '/^\s*(\d{4})\. ?(\d{1,2})\. ?(\d{1,2})\s*\.\s*$/u', //2018.11.27.
            '/^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s*г\.\s*$/u', //23.3.2019 г.
            '/^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s*г\.\s*(\d{1,2}:\d{2})\s*$/u', //9.5.2019 г. 14:30
            '/^(\d+)\. (\d+)\. (\d{4})$/u', // 8. 2. 2020
            '/^(\d{1,2})\. (\d{1,2})\. (\d{4}) (\d+:\d+)$/u', // 8. 2. 2020 9:40
        ];
        $out = [
            '$3.$2.$1, $4',
            '$3.$2.$1',
            '$3-$2-$1',
            '$3-$2-$1, $4',
            '$3-$2-$1',
            '$3-$2-$1, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function contains($field, $node = ''): string
    {
        $field = (array)$field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'contains(normalize-space(' . $node . '),"' . $s . '")';
            }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array)$field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'normalize-space(' . $node . ')="' . $s . '"';
            }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
            }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array)$field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
                return preg_quote($s, '/');
            }, $field)) . ')';
    }
}
