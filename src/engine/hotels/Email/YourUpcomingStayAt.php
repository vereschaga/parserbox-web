<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourUpcomingStayAt extends \TAccountChecker
{
    public $mailFiles = "hotels/it-10709073.eml, hotels/it-137161511-de.eml, hotels/it-145886697-pt.eml, hotels/it-187602787.eml, hotels/it-2862405.eml, hotels/it-3313160.eml, hotels/it-3313645.eml, hotels/it-33935816.eml, hotels/it-34011521.eml, hotels/it-3459265.eml, hotels/it-3459410.eml"; // +1 bcdtravel(html)[sv]
    public static $dict = [
        'en' => [
            //            'Confirmation number' => '',
            //            'Check in' => '',
            //            'Check out' => '',
            //            'Guests' => '',
            //            'adults' => '',
            //            'children' => '',
            // 'Number of Rooms' => '',
            //            'Your booking is guaranteed' => '',
            //            'Membership Number' => '',
            //            'View on map' => '',
            //            'Contact this property directly:' => '',
            //            'Total' => '',
            // Statements
            'UntilNextFreeNight' => [
                [
                    'text' => [
                        'away from earning another reward1 night',
                        'away from earning another reward¹ night',
                        'away from earning another free¹ night',
                        'away from earning another free1 night',
                        'stamps away from earning a reward1 night',
                    ],
                    'regexp' => 'You are (?<stamp>\d+) (?:stamps?|nights?) away from earning', ],
                //                You are 5 stamps away from earning a reward1 night!
                //                                ['text' => '', 'regexp' => '(?<stamp>\d+)'],
            ],
        ],
        'pt' => [ // it-145886697-pt.eml
            'Hi'                              => 'Olá',
            'Confirmation number'             => 'Número de confirmação',
            'Check in'                        => 'Check-in',
            'Check out'                       => 'Check-out',
            'Guests'                          => 'Hóspedes',
            'adults'                          => 'adultos',
            'children'                        => 'crianças',
            'Number of Rooms'                 => 'Número de quartos:',
            'Your booking is guaranteed'      => 'Sua reserva está confirmada',
            'Membership Number'               => 'Número de associado',
            'View on map'                     => 'Ver no mapa',
            'Contact this property directly:' => 'Fale diretamente com o estabelecimento:',
            //			'Total' => '',
            // Statements
            'UntilNextFreeNight' => [
                ['text'      => ['selos para ganhar mais uma noite de recompensa1!', 'selos para ganhar uma noite de recompensa1', 'selo para ganhar uma noite de recompensa1'],
                    'regexp' => 'Você só precisa juntar (?<stamp>\d+) selos? para ganhar(?: mais)? uma noite de recompensa1!', ],
            ],
        ],
        'fr' => [
            'Hi'                  => 'Bonjour',
            'Confirmation number' => 'Numéro de confirmation',
            'Check in'            => 'Arrivée',
            'Check out'           => 'Départ',
            'Guests'              => 'Clients',
            'adults'              => 'adultes',
            //			'children'=>'',
            'Number of Rooms'                 => 'Nombre de chambres :',
            'Your booking is guaranteed'      => 'Votre réservation est confirmée',
            'Membership Number'               => 'Numéro de membre',
            'View on map'                     => ['Voir une carte', 'Affichez sur une carte', 'Afficher sur la carte'],
            'Contact this property directly:' => ['Veuillez contacter cet hébergement au numéro suivant :', 'Communiquer avec cet établissement directement :'],
            'Total'                           => 'Total',
            // Statements
            'UntilNextFreeNight' => [
                ['text'      => ['vignettes avant de recevoir une nuit bonus1', 'vignette avant de recevoir une nuit bonus1', 'vignettes avant de recevoir une autre nuit bonus1'],
                    'regexp' => 'Plus que (?<stamp>\d+) vignettes? avant de recevoir une (?:autre )?nuit bonus1', ],
            ],
        ],
        'sv' => [
            'Hi'                  => 'Hej',
            'Confirmation number' => 'Bekräftelsenummer',
            'Check in'            => 'Incheckning',
            'Check out'           => 'Utcheckning',
            'Guests'              => 'Antal gäster',
            'adults'              => 'vuxna',
            //			'children'=>'',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed'      => 'Din bokning är bekräftad',
            'Membership Number'               => 'Medlemsnummer',
            'View on map'                     => 'Visa på karta',
            'Contact this property directly:' => 'Kontakta boendet direkt på:',
            'Total'                           => 'Totalt pris',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => ['stämpel ifrån att tjäna en bonusnatt1!', 'stämplar ifrån att tjäna en bonusnatt1'], 'regexp' => 'Du är (?<stamp>\d+) (?:stämpel|stämplar) ifrån att tjäna en bonusnatt1'],
            ],
        ],
        'de' => [ // it-137161511-de.eml
            'Hi'                  => 'Hallo',
            'Confirmation number' => 'Bestätigungsnummer',
            'Check in'            => 'Anreise',
            'Check out'           => 'Abreise',
            'Guests'              => 'Gäste',
            'adults'              => 'Erwachsene',
            //			'children'=>'',
            'Number of Rooms'                 => 'Zimmeranzahl',
            'Your booking is guaranteed'      => 'Ihre Buchung ist jetzt garantiert',
            'Membership Number'               => 'Mitgliedsnummer',
            'View on map'                     => 'Auf Karte anzeigen',
            'Contact this property directly:' => 'Kontaktieren Sie diese Unterkunft direkt unter folgender Nummer:',
            'Total'                           => 'Gesamtbetrag',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => ['Stempel bis zu einer weiteren Prämiennacht1!', 'Stempel bis zu einer Prämiennacht1'], 'regexp' => 'Nur noch (?<stamp>\d+) Stempel bis zu einer(?: weiteren)? Prämiennacht1'],
            ],
        ],
        'ja' => [
            'Hi'                  => '様、',
            'Confirmation number' => '確認番号',
            'Check in'            => 'チェックイン',
            'Check out'           => 'チェックアウト',
            'Guests'              => '宿泊者',
            'adults'              => '大人',
            //			'children'=>'',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed'      => '予約が確定しました',
            'Membership Number'               => '会員番号',
            'View on map'                     => '地図を見る',
            'Contact this property directly:' => '施設の電話番号 ：',
            'Total'                           => '合計',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => '個で 1 泊分のボーナスステイ1を獲得できます !', 'regexp' => 'スタンプあと (?<stamp>\d+) 個で 1 泊分のボーナスステイ1を獲得できます'],
                ['text' => '個でボーナスステイ1を、さらにもう 1 泊獲得できます !', 'regexp' => 'スタンプあと (?<stamp>\d+) 個でボーナスステイ1を、さらにもう 1 泊獲得できます'],
            ],
        ],
        'zh' => [
            'Hi'                              => ' 您好，',
            'Confirmation number'             => '確認編號',
            'Check in'                        => '入住',
            'Check out'                       => '退房',
            'Guests'                          => '旅客',
            'adults'                          => '位成人',
            'children'                        => '位兒童',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed'      => '您的訂房已經確認',
            'Membership Number'               => '會員編號',
            'View on map'                     => '在地圖上檢視',
            'Contact this property directly:' => '直接與住宿聯絡：',
            'Total'                           => '總計',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => '晚就能獲得 1 晚免費¹ 住宿！', 'regexp' => '還差 (?<stamp>\d+) 晚就能獲得 1 晚免費'],
                ['text' => ['個印花就能再換 1 晚獎勵1住宿', '個印花就能換 1 晚獎勵1住宿'], 'regexp' => '(?:還差|再集) (?<stamp>\d+) 個印花就能再?換 1 晚獎勵1住宿'],
            ],
        ],
        'ko' => [
            // 'Hi' => '',
            'Confirmation number' => '예약 번호',
            'Check in'            => '체크인',
            'Check out'           => '체크아웃',
            'Guests'              => '숙박객',
            'adults'              => '성인 ',
            //			'children'=>'',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed'      => 'Votre réservation est confirmée',
            'Membership Number'               => 'Numéro de membre',
            'View on map'                     => ['Voir une carte', 'Affichez sur une carte'],
            'Contact this property directly:' => 'Veuillez contacter cet hébergement au numéro suivant :',
            'Total'                           => 'Total',
            // Statements
            // 'UntilNextFreeNight' => [
            //     ['text' => '', 'regexp' => ''],
            // ],
        ],
        'pl' => [
            // 'Hi' => '',
            'Confirmation number' => 'Numer potwierdzenia',
            'Check in'            => 'Zameldowanie',
            'Check out'           => 'Wymeldowanie',
            'Guests'              => 'Goście',
            'adults'              => 'dorośli',
            'children'            => 'dzieci',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed' => 'Rezerwacja została zagwarantowana',
            //            'Membership Number' => '',
            'View on map'                     => ['Wyświetl na mapie'],
            'Contact this property directly:' => 'Skontaktuj się z obiektem bezpośrednio:',
            'Total'                           => 'Suma',
            // Statements
            // 'UntilNextFreeNight' => [
            //     ['text' => '', 'regexp' => ''],
            // ],
        ],
        'no' => [
            'Hi'                  => 'Hei,',
            'Confirmation number' => 'Bekreftelsesnummer',
            'Check in'            => 'Innsjekking',
            'Check out'           => 'Utsjekking',
            'Guests'              => 'Gjester',
            'adults'              => 'voksne',
            //			'children'=>'',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed'      => 'Bestillingen din er garantert',
            'Membership Number'               => 'Medlemsnummer',
            'View on map'                     => ['Vis på kart'],
            'Contact this property directly:' => 'Kontakt overnattingsstedet direkte:',
            //            'Total' => '',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => 'stempler til for å få en bonusovernatting1', 'regexp' => 'Du trenger bare (?<stamp>\d+) stempler til for å få en bonusovernatting1'],
                // Du trenger bare 10 stempler til for å få en bonusovernatting1!
            ],
        ],
        'da' => [
            'Hi'                  => 'Hej',
            'Confirmation number' => 'Bekræftelsesnummer',
            'Check in'            => 'Indtjekning',
            'Check out'           => 'Udtjekning',
            'Guests'              => 'Gæster',
            'adults'              => 'voksne',
            'children'            => 'børn',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed'      => 'Din reservation er sikret',
            'Membership Number'               => 'Medlemsnummer',
            'View on map'                     => ['Se på kort'],
            'Contact this property directly:' => 'Kontakt overnatningsstedet direkte:',
            //            'Total' => 'Suma',
            // Statements
            'UntilNextFreeNight' => [
                ['text'      => ['stempler fra at optjene en bonusnat1', 'stempler fra at optjene endnu en bonusnat1'],
                    'regexp' => 'Du er (?<stamp>\d+) stempler fra at optjene(?: endnu)? en bonusnat', ],
            ],
        ],
        'nl' => [
            'Hi'                              => 'Beste',
            'Confirmation number'             => 'Bevestigingsnummer',
            'Check in'                        => 'Inchecken',
            'Check out'                       => 'Uitchecken',
            'Guests'                          => 'Gasten',
            'adults'                          => 'volwassenen',
            'children'                        => 'kinderen',
            'Number of Rooms'                 => 'Aantal kamers',
            'Your booking is guaranteed'      => 'Je boeking is gegarandeerd',
            'Membership Number'               => 'Lidmaatschapsnummer',
            'View on map'                     => ['Bekijk op kaart'],
            'Contact this property directly:' => 'Neem rechtstreeks contact op met deze accommodatie:',
            //            'Total' => 'Suma',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => 'stempler fra at optjene en bonusnat1', 'regexp' => 'Du er (?<stamp>\d+) stempler fra at optjene en bonusnat'],
                ['text'      => ['stempel verwijderd van een bonusnacht1', 'stempels verwijderd van een bonusnacht1', 'stempels verwijderd van nog een bonusnacht1'],
                    'regexp' => 'Je bent (?<stamp>\d+) (?:stempel|stempels) verwijderd van (?:nog )?een bonusnacht1', ],
                //                                                stempels verwijderd van nog een bonusnacht1
            ],
        ],
        'it' => [
            'Hi'                  => 'Ciao',
            'Confirmation number' => 'Numero di conferma',
            'Check in'            => 'Check-in',
            'Check out'           => 'Check-out',
            'Guests'              => 'Ospiti',
            'adults'              => 'adulti',
            //			'children'=>'',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed'      => 'La tua prenotazione è garantita',
            'Membership Number'               => 'Numero di iscrizione',
            'View on map'                     => 'Mostra sulla mappa',
            'Contact this property directly:' => 'Contatta direttamente la struttura al numero:',
            //            'Total' => 'Suma',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => 'timbri e guadagnerai un’altra notte bonus1', 'regexp' => 'Ancora (?<stamp>\d+) timbri e guadagnerai un’altra notte bonus'],
            ],
        ],
        'es' => [
            'Hi'                  => 'Hola,',
            'Confirmation number' => 'Número de confirmación',
            'Check in'            => 'Check-in',
            'Check out'           => 'Check-out',
            'Guests'              => 'Huéspedes',
            'adults'              => 'adultos',
            //			'children'=>'',
            'Number of Rooms'            => 'Cantidad de habitaciones',
            'Your booking is guaranteed' => 'Tu reservación está garantizada',
            //            'Membership Number' => 'Numero di iscrizione',
            'View on map'                     => 'Ver en el mapa',
            'Contact this property directly:' => 'Comunícate directamente con este establecimiento:',
            //            'Total' => 'Suma',
            // Statements
            'UntilNextFreeNight' => [
                ['text' => 'sellos de obtener una noche de recompensa1', 'regexp' => 'Estás a (?<stamp>\d+) sellos de obtener una noche de recompensa1'],
            ],
        ],
        'id' => [
            'Hi' => 'Halo',
            //            'Confirmation number' => '',
            'Check in'            => 'Check-in',
            'Check out'           => 'Check-out',
            'Guests'              => 'Tamu',
            'adults'              => 'dewasa',
            //			'children'=>'',
            // 'Number of Rooms' => '',
            'Your booking is guaranteed' => 'Pemesanan Anda telah dijamin',
            //            'Membership Number' => 'Numero di iscrizione',
            'View on map'                     => 'Lihat di peta',
            'Contact this property directly:' => 'Langsung hubungi properti ini:',
            //            'Total' => 'Suma',
            // Statements
            'UntilNextFreeNight' => [
                //                 ['text' => 'sellos de obtener una noche de recompensa1', 'regexp' => 'Estás a (?<stamp>\d+) sellos de obtener una noche de recompensa1'],
            ],
        ],
    ];

    private $reBody = [
        'en'    => ['Confirmation number', 'Guests'],
        'en2'   => ['Confirmation number', 'Number of Rooms'],
        'en3'   => ['Check in', 'Guests'],
        'pt'    => ['Número de confirmação', 'Hóspedes'],
        'pt2'   => ['Ver no mapa', 'Hóspedes'],
        'fr'    => ['Numéro de membre:', 'Clients'],
        'fr2'   => ['Numéro de confirmation', 'Clients'],
        'fr3'   => ['Afficher sur la carte', 'Clients'],
        'sv'    => ['Bekräftelsenummer', 'Antal gäster'],
        'sv2'   => ['Visa på karta', 'Antal gäster'],
        'de'    => ['Bestätigungsnummer', 'Gäste'],
        'de2'   => ['Bestätigungsnummer', 'Zimmeranzahl'],
        'de3'   => ['Anreise', 'Gäste'],
        'ja'    => ['確認番号', '宿泊者'],
        'ja2'   => ['地図を見る', '宿泊者'],
        'zh'    => ['確認編號', '旅客'],
        'zh2'   => ['在地圖上檢視', '旅客'],
        'ko'    => ['예약 번호', '숙박객'],
        'pl'    => ['Numer potwierdzenia', 'Goście'],
        'pl2'   => ['Wyświetl na mapie', 'Goście'],
        'no'    => ['Innsjekking', 'Gjester'],
        'da'    => ['Indtjekning', 'Gæster'],
        'nl'    => ['Inchecken', 'Gasten'],
        'it'    => ['Mostra sulla mappa', 'Ospiti'],
        'es'    => ['Ver en el mapa', 'Cantidad de habitaciones'],
        'es2'   => ['Ver en el mapa', 'Huéspedes'],
        'id'    => ['Lihat di peta', 'Tamu'],
    ];
    private $reSubject = [
        'pt' => ['Sua próxima hospedagem em'], // R$=BRL
        'en' => ['Your upcoming stay at'],
        'fr' => ['Votre prochain séjour à'],
        'sv' => ['Din kommande vistelse på'],
        'de' => ['Ihr bevorstehender Aufenthalt im'],
        'ja' => ['での滞在予定'],
        'zh' => ['您即將入住'],
        'ko' => ['펜타즈에서'],
        'pl' => ['Twój planowany pobyt w hotelu'],
        'no' => ['Ditt kommende opphold på'],
        'da' => ['Dit kommende ophold på'],
        'nl' => ['Uw komende verblijf bij'],
        'it' => ['Il tuo prossimo soggiorno presso'],
        'es' => ['Tu próxima estancia en el'],
        'id' => ['Masa menginap Anda yang akan datang di'],
    ];
    private $lang = '';

    private $USFormat = false;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
            $body = $this->http->Response['body'];
        }
        $this->assignLang($body);

        if (in_array($this->lang, ['en']) && !empty($this->http->FindSingleNode("(//text()[contains(.,'ºF')])[1]"))) {
            $this->USFormat = true;
        } elseif (in_array($this->lang, ['en'])) {
            $tds = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Weather update for')]/following::tr[normalize-space()][1]//td[not(.//td)][normalize-space()]", null, "#^\s*[A-Za-z]{3}\s*(\d{2}/\d{2})\s*$#"));
            $v1 = [];
            $v2 = [];

            foreach ($tds as $td) {
                $td = explode("/", $td);
                $v1[] = $td[0];
                $v2[] = $td[1];
            }
            $v1 = array_unique($v1);
            $v2 = array_unique($v2);

            if (count($v1) <= 2 && count($v2) >= 5) {
                $this->USFormat = true;
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
            $body = $this->http->Response['body'];
        }

        if ($this->http->XPath->query("//a[contains(@href,'hotels.com')]")->length === 0) {
            return false;
        }

        foreach ($this->reBody as $lang => $reBody) {
            if (
                stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $reBody[0] . '")]')->length > 0 && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $reBody[1] . '")]')->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        // info@mail.hotels.com
        return preg_match('/[.@]hote[li]s\.com/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]',
        ];

        $h = $email->add()->hotel();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,:?!]|$)/u"));

        if (in_array($this->lang, ['zh', 'ja'])) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Hi'))}]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Hi'))}$/u"));
        }

        $travellerNames = array_filter(preg_replace("/^\s*travell?er\s*$/i", '', $travellerNames));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if ($traveller) {
            $h->general()->traveller($traveller, false);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Confirmation number')) . "])[1]"))
            && empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Confirmation number')) . "]/following::text()[normalize-space()][position() < 5][" . $this->starts($this->t('Check in')) . "]"))) {
            // it-2862405.eml

            // Travel Agency
            $email->ota()
                ->confirmation($this->http->FindSingleNode("(//text()[" . $this->contains(preg_replace('/(.+)/', '$1:', $this->t('Confirmation number'))) . "]/ancestor::td[1])[1]", null, true, "#:\s+([A-Z\d]+)#"));

            // General
            $h->general()
                ->noConfirmation();

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check in')) . "]/preceding::a[1]"))
                ->address($this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check in')) . "]/preceding::a[1]/following::text()[normalize-space(.)][1]"))
            ;

            // Booked
            $dateIn = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check in')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
            $dateOut = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check out')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
            $this->detectDateUS($dateIn, $dateOut);
            $h->booked()
                ->checkIn($this->normalizeDate($dateIn))
                ->checkOut($this->normalizeDate($dateOut))
            ;

            $node = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Guests')) . "]/ancestor::tr[1]", null, true, "#:\s+(.+)#");

            if (preg_match("/" . $this->opt($this->t('adults')) . "\s+\-\s+(?<Guests>\d+)(?:\s*" . $this->opt($this->t('children')) . "\s+\-\s+(?<Kids>\d+))?/ui", $node, $m)) {
                $h->booked()
                    ->guests($m['Guests'])
                    ->kids($m['Kids'] ?? null, true, true)
                ;
            }
        } else {
            // it-10709073.eml

            // Travel Agency
            $otaConfirmationVal = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation number'))}]/ancestor::tr[1])[1]");

            if (preg_match("/^\s*({$this->opt($this->t('Confirmation number'))})\s*([-A-Z\d]{5,})\s*$/", $otaConfirmationVal, $m)) {
                $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
                $h->general()->noConfirmation();
            } elseif (!empty($this->http->FindSingleNode("//img[contains(@src, '/green_check.png') and contains(@src, '.hotels.com/')]/ancestor::*[normalize-space()][1]/following::text()[normalize-space()][1][" . $this->contains($this->t('Check in')) . "]"))) {
                $h->general()->noConfirmation();
            }

            // General
            if ($this->http->XPath->query("//text()[" . $this->contains($this->t('Your booking is guaranteed')) . "]")->length > 0) {
                $h->general()->status('Confirmed');
            }

            // Hotel
            $name = $this->http->FindSingleNode("//a[" . $this->eq($this->t('View on map')) . "]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()]");

            if (!empty($name)) {
                $address = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"' . $name . '")]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]');
            }

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//a[" . $this->eq($this->t('View on map')) . "]/ancestor::div[1][count(ancestor::*[1]/*) = 2][preceding-sibling::div[1][not(normalize-space()) and .//img]]/descendant::text()[normalize-space()][1]");

                if (!empty($name)) {
                    $address = $this->http->FindSingleNode("//a[" . $this->eq($this->t('View on map')) . "]/ancestor::div[1][count(ancestor::*[1]/*) = 2][preceding-sibling::div[1][not(normalize-space()) and .//img]]/descendant::text()[normalize-space()][2]");
                }
            }
            $h->hotel()
                ->name($name)
                ->address($address ?? null)
            ;

            // Phone
            $phone = $this->http->FindSingleNode("//a[{$this->eq($this->t('View on map'))}]/ancestor::table[1]", null, true, "/{$this->t('Contact this property directly:')}\s*({$patterns['phone']})/")
                ?? $this->http->FindSingleNode("//a[{$this->eq($this->t('View on map'))}]/ancestor::tr[1]/preceding-sibling::tr[1]", null, true, "/{$this->t('Contact this property directly:')}\s*({$patterns['phone']})/")
                ?? $this->http->FindSingleNode("//a[{$this->eq($this->t('View on map'))}]/ancestor::div[1]", null, true, "/{$this->t('Contact this property directly:')}\s*({$patterns['phone']})/")
            ;
            $h->hotel()->phone($phone, false, true);

            $dateIn = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check in')) . "]/ancestor::td[1]/following-sibling::td[1]");
            $dateOut = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check out')) . "]/ancestor::td[1]/following-sibling::td[1]");
            $this->detectDateUS($dateIn, $dateOut);
            $h->booked()
                ->checkIn($this->normalizeDate($dateIn))
                ->checkOut($this->normalizeDate($dateOut));

            $node = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Guests')) . "]/ancestor::td[1]/following-sibling::td[1]");

            if (preg_match("/" . $this->opt($this->t('adults')) . "[\s\-:]+(?<Guests>\d+)(?:[\s,，]*" . $this->opt($this->t('children')) . "[\s\-:]+(?<Kids>\d+))?/u", $node, $m)
                || preg_match("/(?<Guests>\d+)\s*" . $this->opt($this->t('adults')) . "\s*(?:[,，]\s*(?<Kids>\d+)\s*" . $this->opt($this->t('children')) . ")?/u", $node, $m)
            ) {
                $h->booked()
                    ->guests($m['Guests'])
                    ->kids($m['Kids'] ?? null, true, true)
                ;
            }

            $rooms = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Number of Rooms'))}] ]/*[normalize-space()][2]", null, true, '/^\d{1,3}$/');
            $h->booked()->rooms($rooms, false, true);
        }

        // Price
        $node = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->contains($this->t('Total')) . "]/ancestor::tr[1]", null, true, "/" . $this->opt($this->t('Total')) . "[\*]?\s*(.+)/"));

        if (!empty($node['Total'])) {
            $h->price()
                ->total($node['Total'])
                ->currency($node['Currency'])
            ;
        }

        // Program
        $account = $this->http->FindSingleNode("(//text()[" . $this->contains(preg_replace('/(.+)/', '$1:', $this->t('Membership Number'))) . "]/ancestor::*[self::td or self::div][1])[1]", null, true, "#:\s*([A-Z\d]+)#");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);

            $st = $email->add()->statement();
            $st
                ->setNumber($account)
                ->setNoBalance(true)
            ;

            if ($traveller) {
                $st->addProperty('Name', $traveller);
            }

            if (!empty($this->http->FindSingleNode("//text()[" . $this->contains(preg_replace('/(.+)/', '$1:', $this->t('Membership Number'))) . "]/preceding::"
                . "img[1][contains(@src, 'HotelsR-40H-gold.png') or contains(@src, 'HotelsR_40H_gold_Inv.png') or contains(@src, 'HotelsR-40H-gold-JP.png') or contains(@src, 'HoteisR-40H-gold.png')]/@src"))
            ) {
                $st->addProperty('Status', "Gold");
            }

            if (!empty($this->http->FindSingleNode("//text()[" . $this->contains(preg_replace('/(.+)/', '$1:', $this->t('Membership Number'))) . "]/preceding::"
                . "img[1][contains(@src, 'HotelsR-40H-silver.png') or contains(@src, 'HotelsR_40H_silver_Inv.png') or contains(@src, 'HotelsR-40H-silver-JP.png') or contains(@src, 'HoteisR-40H-silver.png')]/@src"))
            ) {
                $st->addProperty('Status', "Silver");
            }

            if (!empty($this->t('UntilNextFreeNight')) && is_array($this->t('UntilNextFreeNight'))) {
                foreach ($this->t('UntilNextFreeNight') as $d) {
                    if (!empty($d['text']) && !empty($d['regexp'])) {
                        $stamp = $this->http->FindSingleNode("//div[not(.//div) and " . $this->contains($d['text']) . "]");

                        if (empty($stamp)) {
                            $stamp = $this->http->FindSingleNode("//td[not(.//td) and " . $this->contains($d['text']) . "]");
                        }

                        if (preg_match("/" . $d['regexp'] . "/u", $stamp, $m) && isset($m['stamp'])) {
                            $st->addProperty('UntilNextFreeNight', $m['stamp']);
                        }
                    }
                }
            }
        }
    }

    private function detectDateUS(?string $dateIn, ?string $dateOut): void
    {
        if ((empty($this->normalizeDate($dateIn)) || empty($this->normalizeDate($dateOut)))
            && preg_match('/\b(\d{1,2})\/(\d{1,2})\/\d{4}\b/', $dateIn, $mIn)
            && preg_match('/\b(\d{1,2})\/(\d{1,2})\/\d{4}\b/', $dateOut, $mOut)
        ) {
            if ($this->USFormat === false && ($mIn[1] < 12 && $mIn[2] > 12 || $mOut[1] < 12 && $mOut[2] > 12)) {
                $this->USFormat = true;
            }
        }
        $this->logger->debug('Date Format United States: ' . ($this->USFormat ? 'YES' : 'NO'));
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('date in = '.print_r( $str,true));
        $date = preg_replace("#\s+(\d+)\s*([apm]+)$#i", " 0\\1:00 \\2", $str); // 8 PM -> 08:00 PM
        $date = preg_replace("#\b\d*(\d{2}:\d+)\b#", "\\1", $date);
        $date = preg_replace(["/\(\s*(\d{1,2})\s*(?:Uhr|uur|hrs|h)?\s*\)\s*$/i", '/\s+/'], [" \\1:00", ' '], $date); // (14) -> 14:00    |    (12 Uhr) -> 12:00; (14 uur); (11 hrs)
        $date = preg_replace("#\s*\((\d{1,2})\s*([AP]M)\)\s*$#i", ' $1:00 $2', $date); //(8 PM) -> 8:00 PM
        $date = preg_replace("#\s*\(\s*kl\.\s*(\d{1,2})\s*\)\s*$#i", ' $1:00', $date); //(kl. 15) -> 15:00
        $date = preg_replace("#\s*\(\s*(?:kl\.)?\s*(\d{1,2}) ?[\.h] ?(\d{2})\s*(?:uur)?\s*\)\s*$#i", ' $1:$2', $date); // (11.30 uur)  -> 11:30; (kl. 10.30);  (11 h 30)

        $date = preg_replace("#(\d+\/\d+\/\d{4}\s+\d+:\d+(?:\s*[AP]M)?).*$#", "$1", $date);

//        $this->logger->debug('date (replace time) = '.print_r( $date,true));
        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})$#", // 01/10/2018
            "#^(\d+)-(\d+)-(\d{4})\s+(\d+)$#", // 01-10-2018 12
            "#^(\d+)/(\d+)/(\d{4}) \(?(\d+) h\)?$#", // 01/10/2018 (8 h)
            "#^(\d+)/(\d+)/(\d{4}) \(?(\d+) h (\d+)\)?$#", // 01/10/2018 (8 h 00)

            "/^(\d{1,2})\/(\d{1,2})\/(\d{4})[\s(]+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)[)\s]*$/", // 01/10/2018 8:00 PM
            "/^(\d{1,2})\.(\d{1,2})\.(\d{4})[\s(]+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)[)\s]*$/", // 04.01.2019 12:00
            "#^(\d{4})\.(\d{1,2})\.(\d{1,2})\s+(\d+:\d+(?:\s*[AP]M)?)?$#", // 2020.6.21 14:00
            // Freitag, 17. Dezember 2021 15:00
            // Fredag 26. august 2022 (kl. 15) -> Fredag 26. august 2022 15:00
            // Martedì 9 agosto 2022 (10:30)
            // Friday, 2nd September 2022 (10:30 AM)
            "/^.*\b(\d{1,2})(?:st|nd|th)?[.\s]+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?[.\s]+(\d{4})[\s\(]+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*\)*\s*$/u",

            // Viernes, 15 de julio de 2022
            "/^.*\b(\d{1,2})[.\s]+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?[.\s]+(\d{4})\s*$/u",
            // 2022 年 8 月 22 日 (星期一) (15)   ->   2022 年 8 月 22 日 (星期一) 15:00
            "#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\D*\s+(\d{1,2}:\d{2})\s*$#", // 01/10/2018 (8 h 00)
            // Thursday, September 1st, 2022 (2 PM)
            "/^.*[\s,]+([[:alpha:]]+)\s+(\d{1,2})(?:st|nd|th)[\s,]+(\d{4})[\s\(]+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*\)*\s*$/u",
        ];

        if ($this->USFormat == true) {
            // m/d/y
            $out = [
                "$2.$1.$3",
                "$1.$2.$3 $4:00",
                "$2.$1.$3 $4:00",
                "$2.$1.$3 $4:$5",

                "$2.$1.$3 $4",
                "$1.$2.$3 $4",
                "$3.$2.$1 $4:00",
                "$1 $2 $3 $4",

                "$1 $2 $3",
                "$1-$2-$3, $4",
                "$2 $1 $3, $4",
            ];
        } else {
            // d/m/y
            $out = [
                "$1.$2.$3",
                "$1.$2.$3 $4:00",
                "$1.$2.$3 $4:00",
                "$1.$2.$3 $4:$5",

                "$1.$2.$3 $4",
                "$1.$2.$3 $4",
                "$3.$2.$1 $4:00",
                "$1 $2 $3 $4",

                "$1 $2 $3",
                "$1-$2-$3, $4",
                "$2 $1 $3, $4",
            ];
        }

        foreach ($in as $i => $pattern) {
            $date = preg_replace($pattern, $out[$i], $date, -1, $count);

            if ($count > 0) {
                break;
            }
        }
//        $this->logger->debug('date (replace) = '.print_r( $date,true));

        if (preg_match("#(.*\s+)(\d+)(:\d+)\s*[AP]M\s*$#", $date, $m) && $m[2] > 12) {
            $date = $m[1] . $m[2] . $m[3];
        }

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (
                    stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false
                    || $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $reBody[0] . '")]')->length > 0 || $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $reBody[1] . '")]')->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function UpdateSymbol($node)
    {
        //order is matters
        $str = str_replace("£", "GBP", $node); //en
        $str = str_replace("R$", "BRL", $str); //pt
        $str = str_replace("A$", "AUD", $str); //pt
        $str = str_replace("NT$", "TWD", $str); //zh
        $str = str_replace("$", "USD", $str); //en
        $str = str_replace("€", "EUR", $str); //en
        $str = str_replace("￥", "JPY", $str); //jz
        $str = str_replace("₩", "KRW", $str); //en

        return $str;
    }

    private function getTotalCurrency($node)
    {
        $node = $this->UpdateSymbol($node);
        $tot = '';
        $cur = '';

        if (preg_match("#\b(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})\b#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = (float) str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_replace('/([.$*)|(\/])/', '\\\\$1', $s);
        }, $field)) . ')';
    }
}
