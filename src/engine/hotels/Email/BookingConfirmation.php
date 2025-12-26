<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "hotels/it-12.eml, hotels/it-13.eml, hotels/it-14.eml, hotels/it-15.eml, hotels/it-1545409.eml, hotels/it-1551153.eml, hotels/it-1558592.eml, hotels/it-1564633.eml, hotels/it-1566971.eml, hotels/it-1571561.eml, hotels/it-16.eml, hotels/it-1604436.eml, hotels/it-1682847.eml, hotels/it-17.eml, hotels/it-1726490.eml, hotels/it-1734968.eml, hotels/it-1739853.eml, hotels/it-1855016.eml, hotels/it-1919858.eml, hotels/it-1934139.eml, hotels/it-2030650.eml, hotels/it-2192441.eml, hotels/it-2194006.eml, hotels/it-2209555.eml, hotels/it-2210281.eml, hotels/it-2210290.eml, hotels/it-2210296.eml, hotels/it-2468207.eml, hotels/it-2504145.eml, hotels/it-2514183.eml, hotels/it-2551941.eml, hotels/it-2551948.eml, hotels/it-2586932.eml, hotels/it-2589574.eml, hotels/it-2590910.eml, hotels/it-2591763.eml, hotels/it-2591934.eml, hotels/it-2592724.eml, hotels/it-2592849.eml, hotels/it-2630802.eml, hotels/it-2631206.eml, hotels/it-2631337.eml, hotels/it-2631345.eml, hotels/it-2631389.eml, hotels/it-2631394.eml, hotels/it-271067137.eml, hotels/it-2711451.eml, hotels/it-2726700.eml, hotels/it-2733294.eml, hotels/it-2734324.eml, hotels/it-2736247.eml, hotels/it-2736278.eml, hotels/it-2756815.eml, hotels/it-2955328.eml, hotels/it-2956277.eml, hotels/it-2998496.eml, hotels/it-2998511.eml, hotels/it-2998523.eml, hotels/it-3.eml, hotels/it-3025270.eml, hotels/it-3140990.eml, hotels/it-3238628.eml, hotels/it-3461857.eml, hotels/it-4.eml, hotels/it-5.eml, hotels/it-6.eml, hotels/it-7.eml";

    public static $dictionary = [
        'en' => [
            //            "Confirmation Number is" => "",
            //            "Hotel contact details" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Room:" => "",
            //            "Room #:" => "",
            //            "Occupancy" => "",
            "adult" => ["adult", "adults"],
            //            "children" => "",
            //            "Check in date" => "",
            //            "Check out date" => "",
            //            "Room details" => "",
            "Tax recovery charges and service fees" => ["Tax recovery charges and service fees", "Taxes & fees"],
            "Total"                                 => ["Total", "Total due at hotel", "Total to be charged by the hotel"],
            //            "Welcome Rewards™ free night applied" => "",
            //            "Cancellation policy" => "",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'es' => [
            "Confirmation Number is"                     => ["número de confirmación de Hotels.com es", "Hotels.com es", "número de confirmación de Hoteles.com es"],
            "Hotel contact details"                      => ["Datos de contacto del hotel", "Información de contacto del hotel"],
            "Check-in time"                              => ["Hora de check-in", "Hora de llegada"],
            "Check-out time"                             => "Hora de salida",
            "Room:"                                      => "Habitación:",
            "Room #:"                                    => "Habitación #:",
            "Occupancy"                                  => "Ocupación",
            "adult"                                      => ["adulto", "adultos"],
            "children"                                   => ["niño"],
            "Check in date"                              => ["Fecha de check-in", "Fecha de entrada"],
            "Check out date"                             => ["Fecha de check-out", "Fecha de salida"],
            "Room details"                               => "Detalles de la habitación",
            "Tax recovery charges and service fees"      => "Impuestos y cargos por servicios",
            "Total"                                      => "Total",
            "Welcome Rewards™ free night applied"        => ["Noche gratis de Welcome Rewards™ aplicada", "Se ha utilizado una noche Hoteles.com™ Rewards gratis"],
            "Cancellation policy"                        => "Política de cancelación",
            "Your Welcome Rewards™ membership number is" => ["Tu número de socio Welcome Rewards™ es", "Tu Welcome Rewards™ número de socio es"],
        ],
        'de' => [
            "Confirmation Number is" => ["Bestätigungsnummer ist", "Hotels.com-Bestätigungsnummer lautet"],
            "Hotel contact details"  => "Kontaktdaten des Hotels",
            "Check-in time"          => "Check-in-Zeit",
            "Check-out time"         => "Check-out-Zeit",
            "Room:"                  => "Zimmer:",
            "Room #:"                => "Zimmer #:",
            "Occupancy"              => ["Belegung:", "Belegung"],
            "adult"                  => ["Erwachsene", "Erwachsen"],
            //            "children" => "",
            "Check in date"                         => ["Anreisedatum:", "Anreisedatum"],
            "Check out date"                        => ["Abreisedatum:", "Abreisedatum"],
            "Room details"                          => "Zimmerdetails",
            "Tax recovery charges and service fees" => "Steuern und Gebühren",
            "Total"                                 => ["Gesamtpreis", "Gesamtpreis (im Hotel zu zahlen)"],
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => ["Stornierungsbedingungen:", "Stornierungsbedingungen"],
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'no' => [
            "Confirmation Number is" => ["Hotels.com-bekreftelsesnummeret ditt:", "Hotels.com er"],
            "Hotel contact details"  => "Hotellets kontaktopplysninger",
            "Check-in time"          => "Innsjekkingstid",
            "Check-out time"         => "Utsjekkingstid",
            "Room:"                  => "Rom:",
            "Room #:"                => "Rom #:",
            "Occupancy"              => "Antall personer",
            "adult"                  => ["voksen", "voksne"],
            //            "children" => "",
            "Check in date"                         => "Innsjekkingsdato",
            "Check out date"                        => "Utsjekkingsdato",
            "Room details"                          => "Romdetaljer",
            "Tax recovery charges and service fees" => "Skatter og avgifter",
            "Total"                                 => "Totalt",
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => "Avbestillingsregler",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'pt' => [
            "Confirmation Number is" => "número de confirmação da Hoteis.com é",
            "Hotel contact details"  => "Informações de contato do hotel",
            "Check-in time"          => "Horário de check-in",
            "Check-out time"         => "Horário de check-out",
            "Room:"                  => "Quarto:",
            "Room #:"                => "Quarto #:",
            "Occupancy"              => "Número de hóspedes/quarto",
            "adult"                  => ["adulto", "adultos"],
            //            "children" => "",
            "Check in date"                         => "Data de check-in",
            "Check out date"                        => "Data de check-out",
            "Room details"                          => "Detalhes do quarto",
            "Tax recovery charges and service fees" => "Impostos e taxas",
            "Total"                                 => "Total",
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => "Política de cancelamento",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'fr' => [
            "Confirmation Number is" => ["numéro de confirmation Hotels.com :", "numГ©ro de confirmation Hotels.comВ"],
            "Hotel contact details"  => ["Pour contacter l’hôtel", "Pour contacter lвЂ™hГґtel"],
            "Check-in time"          => ["Heure d’arrivée", "Heure dвЂ™arrivГ©e"],
            "Check-out time"         => ["Heure de départ", "Heure de dГ©part"],
            "Room:"                  => "Chambre :",
            "Room #:"                => "Chambre # :", // check
            "Occupancy"              => "Personnes",
            "adult"                  => ["adulte"],
            //            "children" => "",
            "Check in date"                         => ["Date d’arrivée", "Date dвЂ™arrivГ©e"],
            "Check out date"                        => ["Date de départ", "Date de dГ©part"],
            "Room details"                          => "Informations sur la chambre",
            "Tax recovery charges and service fees" => "Taxes et frais",
            "Total"                                 => "Total",
            "Welcome Rewards™ free night applied"   => ["Nuit gratuite Welcome Rewards™ appliquée", "Nuit gratuite Welcome Rewardsв„ў appliquГ©e"],
            "Cancellation policy"                   => ["Conditions d’annulation", "Conditions dвЂ™annulation"],
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'sv' => [
            "Confirmation Number is"                => ["bekräftelsenummer från Hotels.com är", "bekräftelsenummer från Hotels.com", "bekrГ¤ftelsenummer frГҐn Hotels.com Г¤r"],
            "Hotel contact details"                 => "Hotellets kontaktuppgifter",
            "Check-in time"                         => "Incheckningstid",
            "Check-out time"                        => "Utcheckningstid",
            "Room:"                                 => "Rum:",
            "Room #:"                               => "Rum #:",
            "Occupancy"                             => ["Beläggning", "BelГ¤ggning"],
            "adult"                                 => ["vuxen", "vuxna"],
            "children"                              => "barn",
            "Check in date"                         => "Incheckning",
            "Check out date"                        => "Utcheckning",
            "Room details"                          => "Information om rummet",
            "Tax recovery charges and service fees" => "Skatter och avgifter",
            "Total"                                 => ["Summa"],
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => "Avbokningsregler",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'da' => [
            "Confirmation Number is" => "Hotels.com-bekræftelsesnummer er",
            "Hotel contact details"  => "Hotellets kontaktoplysninger",
            "Check-in time"          => "Ankomsttid",
            "Check-out time"         => "Afrejsetid",
            "Room:"                  => "Værelse:",
            "Room #:"                => "Værelse #:",
            "Occupancy"              => "Ocupación",
            "adult"                  => ["voksen", "voksne"],
            //            "children" => "",
            "Check in date"  => "Indtjekningsdato",
            "Check out date" => "Udtjekningsdato",
            "Room details"   => "Værelsesoplysninger",
            //            "Tax recovery charges and service fees" => [""],
            "Total" => ["Samlet beløb til betaling på hotellet"],
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => "Afbestillingspolitik",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'ja' => [
            "Confirmation Number is" => "Hotels.com 確認番号 :",
            "Hotel contact details"  => "ホテルの連絡先詳細",
            "Check-in time"          => "チェックイン時間",
            "Check-out time"         => "チェックアウト時間",
            "Room:"                  => "客室 :",
            "Room #:"                => "客室 # :", // to check
            "Occupancy"              => "定員",
            "adult"                  => ["大人"],
            //            "children" => "",
            "Check in date"                         => "チェックイン 日",
            "Check out date"                        => "チェックアウト日",
            "Room details"                          => "客室の詳細",
            "Tax recovery charges and service fees" => ["税およびサービス料"],
            "Total"                                 => ["合計"],
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => "キャンセル ポリシー",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'pl' => [
            "Confirmation Number is" => "Numer potwierdzenia rezerwacji Hotels.com to",
            "Hotel contact details"  => "Dane kontaktowe hotelu",
            "Check-in time"          => "Godzina zameldowania",
            "Check-out time"         => "Godzina wymeldowania",
            "Room:"                  => "Pokój",
            "Room #:"                => "Pokój #", // to check
            "Occupancy"              => "Liczba gości",
            "adult"                  => ["osoby dorosłe"],
            //            "children" => "",
            "Check in date"  => "Data zameldowania",
            "Check out date" => "Data wymeldowania",
            "Room details"   => "Szczegóły dotyczące pokoju",
            //            "Tax recovery charges and service fees" => [""],
            "Total" => ["Całkowita kwota należna w hotelu"],
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => "Zasady anulowania rezerwacji",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'cs' => [
            "Confirmation Number is" => "Číslo potvrzení společnosti Hotels.com je",
            "Hotel contact details"  => "Kontaktní údaje hotelu",
            "Check-in time"          => "Čas registrace",
            "Check-out time"         => "Čas odhlášení",
            "Room:"                  => "Pokoj:",
            "Room #:"                => "Pokoj:", // to check
            "Occupancy"              => "Obsazenost",
            "adult"                  => ["dospělí"],
            //            "children" => "",
            "Check in date"                         => "Datum registrace",
            "Check out date"                        => "Datum odhlášení",
            "Room details"                          => "Podrobné informace o pokoji",
            "Tax recovery charges and service fees" => ["Daně a poplatky"],
            "Total"                                 => ["Celkem"],
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => "Storno podmínky",
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'zh' => [
            "Confirmation Number is" => ["確認編號是", "Hotels.com 確認編號："],
            "Hotel contact details"  => ["飯店聯絡資料", "酒店聯絡資料"],
            "Check-in time"          => "入住時間",
            "Check-out time"         => "退房時間",
            "Room:"                  => "客房：",
            "Room #:"                => "客房 #：",
            "Occupancy"              => "入住人數",
            "adult"                  => ["位成人"],
            //            "children" => "",
            "Check in date"                         => "入住日期",
            "Check out date"                        => "退房日期",
            "Room details"                          => ["客房詳細資訊", "客房資料"],
            "Tax recovery charges and service fees" => ["稅金和服務費", "稅項及其他費用"],
            "Total"                                 => ["總金額", "總額"],
            //            "Welcome Rewards™ free night applied" => "",
            "Cancellation policy" => ["取消規定", "取消政策"],
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
        'fi' => [
            "Confirmation Number is"                => "Hotels.com-varausnumerosi on",
            "Hotel contact details"                 => "Hotellin yhteystiedot",
            "Check-in time"                         => "Tuloaika",
            "Check-out time"                        => "Lähtöaika",
            "Room:"                                 => "Huone:",
            "Room #:"                               => "Huone #:",
            "Occupancy"                             => "Henkilömäärä",
            "adult"                                 => ["aikuinen", "aikuista"],
            "children"                              => "lapsi",
            "Check in date"                         => "Tulopäivä",
            "Check out date"                        => "Lähtöpäivä",
            "Room details"                          => "Huoneen tiedot",
            "Tax recovery charges and service fees" => ["Verot & maksut"],
            "Total"                                 => ["Loppusumma", "Hotellilla maksettava loppusumma"],
            "Welcome Rewards™ free night applied"   => "Hotels.com™ Rewards -ilmainen* palkintoyö käytetty",
            "Cancellation policy"                   => ["Peruutusehdot"],
            //            "Your Welcome Rewards™ membership number is" => "",
        ],
    ];

    private $detectFrom = '.hotels.com';

    private $detectSubject = [
        'en' => 'Hotels.com booking confirmation',
        'es' => 'Confirmación de reservación de Hotels.com',
        'Número de confirmación de reserva de Hoteles.com',
        'de' => 'Hotels.com-Buchungsbestätigung',
        'no' => 'Hotels.com bestillingsbekreftelse',
        'pt' => ' da Hoteis.com -', //Confirmação de reserva 116763376894 da Hoteis.com - Courtyard by Marriott Aventura Mall - Aventura
        'fr' => 'Hotels.com - Confirmation deréservation',
        'sv' => 'Hotels.coms bokningsbekräftelse',
        'da' => 'Hotels.coms reservationsbekræftelse',
        'ja' => 'Hotels.com 予約確認番号',
        'pl' => 'Nr potwierdzenia rezerwacji Hotels.com',
        'cs' => 'Číslo potvrzení rezervace společnosti Hotels.com',
        'zh' => 'Hotels.com 訂房確認',
        'fi' => 'Hotels.com varausvahvistus',
    ];
    private $detectBody = [
        'en' => ['just look forward to your stay', 'That’s all done for you so now you can look forward to your stay'],
        'es' => ['Ya no tienes que hacer nada más, ¡aparte de esperar tu viaje!', 'No tienes que hacer nada más, solo disfrutar de tu estancia'],
        'de' => ['also lehnen Sie sich zurück und freuen Sie sich auf Ihren Aufenthalt', 'Sie nicht zu tun - genießen Sie einfach die Vorfreude in vollen Zügen'],
        'no' => ['nå kan du bare glede deg til oppholdet ditt'],
        'pt' => ['Agora você só precisa esperar até sua estadia'],
        'fr' => ['Maintenant, plus quвЂ™Г vous dГ©tendre en attendant votre voyageВ', 'Maintenant, plus qu’à vous détendre en attendant votre voyage '],
        'sv' => ['du kan bara se fram emot din vistelse'],
        'da' => ['så nu kan du bare glæde dig til dit ophold'],
        'ja' => ['素敵なご旅行を。'],
        'pl' => ['Nie trzeba już nic więcej robić. Życzymy udanego pobytu'],
        'cs' => ['Rezervace byla dokončena, a můžete se tedy těšit na cestu'],
        'zh' => ['預祝您有一個愉快的旅程', '客房：', '客房 1：'],
        'fi' => ['Varauksesi on nyt tehty ja voit alkaa suunnitella lomaasi'],
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".hotels.com") or contains(@href, "hoteis.com")]')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $account = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your Welcome Rewards™ membership number is")) . "]", null, true, "#" . $this->preg_implode($this->t("Your Welcome Rewards™ membership number is")) . "\s+(\d{5,})\b#");

        if (!empty($account)) {
            $email->ota()->account($account, false);
        }

        $conf = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Confirmation Number is")) . "]/following::text()[normalize-space()][1])[1]",
            null, true, "#^\s*([A-Z\d]{5,})\s*[.]?\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Number is")) . "]",
                null, true, "#" . $this->preg_implode($this->t("Confirmation Number is")) . "\s+([A-Z\d]{5,})\s*[.]?\s*$#");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Number is")) . "]/following::text()[normalize-space()][1]",
                null, true, "#^\s*([A-Z\d]{5,})\s*[.]?\s*$#");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//*[" . $this->contains($this->t("Confirmation Number is")) . " and not(.//*[" . $this->contains($this->t("Confirmation Number is")) . "])]",
                null, true, "#" . $this->preg_implode($this->t("Confirmation Number is")) . "\s+([A-Z\d]{5,})\s*[.]?\s*$#");
        }
        $email->ota()
            ->confirmation($conf);

        // Hotel
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->travellers(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t('Occupancy')) . "]/ancestor::td[1]/following::td[" . $this->contains($this->t("adult")) . "][1]", null, "#^\s*([^,、，]+)[、,，]#u")))
        ;
        $cancellation = implode('. ', $this->http->FindNodes("//text()[" . $this->eq($this->t('Cancellation policy')) . "]/ancestor::td[1]/following::td[1]/descendant-or-self::*[count(./node()[normalize-space()]) > 1][1]/node()[normalize-space()]"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Cancellation policy')) . "]/ancestor::td[1]/following::td[1]");
        }
        $h->general()
            ->cancellation($cancellation, true, true);

        // Hotel
        $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel contact details")) . "]/preceding::a[1][contains(@href, 'hotels.com') or contains(@href, 'hoteis.com') or contains(@href, 'hoteles.com')]");
        $address = implode("\n", $this->http->FindNodes("//*[contains(@class, 'hotel-address')][1]//text()[normalize-space()]"));

        if (empty($address) && !empty($name)) {
            $addressStr = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Hotel contact details")) . "]/ancestor::*[contains(., '" . $name . "')][1]//text()[normalize-space()]"));

            if (preg_match("#" . $this->preg_implode($this->t("Hotel contact details")) . "\s+([\s\S]+\n(.+))\n[\s\S]+\n\\2\s*$#u", $addressStr, $m)
                    || preg_match("#" . $this->preg_implode($this->t("Hotel contact details")) . "\s+([\s\S]+\n\s*[\+\- \(\)\d]{5,})\s*$#u", $addressStr, $m)) {
                $address = $m[1];
            }
        }

        if (preg_match("#([\s\S]+)\n\s*([\+\- \(\)\d]{5,})\s*$#", $address, $m)) {
            $address = preg_replace("#\s*\n\s*#", ', ', trim($m[1]));
            $phone = trim($m[2]);
        } else {
            $address = preg_replace("#\s*\n\s*#", ', ', $address);
        }

        $h->hotel()
            ->name($name)
            ->address($address)
            ->phone($phone ?? null, true, true)
        ;

        // Booked
        $dates = array_values(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Check in date")) . "]/ancestor::td[1]/following::td[position()<3][contains(translate(.,'0123456789','%%%%%%%%%%'), '%')][1]/descendant::text()[normalize-space()][1]/ancestor-or-self::*[1]")));
        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in time")) . "]/following::text()[normalize-space()][1]"));

        if (count($dates) == 1 && !empty($dates[0])) {
            $date = $this->normalizeDate($dates[0]);

            if (!empty($time) && !empty($date)) {
                $date = strtotime($time, $date);
            }
            $h->booked()->checkIn($date);
        }

        $dates = array_values(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Check out date")) . "]/ancestor::td[1]/following::td[position()<3][contains(translate(.,'0123456789','%%%%%%%%%%'), '%')][1]/descendant::text()[normalize-space()][1]/ancestor-or-self::*[1]")));
        $time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-out time")) . "]/following::text()[normalize-space()][1]"));

        if (count($dates) == 1 && !empty($dates[0])) {
            $date = $this->normalizeDate($dates[0]);

            if (!empty($time) && !empty($date)) {
                $date = strtotime($time, $date);
            }
            $h->booked()->checkOut($date);
        }

        $guestsNodes = $this->http->XPath->query("//text()[" . $this->eq($this->t("Occupancy")) . "]/ancestor::td[1]/following::td[1]");
        $guests = 0;
        $kids = 0;

        foreach ($guestsNodes as $groot) {
            $text = implode("\n", $this->http->FindNodes("node()", $groot));

            if (preg_match("#^\s*[^,、，]+[、,，]\s*(\d+)\s" . $this->preg_implode($this->t('adult')) . "#u", $text, $m)
                || preg_match("#^\s*[^,、，]+[、,，]\s*" . $this->preg_implode($this->t('adult')) . "\s(\d+)\b#u", $text, $m)) {
                $guests += $m[1];
            }

            if (preg_match("#[、,，]\s*(\d+)\s" . $this->preg_implode($this->t('children')) . "#u", $text, $m)
                || preg_match("#[、,，]\s*" . $this->preg_implode($this->t('children')) . "\s(\d+)\b#u", $text, $m)) {
                $kids += $m[1];
            }
        }

        if (!empty($guests)) {
            $h->booked()->guests($guests);
        }

        if (!empty($kids)) {
            $h->booked()->kids($kids);
        }

        // Rooms
        $types = $this->http->FindNodes("//text()[" . $this->eq($this->t('Room:')) . " or " . $this->eq($this->t('Room #:'), "translate(normalize-space(),'1234567890', '##########')") . "]/ancestor::td[1]/following-sibling::td[1]");
        $typeDescs = $this->http->FindNodes("//text()[" . $this->eq($this->t('Room details')) . "]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($types) && empty($typeDescs)) {
            $types = $this->http->FindNodes("//text()[" . $this->eq($this->t('Room:')) . " or " . $this->eq($this->t('Room #:'), "translate(normalize-space(),'1234567890', '##########')") . "]/ancestor::table[1]/following-sibling::table[1]");
            $typeDescs = $this->http->FindNodes("//text()[" . $this->eq($this->t('Room details')) . "]/ancestor::table[1]/following-sibling::table[1]");
        }

        if (!empty($types) && !empty($typeDescs)) {
            if (count($types) == count($typeDescs)) {
                foreach ($types as $i => $type) {
                    $h->addRoom()
                        ->setType($type)
                        ->setDescription($typeDescs[$i])
                    ;
                }
            } elseif (count($types) > count($typeDescs)) {
                foreach ($types as $i => $type) {
                    $h->addRoom()
                        ->setType($type);
                }
            } elseif (count($types) < count($typeDescs)) {
                foreach ($typeDescs as $i => $type) {
                    $h->addRoom()
                        ->setDescription($type);
                }
            }
        } elseif (!empty($types)) {
            foreach ($types as $i => $type) {
                $h->addRoom()
                    ->setType($type);
            }
        } elseif (!empty($typeDescs)) {
            foreach ($typeDescs as $i => $type) {
                $h->addRoom()
                    ->setDescription($type);
            }
        }

        $this->detectDeadLine($h);

        // Price
        $tax = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Tax recovery charges and service fees')) . "]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $tax, $m)) {
            $h->price()
                ->tax($this->amount($m['amount']))
            ;
        }
        $total = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Total')) . "]/ancestor::td[1]/following-sibling::td[1])[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ']*)\s*$#u", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ']*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
        $spents = $this->http->FindNodes("//*[" . $this->contains($this->t('Welcome Rewards™ free night applied')) . " and (not(./*) or not(.//*[" . $this->contains($this->t('Welcome Rewards™ free night applied')) . "]))]");

        if (!empty($spents)) {
            $h->price()
                ->spentAwards(count($spents) . ' night Rewards');
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
              preg_match("#^Cancel at least (\d+ hours) before day of arrival($|\.)#u", $cancellationText, $m) // en
           || preg_match("#^Free cancellation can be made up until (\d+ days) before the check-in day($|\.)#u", $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadlineRelative($m[1], '00:00');
        }

        if (
               preg_match("#Kostenlose Stornierung bis zum (?<date>\d+\.\d+\.\d+)\.#ui", $cancellationText, $m) // de
            || preg_match("#Cancelación sin costo hasta el (?<date>\d+[/.]\d+[/.]\d+)\.#ui", $cancellationText, $m) // es
            || preg_match("#Cancelamento grátis até (?<date>\d+/\d+/\d+)\.#ui", $cancellationText, $m) // pt
            || preg_match("#Free cancellation until (?<date>\d+/\d+/\d+)\.#ui", $cancellationText, $m) // en
            || preg_match("#Gratis afbestilling indtil (?<date>\d+/\d+/\d+)\.#ui", $cancellationText, $m) // da
            || preg_match("#Można anulować bezpłatnie do dnia (?<date>\d+/\d+/\d+)\.#ui", $cancellationText, $m) // pl
            || preg_match("#Bezplatné zrušení do (?<date>\d+\.\d+\.\d+)\.#ui", $cancellationText, $m) // cs
            || preg_match("#Ilmainen peruutus (?<date>\d+\.\d+\.\d+) mennessä\.#ui", $cancellationText, $m) // cs
        ) {
            if ($this->lang == 'en' && !empty($this->http->FindSingleNode("(//a[contains(@href, 'locale=en_US')])[1]/@href"))) {
                $m['date'] = preg_replace('#(\d+)/(\d+)/(\d{2})$#', '$2.$1.20$3', $m['date']);
            } else {
                $m['date'] = str_replace('/', '.', $m['date']);
            }
            $h->booked()
                ->deadline(strtotime($m['date']));
        }

        if (
            preg_match("#^(?<y>\d{4})/(?<m>\d+)/(?<d>\d+)\s*までキャンセル手数料無料#ui", $cancellationText, $m) // ja
        ) {
            $h->booked()
                ->deadline(strtotime($m['d'] . '.' . $m['m'] . '.' . $m['y']));
        }

        if (
               preg_match("#This special discounted rate is non-refundable\.#ui", $cancellationText, $m) // en
            || preg_match("#Denne spesialprisen er ikke[ \-]refunderbar\.#ui", $cancellationText, $m) // no
            || preg_match("#Esta tarifa especial com desconto não é reembolsável\.#ui", $cancellationText, $m) // pt
            || preg_match("#Ce tarif exceptionnel n’est pas remboursable\.#ui", $cancellationText, $m) // fr
            || preg_match("#Tarif spГ©cial non-remboursable Ce tarif exceptionnel nвЂ™est pas remboursable\.#ui", $cancellationText, $m) // fr
            || preg_match("#Det här är ett rabatterat specialpris och återbetalas inte\.#ui", $cancellationText, $m) // sv
            || preg_match("#Det hГ¤r Г¤r ett rabatterat specialpris och ГҐterbetalas inte\.#ui", $cancellationText, $m) // sv
            || preg_match("#此優惠價格不得退款。\.#ui", $cancellationText, $m) // zh
            || preg_match("#不設退款特別房價#ui", $cancellationText, $m) // zh
        ) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('date = '.$date);
        $in = [
            // Friday, January 4, 2013
            "#^\s*[^\s\d]+[,\s]+([^\s\d]+)\s+(\d{1,2})[\s,]+(\d{4})\s*$#u",
            // Søndag 27. januar 2013; Søndag d. 1. september 2013
            "#^\s*[^\d]+\s+(\d{1,2})[\s\.]+([^\s\d]+)\s+(\d{4})\s*$#u",
            // sábado, 17 de enero de 2015
            "#^\s*[^\d]+[\s,]+(\d{1,2})\s+de\s+([^\s\d]+)\s+de\s+(\d{4})\s*$#u",
            // 15/07/2013
            "#^\s*(\d{1,2})/(\d{2})/(\d{4})\s*$#",
            // 2014 年 07 月 24 日 (木曜日)
            "#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*[\(,]\D+$#u",
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1.$2.$3",
            "$3.$2.$1",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date = '.$date);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeTime($time)
    {
//        $this->logger->debug('$time = '.$time);
        $in = [
            "#^\s*(\d{1,2})\s*([ap]m)\s*$#i", // 2 PM
            "#(noon|middag|mediodía|meio-dia|中午|正午)#",
            "#(meia-noite)#",
            "#^\s*(?:kl\.\s*)?(\d{1,2})[h.:](\d{2})(?:\s*Uhr|\s*h)?\s*$#i", // 11.00 Uhr; kl. 14.00; 15h00
        ];
        $out = [
            "$1:00 $2",
            "12:00",
            "00:00",
            "$1:$2",
        ];
        $time = preg_replace($in, $out, $time);

        if (!preg_match("#^\d{1,2}:\d{2}( [ap]m)?$#i", $time)) {
            return null;
        }

        return $time;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = trim(str_replace(",", ".", preg_replace("#[., '](\d{3})#", "$1", $price)));

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'     => 'EUR',
            'HK$'   => 'HKD',
            'в‚¬'   => 'EUR',
            '$'     => 'USD',
            '£'     => 'GBP',
            'R$'    => 'BRL',
            'NZ$'   => 'NZD',
            'NT$'   => 'TWD',
            '￥'     => 'JPY',
            '฿'     => 'THB',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function nextTd($field, $regExp = null)
    {
        return $this->http->FindSingleNode("(//text()[{$this->eq($field)}])[1]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][normalize-space()!=''][1]",
            null, false, $regExp);
    }

    private function nextTds($field, $regExp = null)
    {
        return $this->http->FindNodes("//text()[{$this->eq($field)}]/ancestor::*[self::td or self::th][1]/following-sibling::*[self::td or self::th][normalize-space()!=''][1]",
            null, $regExp);
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return $text . "=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
