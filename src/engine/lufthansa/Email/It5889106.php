<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Common\Parser\Util\PriceHelper;
use PlancakeEmailParser;

// TODO: We put it here with PDF -> BookingDetailsPdf

class It5889106 extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-2212978.eml, lufthansa/it-2212979.eml, lufthansa/it-2213909.eml, lufthansa/it-2215229.eml, lufthansa/it-256757281.eml, lufthansa/it-2603386.eml, lufthansa/it-2603394.eml, lufthansa/it-2905019.eml, lufthansa/it-2996256.eml, lufthansa/it-3131716.eml, lufthansa/it-3131789.eml, lufthansa/it-3170580.eml, lufthansa/it-3261025.eml, lufthansa/it-3261029.eml, lufthansa/it-3261064.eml, lufthansa/it-3261066.eml, lufthansa/it-3261067.eml, lufthansa/it-3261072.eml, lufthansa/it-3261075.eml, lufthansa/it-3261076.eml, lufthansa/it-3261077.eml, lufthansa/it-3261083.eml, lufthansa/it-4319526.eml, lufthansa/it-5146375.eml, lufthansa/it-5176525.eml, lufthansa/it-58634458.eml, lufthansa/it-5889106.eml, lufthansa/it-5909241.eml, lufthansa/it-60193382.eml, lufthansa/it-616892510.eml";

    public static $dictionary = [
        "en" => [
            //            'Your booking codes:' => '',
            'Lufthansa booking code:' => ['Your booking code', 'Lufthansa booking code:'],
            //            'Important Notice' => '',
            "codehide"=> "booking code is not displayed",
            //            'Total Price for all Passengers' => '',
            //            'operated by:' => '',
            "Passenger Information"=> ["Passenger Information", "Passenger information"],
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            "Miles & More Member"                   => ["Miles & More Member", "Frequent Traveller", "Miles & More-Number:", "FTL-Number:", "Miles & More"],
            'segmentStatusCancelled'                => ['cancelled'],
            'Class:'                                => ['Class:', 'Class/Fare:', 'Class of service/Fare:', 'Class of service:'],
            'Cancellation Confirmation'             => ['Cancellation Confirmation', 'Cancellation acknowledgement'],
            //            'Name' => '',
            //            'Seat' => '',
            //            'Dear ' => '',
            'Seats:' => ['Seats:', 'Seat:'],
        ],
        "pt" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => "Código da reserva da Lufthansa:",
            'Important Notice'              => 'Informação importante',
            "codehide"                      => "NOTTRANSLATED",
            "Total Price for all Passengers"=> "Preço total para todos os passageiros",
            "operated by:"                  => "operado por:",
            "Passenger Information"         => "Informação do passageiro",
            " with "                        => " com ", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'           => 'Cartão Miles & More nº.',
            "Class:"                        => ["Classe de reserva:", "Classe/Tarifa:", "Classe:"],
            "Cancellation Confirmation"     => "Confirmação de cancelamento",
            'Name'                          => 'NOTTRANSLATED',
            "Seat"                          => "NOTTRANSLATED",
            'Dear '                         => 'NOTTRANSLATED',
            'Seats:'                        => ['Lugares:', 'Lugar:'],
        ],
        "el" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => "Κωδικός κράτησης Lufthansa:",
            'Important Notice'              => 'Σημαντική ειδοποίηση',
            "codehide"                      => "Όσον αφορά το αίτημά σας, ο κωδικός κράτησής σας δεν εμφανίζεται",
            "Total Price for all Passengers"=> "Συνολική τιμή για όλους τους επιβάτες πτήσης",
            "operated by:"                  => "εκτελείται από:",
            "Passenger Information"         => "Πληροφορίες επιβάτη",
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'      => 'NOTTRANSLATED',
            "Class:"                   => ["Κατηγορία/Ναύλος:", "Κατηγορία Θέσης:"],
            "Cancellation Confirmation"=> "NOTTRANSLATED",
            'Name'                     => 'NOTTRANSLATED',
            'Seat'                     => 'NOTTRANSLATED',
            'Dear '                    => 'NOTTRANSLATED',
            'Seats:'                   => 'Θέσεις:',
        ],
        "zh" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => ["德國漢莎航空預訂編號:", "汉莎航空预订代码:", "汉莎航空预订代码", "预订编号："],
            "Important Notice"              => "重要通知",
            "codehide"                      => "NOTTRANSLATED",
            "Total Price for all Passengers"=> ["所有乘客票價總額", "所有乘客的总票价"],
            "operated by:"                  => ["承運人：", "承运人："],
            "Passenger Information"         => ["乘客資料", "旅客信息"],
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'       => 'NOTTRANSLATED',
            "Class:"                    => ["客艙級別:", "舱位/票价:", "舱位:"],
            'Cancellation Confirmation' => 'NOTTRANSLATED',
            'Name'                      => 'NOTTRANSLATED',
            'Seat'                      => 'NOTTRANSLATED',
            'Dear '                     => 'NOTTRANSLATED',
            //            'Seats:' => '',
        ],
        "fr" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => ["Code de réservation Lufthansa:", "Code de réservation:"],
            "Important Notice"              => ["Note importante", 'Informations importantes'],
            "codehide"                      => "Conformément à votre demande, votre code de réservation ne s'affiche pas",
            "Total Price for all Passengers"=> "Prix total pour tous les passagers",
            "operated by:"                  => "opéré par",
            "Passenger Information"         => "Informations passager",
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'      => ['Numéro Senator:', 'Numéro Miles & More:'],
            "Class:"                   => ["Classe de réservation:", "Classe/tarif:", "Classe:"],
            "Cancellation Confirmation"=> "Confirmation d'annulation",
            'Name'                     => 'NOTTRANSLATED',
            'Seat'                     => 'Siège',
            'Dear '                    => 'NOTTRANSLATED',
            'Seats:'                   => 'Sièges:',
        ],
        "it" => [
            "Your booking codes:"           => "I suoi codici di prenotazione:",
            "Lufthansa booking code:"       => ["Codice di prenotazione Lufthansa:", 'Codice di prenotazione:'],
            "Important Notice"              => ["Avvertenza importante", 'Avvertenze importanti'],
            "codehide"                      => "NOTTRANSLATED",
            "Total Price for all Passengers"=> "prezzo totale per tutti I passeggeri",
            "operated by:"                  => "operato da",
            "Passenger Information"         => "Informazioni sui passeggeri",
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'      => ['Numero di Miles & More:', 'Numero di Senator:'],
            "Class:"                   => ["Classe di prenotazione:", "Classe/Tariffa:", "Classe:"],
            "Cancellation Confirmation"=> "conferma di avvenuta cancellazione",
            'Name'                     => 'NOTTRANSLATED',
            'Seat'                     => 'NOTTRANSLATED',
            'Dear '                    => 'NOTTRANSLATED',
            'Seats:'                   => ['Posti:', 'Posto:'],
        ],
        "ja" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => "ルフトハンザの予約番号:",
            "Important Notice"              => "ご注意",
            "codehide"                      => "NOTTRANSLATED",
            "Total Price for all Passengers"=> "合計金額（全員分）",
            "operated by:"                  => "運航航空会社:",
            "Passenger Information"         => "搭乗者情報",
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'       => 'NOTTRANSLATED',
            "Class:"                    => ["予約クラス:", 'クラス:'],
            'Cancellation Confirmation' => 'NOTTRANSLATED',
            'Name'                      => 'NOTTRANSLATED',
            //            'Seat'                      => '',
            'Dear '                     => 'NOTTRANSLATED',
            'Seats:'                    => '座席: ',
        ],
        "ko" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => "루프트한자 예약코드::",
            "Important Notice"              => "중요한 정보",
            "codehide"                      => "NOTTRANSLATED",
            "Total Price for all Passengers"=> "모든 탑승객에 대한 총 요금",
            "operated by:"                  => "운항사:",
            "Passenger Information"         => "승객 정보",
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'       => 'NOTTRANSLATED',
            "Class:"                    => "클래스:",
            'Cancellation Confirmation' => 'NOTTRANSLATED',
            'Name'                      => 'NOTTRANSLATED',
            'Seat'                      => 'NOTTRANSLATED',
            'Dear '                     => 'NOTTRANSLATED',
            //            'Seats:' => '',
        ],
        "pl" => [
            "Your booking codes:"           => ["Twoje numery rezerwacji:"],
            "Lufthansa booking code:"       => ["Kod rezerwacji Lufthansy:", "Kod rezerwacji:"],
            "Important Notice"              => ["Ważna uwaga", 'Ważne uwagi'],
            "codehide"                      => "NOTTRANSLATED",
            "Total Price for all Passengers"=> "Cena całkowita dla wszystkich pasażerów",
            "operated by:"                  => "obsługiwany przez",
            "Passenger Information"         => "Informacja dla pasażerów",
            //            " with " => "", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'       => ['Numer karty FTL', 'Numer karty Miles & More'],
            "Class:"                    => ["Klasa:", "Klasa/Taryfa:"],
            'Cancellation Confirmation' => 'Potwierdzenie anulowania rezerwacji',
            'Name'                      => 'NOTTRANSLATED',
            'Seat'                      => 'NOTTRANSLATED',
            'Dear '                     => 'NOTTRANSLATED',
            'Seats:'                    => 'Miejsca:',
        ],
        "ru" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => "Код бронирования Lufthansa:",
            "Important Notice"              => ["Важное примечание", 'Важные примечания'],
            "codehide"                      => "код бронирования не показан",
            "Total Price for all Passengers"=> "общая сумма за всех пассажиров",
            "operated by:"                  => "выполняется",
            "Passenger Information"         => "Данные пассажира",
            " with "                        => " с ", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'           => 'NOTTRANSLATED',
            "Class:"                        => ["Класс:", "Класс / Тариф:"],
            'Cancellation Confirmation'     => 'NOTTRANSLATED',
            'Name'                          => 'NOTTRANSLATED',
            'Seat'                          => 'NOTTRANSLATED',
            'Dear '                         => 'NOTTRANSLATED',
            //            'Seats:' => '',
        ],
        "es" => [
            "Your booking codes:"           => "NOTTRANSLATED",
            "Lufthansa booking code:"       => ["Código de reserva de Lufthansa:", 'Código de reserva:'],
            "Important Notice"              => ["Aviso importante", 'Avisos importantes'],
            "codehide"                      => "En respuesta a su petición, no le mostramos su código de reserva.",
            "Total Price for all Passengers"=> "Precio total para todos los pasajeros",
            "operated by:"                  => "operado por:",
            "Passenger Information"         => "Información del pasajero",
            " with "                        => " con ", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'           => 'Numero de Miles & More',
            "Class:"                        => ["Clase de reserva:", "Clase:", 'Clase/Tarifa:'],
            'Cancellation Confirmation'     => 'NOTTRANSLATED',
            'Name'                          => 'NOTTRANSLATED',
            'Seat'                          => 'NOTTRANSLATED',
            'Dear '                         => 'NOTTRANSLATED',
            'Seats:'                        => 'Asientos:',
        ],
        "de" => [
            "Your booking codes:"           => "Ihre Buchungscodes:",
            "Lufthansa booking code:"       => ["Lufthansa Buchungscode:", "Ihr Buchungscode"],
            "Important Notice"              => ["Wichtiger Hinweis", "Wichtige Hinweise"],
            "codehide"                      => "Ihrem Wunsch entsprechend wird Ihr Buchungscode nicht angezeigt",
            "Total Price for all Passengers"=> ["Gesamtpreis für alle Reisenden"],
            "operated by:"                  => "durchgeführt von:",
            "Passenger Information"         => "Passagierinformationen",
            " with "                        => " mit ", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'           => ['Miles & More', 'Senator-Nummer:', 'Miles & More-Nummer:', 'FTL-Nummer:', 'Miles & More-Nummer:', 'FTL'],
            "Class:"                        => ["Klasse/Tarif:", "Buchungsklasse:", "Klasse:", "Beförderungsklasse/Tarif:"],
            "Cancellation Confirmation"     => "Stornierungsbestätigung",
            'Name'                          => 'Name',
            'Seat'                          => 'Sitzplatz',
            'Dear '                         => 'Sehr geehrte/r',
            'Seats:'                        => ['Sitzplätze:', 'Sitzplatz:'],
        ],
        "ro" => [
            //            "Your booking codes:"           => "",
            "Lufthansa booking code:"       => "Cod de rezervare Lufthansa:",
            "Important Notice"              => "Wichtiger Hinweis",
            "codehide"                      => "NOTTRANSLATED",
            //            "Total Price for all Passengers"=> ["Gesamtpreis für alle Reisenden"],
            "operated by:"                  => "operat de:",
            "Passenger Information"         => "Passenger Information",
            //            " with "                        => " mit ", // DURDEN / TEODOR MR with PETRA
            //            'Miles & More Member'           => ['Miles & More::'],
            "Class:"                        => ["Reisklasse/Tarief:"],
            //            "Cancellation Confirmation"     => "",
            //            'Name'                          => 'NOTTRANSLATED',
            'Seat'                          => 'Seat',
            'Dear '                         => 'NOTTRANSLATED',
            'Seats:'                        => ['Sitzplätze:', 'Sitzplatz:'],
        ],
        "nl" => [
            //            "Your booking codes:"           => "",
            "Lufthansa booking code:"       => "Boekingscode:",
            //            "Important Notice"              => "Wichtiger Hinweis",
            //            "codehide"                      => "NOTTRANSLATED",
            //            "Total Price for all Passengers"=> ["Gesamtpreis für alle Reisenden"],
            "operated by:"                  => "uitgevoerd door:",
            "Passenger Information"         => "Passenger information",
            //            " with "                        => " mit ", // DURDEN / TEODOR MR with PETRA
            'Miles & More Member'           => ['Miles & More::'],
            "Class:"                        => ["Class:"],
            //            "Cancellation Confirmation"     => "",
            //            'Name'                          => 'NOTTRANSLATED',
            //            'Seat'                          => 'Seat',
            //            'Dear '                         => 'NOTTRANSLATED',
            //            'Seats:'                        => ['Sitzplätze:', 'Sitzplatz:'],
        ],
    ];

    public $lang = "en";

    private $reSubject = [
        "en"=> "Booking Details", "Your seat has changed!", "Cancellation Confirmation",
        "Get ready for your upcoming trip to",
        'Booking details | Departure:',
        "pt"=> "Dados da reserva",
        "fr"=> "Le détail de votre réservation",
        "de"=> "Buchungsdetail",
        "ro"=> "Informatii despre calatorie",
    ];
    private $reBody = 'www.lufthansa.com';
    private $reBody2 = [
        "ro"  => "Detalii despre calatorie", // before en
        "nl"  => "Reisinformatie awardboekingen", // before en
        "en"  => "Your itinerary",
        "en2" => "Your Flight",
        "en3" => "Airlines is not liable for any changes",
        "en4" => "Cancellation Confirmation",
        "pt"  => "O seu itinerário",
        "zh"  => "您的",
        "fr"  => "Déroulement de votre voyage",
        "it"  => "Il suo itinerario",
        "ja"  => "お客様のご旅程",
        "ko"  => "여정",
        "pl"  => "Trasa Twojej podróży",
        "ru"  => "Ваш маршрут",
        "es"  => "Su itinerario",
        "de"  => "Ihr Reiseverlauf",
        "de2" => "Änderung der Sitzplatzreservierung",
        'el'  => 'Το δρομολόγιό σας',
    ];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
        'nameCodeTerminal' => '/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s*(?i)TERMINAL[:\s]+(?<terminal>[-\dA-z ]*)$/', // ST PETERSBURG RU PULKOVO (LED) TERMINAL 1 - PULKOVO 1
        'nameCode' => '/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/', // ST PETERSBURG RU PULKOVO (LED)
        'nameTerminal' => '/^(?<name>.{3,}?)\s*(?i)Terminal[:\s]+(?<terminal>[-\dA-z ]*)$/', // Frankfurt Intl Terminal 1
    ];

    public function parseHtmlFlight(Email $email): void
    {
        $f = $email->add()->flight();

        foreach ($this->http->FindNodes("//text()[" . $this->eq($this->t("Your booking codes:")) . "]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space(.)]") as $node) {
            if (preg_match("#^(\w+)\s+\((.+)\)$#", $node, $m)) {
                $rls = [];

                foreach (explode("/", $m[2]) as $airline) {
                    $rls[$m[1]][] = trim($airline);
                }

                foreach ($rls as $code => $airline) {
                    $f->general()
                        ->confirmation($code, implode(', ', $airline));
                }
            }
        }
        $rl = $this->nextText($this->t("Lufthansa booking code:"), null, 1, 1);

        if (!empty($rl) && !in_array($rl, array_column($f->getConfirmationNumbers(), 0))) {
            $f->general()
                ->confirmation($rl);
        }

        if (empty($f->getConfirmationNumbers()) && $this->http->FindSingleNode("(//*[{$this->contains($this->t('codehide'))}])[1]")) {
            $f->general()
                ->noConfirmation();
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Information'))}]/following::table[1]//b", null, "#^\s*({$this->patterns['travellerName2']}.*?)\s*(?:\([^\)]*\)\s*)?$#u"));

        if (count($travellers) > 0) {
            $travellers = preg_replace("/" . $this->opt($this->t(" with ")) . "/u", ", ", $travellers);
            $travellers = explode(", ", implode(", ", $travellers));
            $travellers = array_map(function ($item) {
                return $this->normalizeTraveller($item);
            }, $travellers);
        }

        if (count($travellers) === 0) {
            // it-58634458.eml
            $travellerNames = [];
            $seats = [];
            $newSeatRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Name'))}] and *[2][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr[ *[2] ]");

            foreach ($newSeatRows as $sRow) {
                $travellerNames[] = $this->normalizeTraveller($this->http->FindSingleNode('*[1]', $sRow, true, "/^{$this->patterns['travellerName']}$/u"));
                $seats[] = $this->http->FindSingleNode('*[2]', $sRow, true, '/^\d+[A-Z]$/');
            }

            if (count($travellerNames)) {
                $travellers = array_unique($travellerNames);
            }
        }

        if (count($travellers) === 0) {
            // it-58634458.eml
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true, "/{$this->opt($this->t('Dear '))}\s*({$this->patterns['travellerName']})(?:\s*[,;!?]|$)/u");

            if ($traveller && !preg_match("/^\s*Passenger\s*$/i", $traveller)) {
                $travellers = [$traveller];
            }
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        }

        $ticketNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Information'))}]/following::table[1]//text()[normalize-space()]", null, '/^\D*(\d{3}[- ]*\d{5,}[- ]*\d{1,2}(?:[,\s]+\d{3}[- ]*\d{5,}[- ]*\d{1,2})?)\s*$/'));
//        $this->logger->debug('$ticketNumbers = '.print_r( $ticketNumbers,true));
        if (count($ticketNumbers)) {
            $ticketNumbers = array_unique(array_map('trim', explode(",", implode(",", $ticketNumbers))));

            foreach ($ticketNumbers as $ticket) {
                $pax = $this->normalizeTraveller(
                    $this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][position()<3][not(contains(translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆'))]", null, true, "/^(?:{$this->patterns['travellerName']}|{$this->patterns['travellerName2']})$/u")
                    ?? $this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/preceding::tr[not(contains(normalize-space(),'FTL'))][1]", null, true, "/^(?:{$this->patterns['travellerName']}|{$this->patterns['travellerName2']})$/u")
                    ?? $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]", null, true, "/^(?:{$this->patterns['travellerName']}|{$this->patterns['travellerName2']})$/u")
                );

                $f->issued()->ticket($ticket, false, $pax);
            }
        }

        $ffNumbers = [];
        $ffNumberNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Miles & More Member'))}]");

        foreach ($ffNumberNodes as $ffNumRoot) {
            $ffNumber = $ffNumberDescription = null;

            if (preg_match("/({$this->opt($this->t('Miles & More Member'))})[:\s]*([-X\d ]{7,})$/", $this->http->FindSingleNode(".", $ffNumRoot), $m)) {
                $ffNumber = preg_replace(["/\W+/", "/X+/"], ['', 'XXX'], $m[2]);
                $ffNumberDescription = $m[1];
            }

            $pax = $this->normalizeTraveller(
                $this->http->FindSingleNode("ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1]", $ffNumRoot, true, "/^(?:{$this->patterns['travellerName']}|{$this->patterns['travellerName2']})$/u")
            );

            if ($ffNumber && !in_array($ffNumber, $ffNumbers)) {
                $f->program()->account($ffNumber, true, $pax, $ffNumberDescription);
                $ffNumbers[] = $ffNumber;
            }
        }

        // Status
        $status = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Cancellation Confirmation'))}])[1]");

        if (!empty($status)) {
            $f->general()
                ->status($status)
                ->cancelled();
        }

        $xpath1 = '//*[count(tr[starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")])=2]/ancestor-or-self::table[ following-sibling::table[normalize-space()] ][1]/following-sibling::table[normalize-space()][1][not(contains(normalize-space(), "RAILWAYS"))]';
        $segments = $this->http->XPath->query($xpath1);

        if (0 === $segments->length) {
            $this->logger->debug("Segments did not found by xpath1: {$xpath1}");
        }

        foreach ($segments as $root) {
            $this->logger->debug('$stype = ' . print_r('1', true));

            if (!empty($this->http->FindSingleNode("(./following::text()[normalize-space(.)!=''][position()<=6]/ancestor::td[1][{$this->contains($this->t("Status:"))}])[1]",
                $root, true, "/:\s*" . $this->opt($this->t("segmentStatusCancelled")) . "\s*$/"))) {
                continue;
            }

            $date = $this->http->FindSingleNode($xpathChild = "(./preceding::table[not({$this->contains($this->t('Important Notice'))}) and ./preceding::text()[normalize-space()!=''][1][not({$this->contains($this->t('Important Notice'))})]][position()=2 or position()=3][contains(.,':') and (contains(.,'-') or contains(., '–') or contains(., '-') or contains(., '?'))])[1]/descendant::text()[normalize-space(.)!=''][1]/ancestor::td[1]",
                $root, false, "#(.+)\s*: *.+#");

            $date = strtotime($this->normalizeDate($date));

            $s = $f->addSegment();

            $xpathLeftTable = 'preceding-sibling::table[normalize-space()][1]/descendant::tr[count(*[normalize-space()])=2]';

            // Departure
            $departure = implode("\n",
                $this->http->FindNodes($xpathLeftTable . '[1]/*[normalize-space()][2]/descendant::text()[normalize-space()]',
                    $root));

            $name = $code = $terminal = null;

            if (preg_match($this->patterns['nameCodeTerminal'], $departure, $m)) {
                $name = $m['name'];
                $code = $m['code'];
                $terminal = $m['terminal'] ?? null;
            } elseif (preg_match($this->patterns['nameCode'], $departure, $m)) {
                $name = $m['name'];
                $code = $m['code'];
            } else {
                $name = $this->http->FindSingleNode("preceding-sibling::table[1]/descendant::tr[not(.//tr) and normalize-space()][1]/descendant::text()[normalize-space()][2]",
                        $root);
            }

            if (empty($terminal) && !empty($name)
                && preg_match($this->patterns['nameTerminal'], $name, $m)
            ) {
                $name = $m['name'];
                $terminal = $m['terminal'];
            }

            $s->departure()
                ->name($name)
                ->terminal($terminal, true, true);

            if (!empty($code)) {
                $s->departure()
                    ->code($code);
            } elseif (!empty($name)) {
                $s->departure()
                    ->noCode();
            }

            // DepDate
            $time = preg_replace("#[^\d\s:apmh]+#", "",
                $this->http->FindSingleNode($xpathLeftTable . '[1]/*[normalize-space()][1]', $root));

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($this->normalizeDate($time), $date))
                    ->strict()
                ;
            }

            // Arrival
            $arrival = implode("\n",
                $this->http->FindNodes($xpathLeftTable . '[2]/*[normalize-space()][2]/descendant::text()[normalize-space()]',
                    $root));

            if (preg_match($this->patterns['nameCodeTerminal'], $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code']);

                if (!empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            } elseif (preg_match($this->patterns['nameCode'], $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code']);
            }

            if (empty($s->getArrName()) && empty($s->getArrCode())) {
                $s->arrival()
                    ->name($this->http->FindSingleNode("preceding-sibling::table[1]/descendant::tr[not(.//tr) and normalize-space()][2]/descendant::text()[normalize-space()][2]",
                        $root))
                    ->noCode()
                ;
            }

            if (empty($s->getArrTerminal()) && !empty($s->getArrName())
                && preg_match($this->patterns['nameTerminal'], $s->getArrName(), $m)
            ) {
                $s->arrival()
                    ->name($m['name'])
                    ->terminal($m['terminal'])
                ;
            }

            // ArrDate
            $s->arrival()
                ->date(strtotime($this->normalizeDate(preg_replace("#[^\d\s:apmh]+#", "",
                    $this->http->FindSingleNode($xpathLeftTable . '[2]/*[normalize-space()][1]', $root))), $date));
            $overnight = $this->http->FindSingleNode($xpathLeftTable . '[2]/*[normalize-space()][1]', $root, true, "/([\-+] ?\d)\s*$/");

            if (!empty($overnight) && !empty($s->getArrDate())) {
                $s->arrival()
                    ->date(strtotime($overnight . ' day', $s->getArrDate()));
            }
            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('descendant::text()[normalize-space()][1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            // Operator
            $operator = trim($this->http->FindSingleNode(".//text()[{$this->starts($this->t("operated by:"))}]/ancestor::td[1]",
                $root, true, "#{$this->opt($this->t("operated by:"))}\s*(.+)#"), ": ");

            if ($operator) {
                if (stripos($operator, $this->t('CARRIER_CODE')) !== false) {
                    $operator = $this->re("/^(.+)\s*{$this->opt($this->t('CARRIER_CODE'))}/", $operator);
                }
                $s->airline()
                    ->operator($operator);
            }

            // Cabin
            $cabin = str_replace('/', '',
                $this->http->FindSingleNode("(./following::text()[normalize-space(.)!=''][position()<=6]/ancestor::td[1][{$this->contains($this->t("Class:"))}])[1]",
                    $root, null, "#{$this->opt($this->t("Class:"))}\s+([\w\s]+)#u"));

            $t = preg_replace("/\s*:\s*$/", '', $this->t("Class:"));
            $cabin = trim(preg_replace("/(^\s*{$this->opt($t)} | {$this->opt($t)}\s*$)/u", '', $cabin));

            if (preg_match("/^\s*\([A-Z]{1,2}\)\s*$/", $cabin)) {
                $cabin = null;
            }

            if (!empty($cabin)) {
                $s->extra()->cabin($cabin);
            }

            // BookingClass
            $s->extra()->bookingCode($this->http->FindSingleNode("(./following::text()[normalize-space(.)!=''][position()<=6]/ancestor::td[1][{$this->contains($this->t("Class:"))}])[1]",
                $root, null, "#\(([A-Z]{1,2})\)#"), false, true);

            if (isset($seats) && count($seats) && $segments->length === 1) {
                // it-58634458.eml
                $s->extra()->seats($seats);
            }
            $seats2 = $this->http->FindSingleNode("(./following::text()[normalize-space(.)!=''][position()<=6]/ancestor::td[1][{$this->contains($this->t("Seats:"))}])[1]",
                $root, true, "/" . $this->opt($this->t("Seats:")) . "\s*(.+)/u");

            if (!empty($seats2)) {
                $s->extra()
                    ->seats(preg_replace("/^\s*(\d{1,3}[A-Z])(?:\s*\*\D*| \D+)\s*$/", '$1', preg_split('/\s*[,\/]\s*/', $seats2)));
            }
        }

        if ($segments->length > 0) {
            return;
        }
        $xpath2 = "//text()[{$this->starts($this->t('operated by:'))}]/ancestor::td[1][not(contains(normalize-space(), 'RAILWAYS'))]/following::*[starts-with(translate(normalize-space(),'0123456789：','dddddddddd:'),'dd:dd')][following-sibling::*[string-length()>5][1][starts-with(translate(normalize-space(),'0123456789：','dddddddddd:'),'dd:dd')]][1]";
        $segments = $this->http->XPath->query($xpath2);

        if (0 === $segments->length) {
            $this->logger->debug("Segments did not found by xpath2: {$xpath2}");
        }

        foreach ($segments as $root) {
            $this->logger->debug('$stype = ' . print_r('2', true));

            if (!empty($this->http->FindSingleNode("(following-sibling::*[normalize-space()][2]//td[not(.//td)][{$this->contains($this->t("Status:"))}])[1]",
                $root, true, "/:\s*" . $this->opt($this->t("segmentStatusCancelled")) . "\s*$/"))) {
                continue;
            }

            $date = $this->http->FindSingleNode("preceding-sibling::*[not({$this->starts($this->t('Important Notice'))})][string-length()>5][1]/descendant::text()[contains(translate(normalize-space(),'0123456789：','dddddddddd:'),'dddd')][1]/ancestor::td[1]",
                $root, false, "#.*\d+.*#");
            $date = strtotime($this->normalizeDate($date));

            $s = $f->addSegment();

            $departure = $this->http->FindSingleNode('descendant::td[not(.//td)][normalize-space()][2]',
                    $root);

            if (preg_match($this->patterns['nameCodeTerminal'], $departure, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                ;

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }
            } elseif (preg_match($this->patterns['nameCode'], $departure, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                ;
            }

            if (empty($s->getDepTerminal()) && !empty($s->getDepName())
                && preg_match($this->patterns['nameTerminal'], $s->getDepName(), $m)
            ) {
                $s->departure()
                    ->name($m['name'])
                    ->terminal($m['terminal'])
                ;
            }

            if (empty($s->getDepTerminal())) {
                $node = trim(preg_replace('/\s*Terminal\s*/', '', $this->http->FindSingleNode('descendant::td[not(.//td)][normalize-space()][3]',
                    $root, true, "/.*\bTerminal\b.*/")));

                if (!empty($node)) {
                    $s->departure()
                        ->terminal($node);
                }
            }

            // DepDate
            $time = preg_replace("#[^\d\s:apmh]+#", "",
                $this->http->FindSingleNode('descendant::td[not(.//td)][normalize-space()][1]', $root));
            $s->departure()
                ->date(strtotime($this->normalizeDate($time), $date));

            $arrival = $this->http->FindSingleNode('following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][2]', $root);
            $nextDay = null;

            if (preg_match("/^\s*[-+]\s*\d\s*$/", $arrival)) {
                $nextDay = $arrival;
                $arrival = $this->http->FindSingleNode('following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][3]', $root);
            }

            if (preg_match($this->patterns['nameCodeTerminal'], $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                ;

                if (!empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal'])
                    ;
                }
            } elseif (preg_match($this->patterns['nameCode'], $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                ;
            }

            if (empty($s->getArrName()) && empty($s->getArrCode())) {
                $s->arrival()
                    ->noCode()
                    ->name($this->http->FindSingleNode("preceding-sibling::table[1]/descendant::tr[not(.//tr) and normalize-space()][2]/descendant::text()[normalize-space()][2]",
                    $root))
                ;
            }

            if (empty($s->getArrTerminal())) {
                $node = trim(preg_replace('/\s*Terminal\s*/', '', $this->http->FindSingleNode('following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][position() = 3 or position() = 4][contains(., "Terminal")]',
                    $root, true, "/.*\bTerminal\b.*/")));

                if (!empty($node)) {
                    $s->arrival()
                        ->terminal($node);
                }
            }

            if (empty($s->getArrTerminal()) && !empty($s->getArrName())
                && preg_match($this->patterns['nameTerminal'], $s->getArrName(), $m)
            ) {
                $s->arrival()
                    ->name($m['name'])
                    ->terminal($m['terminal'])
                ;
            }

            // ArrDate
            $s->arrival()
                ->date(strtotime($this->normalizeDate(preg_replace("#[^\d\s:apmh]+#", "",
                    $this->http->FindSingleNode('following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][1]', $root))), $date));

            if (!empty($nextDay) && !empty($s->getArrDate())) {
                $s->arrival()->date(strtotime($nextDay . " days", $s->getArrDate()));
            }

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode("preceding-sibling::*[not({$this->starts($this->t('Important Notice'))})][1]/descendant::text()[normalize-space(.)!=''][3]/ancestor::td[1]",
                $root);

            if (!preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)$/", $flight)) {
                $flight = $this->http->FindSingleNode("following::img[contains(@src, 'icon')][1]/preceding::img[contains(@src, 'Logos')][1]/ancestor::*[normalize-space()][1]", $root);
            }

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            // Operator
            $operator = trim($this->http->FindSingleNode("preceding-sibling::*[not({$this->starts($this->t('Important Notice'))})][1]//text()[{$this->starts($this->t("operated by:"))}]/ancestor::td[1]",
                $root, true, "#{$this->opt($this->t("operated by:"))}\s*(.+)#"), ": ");

            if ($operator) {
                $s->airline()
                    ->operator($operator);
            }

            // Cabin
            $cabin = trim(str_replace('/', '',
                $this->http->FindSingleNode("following-sibling::*[normalize-space()][2]//td[not(.//td)][{$this->starts($this->t("Class:"))}]",
                    $root, null, "#{$this->opt($this->t("Class:"))}\s+([^\d\s]+)#")));

            if (preg_match("/^\s*\([A-Z]{1,2}\)\s*$/", $cabin)) {
                $cabin = null;
            }

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            // BookingClass
            $s->extra()
                ->bookingCode($this->http->FindSingleNode("following-sibling::*[normalize-space()][2]//td[not(.//td)][{$this->starts($this->t("Class:"))}]",
                $root, null, "#\(([A-Z]{1,2})\)#"), false, true);

            if (isset($seats) && count($seats) && $segments->length === 1) {
                $s->extra()
                    ->seats($seats);
            }
            $seats2 = $this->http->FindSingleNode("following-sibling::*[normalize-space()][2]//td[not(.//td)][{$this->starts($this->t("Seats:"))}]",
                $root, true, "/" . $this->opt($this->t("Seats:")) . "\s*(.+)/u");

            if (!empty($seats2)) {
                $s->extra()
                    ->seats(preg_replace("/^\s*(\d{1,3}[A-Z])(?:\s*\*\D*| \D+)\s*$/", '$1', preg_split('/\s*[,\/]\s*/', $seats2)));
            }
        }
    }

    public function parseHtmlTrain(Email $email): void
    {
        $t = $email->add()->train();

        foreach ($this->http->FindNodes("//text()[" . $this->eq($this->t("Your booking codes:")) . "]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space(.)]") as $node) {
            if (preg_match("#^(\w+)\s+\((.+)\)$#", $node, $m)) {
                $rls = [];

                foreach (explode("/", $m[2]) as $airline) {
                    $rls[$m[1]][] = trim($airline);
                }

                foreach ($rls as $code => $airline) {
                    $t->general()
                        ->confirmation($code, implode(', ', $airline));
                }
            }
        }
        $rl = $this->nextText($this->t("Lufthansa booking code:"), null, 1, 1);

        if (!empty($rl) && !in_array($rl, array_column($t->getConfirmationNumbers(), 0))) {
            $t->general()
                ->confirmation($rl);
        }

        if (empty($t->getConfirmationNumbers()) && $this->http->FindSingleNode("(//*[{$this->contains($this->t('codehide'))}])[1]")) {
            $t->general()
                ->noConfirmation();
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Information'))}]/following::table[1]//b", null, "#^\s*({$this->patterns['travellerName2']}.*?)\s*(?:\([^\)]*\)\s*)?$#u"));

        if (count($travellers) > 0) {
            $travellers = preg_replace("/" . $this->opt($this->t(" with ")) . "/u", ", ", $travellers);
            $travellers = explode(", ", implode(", ", $travellers));
            $travellers = array_map(function ($item) {
                return $this->normalizeTraveller($item);
            }, $travellers);
        }

        if (count($travellers) === 0) {
            // it-58634458.eml
            $travellerNames = [];
            $newSeatRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Name'))}] and *[2][{$this->eq($this->t('Seat'))}] ]/following-sibling::tr[ *[2] ]");

            foreach ($newSeatRows as $sRow) {
                $travellerNames[] = $this->normalizeTraveller($this->http->FindSingleNode('*[1]', $sRow, true, "/^{$this->patterns['travellerName']}$/u"));
            }

            if (count($travellerNames)) {
                $travellers = array_unique($travellerNames);
            }
        }

        if (count($travellers) === 0) {
            // it-58634458.eml
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true, "/{$this->opt($this->t('Dear '))}\s*({$this->patterns['travellerName']})(?:\s*[,;!?]|$)/u");

            if ($traveller && !preg_match("/^\s*Passenger\s*$/i", $traveller)) {
                $travellers = [$traveller];
            }
        }

        if (count($travellers) > 0) {
            $t->general()
                ->travellers($travellers);
        }

        // Status
        $status = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Cancellation Confirmation'))}])[1]");

        if (!empty($status)) {
            $t->general()
                ->status($status)
                ->cancelled();
        }

        //$xpath = '//text()[starts-with(normalize-space(), "operated by:") or ' . $this->starts($this->t('operated by:')) . ']/ancestor::td[1][contains(normalize-space(), "RAILWAYS")]/following::*[starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")][following-sibling::*[string-length()>5][1][starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")]][1]';
        $xpath = '//text()[starts-with(normalize-space(), "operated by:") or ' . $this->starts($this->t('operated by:')) . ']/ancestor::table[4][contains(normalize-space(), "RAILWAYS")]/descendant::*[starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")][following-sibling::*[string-length()>5][1][starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")]][1]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = '//text()[starts-with(normalize-space(), "operated by:") or ' . $this->starts($this->t('operated by:')) . ']/ancestor::table[5][contains(normalize-space(), "RAILWAYS")]/descendant::*[starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")][following-sibling::*[string-length()>5][1][starts-with(translate(normalize-space(),"0123456789：","dddddddddd:"),"dd:dd")]][1]';
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $root) {
            $this->logger->debug('$stype = ' . print_r('2', true));

            if (!empty($this->http->FindSingleNode("(following-sibling::*[normalize-space()][2]//td[not(.//td)][{$this->contains($this->t("Status:"))}])[1]",
                $root, true, "/:\s*" . $this->opt($this->t("segmentStatusCancelled")) . "\s*$/"))) {
                continue;
            }

            $date = $this->http->FindSingleNode("preceding-sibling::*[not({$this->starts($this->t('Important Notice'))})][string-length()>5][1]/descendant::text()[contains(translate(normalize-space(),'0123456789：','dddddddddd:'),'dddd')][1]/ancestor::td[1]",
                $root, false, "#.*\d+.*#");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("preceding::*[not({$this->starts($this->t('Important Notice'))})][string-length()>5][1]/descendant::text()[contains(translate(normalize-space(),'0123456789：','dddddddddd:'),'dddd')][1]",
                    $root, false, "#(.*\d+.*)\:\s#");
            }

            $date = strtotime($this->normalizeDate($date));

            $s = $t->addSegment();

            $departure = $this->http->FindSingleNode('descendant::td[not(.//td)][normalize-space()][2]', $root);

            if (preg_match($this->patterns['nameCode'], $departure, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                ;
            } else {
                $s->departure()
                    ->name($departure);
            }

            // DepDate
            $time = preg_replace("#[^\d\s:apmh]+#", "",
                $this->http->FindSingleNode('descendant::td[not(.//td)][normalize-space()][1]', $root));
            $s->departure()
                ->date(strtotime($this->normalizeDate($time), $date));

            $arrival = $this->http->FindSingleNode('following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][2]', $root);
            $nextDay = null;

            if (preg_match("/^\s*[-+]\s*\d\s*$/", $arrival)) {
                $nextDay = $arrival;
                $arrival = $this->http->FindSingleNode('following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][3]', $root);
            }

            if (preg_match($this->patterns['nameCode'], $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code']);
            } else {
                $s->arrival()
                    ->name($arrival);
            }

            // ArrDate
            $s->arrival()
                ->date(strtotime($this->normalizeDate(preg_replace("#[^\d\s:apmh]+#", "",
                    $this->http->FindSingleNode('following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][1]', $root))), $date));

            if (!empty($nextDay) && !empty($s->getArrDate())) {
                $s->arrival()->date(strtotime($nextDay . " days", $s->getArrDate()));
            }

            // AirlineName
            // FlightNumber
            $trainInfo = $this->http->FindSingleNode("preceding-sibling::*[not({$this->starts($this->t('Important Notice'))})][1]/descendant::text()[normalize-space(.)!=''][3]/ancestor::td[1]",
                $root);

            if (empty($trainInfo)) {
                $trainInfo = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('operated by:'))}][1]/preceding::text()[normalize-space()][1]", $root);
            }

            if (!preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)$/", $trainInfo)) {
                $trainInfo = $this->http->FindSingleNode("following::img[contains(@src, 'icon')][1]/preceding::img[contains(@src, 'Logos')][1]/ancestor::*[normalize-space()][1]", $root);
            }

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<number>\d+)$/', $trainInfo, $m)) {
                $s->setNumber($m['number']);
                $s->setServiceName($m['name']);
            }

            // Operator
            $operator = trim($this->http->FindSingleNode("preceding-sibling::*[not({$this->starts($this->t('Important Notice'))})][1]//text()[{$this->starts($this->t("operated by:"))}]/ancestor::td[1]",
                $root, true, "#{$this->opt($this->t("operated by:"))}\s*(.+)#"), ": ");

            if ($operator) {
                $s->setServiceName($operator);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking-lufthansa.com') !== false
            || stripos($from, '@booking.lufthansa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace([" ", "‑", '=E2=80=93', '​'], [" ", "-", '-', ''], $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'booking code')]/ancestor::td[1][contains(normalize-space(), 'is not displayed')]")->length > 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'booking code')]/ancestor::td[1][contains(normalize-space(), 'is not displayed')]/preceding::text()[normalize-space()][1][contains(normalize-space(), 'Cancellation')]")->length > 0) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->parseHtmlFlight($email);

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('operated by:'))}]/ancestor::td[1][contains(normalize-space(), 'RAILWAYS')]")->length > 0) {
            $this->parseHtmlTrain($email);
        }

        $totalPrice = $this->nextText($this->t("Total Price for all Passengers"));

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d]*?)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $matches)
        ) {
            // USD 710.42    |    204200 HUF
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

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

    private function nextText($field, $root = null, $n = 1, $strlen = 0): ?string
    {
        $rule = implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, (array) $field));

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[string-length(normalize-space(.))>{$strlen}][1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str): string
    {
        //$this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            "#^[^\d\s]+\.\s+(\d+\s+[^\d\s]+\s+\d{4})$#",
            "#^\d\W+\s+[^\d\s]+\.\s+(\d+)\.\s+([^\d\s]+)\s+(\d{4})$#", //4ª f. 30. Novembro 2016
            "#^[^\d\s]+\.\s+(\d+)\.?\s+([^\d\s]+)\s+(\d{4})$#", //Dom. 04. Dezembro 2016
            "#^[^\d\s]+\.\s+(\d{4})\.([^\d\s]+)\.(\d+)$#", //星期六. 2016.十月.01   (zh)
            "#^(\d{4})年(\d+)月(\d+)日\s+\([^\d\s]+\)#", //2016年9月10日 (土)
            "#^[^\d\s]+\.\s+(\d{4})년\s+(\d+)월\s+(\d+)일$#", //토. 2016년 9월 10일
            "#^[^\d\s]+\.\s+(\d+\.\d+.\d{4})$#", //Sob. 10.09.2016
            "#^[^\d\s]+\.\s*(\d+)\.\s*([^\d\s]+)\s*(\d{4})$#", //周五. 31. 八月 2018
            '/^\w+\. (\d{1,2}) (\d{1,2})[ ]*\w+ (\d{2,4})$/u', // 金. 22 3月 2019

            "#(\d+:\d+)\s+h#",
            "#(\d+:\d+)\s+a.*#", //12:10 a
            "#(\d+:\d+)\s+\d+#", //08:00  1
        ];
        $out = [
            "$1",
            "$1 $2 $3",
            "$1 $2 $3",
            "$3 $2 $1",
            "$3.$2.$1",
            "$3.$2.$1",
            "$1",
            "$1 $2 $3",
            '$3-$2-$1',

            "$1",
            "$1",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function eq($field
    ) {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return "false()";
        }

        return "(" . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.),\"" . $s . "\")"; }, $field)) . ")";
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MRSPROF|MRSDR|MRDR|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }
}
