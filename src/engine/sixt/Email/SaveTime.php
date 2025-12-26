<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class SaveTime extends \TAccountChecker
{
    public $mailFiles = "sixt/it-139848172.eml, sixt/it-160110590.eml, sixt/it-282194946.eml, sixt/it-348886528.eml, sixt/it-376725063.eml, sixt/it-40384069.eml, sixt/it-60844595.eml, sixt/it-67712174.eml, sixt/it-77106861.eml, sixt/it-79735505.eml, sixt/it-99260812.eml";

    public $reFrom = ["@sixt.", "@partner.sixt.", "@twiltravel.com"];
    public $reBody = [
        'en' => [
            'Please bring your documents with you',
            ', thank you for renting with Sixt',
            'YOUR INVOICE IS ATTACHED.',
            'Soon your rental car will be ready to go',
            'THANK YOU FOR RENTING WITH SIXT',
            'ATTACHED YOU FIND YOUR CREDIT NOTE',
            'YOUR BOOKING WAS SUCCESSFUL',
            'THE COUNTDOWN HAS STARTED',
            'A specific model is not guaranteed.',
            'Congrats - your reservation was successful',
            'your reservation change was',
            'Your SIXT team',
            'Your SIXT Team',
        ],
        'fr' => [
            'Veuillez apporter vos documents',
            'Félicitations - votre réservation est validée',
            'LE COMPTE À REBOURS A COMMENCÉ !',
            'VOTRE RÉSERVATION A ÉTÉ EFFECTUÉE AVEC SUCCÈS',
            'NOUS SOMMES HEUREUX DE VOUS REVOIR',
            'Prix de la location, incluant les frais, extras et protections',
            'MERCI D\'AVOIR LOUÉ CHEZ SIXT',
        ],
        'de' => ['bringen Sie Ihre Dokumente mit', 'Glückwunsch - Ihre Reservierungsänderung war erfolgreich',
            'Mehr Infos zu unseren Vermietstationen, Fahrzeugen und Vermietbedingungen finden Sie auf unserer Webseite',
            'Sie haben eine hervorragende Wahl getroffen', ],
        'nl' => [
            'Uw informatie is volledig',
            'Gefeliciteerd, je reservering is gelukt!',
        ],
        'it' => [
            'Lista di controllo per il vostro noleggio',
            'RICEVE LA FATTURA IN ALLEGATO',
            'la tua prenotazione ha avuto successo',
        ],
        'pt' => [
            'Uma marca e um modelo específicos não são garantidos', 'A sua equipa SIXT',
        ],
        'es' => [
            'En breves, su vehículo de alquiler estará listo para arrancar',
            'Tu reserva se ha realizado',
        ],
        'ru' => [
            'Ваше бронирование подтверждено',
        ],
    ];
    public $reSubject = [
        '#Réservation \d+: Economisez du temps lors de la récupération de votre#u',
        '#Your SIXT reservation \d+ on#u',
        '#Ihre Sixt Rechnung \d+#u',
        '#Reservation \d+: Save time during your pick-up#',
        '#Reservierung \d+: Informationen zu Ihrer#',
        '#\d+\:\s+Informatie over het ophalen van uw voertuig$#',
        '#Reservation confirmation for your SIXT rental car \| Reservation number#',
        '#Your Sicily Catania Airport reservation#',
        // it
        '#Prenotazione numero \d+: Risparmiare tempo al ritiro del veicolo$#',
        //de
        '#Ihre SIXT Reservierung \d+#u',
        // fr
        "/Votre réservation \d+, le [\d\. ]+,/u",
        "/Votre location est imminente – voici toutes les informations utiles \d+/u",
        //pt
        "/Your SIXT reservation \d+ on [\d\/ ]+/u",
        //nl
        "/Jouw reservering \d+, op [\d\/ ]+/u",
        // en
        "/Your reservation \d+ on [\d\/ ]+,/u",
        // es
        "/Coming up soon - All the information you need for your upcoming reservation \d+/u",
        "/Confirmación de reserva de tu vehículo de alquiler SIXT/u",
    ];
    public $lang = '';
    public $subject;
    public $year;
    public static $dict = [
        'en' => [
            'Pickup Location'     => ['Pickup Location', 'Pickup', 'Delivery', 'Pick-up'],
            'RESERVATION NUMBER:' => ['Reservation number:', 'RESERVATION NUMBER:'],
            'Delivery'            => 'Delivery',
            'Return'              => ['Return'],
            'Total price'         => ['Total price', 'Total estimated price', 'Estimated rental price (incl. taxes)', 'Expected rental price (incl. taxes)', 'Expected rental price (excl. taxes)', 'Estimated rental price'],
            'Dear '               => ['Dear ', 'mr/mrs', 'Hello'],
            'Vehicle group'       => ['Vehicle group', 'Your selected Car Group'],
            //            'Taxes' => '',
            //            'Vehicle Subtotal' => '',
            //            'Total price' => '',
            'A specific model is not guaranteed' => ['A specific model is not guaranteed', 'A specific make and model is not guaranteed'],
            //            'or similar' => '',
            //'Included at no additional cost' => ''
        ],
        'fr' => [
            'Pickup Location'     => 'Départ',
            'Return'              => 'Retour',
            'RESERVATION NUMBER:' => ['NUMÉRO DE RÉSERVATION :', 'Numéro de réservation :'],
            'Dear '               => ['Cher ', 'Cher(e)', 'Bonjour'],
            'Vehicle group'       => ['Catégorie de véhicule', 'Votre groupe de voiture sélectionnée'],
            //            'Taxes' => '',
            'Vehicle Subtotal'                   => 'Prix total de base de la location',
            'Total price'                        => ['Prix de location estimé (taxes incluses)', 'PRIX TOTAL', 'Prix de location estimé (taxes excluses)', 'Prix total'],
            'A specific model is not guaranteed' => 'Une marque et un modèle spécifiques ne sont pas garantis',
            'or similar'                         => ['ou similaire', 'ou semblable'],
            //'Included at no additional cost' => ''
        ],
        'de' => [
            'Pickup Location'                    => 'Abholung',
            'Return'                             => 'Rückgabe',
            'RESERVATION NUMBER:'                => ['RESERVIERUNGSNUMMER:', 'Reservierungsnummer:'],
            'Dear '                              => ['Sehr geehrter ', 'Hallo '],
            'Vehicle group'                      => 'Fahrzeuggruppe',
            'Taxes'                              => 'Steuern',
            'Vehicle Subtotal'                   => 'Gesamtbasismietpreis',
            'Total price'                        => ['Gesamtpreis', 'Voraussichtlicher Mietpreis (inkl. Steuern)', 'Voraussichtlicher Mietpreis (exkl. Steuern)'],
            'A specific model is not guaranteed' => 'Wir können kein spezielles Modell garantieren',
            'or similar'                         => 'oder ähnlich',
            //'Included at no additional cost' => ''
        ],
        'nl' => [
            'Pickup Location'     => 'Ophalen',
            'Return'              => 'Inleveren',
            'RESERVATION NUMBER:' => ['RESERVERINGSNUMMER:', 'Reserveringsnummer:'],
            'Dear '               => ['Geachte heer ', 'Beste '],
            'Vehicle group'       => 'Voertuiggroep',
            //            'Taxes' => '',
            'Vehicle Subtotal' => 'Verwachte huurprijs',
            //            'Total price' => '',
            'A specific model is not guaranteed' => 'Een specifiek model en merk is niet gegarandeerd',
            'or similar'                         => ' of gelijkwaardig',
            //'Included at no additional cost' => ''
        ],
        'it' => [
            'Pickup Location'                    => ['Ritiro', 'Presa'],
            'Return'                             => ['Consegna', 'Rilascio'],
            'RESERVATION NUMBER:'                => ['NUMERO DI PRENOTAZIONE:', 'Numero di prenotazione:'],
            'Dear '                              => ['Gentile ', 'Gentile Signor '],
            'Vehicle group'                      => 'Gruppo di veicoli',
            'Taxes'                              => 'Le tasse',
            'Vehicle Subtotal'                   => 'Totale parziale veicolo',
            'Total price'                        => 'Prezzo totale',
            'A specific model is not guaranteed' => 'Una marca e un modello specifici non sono garantiti.',
            'or similar'                         => 'o simile',
            //'Included at no additional cost' => ''
        ],
        'pt' => [
            'Pickup Location'                    => 'Levantamento',
            'Return'                             => 'Devolução',
            'RESERVATION NUMBER:'                => ['NÚMERO DE RESERVA', 'Número de reserva'],
            'Dear '                              => ['Caro(a)', 'Olá,'],
            'Vehicle group'                      => 'Categoria do veículo',
            'Taxes'                              => 'Le tasse',
            'Vehicle Subtotal'                   => 'Totale parziale veicolo',
            'Total price'                        => ['Preço de aluguer esperado (excluido taxas)', 'Preço de aluguer esperado (incluido taxas)', 'Total devido (taxas incluídas)'],
            'A specific model is not guaranteed' => 'Uma marca e um modelo específicos não são garantidos',
            'or similar'                         => 'ou similar',
            'Included at no additional cost'     => 'Incluído sem custos adicionais',
        ],
        'es' => [
            'Pickup Location'     => 'Recogida',
            'Return'              => 'Devolución',
            'RESERVATION NUMBER:' => ['NÚMERO DE RESERVA:', 'Número de reserva:'],
            'Dear '               => ['Estimado/a', 'Hola'],
            'Vehicle group'       => 'Su grupo de vehículo seleccionado',
            //            'Taxes'                              => 'Le tasse',
            //            'Vehicle Subtotal'                   => 'Totale parziale veicolo',
            //            'Total price'                        => ['Preço de aluguer esperado (excluido taxas)', 'Preço de aluguer esperado (incluido taxas)'],
            //            'A specific model is not guaranteed' => 'Uma marca e um modelo específicos não são garantidos',
            'or similar'                         => 'o similar',
            //'Included at no additional cost' => ''
        ],
        'ru' => [
            'Pickup Location'     => 'Получение автомобиля',
            'Return'              => 'Возврат автомобиля',
            'RESERVATION NUMBER:' => 'Номер бронирования:',
            'Dear '               => ['Уважаемый (-ая)'],
            //'Vehicle group'       => '',
            //'Taxes'                              => 'Le tasse',
            //'Vehicle Subtotal'                   => 'Totale parziale veicolo',
            'Total price'                        => ['Стоимость аренды (вкл. налоги)'],
            'A specific model is not guaranteed' => 'Гарантия конкретной марки и модели автомобиля не предоставляется.',
            'or similar'                         => 'или аналог',
            //'Included at no additional cost' => ''
        ],
    ];
    private $keywordProv = 'Sixt';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        if ($this->detectEmailByHeaders($parser->getHeaders()) == true) {
            $date = strtotime($parser->getDate());
            $this->year = date('Y', $date);
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Sixt Logo') or contains(@src,'.sixt.com')] | //a[contains(@href,'.sixt.com')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
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

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION NUMBER:'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION NUMBER:'))}]",
                null, true, "/{$this->opt($this->t('RESERVATION NUMBER:'))}\s*(\d{5,})\s*$/");
        }

        if (empty($confirmation)) {
            $confirmation = $this->re("/\s+(?:Invoice|Rechnung|reservation|fattura Sixt)\s*(\d{9,})\b/u", $this->subject);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle group'))}]/preceding::text()[{$this->starts($this->t('Dear '))}][1]/ancestor::td[1]", null, false,
            "#{$this->opt($this->t('Dear '))}\s*(.+)\,#");

        if (empty($traveller) || $traveller == 'Mr.' || $traveller == 'Ms.') {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]/ancestor::td[1]", null, false,
                "#{$this->opt($this->t('Dear '))}\s*(.+)\,#");
        }

        if (empty($traveller) || $traveller == 'Mr.' || $traveller == 'Ms.') {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, false,
                "#{$this->opt($this->t('Dear '))}\s*(.+)\,#");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle group'))}]/preceding::text()[{$this->starts($this->t('Dear '))}][1]/ancestor::td[1]",
                null, false,
                "#{$this->opt($this->t('Dear '))}\s*([[:alpha:] \-]+)$#u");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION NUMBER:'))}]/ancestor::table[1]/descendant::tr[{$this->starts($this->t('Dear '))}][1]/descendant::span[1]");
        }

        $r->general()
            ->confirmation($confirmation)
            ->traveller(trim(str_replace(['Signor', 'Dear Mr.', 'Geachte heer'], '', $traveller), ','));

        $carType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle group'))}]/following::text()[normalize-space()!=''][1]");
        $carModel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle group'))}]/following::text()[normalize-space()!=''][2]");

        if (empty($carType) && empty($carModel)) {
            $carInfo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('or similar'))}]/ancestor::*[2]");

            if (preg_match("/^\s*(?<model>.+\s*{$this->opt($this->t('or similar'))})\s*\|\s*(?<type>\D+)$/s", $carInfo, $m)) {
                $carType = $m['type'];
                $carModel = $m['model'];
            }
        }

        if (!empty($carType) && !empty($carModel)) {
            $r->car()
                ->type($carType)
                ->model($carModel);
        } else {
            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Pickup Location'))}]/preceding::text()[" . $this->starts($this->t('A specific model is not guaranteed')) . "][1]")->length > 0) {
                $carType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location'))}]/preceding::text()[" . $this->contains($this->t("or similar")) . "][1]");

                if (!empty($carType)) {
                    $r->car()
                        ->type($carType);
                }
            }
        }

        if (stripos($carModel, $this->t('Included at no additional cost')) !== false) {
            $carInfo = $this->http->FindSingleNode("//text()[{$this->contains($this->t('or similar'))}]/ancestor::*[2]");

            if (preg_match("/(?<type>.+)\s*(?<model>\(.+\)\s*{$this->opt($this->t('or similar'))})/", $carInfo, $m)) {
                $r->car()
                    ->type($m['type'])
                    ->model($m['model']);
            }
        }

        $location = implode(", ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][following-sibling::tr[contains(normalize-space(), ':') and contains(normalize-space(), '|')]]"));

        if (empty($location)) {
            $location = implode(", ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[1][not(contains(normalize-space(), ':'))]"));
        }

        if (empty($location)) {
            $location = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[string-length()>5][1]");
        }

        if (empty($location)
            && !empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Delivery'))}])[1]"))
            && empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Pickup Location'))} and not({$this->eq($this->t('Delivery'))})])[1]"))
        ) {
            $r->pickup()
                ->noLocation();
        } else {
            $r->pickup()
                ->location($location);
        }
        $pickUpDate = $this->normalizeDate(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':') and contains(normalize-space(), '|')][1]//text()[normalize-space()]")));

        if (empty($pickUpDate)) {
            $pickUpDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[string-length()>5][2]", null, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4}[\s\|]+[\d\:]+\s*A?P?M)$/"));
        }

        if (empty($pickUpDate) && !empty($this->year)) {
            $pickUpDate = $this->normalizeDate(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[1][contains(normalize-space(), ':')][1]")) . ' ' . $this->year);
        }

        if (empty($pickUpDate)) {
            $pickUpDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':') and contains(normalize-space(), '.')][1]/descendant::text()[normalize-space()][1]"));
        }

        $r->pickup()
            ->date($pickUpDate);

        $dropOffLocation = implode(", ", $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][following-sibling::tr[contains(normalize-space(), ':') and contains(normalize-space(), '|')]]"));

        if (empty($dropOffLocation)) {
            $dropOffLocation = implode(", ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[2][not(contains(normalize-space(), ':'))]"));
        }

        $dropOffDate = $this->normalizeDate(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':') and contains(normalize-space(), '|')][1]//text()[normalize-space()]")));

        if (empty($pickUpDate)) {
            $dropOffDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[string-length()>5][2]", null, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4}[\s\|]+[\d\:]+\s*A?P?M)$/"));
        }

        if (empty($dropOffDate) && !empty($this->year)) {
            $dropOffDate = $this->normalizeDate(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td[2][contains(normalize-space(), ':')][1]")) . ' ' . $this->year);
        }

        if (empty($dropOffDate)) {
            $dropOffDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), ':') and contains(normalize-space(), '.')][1]/descendant::text()[normalize-space()][2]"));
        }

        if ($r->getPickUpDateTime() > $dropOffDate) {
            $r->dropoff()
                ->location($dropOffLocation)
                ->date(strtotime('+1 year', $dropOffDate));
        } else {
            $r->dropoff()
                ->location($dropOffLocation)
                ->date($dropOffDate);
        }

        $tax = $this->amount($this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/ancestor::tr[1]/td[2]", null, true, "/(\d[\d\.\, ]*)/"));
        $cost = $this->amount($this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle Subtotal'))}]/ancestor::tr[1]/td[2]", null, true, "/(\d[\d\.\, ]*)/"));
        $total = $this->amount($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/ancestor::tr[1]/td[2]", null, true, "/(\d[\d\.\, ]*)/"));

        if (empty($total)) {
            $total = $this->amount($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/following::text()[normalize-space()][1]", null, true, "/(\d[\d\.\, ]*)/"));
        }

        if (empty($total)) {
            $total = $this->amount($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/following::text()[normalize-space()][1]/ancestor::*[2]", null, true, "/(\d[\d\.\, ]*)/"));
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/ancestor::tr[1]/td[2]", null, true, "/(\D+)\s?\d[\d\.\, ]*/");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/following::text()[normalize-space()][1]", null, true, "/(\D+)\s?\d[\d\.\, ]*/");
        }

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/following::text()[normalize-space()][1]/ancestor::*[2]", null, true, "/(\D+)\s?\d[\d\.\, ]*/");
        }

        if (!empty($total)) {
            $r->price()
                ->total($total)
                ->currency($this->normalizeCurrency($currency));
        }

        if (!empty($tax)) {
            $r->price()
                ->tax($tax)
                ->cost($cost);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('IN-' . $date);
        $in = [
            //Jul 2 22:14 2019 | Tue PM
            '#^(\w+)\s+(\d+)\s+([\d\:]+)\s+(\d{4})\s*\|\s*\w+\s*(A?P?M)$#',
            // Jun 29 18:30 2019 | Sat
            // juil. 4 10:30 2019 | jeu.
            '#^(\w+)\.?\s+(\d+)\s+(\d+:\d+)\s+(\d{4})\s*\|\s*(\w+)\.?$#u',
            '#^(\w+)\s*(\d+)\s*([\d\:]+)\s*(\d{4})\s*\|\s*\w+\s*(\w+)$#u',
            //12. Feb 12:15 2021 | Fri PM
            '#^(\d+)\.?\s+(\w+)\s+([\d\:]+)\s+(\d{4})\s*\|\s*\w+\s*([AP]M)$#',
            //12. Feb 12:15 2021 | Fri
            //7. Févr. 18:00 2022 | Lun.
            '#^(\d+)\.?\s+(\w+)\.?\s+([\d\:]+)\s+(\d{4})\s*\|\s*\w+\.?\s*$#u',
            //22. Apr 11:00 2023
            '#^(\d+)\.\s*(\w+)\s*([\d\:]+)\s*(\d{4})$#',
            //Sat, Sep 23, 2023 | 3:00 PM
            '#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})[\s\|]+([\d\:]+\s*A?P?M)$#',
        ];
        $out = [
            '$2 $1 $4, $3 $5',
            '$2 $1 $4, $3',
            '$2 $1 $4, $3 $5',
            '$1 $2 $4, $3 $5',
            '$1 $2 $4, $3',
            '$1 $2 $4, $3',
            '$2 $1 $3, $4',
        ];

        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        if (preg_match("/(\d+)\:(\d+)\s*A?P?M/", $str, $m)) {
            if ($m[1] > 12) {
                $str = preg_replace("/\s*A?P?M/", "", $str);
            }
        }
        //$this->logger->debug('OUT-' . $str);

        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Pickup Location'], $words['Return'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Pickup Location'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Return'])}]")->length > 0
                ) {
                    $this->lang = $lang;

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
            }
        }

        return $date;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }
}
