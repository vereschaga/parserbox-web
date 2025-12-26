<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MyHertzReservation extends \TAccountChecker
{
    public $mailFiles = "hertz/it-216338340.eml, hertz/it-219709213.eml, hertz/it-220817196.eml, hertz/it-221645820.eml, hertz/it-257377275-dollar.eml, hertz/it-283603750.eml, hertz/it-879875471.eml";
    public $subjects = [
        'My Hertz Reservation ',
        'Your Hertz Reservation',
        // cs
        'Moje Hertz Rezervace',
        // fr
        'Ma réservation Hertz',
        // it
        'La mia prenotazione Hertz',
        // nl
        'Mijn Hertz Reservering',
        // es
        'Mi Reserva de Hertz',
        'Mi Reserva Hertz',
        // ko
        '나의 Hertz 예약',
        // no
        'Min Hertz-reservasjon',
        // fi
        'Hertz varaukseni',
        // sv
        'Min bokning hos Hertz',
        // pt
        'A minha Reserva Hertz',
        // de
        'Meine Hertz Reservierung',
    ];
    public $lang = '';

    public static $dictionary = [
        "sv" => [ //it-283603750.eml
            'confNumber'           => ['Bekräftelse', 'BEKRÄFTELSE'],
            'Hi'                   => 'Hej',
            'Member Number:'       => 'Medlemsnummer:',
            'Modify My Rental'     => 'Ändra min hyra',
            'or similar'           => ['eller motsvarande'],
            /* Price */
            'Total'                => 'Total',
            'Subtotal'             => 'Subtotal',
            'Fees and Surcharges'  => ['Inkluderat'],
            'Taxes'                => ['Taxes', 'Skatt/Moms'],
            // 'Extras - ' => '',
            /* Itinerary */
            'Your Trip Itinerary'  => ['Din Resväg', 'Din Bokningsinformation'],
            'Pickup Location'      => ['Upphämtningsplats', 'UPPHÄMTNINGSPLATS'],
            'Pickup Date & Time'   => ['Upphämtningsdatum och tid', 'UPPHÄMTNINGSDATUM OCH TID'],
            'Location Hours'       => ['Öppettider', 'ÖPPETTIDER'],
            'Drop-off Location'    => ['Återlämningsplats', 'ÅTERLÄMNINGSPLATS'],
            'Drop-off Date & Time' => ['Återlämningsdatum och tid', 'ÅTERLÄMNINGSDATUM OCH TID'],

            'Reserve Another'      => ['Ge ett pris…ny bokning', 'Gör en ny bokning'],
            // 'The Hertz Corporation' => '',
        ],
        "de" => [ // it-219709213.eml
            'confNumber'           => ['Bestätigung', 'BESTÄTIGUNG'],
            'Hi'                   => 'Hi',
            'Member Number:'       => 'Mitgliedsnummer:',
            'Modify My Rental'     => 'Meine Miete ändern',
            'or similar'           => 'oder ähnlich',
            /* Price */
            'Total'                => 'Total',
            'Subtotal'             => 'Zwischensumme',
            'Fees and Surcharges'  => ['Inbegriffen'],
            'Taxes'                => ['Steuern', 'Taxes'],
            // 'Extras - ' => '',
            /* Itinerary */
            'Your Trip Itinerary'  => 'Ihre Reiseroute',
            'Pickup Location'      => ['Abholstation', 'ABHOLSTATION'],
            'Pickup Date & Time'   => ['Datum und Uhrzeit der Abholung', 'DATUM UND UHRZEIT DER ABHOLUNG'],
            'Location Hours'       => ['Öffnungszeiten', 'ÖFFNUNGSZEITEN'],
            'Drop-off Location'    => ['Abgabestation', 'ABGABESTATION'],
            'Drop-off Date & Time' => ['Abgabedatum und -uhrzeit', 'ABGABEDATUM UND -UHRZEIT'],

            'Reserve Another'       => 'Weitere Reservierung',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "pt" => [ // it-221645820.eml
            'confNumber'            => ['CONFIRMAÇÃO', 'Confirmação'],
            'Hi'                    => 'Olá',
            'Member Number:'        => 'Número de sócio:',
            'Modify My Rental'      => ['Modificar minha reserva', 'Modificar o meu Aluguer'],
            'or similar'            => ['ou similar'],
            /* Price */
            'Total'                 => 'Total',
            'Subtotal'              => 'Subtotal',
            'Fees and Surcharges'   => ['Incluídos'],
            'Taxes'                 => 'Taxes',
            // 'Extras - '   => '',
            /* Itinerary */
            'Your Trip Itinerary'   => ['Itinerário De Sua Viagem', 'O Seu Itinerário De Viagem'],
            'Pickup Location'       => ['LOCAL DE RETIRADA', 'Local de retirada', 'Loja de Levantamento'],
            'Pickup Date & Time'    => ['DIA E HORA DE RETIRADA', 'Dia e hora de retirada', 'Data e Hora de Levantamento'],
            'Location Hours'        => ['HORÁRIO DE LOCALIZAÇÃO', 'Horário de localização', 'Horário de Funcionamento da Loja'],
            'Drop-off Location'     => ['LOCAL DE DEVOLUÇÃO', 'Local de devolução', 'Loja de Devolução'],
            'Drop-off Date & Time'  => ['DIA E HORA DE DEVOLUÇÃO', 'Dia e hora de devolução', 'Data e Hora de Devolução'],

            'Reserve Another'       => ['FAZER OUTRA RESERVA', 'Reservar Outra Viatura'],
            'The Hertz Corporation' => 'The Hertz Corporaciónn',
        ],
        "ko" => [ // it-220817196.eml
            'confNumber'            => ['확인'],
            'Hi'                    => '안녕하세요',
            'Member Number:'        => '회원번호:',
            'The Hertz Corporation' => 'The Hertz Corporation',
            'Modify My Rental'      => '예약 수정하기',
            //'or similar' => [''],
            /* Price */
            'Total'               => '총계',
            'Subtotal'            => '소계',
            'Fees and Surcharges' => ['요금 포함사항'],
            //'Taxes' => '',
            // 'Extras - '   => '',
            /* Itinerary */
            'Your Trip Itinerary'   => '여행 일정',
            'Reserve Another'       => '요금 조회...다른 여정 예약하기',
            'Pickup Location'       => ['픽업 영업소'],
            'Pickup Date & Time'    => ['픽업 날짜 및 시간'],
            'Location Hours'        => ['영업소 영업 시간'],
            'Drop-off Location'     => ['반납 영업소'],
            'Drop-off Date & Time'  => ['반납 날짜 및 시간'],
        ],
        "cs" => [
            'confNumber'           => ['Potvrzení'],
            'Hi'                   => 'Dobrý den',
            // 'Member Number:' => '',
            'Modify My Rental'      => 'Upravit můj pronájem',
            //'or similar' => [''],
            // Price
            'Total'    => 'Celkem',
            'Subtotal' => 'Mezisoučet',
            //'Fees and Surcharges' => [''],
            //'Taxes' => '',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => 'Itinerář Vaší Cesty',
            'Pickup Location'       => ['Místo vyzvednutí'],
            'Pickup Date & Time'    => ['Datum a čas vyzvednutí'],
            'Location Hours'        => ['Místní čas'],
            'Drop-off Location'     => ['Místo vrácení'],
            'Drop-off Date & Time'  => ['Datum a čas vrácení'],

            'Reserve Another'       => 'VYPOČTI...REZERVUJ JINÝ',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "fr" => [
            'confNumber'            => ['Confirmation'],
            'Hi'                    => 'Bonjour',
            'Member Number:'        => 'Numéro de Membre:',
            'Modify My Rental'      => 'Modifier ma location',
            'or similar'            => ['ou similaire'],
            // Price
            'Total'                 => 'Total',
            'Subtotal'              => 'Sous-total',
            'Fees and Surcharges'   => ['Inclus', 'Frais et suppléments'],
            'Taxes'                 => 'Taxes',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => 'Votre Itinéraire De Voyage',
            'Pickup Location'       => ['Agence de départ'],
            'Pickup Date & Time'    => ['Départ date et heure'],
            'Location Hours'        => ['Horaires de l\'Agence'],
            'Drop-off Location'     => ['Agence de retour'],
            'Drop-off Date & Time'  => ['Retour date et heure'],

            'Reserve Another'       => 'Nouvelle réservation',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "no" => [
            'confNumber'            => ['Bekreftelse'],
            'Hi'                    => 'Hei',
            'Member Number:'        => 'Medlemsnummer:',
            'Modify My Rental'      => 'Endre min bestilling',
            'or similar'            => ['eller lignende'],
            // Price
            'Total'                 => 'Total',
            'Subtotal'              => 'Subtotal',
            'Fees and Surcharges'   => ['Inkludert'],
            'Taxes'                 => 'Taxes',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => 'Din Reiserute',
            'Pickup Location'       => ['Hentested'],
            'Pickup Date & Time'    => ['Hentedato og tidspunkt'],
            'Location Hours'        => ['Åpningstider'],
            'Drop-off Location'     => ['Leveringssted'],
            'Drop-off Date & Time'  => ['Leveringsdato og tidspunkt'],

            'Reserve Another'       => 'reserver ny',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "it" => [
            'confNumber'            => ['N. PRENOTAZIONE'],
            'Hi'                    => 'Gentile',
            'Member Number:'        => 'Numero membro:',
            'Modify My Rental'      => 'Modifica/Cancella la mia Prenotazione',
            'or similar'            => ['o similare'],
            // Price
            'Total'               => 'Totale',
            'Subtotal'            => 'Subtotale',
            'Fees and Surcharges' => ['Tasse e Supplementi'],
            'Taxes'               => 'Tasse',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => 'Il Tuo Itinerario',
            'Pickup Location'       => ['Agenzia di ritiro'],
            'Pickup Date & Time'    => ['Data e ora di ritiro'],
            'Location Hours'        => ['Orari di apertura dell\'agenzia'],
            'Drop-off Location'     => ['AGENZIA DI RICONSEGNA'],
            'Drop-off Date & Time'  => ['DATA E ORA DI RICONSEGNA'],

            'Reserve Another'       => 'Fai un\'altra prenotazione',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "nl" => [
            'confNumber'            => ['Bevestigingsnummer'],
            'Member Number:'        => 'lidmaarschapnummer:',
            'Hi'                    => 'Hallo',
            'Modify My Rental'      => 'Mijn reservering wijzigen',
            'or similar'            => ['of gelijksoortig'],
            // Price
            'Total'                 => 'Totaal',
            'Subtotal'              => 'Subtotaal',
            'Fees and Surcharges'   => ['Kosten en toeslagen'],
            'Taxes'                 => 'Belastingen',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => 'Reisplan',
            'Pickup Location'       => ['OPHAALLOCATIE'],
            'Pickup Date & Time'    => ['OPHAALDATUM & -TIJD'],
            'Location Hours'        => ['Openingstijden'],
            'Drop-off Location'     => ['Inleverlocatie'],
            'Drop-off Date & Time'  => ['INLEVERDATUM & -TIJD'],

            'Reserve Another'       => 'Maak een nieuwe reservering',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "es" => [
            'confNumber'            => ['confirmación', 'Confirmación'],
            'Member Number:'        => 'Número de miembro:',
            'Hi'                    => 'Hola',
            'Modify My Rental'      => ['Modificar mi alquiler', 'Modificar mi reserva'],
            'or similar'            => ['o similar'],
            // Price
            'Total'                 => 'Total',
            'Subtotal'              => ['Total Parcial', 'Subtotal'],
            'Taxes'                 => ['Impuestos', 'Taxes'],
            'Fees and Surcharges'   => ['Incluido'],
            // 'Extras - ' => '',
            // Itinerary
            'Your Trip Itinerary'   => ['Itinerario De Viaje', 'Itinerario De Su Viaje'],
            'Pickup Location'       => ['Lugar de recogida'],
            'Pickup Date & Time'    => ['Fecha y hora de recogida', 'Día y hora de recogida'],
            'Location Hours'        => ['Ubicación Horas', 'Horario de la localidad'],
            'Drop-off Location'     => ['Punto de entrega', 'Lugar de devolución'],
            'Drop-off Date & Time'  => ['Fecha y hora de entrega', 'Día y hora de devolución'],

            'Reserve Another'       => ['Hacer otra reserva', 'HACER OTRA RESERVA'],
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "fi" => [
            'confNumber'            => ['VAHVISTUSNUMERO'],
            'Member Number:'        => 'Jäsennumero:',
            'Hi'                    => 'Hei',
            'Modify My Rental'      => 'Muokkaa/peruuta varaus',
            'or similar'            => ['tai vastaava'],
            // Price
            'Total'                 => 'Loppusumma',
            'Subtotal'              => 'Välisumma',
            'Fees and Surcharges'   => ['Sisältyy'],
            'Taxes'                 => 'Verot',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => 'Matkasuunnitelmasi',
            'Pickup Location'       => ['Noutotoimipiste'],
            'Pickup Date & Time'    => ['Noutopäivä & -aika'],
            'Location Hours'        => ['Toimipisteen aukioloajat'],
            'Drop-off Location'     => ['Palautustoimipiste'],
            'Drop-off Date & Time'  => ['Palautuspäivä & -aika'],

            'Reserve Another'       => 'Tee uusi varaus',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "zh" => [
            'confNumber'            => ['确认'],
            'Member Number:'        => '会员号:',
            'Hi'                    => '您好',
            'Modify My Rental'      => '修改订单',
            'or similar'            => ['或类似'],
            // Price
            'Total'                 => '总价',
            'Subtotal'              => '小计',
            'Fees and Surcharges'   => ['附加费'],
            'Taxes'                 => 'Taxes',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => '您的行程',
            'Pickup Location'       => ['取车门店'],
            'Pickup Date & Time'    => ['取车日期和时间'],
            'Location Hours'        => ['门店营业时间'],
            'Drop-off Location'     => ['还车门店'],
            'Drop-off Date & Time'  => ['还车日期和时间'],

            'Reserve Another'       => '继续预订另一辆车',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],
        "pl" => [
            'confNumber'            => ['Potwierdzenie'],
            'Member Number:'        => 'Numer członkowski:',
            'Hi'                    => 'Cześć',
            'Modify My Rental'      => 'Dokonaj zmian w Wynajmie',
            // 'or similar'            => ['或类似'],
            // Price
            'Total'                 => 'Suma',
            'Subtotal'              => 'Suma Częściowa',
            // 'Fees and Surcharges'   => ['附加费'],
            'Taxes'                 => 'Taxes',
            // 'Extras - '   => '',
            // Itinerary
            'Your Trip Itinerary'   => 'Twój Plan Podróży',
            'Pickup Location'       => ['Miejsce odbioru'],
            'Pickup Date & Time'    => ['Data i godzina odbioru'],
            'Location Hours'        => ['Godziny otwarcia'],
            'Drop-off Location'     => ['Miejsce zwrotu'],
            'Drop-off Date & Time'  => ['Data i miejsce zwrotu'],

            'Reserve Another'       => 'Kolejna rezerwacja',
            'The Hertz Corporation' => 'The Hertz Corporation',
        ],

        "en" => [ // it-216338340.eml, it-257377275-dollar.eml
            'confNumber'            => ['Confirmation', 'CONFIRMATION'],
            'Member Number:'        => 'Member Number:',
            'Hi'                    => ['Hi', 'Thanks,'],
            'Modify My Rental'      => ['Modify My Rental', 'Edit My Rental'],
            'or similar'            => ['or similar', 'Tesla'],
            // Price
            'Total'                 => 'Total',
            'Subtotal'              => 'Subtotal',
            'Fees and Surcharges'   => ['Fees and Surcharges', 'Included'],
            'Taxes'                 => 'Taxes',
            'Extras - '             => 'Extras - ',
            // Itinerary
            'Your Trip Itinerary'   => ['Your Trip Itinerary', 'Your Itinerary'],
            'Pickup Location'       => ['Pickup Location', 'PICKUP LOCATION', 'Pick-up Location', 'PICK-UP LOCATION'],
            'Pickup Date & Time'    => ['Pickup Date & Time', 'PICKUP DATE & TIME', 'Pick-up Time', 'PICK-UP TIME'],
            'Location Hours'        => ['Location Hours', 'LOCATION HOURS'],
            'Drop-off Location'     => ['Drop-off Location', 'DROP-OFF LOCATION', 'Return Location', 'RETURN LOCATION'],
            'Drop-off Date & Time'  => ['Drop-off Date & Time', 'DROP-OFF DATE & TIME', 'Return Time', 'RETURN TIME'],

            'Reserve Another'       => ['Reserve Another', 'Start a New Reservation'],
            'The Hertz Corporation' => ['The Hertz Corporation', 'My Hertz Reservation'],
        ],
    ];

    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@emails.hertz.com') !== false) {
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
        return $this->assignProvider($parser->getHeaders()) && $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]emails\.hertz\.com$/', $from) > 0;
    }

    public function ParseCar(Email $email): void
    {
        $xpathNoDisplay = 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none") or contains(@class,"deskTop-hidden")]';

        $r = $email->add()->rental();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }
        $r->general()->traveller($traveller);

        $r->general()->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{8,}$/"));

        $xpathCarImage = "//text()[{$this->eq($this->t('confNumber'))}]/following::*[count(tr[normalize-space()])<3 and tr[1][normalize-space()='']/descendant::img and not({$xpathNoDisplay})][1]";
        $xpathCarImageV2 = "//text()[{$this->eq($this->t('confNumber'))}]/following::tr[count(*[normalize-space()])=2 and *[normalize-space()][2][count(descendant::text()[normalize-space()])=2 and descendant::img] and not({$xpathNoDisplay})][1]/*[normalize-space()][2]"; // it-257377275-dollar.eml
        $image = $this->http->FindSingleNode($xpathCarImage . "/tr[1]/descendant::img[contains(@src,'vehicle') or contains(@src,'rendition')]/@src")
            ?? $this->http->FindSingleNode($xpathCarImageV2 . "/descendant::img[contains(@src,'vehicle') or contains(@src,'rendition')]/@src");
        $carType = $this->http->FindSingleNode($xpathCarImage . "/tr[2][normalize-space()]")
            ?? $this->http->FindSingleNode($xpathCarImageV2 . "/descendant::text()[normalize-space()][1]");
        $carModel = $this->http->FindSingleNode($xpathCarImage . "/tr[3][normalize-space()]")
            ?? $this->http->FindSingleNode($xpathCarImageV2 . "/descendant::text()[normalize-space()][2]");

        $r->car()
            ->image($image, false, true)
            ->type($carType, false, true)
            ->model($carModel);

        $priceText = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match("/(?:^|\D\s*)(?<amount>\d[,.‘\'\d ]*)\s*(?<currencyCode>[A-Z]{3})$/u", $priceText, $matches) // $153.95 USD
            || preg_match("/^(?<amount>\d[,.‘\'\d ]*)$/u", $priceText, $matches) // 0.00
        ) {
            if (!array_key_exists('currencyCode', $matches)) {
                $matches['currencyCode'] = null;
            }

            $r->price()
                ->total(PriceHelper::parse($matches['amount'], $matches['currencyCode']))
                ->currency($matches['currencyCode'], false, true);

            $cost = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Subtotal'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (!empty($matches['currencyCode']) && preg_match('/(?:^|\D\s*)(?<amount>\d[,.‘\'\d ]*?)[ ]*' . preg_quote($matches['currencyCode'], '/') . '$/u', $cost, $m)
                || preg_match("/^(?<amount>\d[,.‘\'\d ]*)$/u", $cost, $m) // 0.00
            ) {
                $r->price()->cost(PriceHelper::parse($m['amount'], $matches['currencyCode']));
            }

            $xpathTaxes = "//tr[{$this->eq($this->t('Taxes'))} and not(.//tr[normalize-space()])]/following::tr[normalize-space()][1]/descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1] ]";

            foreach ($this->http->XPath->query($xpathTaxes) as $taxRoot) {
                $taxName = $this->http->FindSingleNode("*[normalize-space()][1]", $taxRoot, true, '/^(.+?)[\s:：]*$/u');
                $taxVal = $this->http->FindSingleNode("*[normalize-space()][2]", $taxRoot, true, '/^.*\d.*$/');

                if (!empty($matches['currencyCode']) && preg_match('/(?:^|\D\s*)(?<amount>\d[,.‘\'\d ]*?)[ ]*' . preg_quote($matches['currencyCode'], '/') . '$/u', $taxVal, $m)
                    || preg_match("/^(?<amount>\d[,.‘\'\d ]*)$/u", $taxVal, $m) // 0.00
                ) {
                    $r->price()->fee($taxName, PriceHelper::parse($m['amount'], $matches['currencyCode']));
                }
            }

            $feeNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Fees and Surcharges'))}]/ancestor::tr[1]/following::tr[normalize-space()][1]/ancestor::table[1]/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1] ]");

            foreach ($feeNodes as $feeRoot) {
                $feeCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRoot, true, '/^.*\d.*$/');

                if (!empty($matches['currencyCode']) && preg_match('/(?:^|\D\s*)(?<amount>\d[,.‘\'\d ]*?)[ ]*' . preg_quote($matches['currencyCode'], '/') . '$/u', $feeCharge, $m)
                    || preg_match("/^(?<amount>\d[,.‘\'\d ]*)$/u", $cost, $m) // 0.00
                ) {
                    $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRoot, true, '/^(.+?)[\s:：]*$/u');
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $matches['currencyCode']));
                }
            }

            // Extras
            $feeNodes = $this->http->XPath->query("//tr[{$this->starts($this->t('Extras - '))}]/following-sibling::tr[normalize-space()][1]/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1] ]");

            foreach ($feeNodes as $feeRoot) {
                $feeCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRoot, true, '/^.*\d.*$/');

                if (!empty($matches['currencyCode']) && preg_match('/(?:^|\D\s*)(?<amount>\d[,.‘\'\d ]*?)[ ]*' . preg_quote($matches['currencyCode'], '/') . '$/u', $feeCharge, $m)
                    || preg_match("/^(?<amount>\d[,.‘\'\d ]*)$/u", $cost, $m) // 0.00
                ) {
                    $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRoot, true, '/^(.+?)[\s:：]*$/u');
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $matches['currencyCode']));
                }
            }
        }

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Trip Itinerary'))}]/following::text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::*[ ../descendant::text()[{$this->eq($this->t('Pickup Date & Time'))}] ][1]", null, true, "/{$this->opt($this->t('Pickup Location'))}\s*(.+)/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Trip Itinerary'))}]/following::text()[{$this->eq($this->t('Pickup Date & Time'))}][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Pickup Date & Time'))}\s*(.+)/")))
            ->openingHours($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Trip Itinerary'))}]/following::text()[{$this->eq($this->t('Location Hours'))}][1]/ancestor::table[1]", null, true, "/{$this->opt($this->t('Location Hours'))}\s*(.+)/"), false, true);

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Trip Itinerary'))}]/following::text()[{$this->eq($this->t('Drop-off Location'))}]/ancestor::*[ ../descendant::text()[{$this->eq($this->t('Drop-off Date & Time'))}] ][1]", null, true, "/{$this->opt($this->t('Drop-off Location'))}\s*(.+)/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Trip Itinerary'))}]/following::text()[{$this->eq($this->t('Drop-off Date & Time'))}][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Drop-off Date & Time'))}\s*(.+)/")))
            ->openingHours($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Trip Itinerary'))}]/following::text()[{$this->eq($this->t('Location Hours'))}][2]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Location Hours'))}\s*(.+)/"), false, true);

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[{$this->eq($this->t('Member Number:'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Member Number:'))}\s*(\d{5,})/");

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        $this->assignLang();

        $this->ParseCar($email);
        $email->setType('MyHertzReservation' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

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

    public static function getEmailProviders()
    {
        return ['dollar', 'hertz', 'thrifty'];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@emails.dollar.com') !== false
            || stripos($headers['subject'], 'Dollar Reservation') !== false
            || $this->http->XPath->query('//a[contains(@href,".dollar.com/") or contains(@href,"emails.dollar.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for your reservation with Dollar")]')->length > 0
        ) {
            $this->providerCode = 'dollar';

            return true;
        }

        if (stripos($headers['from'], '@emails.thrifty.com') !== false
            || stripos($headers['subject'], 'Thrifty Reservation') !== false
            || $this->http->XPath->query('//a[contains(@href,".thrifty.com/") or contains(@href,"emails.thrifty.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for your reservation with Thrifty")]')->length > 0
        ) {
            $this->providerCode = 'thrifty';

            return true;
        }

        if (stripos($headers['from'], '@emails.hertz.com') !== false
            || stripos($headers['subject'], 'My Hertz Reservation') !== false
            || $this->http->XPath->query('//a[contains(@href,".hertz.com/") or contains(@href,"emails.hertz.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"The Hertz Corporation. All rights reserved")]')->length > 0
        ) {
            $this->providerCode = 'hertz';

            return true;
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));
        $str = str_replace(['오전', '오후'], ['AM', 'PM'], $str);
        $in = [
            "#^\w+\.?\,\s*([[:alpha:]]+)\.?\s*(\d+)\,\s*(\d{4})\s*(?:\,|at|um|às|för|v| à | til | a | om |@|'|时间| a la\(s\)| w )\s*([\d\:]+\s*A?\.?P?\.?M?\.?)$#ui", //Fri, Nov 25, 2022, 10:00 AM
            "#^\w+\.?\,\s*(\d+)\s*([[:alpha:]]+)[.]?\,\s*(\d{4})\s*(?:\,|at|um|às|för|v| à | til | a | om |@|'|时间| a la\(s\)| w )\s*([\d\:]+\s*A?P?M?)$#u", //Sat, 05 Nov, 2022 at 16:00
            "#^\w+\,\s*(\d+)\s*[[:alpha:]]+\s*(\d+)\,\s*(\d{4})\s*[@]\s*([\d\:]+(?:\s*[AP]M)?)$#u", //일, 10월 30, 2022 @ 10:30
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$2.$1.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Your Trip Itinerary'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Your Trip Itinerary'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
