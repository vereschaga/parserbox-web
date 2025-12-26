<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationPdf extends \TAccountChecker
{
    public $mailFiles = "sixt/it-1.eml, sixt/it-1619358.eml, sixt/it-1625726.eml, sixt/it-1633341.eml, sixt/it-1647704.eml, sixt/it-1648325.eml, sixt/it-1671456.eml, sixt/it-1689843.eml, sixt/it-1689844.eml, sixt/it-1707153.eml, sixt/it-1708180.eml, sixt/it-1724726.eml, sixt/it-1732495.eml, sixt/it-1886823.eml, sixt/it-1904139.eml, sixt/it-2.eml, sixt/it-2233253.eml, sixt/it-2233429.eml, sixt/it-2306574.eml, sixt/it-3.eml, sixt/it-3041298.eml, sixt/it-3181905.eml, sixt/it-39067239.eml, sixt/it-39086153.eml, sixt/it-39128850.eml, sixt/it-4018313.eml, sixt/it-41810354.eml";

    public $reSubject = [
        'ru' => ['Подтверждение брони автомобиля'],
        'de' => ['Ihre Reservierung'],
        'pt' => ['A sua reserva'],
        'es' => ['Su reserva'],
        'et' => ['reservation confirmation'],
        'nl' => ['Uw reservering'],
        'en' => ['Your reservation'],
        'pl' => ['Twoja Rezerwacja'],
        'it' => ['Prenotazione'],
    ];

    public $lang = '';

    public $langDetectors = [
        'ru' => ['Выдача:'],
        'de' => ['Übergabestation:', 'Übergabe:', 'Abholung'],
        'pt' => ['Est. de levantamento:', "Est. Devolução:", 'Levantamento'],
        'es' => ['Oficina recogida:', 'Fecha entrega:'],
        'et' => ['Väljavötmise koht:'],
        'nl' => ['Overdrachtstation:', 'Overdracht:'],
        'fr' => ['Toutes les remises accordées sont comprises dans le prix final indiqué'],
        'en' => ['Pick-up station:', 'Pick-up:', 'Pickup:', 'Pickup Location'],
        'pl' => ['Punkt wynajmu:', 'Wynajem:'],
        'it' => ['Stazione di partenza:'],
    ];

    public $pdfPattern = '.*\.pdf';
    public $date;

    public $text = '';

    public static $dictionary = [
        'ru' => [
            // Html 1 - text on main(grey) background
            'Hello'               => 'Добрый день', // + html2
            'Reservation number:' => 'Номер брони:', // + html2
            'Vehicle category:'   => 'Категория автомобиля:',
            'Pick-up:'            => 'Выдача:', // + pdf
            'Drop-off:'           => 'Возврат:', // + pdf

            // Html 2 - text on left column on orange background
            //            'Vehicle group' => '',
            //            'Pickup Location' => '',
            //            'Return' => '',
            //            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            //            'Total estimated price:' => '',

            // pdf
            //            "Date:" => '',
            //            "Your reservation number:" => '',
            //            "Your cancelled reservation number:" => '',
            //            'Pick-up station:' => '',
            //            'Drop-off station:' => '',
            //            "Drivers name:" => '',
            //            "First name:" => '',
            //            "Car group:" => '',
            //            "Sample model:" => '',
            //            "Expected rental price \(gross\)" => '',
            //            "All station information at once:" => '',
            //            "Directions:" => "",
        ],
        'fr' => [
            // Html 1 - text on main(grey) background
            "Hello"              => "Société/Monsieur/Madame", // + html2
            "Reservation number:"=> "numéro de réservation:", // + html2
            //            'Vehicle category:'   => '',
            "Pick-up:"                        => "Date prise véhicule:", // + pdf
            "Drop-off:"                       => "Date retour:", // + pdf

            // Html 2 - text on left column on orange background
            //            'Vehicle group' => '',
            //            'Pickup Location' => '',
            //            'Return' => '',
            //            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            //            'Total estimated price:' => '',

            // pdf
            "Date:"                           => "Date:",
            "Your reservation number:"        => "Votre numéro de réservation:",
            //            "Your cancelled reservation number:" => '',
            "Pick-up station:"                => "Station départ:",
            "Drop-off station:"               => "Station retour:",
            "Drivers name:"                   => "Nom du conducteur:",
            "First name:"                     => "Prénom:",
            "Car group:"                      => "Catégorie véhicule:",
            "Sample model:"                   => "Ex. de véhicules:",
            "Expected rental price \(gross\)" => "Prix location prévu \(TTC\)",
            "All station information at once:"=> "Toutes les informations sur la station à la fois:",
            //            "Directions:" => "",
        ],
        'de' => [
            // Html 1 - text on main(grey) background
            "Hello"                           => ["Guten Tag", "Sehr geehrter Herr"], // + html2
            "Reservation number:"             => ["Reservierungsnummer:", "RESERVIERUNGSNUMMER:"], // + html2
            "Vehicle category:"               => "Fahrzeugkategorie:",
            "Pick-up:"                        => "Übergabe:", // + pdf
            "Drop-off:"                       => "Rückgabe:", // + pdf

            // Html 2 - text on left column on orange background
            "Vehicle group"          => "Fahrzeuggruppe",
            "Pickup Location"        => "Abholung",
            "Return"                 => "Rückgabe",
            "Vehicle Subtotal"       => "Gesamtbasismietpreis",
            "Taxes"                  => "Steuern",
            "Total estimated price:" => "Gesamtpreis:",

            // pdf
            "Date:"                              => "Datum:",
            "Your reservation number:"           => ["Ihre Reservierungsnummer:", "Ihre Referenznummer:"],
            "Your cancelled reservation number:" => 'Ihre stornierte Reservierungsnummer:',
            "Pick-up station:"                   => "Übergabestation:",
            "Drop-off station:"                  => "Rückgabestation:",
            "Drivers name:"                      => "Fahrername:",
            "First name:"                        => "Vorname:",
            "Car group:"                         => "Fahrzeuggruppe:",
            "Sample model:"                      => "Beispielfahrzeug:",
            "Expected rental price \(gross\)"    => "voraussichtl. Mietpreis \(brutto\)",
            "All station information at once:"   => "Alle Stationsinformationen auf einen Blick:",
            "Directions:"                        => "Wegbeschreibung:",
        ],
        'pt' => [
            // Html 1 - text on main(grey) background
            "Hello"                            => ["Olá", "Caro"], // + html2
            "Reservation number:"              => ["Número de reserva :", "NÚMERO DE RESERVA:"], // + html2
            "Vehicle category:"                => "Categoria da viatura:",
            "Pick-up:"                         => ["Levantamento:", "Transferência:"], // + pdf
            "Drop-off:"                        => ["Devolução:", "Devolução :"], // + pdf

            // Html 2 - text on left column on orange background
            "Vehicle group"          => "Grupo do veículo",
            "Pickup Location"        => "Levantamento",
            "Return"                 => "Devolução",
            "Vehicle Subtotal"       => "Subtotal do veículo",
            "Taxes"                  => "Taxas",
            "Total estimated price:" => "Preço total:",

            // pdf
            "Date:"                            => "Data:",
            "Your reservation number:"         => "O seu numero de reserva:",
            //            "Your cancelled reservation number:" => '',
            "Pick-up station:"                 => ["Est. de levantamento:", "Est. Levantamento:"],
            "Drop-off station:"                => ["Est. de devolução:", "Est. Devolução:"],
            "Drivers name:"                    => ["Nome do condutor:", "Apelido do condutor:"],
            "First name:"                      => ["Nom:", "Nome:"],
            "Car group:"                       => "Grupo de viatura:",
            "Sample model:"                    => "Exemplo de viatura:",
            "Expected rental price \(gross\)"  => "(?:Valor esperado para o aluguer \(TOTAL\)|Valor esperado do aluguer \(Total\))",
            "All station information at once:" => "Informação útil sobre a estação:",
            "Directions:"                      => "Direções:",
        ],
        'es' => [
            // Html 1 - text on main(grey) background
            //            'Hello'               => '', // + html2
            //            'Reservation number:' => '', // + html2
            //            'Vehicle category:'   => '',
            "Pick-up:"                           => "Fecha entrega:", // + pdf
            "Drop-off:"                          => "Fecha devolución:", // + pdf

            // Html 2 - text on left column on orange background
            //            'Vehicle group' => '',
            //            'Pickup Location' => '',
            //            'Return' => '',
            //            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            //            'Total estimated price:' => '',

            // pdf
            "Date:"                              => "Fecha:",
            "Your reservation number:"           => "Su número de reserva:",
            "Your cancelled reservation number:" => "Número de reserva cancelada:",
            "Pick-up station:"                   => ["Oficina recogida:", "Estación de recogida:"],
            "Drop-off station:"                  => ["Oficina devolución:", "Estación de entrega:"],
            "Drivers name:"                      => "Apellidos conductor:",
            "First name:"                        => "Nombre:",
            "Car group:"                         => "Grupo de vehículo:",
            "Sample model:"                      => "Ejemplo de vehículo:",
            "Expected rental price \(gross\)"    => "Precio bruto estimado",
            "All station information at once:"   => "Toda la información de la estación a la vez:",
            "Directions:"                        => "Cómo llegar:",
        ],
        'et' => [
            // Html 1 - text on main(grey) background
            //            'Hello'               => '', // + html2
            //            'Reservation number:' => '', // + html2
            //            'Vehicle category:'   => '',
            "Pick-up:"                        => "Rendi Algus:", // + pdf
            "Drop-off:"                       => "Rendi löpp:", // + pdf

            // Html 2 - text on left column on orange background
            //            'Vehicle group' => '',
            //            'Pickup Location' => '',
            //            'Return' => '',
            //            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            //            'Total estimated price:' => '',

            // pdf
            "Date:"                           => "Kuupäev:",
            "Your reservation number:"        => "Teie tellimuse number:",
            //            "Your cancelled reservation number:" => '',
            "Pick-up station:"                => ["Väljavötmise koht:"],
            "Drop-off station:"               => ["Tagastanise koht:"],
            "Drivers name:"                   => "Juhi nimi:",
            "First name:"                     => "NOTTRANSLATED",
            "Car group:"                      => "Auto Grupp:",
            "Sample model:"                   => "Auto mark/mudel:",
            "Expected rental price \(gross\)" => "Renditasu Kokku",
            "All station information at once:"=> "NOTTRANSLATED",
            "Directions:"                     => "NOTTRANSLATED",
        ],
        'nl' => [
            // Html 1 - text on main(grey) background
            //            'Hello'               => '', // + html2
            //            'Reservation number:' => '', // + html2
            //            'Vehicle category:'   => '',
            "Pick-up:"                        => "Overdracht:", // + pdf
            "Drop-off:"                       => "Inleveren:", // + pdf

            // Html 2 - text on left column on orange background
            //            'Vehicle group' => '',
            //            'Pickup Location' => '',
            //            'Return' => '',
            //            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            //            'Total estimated price:' => '',

            // pdf
            "Date:"                           => "Datum:",
            "Your reservation number:"        => "Uw reserveringsnummer:",
            //            "Your cancelled reservation number:" => '',
            "Pick-up station:"                => ["Overdrachtstation:"],
            "Drop-off station:"               => ["Inleverstation:"],
            "Drivers name:"                   => "Naam bestuurder:",
            "First name:"                     => "Voornaam:",
            "Car group:"                      => "Voertuiggroep:",
            "Sample model:"                   => "Voorbeeldvoertuig:",
            "Expected rental price \(gross\)" => "Geschatte huurprijs \(bruto\)",
            "All station information at once:"=> "Alle informatie over het station in een keer:",
            "Directions:"                     => "Routebeschrijving:",
        ],
        'en' => [
            // Html 1 - text on main(grey) background
            "Hello"                   => ["Hello", "Dear"], // + html2
            "Reservation number:"     => ["Reservation number:", "RESERVATION NUMBER:"], // + html2
            "Vehicle category:"       => ["Vehicle category:", "Sample vehicle"],
            "Pick-up:"                => ["Pick-up:", "Pickup:"], // + pdf
            "Drop-off:"               => ["Drop-off:", "Return:"], // + pdf

            // Html 2 - text on left column on orange background
            //            'Vehicle group' => '',
            //            'Pickup Location' => '',
            //            'Return' => '',
            //            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            //            'Total estimated price:' => '',

            // pdf
            //            "Date:" => '',
            "Your reservation number:"=> ["Your reservation number:", "Your reservation request:"],
            //            "Your cancelled reservation number:" => '',
            //            'Pick-up station:' => '',
            //            'Drop-off station:' => '',
            //            "Drivers name:" => '',
            //            "First name:" => '',
            //            "Car group:" => '',
            //            "Sample model:" => '',
            //            "Expected rental price \(gross\)" => '',
            //            "All station information at once:" => '',
            //            "Directions:" => "",
        ],
        'pl' => [
            // Html 1 - text on main(grey) background
            "Hello"                            => "Dzień Dobry!", // + html2
            "Reservation number:"              => "Numer rezerwacji:", // + html2
            "Vehicle category:"                => "Kategoria pojazdu:",
            //            'Vehicle category:'   => '',
            "Pick-up:"                         => ["Przekazanie:", "Wynajem:"], // + pdf
            "Drop-off:"                        => ["Zwrot:"], // + pdf

            // Html 2 - text on left column on orange background
            //            'Vehicle group' => '',
            //            'Pickup Location' => '',
            //            'Return' => '',
            //            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            //            'Total estimated price:' => '',

            // pdf
            "Date:"                            => "Data:",
            "Your reservation number:"         => "Numer rezerwacji:",
            //            "Your cancelled reservation number:" => '',
            "Pick-up station:"                 => ["Punkt wynajmu:"],
            "Drop-off station:"                => ["Punkt zwrotu:"],
            "Drivers name:"                    => "Nazwisko kierowcy:",
            "First name:"                      => "Imie:",
            "Car group:"                       => "Grupa pojazdu:",
            "Sample model:"                    => "Przykladowy model:",
            "Expected rental price \(gross\)"  => "Spodziewana cena \(brutto\)",
            "All station information at once:" => "Wszystkie informacje o stacji na raz:",
            "Directions:"                      => "Kierunki:",
        ],
        'it' => [
            // Html 1 - text on main(grey) background
            'Hello'               => 'Gentile', // + html2
            'Reservation number:' => 'NUMERO DI PRENOTAZIONE:', // + html2
            //            'Vehicle category:'   => '',
            'Pick-up:'            => 'Partenza:', // + pdf
            'Drop-off:'           => 'Rientro:', // + pdf

            // Html 2 - text on left column on orange background
            'Vehicle group'    => 'Gruppo di veicoli',
            'Pickup Location'  => 'Ritiro',
            'Return'           => 'Consegna',
            'Vehicle Subtotal' => '',
            //            'Taxes' => '',
            'Total estimated price:' => 'Prezzo totale',

            // pdf
            "Date:"                    => 'Data:',
            "Your reservation number:" => 'Numero prenotazione:',
            //            "Your cancelled reservation number:" => '',
            'Pick-up station:'                 => 'Stazione di partenza:',
            'Drop-off station:'                => 'Stazione di rientro:',
            "Drivers name:"                    => 'Conducente:',
            "First name:"                      => 'Nome:',
            "Car group:"                       => 'Gruppo veicolo:',
            "Sample model:"                    => 'Esempio modello:',
            "Expected rental price \(gross\)"  => 'Prezzo noleggio previsto \(lordo\)',
            "All station information at once:" => 'Panoramica informazioni filiale:',
            "Directions:"                      => "Direzioni:",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sixt.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // PDF
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (isset($pdfs[0])) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            if (strpos($textPdf, '.sixt.') !== false && $this->assignLang($textPdf)) {
                return true;
            }
        }

        // HTML
        $textHtml = html_entity_decode($this->http->Response['body']);

        if (!empty($textHtml)
            && $this->http->XPath->query('//a[contains(@href,".sixt.com") or contains(@href,".sixt.ru")]')->length > 0
            && $this->assignLang($textHtml)
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (isset($pdfs[0])) {
            $this->text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            if (!empty($this->text)) {
                $this->assignLang($this->text);
            }
        }

        if (empty($this->lang)) {
            $this->text = html_entity_decode($this->http->Response['body']);

            if (!empty($this->text)) {
                $this->http->SetEmailBody($this->text);
                $this->assignLang($this->text);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        if (!empty($this->text)) {
            $this->parsePdf($email);
        }

        if (empty($this->text)) {
            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[./preceding-sibling::tr][1]/preceding-sibling::tr[1][{$this->starts($this->t('Vehicle group'))}]")->length === 1) {
                // it-39128850.eml
                $this->parseHtml_2($email);
            } else {
                // it-3181905.eml
                $this->parseHtml($email);
            }
        }

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
        $formats = 3; // pdf | html | html2
        $cnt = $formats * count(self::$dictionary);

        return $cnt;
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

    private function parsePdf(Email $email)
    {
        $this->logger->warning('parsePdf');

        $text = $this->text;

        $table = $this->re("#\n([^\n\S]*" . $this->opt($this->t("Pick-up:")) . ".*?)\n\s*" . $this->opt($this->t("Drivers name:")) . "#ms", $text);

        $rows = explode("\n", $table);

        if (count($rows) > 2) {
            $pu = (array) $this->t('Pick-up:');
            $left = null;

            foreach ($pu as $p) {
                if (($pos = mb_strpos($rows[0], $p, 0, "UTF-8")) !== false) {
                    $left = min([$pos, $this->mb_strpos_array($rows[1], $this->t('Pick-up station:'))]);
                }
            }

            if (!isset($left)) {
                return;
            }

            $rightValues = [];

            foreach ((array) $this->t('Drop-off:') as $phrase) {
                $rightValues[] = mb_strpos($rows[0], $phrase, 0, "UTF-8");
            }
            $rightValues[] = $this->mb_strpos_array($rows[1], $this->t('Drop-off station:'));

            $right = min(array_values(array_filter($rightValues)));
        } else {
            $this->logger->debug("Incorrect parse table");

            return;
        }
        $table = $this->splitCols($table, [$left, $right]);

        if (count(array_filter($table, function ($s) { return trim($s); })) != 2) {
            $this->logger->debug("Incorrect parse table");

            return;
        }
        $table2 = $this->re("#\n([^\n\S]*" . $this->opt($this->t("Drivers name:")) . ".*?)\n\n#ms", $text);

        if (is_array($this->t('Drivers name:'))) {
            foreach ($this->t('Drivers name:') as $driver) {
                $pos1 = mb_strpos($table2, $driver, 0, "UTF-8");

                if (!empty($pos1)) {
                    break;
                }
            }
        } else {
            $pos1 = mb_strpos($table2, $this->t('Drivers name:'), 0, "UTF-8");
        }

        if (is_array($this->t('First name:'))) {
            foreach ($this->t('First name:') as $name) {
                $pos2 = mb_strpos($table2, $name, 0, "UTF-8");

                if (!empty($pos2)) {
                    break;
                }
            }
        } else {
            $pos2 = mb_strpos($table2, $this->t('First name:'), 0, "UTF-8");
        }
        $table2 = $this->splitCols($table2, [$pos1, ($p = $pos2) ? $p : 40]);

        if (count(array_filter($table2, function ($s) { return trim($s); })) != 2) {
            $this->logger->debug("Incorrect parse table2");

            return;
        }
        $stations = $this->re("#(" . $this->opt($this->t("All station information at once:")) . ".*?)(?:\n\n\n|$)#s", $text);

        $pickupLoc = explode("\n", trim($this->re("#" . $this->opt($this->t("Pick-up station:")) . "\s*(.*?)(?:\n[^\n]*:|$)#s", $table[0])));
        $dropoffLoc = explode("\n", trim($this->re("#" . $this->opt($this->t("Drop-off station:")) . "\s*(.*?)(?:\n[^\n]*:|\n\s*Please.*|\n\s*Die Rückgabeparkplätze|$)#su", $table[1])));
        $pickupend = end($pickupLoc);
        $dropoffend = end($dropoffLoc);
        $pickupHours = $this->re("#{$pickupend}\n\s*(.+)#", $this->re("#" . $this->opt($this->t("Pick-up station:")) . "\s*(.*?)" . $this->t("Directions:") . "#ms", $stations));
        $dropoffHours = $this->re("#{$dropoffend}\n\s*(.+)#", $this->re("#" . $this->opt($this->t("Drop-off station:")) . "\s*(.*?)" . $this->opt($this->t("Directions:")) . "#ms", $stations));

        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->re("#" . $this->opt($this->t("First name:")) . "\s*(.+)#", $table2[1]) . ' ' . $this->re("#" . $this->opt($this->t("Drivers name:")) . "\s*(.+)#", $table2[0]));

        $confirmation = $this->re("#" . $this->opt($this->t("Your reservation number:")) . "\s*(.+)#", $text);

        if (!empty($confirmation)) {
            $r->general()
                ->confirmation($confirmation);
        }

        $cancellation = $this->re("#" . $this->opt($this->t("Your cancelled reservation number:")) . "\s*(.+)#", $text);

        if (!empty($cancellation)) {
            $r->general()
                ->cancelled()
                ->status('cancelled')
                ->cancellationNumber($cancellation)
                ->confirmation($cancellation, 'cancelled reservation number');
        }

        $r->pickup()
            ->date(strtotime($this->normalizeDate($this->re("#" . $this->opt($this->t("Pick-up:")) . "\s*(.+)#", $table[0]))))
            ->location(implode(', ', preg_replace("/\s+/", ' ', array_map('trim', $pickupLoc))));

        if (!empty($pickupHours)) {
            $r->pickup()
                ->openingHours($pickupHours);
        }

        $r->dropoff()
            ->location(implode(', ', preg_replace("/\s+/", ' ', array_map('trim', $dropoffLoc))))
            ->date(strtotime($this->normalizeDate($this->re("#" . $this->opt($this->t("Drop-off:")) . "\s*(.+)#", $table[1]))));

        if (!empty($dropoffHours)) {
            $r->dropoff()
                ->openingHours($dropoffHours);
        }

        $r->car()
            ->type($this->re("#" . $this->opt($this->t("Car group:")) . "\s*(.+)#", $table2[0]))
            ->model($this->re("#" . $this->opt($this->t("Sample model:")) . "\s*(.+)#", $text));

        $total = $this->amount($this->re("#" . $this->t("Expected rental price \(gross\)") . "\s*([\d,.]+) [A-Z]{3}#", $text));
        $currency = $this->re("#" . $this->t("Expected rental price \(gross\)") . "\s*[\d,.]+ ([A-Z]{3})#", $text);

        if (!empty($total)) {
            $r->price()
                ->total($total)
                ->currency($currency);
        }

        $date = $this->re("#" . $this->opt($this->t("Date:")) . "\s*(.+)#", $text);

        if (preg_match("/^(\d+)\S(\d+)\S(\d+)$/u", $date, $m)) {
            if ($m[2] > 12) {
                $r->general()
                    ->date(strtotime($m[2] . '.' . $m[1] . '.' . $m[3]));
            } else {
                $r->general()
                    ->date(strtotime($m[1] . '.' . $m[2] . '.' . $m[3]));
            }
        } else {
            $r->general()
                ->date(strtotime($this->normalizeDate($date)));
        }

        return true;
    }

    private function parseHtml(Email $email)
    {
        $this->logger->warning('parseHtml');

        $patterns = [
            'dateTime' => '/(?<date>\d{1,2}[.\/\-]\d{1,2}[.\/\-]\d{2,4})[-\s]+(?<time>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)/', // Thursday, 10/22/2015 - 12:00 hrs    |    Freitag, 16.02.2018 - 18:00 Uhr
        ];

        $r = $email->add()->rental();
        $r->general()
            ->traveller($this->http->FindSingleNode("//*[self::h2 or self::h1][{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}[,\s]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[,!]+|$)/ui"));

        // Number
        if (empty($confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]", null, true, '/^([A-Z\d]{5,})$/'))) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number:'))}]", null, true, '/:\s*([A-Z\d]{5,})$/');
        }

        $r->general()
            ->confirmation($confirmation);

        // CarModel
        if (empty($model = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Vehicle category:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]"))) {
            $model = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Vehicle category:'))}]", null, false, "#:\s*(.+)#");
        }

        $r->car()
            ->model($model);

        $xpathFragment1 = '//text()[' . $this->contains($this->t('Pick-up:')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]';

        // PickupDatetime
        $dateTimePickup = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][1]');

        if (preg_match($patterns['dateTime'], $dateTimePickup, $matches)) {
            $r->pickup()
                ->date(strtotime($matches['date'] . ', ' . $matches['time']));
        }

        // PickupLocation
        $pickUpLocation = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][position()>1][last()]');

        if (!empty($pickUpLocation)) {
            $r->pickup()
                ->location($pickUpLocation);
        }

        $xpathFragment2 = '//text()[' . $this->contains($this->t('Drop-off:')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]';

        // DropoffDatetime
        $dateTimeDropoff = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[normalize-space(.)][1]');

        if (preg_match($patterns['dateTime'], $dateTimeDropoff, $matches)) {
            $r->dropoff()
                ->date(strtotime($matches['date'] . ', ' . $matches['time']));
        }

        // DropoffLocation
        $dropOffLocation = $this->http->FindSingleNode($xpathFragment2 . '/descendant::text()[normalize-space(.)][position()>1][last()]');

        if (!empty($dropOffLocation)) {
            $r->dropoff()
                ->location($dropOffLocation);
        }

        if (empty($r->getPickUpDateTime()) && empty($r->getDropOffDateTime())) { // it-1625726.eml
            $node1 = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pick-up:'))}]/ancestor::li[1]");
            $node2 = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Drop-off:'))}]/ancestor::li[1]");
            $node3 = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pick-up:'))}]/ancestor::li[1]/following-sibling::li[2]");

            if (preg_match("#:\s+(.+?\b\d{4})\s+\-\s+(.+?\b\d{4})$#", $node3, $m)) {
                $pickup = strtotime($m[1]);
                $dropoff = strtotime($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('Pick-up:'))}\s*(.+?)\s+\((\d+:\d+)\s*[hrs]*\)#", $node1, $m)) {
                $r->pickup()
                    ->location($m[1]);

                if (isset($pickup) && !empty($pickup)) {
                    $r->pickup()
                        ->date(strtotime($m[2], $pickup));
                }
            }

            if (preg_match("#{$this->opt($this->t('Drop-off:'))}\s*(.+?)\s+\((\d+:\d+)\s*[hrs]*\)#", $node2, $m)) {
                $r->dropoff()
                    ->location($m[1]);

                if (isset($dropoff) && !empty($dropoff)) {
                    $r->dropoff()
                        ->date(strtotime($m[2], $dropoff));
                }
            }
        }

        return true;
    }

    private function parseHtml_2(Email $email)
    {
        $this->logger->warning('parseHtml_2');

        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number:'))}]/following::text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\s*(\b[[:alpha:]]{1,4}\.\s+)?(\b[[:alpha:]][^,]*[[:alpha:]])[^\w\s]?\s*$/ui"));

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation number:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, '/^([A-Z\d]{5,})$/');

        if (!empty($confirmation)) {
            $r->general()
                ->confirmation($confirmation);
        }

        $r->car()
            ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle group'))}]/following::text()[normalize-space()!=''][1]"))
            ->model($this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle group'))}]/following::text()[normalize-space()!=''][2]"));

        // PickupDatetime
        $r->pickup()
            ->date(strtotime($this->normalizeDate(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr[last()]/descendant::text()[normalize-space()!='']")))))
            ->location(implode(", ", $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup Location'))}]/ancestor::tr[1]/following-sibling::tr[position()!=last()]/descendant::text()[normalize-space()!='']")));

        $r->dropoff()
            ->date(strtotime($this->normalizeDate(implode(" ", $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/ancestor::tr[1]/following-sibling::tr[last()]/descendant::text()[normalize-space()!='']")))))
            ->location(implode(", ",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/ancestor::tr[1]/following-sibling::tr[position()!=last()]/descendant::text()[normalize-space()!='']")));

        // TotalCharge
        // Currency
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total estimated price:'))}]/following::text()[normalize-space()!=''][1]");
        $total = $this->amount($node);
        $currency = $this->currency($node);

        if (!empty($total)) {
            $r->price()
                ->total($total)
                ->currency($currency);
        }

        // BaseFare
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle Subtotal'))}]/following::text()[normalize-space()!=''][1]");
        $cost = $this->amount($node);

        if (!empty($cost)) {
            $r->price()
                ->cost($cost);
        }

        // TotalTaxAmount
        $node = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/following::text()[normalize-space()!=''][1]");
        $tax = $this->amount($node);

        if (!empty($tax)) {
            $r->price()
                ->tax($tax);
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->info($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\.\d+\.\d{4}) (\d+:\d+) (?:hrs\.|Uhr|horas|heures|h|Uur:?|R\.|godzina|Hora|ora)\s*$#", //29.04.2014 17:00 hrs.
            "#^(\d+\/\d+\/\d{4}) (\d+:\d+) hrs\.$#", //10/12/2015 17:00 hrs.
            "#^(\w+)\s+(\d+)\s+(\d+:\d+)\s+(\d{4})\s+\|\s+\w+$#", // Jun  7 9:30 2019 | Fri
            "#^(\w+)\s+(\d+)\s+([\d\:]+)\s+(\d{4})\s+\|\s+\w+\s(A?P?M)$#", //Jun 11 12:34 2020 | Thu PM
            "#^(\d+)\/(\d+)\/(\d{4})$#", //23/05/2021
            "#^(\d+)\/(\d+)\/(\d{4})\s*([\d\:]+)\s*\w+$#",
        ];
        $out = [
            "$1, $2",
            "$1, $2",
            "$2 $1 $4, $3",
            "$2 $1 $4, $3$5",
            "$1.$2.$3",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->info($str);

        return $this->dateStringToEnglish($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    //	private function split($re, $text){
    //		$r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    //		$ret = [];
//
    //		if (count($r) > 1){
    //			array_shift($r);
    //			for($i=0; $i<count($r)-1; $i+=2){
    //				$ret[] = $r[$i].$r[$i+1];
    //			}
    //		} elseif (count($r) == 1){
    //			$ret[] = reset($r);
    //		}
    //		return $ret;
    //	}

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    //	private function ColsPos($table, $correct = 5){
    //		$pos = [];
    //		$rows = explode("\n", $table);
    //		foreach($rows as $row)
    //			$pos = array_merge($pos, $this->rowColsPos($row));
    //		$pos = array_unique($pos); sort($pos); $pos = array_merge([], $pos);
//
    //		foreach($pos as $i=>$p){
    //			for($j=$i-1; $j>=0; $j=$j-1){
    //				if(isset($pos[$j])){
    //					if(isset($pos[$i])){
    //						if($pos[$i] - $pos[$j]<$correct)
    //							unset($pos[$i]);
    //					}
    //					break;
    //				}
    //			}
    //		}
//
    //		sort($pos); $pos = array_merge([], $pos);
    //		return $pos;
    //	}

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if ($pos === false) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function amount($s)
    {
        if (strlen($s) === 0) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            'US$'=> 'USD',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            '₹'  => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function mb_strpos_array($str, $arr)
    {
        $substrs = (array) $arr;

        foreach ($substrs as $substr) {
            if (($pos = mb_strpos($str, $substr, 0, "UTF-8")) !== false) {
                return $pos;
            }
        }

        return false;
    }
}
