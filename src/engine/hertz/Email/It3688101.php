<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3688101 extends \TAccountChecker
{
    public $mailFiles = "hertz/it-11522157.eml, hertz/it-14413092.eml, hertz/it-151478823.eml, hertz/it-35991788.eml, hertz/it-48460574.eml"; // +4 bcdtravel

    public $lang = '';

    public static $dict = [
        'fi' => [
            'Confirmation Number is:'                     => ['Varaus'],
            'Thanks for Traveling at the Speed of Hertz®' => ['Kiitos, että valitsit Hertzin,'],
            // 'You have successfully checked-in' => [''],
            // 'Service Type' => '',
            'Modify/ Cancel Reservation'        => 'Muuta /Peruuta Varaus',
            'Pickup and Return Location'        => 'Nouto ja palautuspaikka',
            'Address'                           => 'Osoite',
            'Hours of Operation'                => ['Aukioloajat:', 'Aukioloajat :'],
            'Phone Number'                      => ['Puhelinnumero:', 'Puhelinnumero :'],
            // 'Fax Number' => '',
            // 'Pickup Location' => '',
            // 'Return Location' => '',
            'Pickup Time'                       => ['Noutoaika'],
            'Return Time'                       => ['Palautusaika'],
            'Amount to be paid at time of rent' => ['Mitä Maksat Nyt'],
            'Total'                             => 'Loppusumma',
            'Your Vehicle'                      => 'Ajoneuvosi',
            'Discounts'                         => 'Alennukset',
            // 'Fees' => '',
            // 'Taxes' => '',
            'feeNames'  => ['Myyntivero', 'Toimipisteen palvelumaksu'],
            'Rate Code' => ['Hintakoodi:', 'Hintakoodi :'],
        ],
        'cs' => [
            'Confirmation Number is:'                     => 'Vaše rezervační číslo je:',
            'Thanks for Traveling at the Speed of Hertz®' => 'Děkujeme, že cestujete s rychlostí Hertz,',
            'Service Type'                                => 'Typ servisu:',
            'Modify/ Cancel Reservation'                  => 'ZMĚNIT ZRUŠIT REZERVACI',
            //'Pickup and Return Location'        => 'Agenzia di Ritiro e Consegna',
            'Address'                           => 'Adresa',
            'Hours of Operation'                => 'Otevírací doba:',
            'Phone Number'                      => 'Telefonní číslo:',
            //'Fax Number'                        => '',
            'Pickup Location'                   => 'Pobočka vyzvednutí:', // to check
            'Return Location'                   => 'Pobočka vrácení:', // to ckeck
            'Pickup Time'                       => 'Vyzvednutí:',
            'Return Time'                       => 'Vrácení:',
            'Amount to be paid at time of rent' => 'Platba na pobočce',
            'Total'                             => 'Cena celkem',
            'Your Vehicle'                      => 'Vaše vozidlo',
            //'Discounts'                         => '',
            //			'Fees' => '',
            //			'Taxes' => '',
            // 'feeNames' => [''],
            'Rate Code' => 'Tarifní kód: :',
        ],
        'it' => [
            'Confirmation Number is:' => 'Il tuo numero di prenotazione è:',
            //			'Thanks for Traveling at the Speed of Hertz®' => '',
            //			'Service Type' => '',
            'Modify/ Cancel Reservation'        => 'Modifica/Cancella',
            'Pickup and Return Location'        => 'Agenzia di Ritiro e Consegna',
            'Address'                           => 'Indirizzo',
            'Hours of Operation'                => "Orario d'apertura",
            'Phone Number'                      => 'Numero di telefono',
            'Fax Number'                        => 'Numero di Fax',
            'Pickup Location'                   => 'Agenzia di Ritiro', // to check
            'Return Location'                   => 'Agenzia di Consegna', // to ckeck
            'Pickup Time'                       => 'Ritiro',
            'Return Time'                       => 'Consegna',
            'Amount to be paid at time of rent' => 'Pago a fine noleggio',
            'Total'                             => 'Totale',
            'Your Vehicle'                      => 'Il tuo veicolo',
            'Discounts'                         => 'Sconti',
            //			'Fees' => '',
            //			'Taxes' => '',
            // 'feeNames' => [''],
            'Rate Code' => 'Codice Tariffa',
        ],
        'en' => [
            'Confirmation Number is:'                     => ['Confirmation Number is:', 'confirmation number is:', 'Your Confirmation Number is:'],
            'Thanks for Traveling at the Speed of Hertz®' => ['Thanks for Traveling at the Speed of Hertz®', 'Thanks for Travelling at the Speed of Hertz®', 'Thanks for Travelling at the Speed of Hertz', 'Thanks for Traveling at the Speed of Hertz'],
            //			'You have successfully checked-in' => [''],
            //			'Service Type' => '',
            'Modify/ Cancel Reservation' => ['Modify/ Cancel Reservation', 'Modify/Cancel Reservation'],
            'Pickup and Return Location' => ['Pickup and Return Location', 'Pick-up and Return Location', 'Pick-Up And Return Location'],
            //			'Address' => '',
            'Hours of Operation' => ['Hours of Operation', 'Hours Of Operation'],
            //			'Phone Number' => '',
            //			'Fax Number' => '',
            'Pickup Location' => ['Pickup Location', 'Pick Up Location', 'Pick-up Location'],
            //			'Return Location' => '',
            'Pickup Time'                       => ['Pickup Time', 'Pick Up time', 'Pick-up Time', 'Pick-up time', 'Pick-Up Time'],
            'Return Time'                       => ['Return Time', 'Return time'],
            'Amount to be paid at time of rent' => ['Amount to be paid at time of rent', 'Total *', 'Total', 'Amount To Be Paid At Time Of Rent'],
            //			'Total' => '',
            //			'Your Vehicle' => '',
            //			'Discounts' => '',
            'Fees'     => 'Fees and Surcharges',
            'Taxes'    => 'Total Sales Tax',
            'feeNames' => ['Airport Concession Fee', 'Vehicle Licensing Cost Recovery', 'Customer Facility Charge', 'State Tourism Assessment', 'AIRPORT CONCESSION RECOVERY:', 'AIRPORT FEE:'],
            //			'Rate Code' => '',
        ],
        'nl' => [
            'Confirmation Number is:'                     => ['Uw bevestigingsnummer is:'],
            'Thanks for Traveling at the Speed of Hertz®' => ['Dank u voor uw reservering bij Hertz'],
            'You have successfully checked-in'            => ['uw check-in is succesvol verlopen'],
            //			'Service Type' => '',
            'Modify/ Cancel Reservation' => 'Bekijk/wijzig/annuleer reservering',
            'Pickup and Return Location' => 'Ophaal- en inleverlocatie',
            'Address'                    => 'Addres',
            'Hours of Operation'         => 'Openingstijden:',
            'Phone Number'               => 'Telefoonnummer:',
            'Fax Number'                 => 'Faxnummer::',
            //			'Pickup Location' => '',
            //			'Return Location' => '',
            'Pickup Time'                       => ['Ophaalgegevens'],
            'Return Time'                       => ['Inlevergegevens'],
            'Amount to be paid at time of rent' => ['Wat u nu betaalt'],
            'Total'                             => 'Totaal',
            'Your Vehicle'                      => 'uw voertuig:',
            'Discounts'                         => 'Kortingen',
            //			'Fees' => '',
            //			'Taxes' => '',
            // 'feeNames' => [''],
            'Rate Code' => 'Tariefcode:',
        ],
        'ja' => [
            'Confirmation Number is:'                     => ['ご予約番号：'],
            'Thanks for Traveling at the Speed of Hertz®' => ['ご予約ありがとうございます'],
            //            'You have successfully checked-in' => ['uw check-in is succesvol verlopen'],
            //			'Service Type' => '',
            'Modify/ Cancel Reservation'        => '予約の変更・キャンセル',
            'Pickup and Return Location'        => '借り出し・返却営業所',
            'Address'                           => '住所',
            'Hours of Operation'                => '営業時間',
            'Phone Number'                      => '電話番号',
            'Fax Number'                        => 'ＦＡＸ番号',
            'Pickup Location'                   => '借り出し場所',
            'Return Location'                   => '返却場所',
            'Pickup Time'                       => ['借り出し時間'],
            'Return Time'                       => ['返却時間'],
            'Amount to be paid at time of rent' => ['概算合計額'],
            'Total'                             => '概算合計額',
            'Your Vehicle'                      => '車種タイプ',
            'Discounts'                         => '割引',
            'Fees'                              => '手数料および追加料金',
            //			'Taxes' => '',
            'feeNames'  => ['税'],
            'Rate Code' => 'Rate Code :',
        ],
        'es' => [
            'Confirmation Number is:'                     => ['Su número de confirmación es el siguiente:'],
            'Thanks for Traveling at the Speed of Hertz®' => ['Gracias por viajar a la velocidad de Hertz,'],
            //            'You have successfully checked-in' => ['uw check-in is succesvol verlopen'],
            //			'Service Type' => '',
            'Modify/ Cancel Reservation' => 'MODIFICAR/CANCELAR',
            //			'Pickup and Return Location' => '',
            'Address'                           => 'Dirección',
            'Hours of Operation'                => 'Horarios de Atención',
            'Phone Number'                      => 'Teléfono:',
            'Fax Number'                        => 'Fax:',
            'Pickup Location'                   => 'Localidad de Recogida',
            'Return Location'                   => 'Localidad de Devolución',
            'Pickup Time'                       => ['Recogida'],
            'Return Time'                       => ['Devolución'],
            'Amount to be paid at time of rent' => ['Total a pagar en el mostrador', 'Total A Pagar En El Mostrador'],
            'Total'                             => 'Total',
            'Your Vehicle'                      => 'Vehículo',
            'Discounts'                         => 'Descuentos',
            //			'Fees' => '',
            'Taxes'     => 'tasas y recargos',
            // 'feeNames' => [''],
            'Rate Code' => 'Código de Tarifa :',
        ],
        'fr' => [
            'Confirmation Number is:'                     => ['Votre numéro de confirmation est:'],
            'Thanks for Traveling at the Speed of Hertz®' => ["Merci d'avoir choisi Hertz"],
            //            'You have successfully checked-in' => ['uw check-in is succesvol verlopen'],
            //			'Service Type' => '',
            'Modify/ Cancel Reservation'        => 'Modifier/Annuler une réservation',
            'Pickup and Return Location'        => ['Agence de prise en charge et de restitution'],
            'Address'                           => 'Adresse',
            'Hours of Operation'                => "Horaires d'ouverture",
            'Phone Number'                      => 'Téléphone:',
            'Fax Number'                        => 'Fax :',
            'Pickup Location'                   => 'Lieu de départ :',
            'Return Location'                   => 'Lieu de restitution :',
            'Pickup Time'                       => ['Date de départ :'],
            'Return Time'                       => ['Date de retour :'],
            'Amount to be paid at time of rent' => ['Montant à régler maintenant'],
            'Total'                             => 'Total',
            'Your Vehicle'                      => 'Votre véhicule',
            'Discounts'                         => 'Discounts',
            //			'Fees' => '',
            // 'Taxes' => '',
            // 'feeNames' => [''],
            'Rate Code' => 'Code tarif :',
        ],
        'de' => [
            'Confirmation Number is:'                     => ['Ihre Reservierungsnummer lautet:'],
            'Thanks for Traveling at the Speed of Hertz®' => ["Vielen Dank für Ihre Reservierung"],
            //            'You have successfully checked-in' => ['uw check-in is succesvol verlopen'],
            //			'Service Type' => '',
            'Modify/ Cancel Reservation'        => ['Reservierung ändern / stornieren', 'Reservierung Ändern / Stornieren'],
            'Pickup and Return Location'        => 'Ort der Anmietung und Ort der Rückgabe',
            'Address'                           => 'Adresse',
            'Hours of Operation'                => "Öffnungszeiten:",
            'Phone Number'                      => 'Telefonnummer::',
            'Fax Number'                        => 'Fax Nummer: :',
            'Pickup Location'                   => 'Anmietstation',
            'Return Location'                   => 'Rückgabestation',
            'Pickup Time'                       => ['Anmietung'],
            'Return Time'                       => ['Rückgabe'],
            'Amount to be paid at time of rent' => [
                'Später zahlen',
                'Voraussichtliche Kosten *',
                'Voraussichtlicher Gesamtpreis',
            ],
            'Total'        => 'Voraussichtlicher Gesamtpreis',
            'Your Vehicle' => 'Ihr Fahrzeug',
            'Discounts'    => 'Zugehörigkeit',
            //			'Fees' => '',
            //			'Taxes' => '',
            // 'feeNames' => [''],
            'Rate Code' => 'Tarifcode: :',
        ],
        'zh' => [
            'Confirmation Number is:'                     => ['您的预订确认单号是'],
            'Thanks for Traveling at the Speed of Hertz®' => ["感谢您选择赫兹国际租车，"],
            //            'You have successfully checked-in' => ['uw check-in is succesvol verlopen'],
            'Service Type'                      => '服务类型',
            'Modify/ Cancel Reservation'        => '修改/取消预定',
            'Pickup and Return Location'        => '取车及还车门店',
            'Address'                           => '地址',
            'Hours of Operation'                => "运营时间:",
            'Phone Number'                      => '电话号码:',
            'Fax Number'                        => 'Fax Nummer: :',
            'Pickup Location'                   => '',
            'Return Location'                   => '',
            'Pickup Time'                       => ['取车'],
            'Return Time'                       => ['还车'],
            'Amount to be paid at time of rent' => ['需在柜台支付的预估金额'],
            'Total'                             => 'Total',
            'Your Vehicle'                      => '您的车辆',
            'Discounts'                         => '折扣',
            //			'Fees' => '',
            //			'Taxes' => '',
            // 'feeNames' => [''],
            'Rate Code' => '价格代码: :',
        ],
        'ko' => [
            'Confirmation Number is:'                     => ['귀하의 예약번호:'],
            'Thanks for Traveling at the Speed of Hertz®' => ["Hertz를 이용해 주셔서 감사합니다 –"],
            //            'You have successfully checked-in' => ['uw check-in is succesvol verlopen'],
            // 'Service Type'                      => '服务类型',
            'Modify/ Cancel Reservation'        => '현재 예약의 변경/ 취소',
            'Pickup and Return Location'        => '임차 & 반환 영업소',
            'Address'                           => '주소',
            'Hours of Operation'                => "영업 시간:",
            'Phone Number'                      => '전화 번호:',
            // 'Fax Number'                        => 'Fax Nummer: :',
            'Pickup Location'                   => '임차 영업소',
            'Return Location'                   => '반환 영업소',
            'Pickup Time'                       => ['차량 인수'],
            'Return Time'                       => ['차량 반환'],
            'Amount to be paid at time of rent' => ['현지 카운터에서 지불하실 예상 금액'],
            'Total'                             => '예상 총 임차비용',
            'Your Vehicle'                      => '선택 차량',
            'Discounts'                         => '할인 정보',
            //			'Fees' => '',
            //			'Taxes' => '',
            // 'feeNames' => [''],
            'Rate Code' => '요금 코드: :',
        ],
    ];

    private $subjects = [
        'fi' => 'Hertz varaukseni',
        'it' => 'La mia prenotazione Hertz',
        'en' => 'Hertz Reservation',
        'nl' => 'Mijn Hertz Reservering',
        'ja' => 'ご予約内容',
        'es' => 'Mi Reserva Hertz',
        'fr' => 'Ma réservation Hertz',
        'de' => 'Meine Hertz Reservierung',
        'zh' => 'My Hertz Reservation',
        'cs' => 'Moje Hertz Rezervace',
        'ko' => '나의 Hertz 예약',
    ];
    private $body = [
        'fi'  => 'Muuta /Peruuta Varaus',
        'it'  => 'Modifica/Cancella',
        'en'  => 'Modify/ Cancel Reservation',
        'en2' => 'Modify/Cancel Reservation',
        'nl'  => 'Bekijk/wijzig/annuleer reservering',
        'ja'  => '予約の変更・キャンセル',
        'es'  => 'MODIFICAR/CANCELAR',
        'fr'  => 'Modifier/Annuler une réservation',
        'de'  => 'Reservierung ändern / stornieren',
        'de2' => 'Reservierung Ändern / Stornieren',
        'zh'  => '修改/取消预定',
        'cs'  => 'ZMĚNIT ZRUŠIT REZERVACI',
        'ko'  => '현재 예약의 변경/ 취소',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $html = trim($parser->getHTMLBody());

        if (empty($html)) {
            $html = $parser->getPlainBody();
            $this->http->SetEmailBody($html);
        }

        foreach ($this->body as $lang => $body) {
            if ($this->http->XPath->query("//a[{$this->contains($this->t($body))}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseEmail($parser, $email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $phrase) {
            if (stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hertz.com') !== false
            || stripos($from, '@emails.hertz.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".hertz.com")]')->length === 0) {
            return false;
        }

        foreach ($this->body as $body) {
            if ($this->http->XPath->query("//a[{$this->contains($body)}]")->length > 0) {
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

    protected function parseEmail(PlancakeEmailParser $parser, Email $email): void
    {
        $r = $email->add()->rental();

        // Number
        $confirmation = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->starts($this->t('Confirmation Number is:'))}] ]/tr[not(.//tr) and normalize-space()][2]", null, true, '/^[A-Z\d]{5,}\b/');

        if (empty($confirmation)) {
            // My Hertz Reservation J0421647449
            $confirmation = $this->http->FindPreg('/Reservation\s+([A-Z\d]{5,})/', false, $parser->getHeader('subject'));
        }
        $r->general()->confirmation($confirmation);

        if (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('Pickup and Return Location'))}]"))) { // it-48460574.eml
            // Pickup and Dropoff Location
            $pickupLocation[] = trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Pickup and Return Location')) . "]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t('Pickup and Return Location')) . "\s*\W?(.+)#s"));
            $pickupLocation[] = trim($this->http->FindSingleNode("//text()[(" . $this->eq($this->t('Address')) . ") and (" . $this->contains($this->t('Pickup and Return Location'), './ancestor::table[1]') . ")]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t('Address')) . "\s*(.+)#s"));

            $r->pickup()
                ->location(implode(', ', array_filter($pickupLocation)))
                ->openingHours(trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Hours of Operation')) . "]/ancestor::td[1]", null, true, '#:\s*([^>]+)#s')), true, true)
                ->phone(trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Phone Number')) . "]/ancestor::td[1]", null, true, '#:\s*([\d\-\+\(\) ]{5,})#s')), true, true);

            $r->dropoff()
                ->location(implode(', ', array_filter($pickupLocation)))
                ->openingHours(trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Hours of Operation')) . "]/ancestor::td[1]", null, true, '#:\s*([^>]+)#s')), true, true)
                ->phone(trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Phone Number')) . "]/ancestor::td[1]", null, true, '#:\s*([\d\-\+\(\) ]{5,})#s')), true, true);

            $fax = trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Fax Number')) . "]/ancestor::td[1]", null, true, '#:\s*([\d\-\+\(\) ]{5,})#s'));

            if (!empty($fax)) {
                $r->pickup()
                    ->fax($fax);

                $r->dropoff()
                    ->fax($fax);
            }
        } else {
            // PickupLocation and ReturnLocation
            $pickupLocation[] = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Pickup Location')) . "][following::text()[" . $this->eq($this->t('Return Location')) . "]]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t('Pickup Location')) . "\s*(.+)#s"));
            $pickupLocation[] = trim($this->http->FindSingleNode("//text()[(" . $this->eq($this->t('Address')) . ") and (" . $this->contains($this->t('Pickup Location'), './ancestor::table[1]') . ")][following::text()[" . $this->eq($this->t('Return Location')) . "]]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t('Address')) . "\s*(.+)#s"));
            $r->pickup()
                ->location(implode(', ', array_filter($pickupLocation)))
                ->openingHours(trim($this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Pickup Location')) . "]/following::text()[(" . $this->starts($this->t('Hours of Operation')) . ") and (" . $this->contains($this->t('Pickup Location'), './ancestor::table[1]') . ")])[1]/ancestor::td[1]", null, true, '#:\s*([^>]+)#s')), true, true)
                ->phone(trim($this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Pickup Location')) . "]/following::text()[(" . $this->starts($this->t('Phone Number')) . ") and (" . $this->contains($this->t('Pickup Location'), './ancestor::table[1]') . ")])[1]/ancestor::td[1]", null, true, '#:\s*([\d\-\+\(\) ]{5,})#s')), true, true);

            // DropoffLocation
            $dropoffLocation[] = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Return Location')) . "]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t('Return Location')) . "\s*(.+)#s"));
            $dropoffLocation[] = trim($this->http->FindSingleNode("//text()[(" . $this->eq($this->t('Address')) . ") and (" . $this->contains($this->t('Return Location'), './ancestor::table[1]') . ")]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t('Address')) . "\s*(.+)#s"));
            $r->dropoff()
                ->location(implode(', ', array_filter($dropoffLocation)))
                ->openingHours(trim($this->http->FindSingleNode("//text()[(" . $this->starts($this->t('Hours of Operation')) . ") and (" . $this->contains($this->t('Return Location'), './ancestor::table[1]') . ")]/ancestor::td[1]", null, true, '#:\s*([^>]+)#s')), true)
                ->phone(trim($this->http->FindSingleNode("//text()[(" . $this->starts($this->t('Phone Number')) . ") and (" . $this->contains($this->t('Return Location'), './ancestor::table[1]') . ")]/ancestor::td[1]", null, true, '#:\s*([\d\-\+\(\) ]{5,})#s')), true);

            // PickupFax
            $pickupFax = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Pickup Location')) . "]/following::text()[(" . $this->starts($this->t('Fax Number')) . ") and (" . $this->contains($this->t('Pickup Location'), './ancestor::table[1]') . ")])[1]/ancestor::td[1]", null, true, '#:\s*([\d\-\+\(\) ]{5,})#s');

            if ($pickupFax) {
                $r->pickup()
                    ->fax(trim($pickupFax));
            }

            // DropoffFax
            $dropoffFax = $this->http->FindSingleNode("//text()[(" . $this->starts($this->t('Fax Number')) . ") and (" . $this->contains($this->t('Return Location'), './ancestor::table[1]') . ")]/ancestor::td[1]", null, true, '#:\s*([\d\-\+\(\) ]{5,})#s');

            if ($dropoffFax) {
                $r->dropoff()
                    ->fax(trim($dropoffFax));
            }
        }

        // PickupDatetime
        $r->pickup()
            ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Time'))}]/ancestor::td[1]", null, true, '/' . $this->preg_implode($this->t('Pickup Time')) . '\s*(.+)/is'))));

        $dropoffDate = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->starts($this->t('Return Time')) . "]/ancestor::td[1])[1]", null, true, '/' . $this->preg_implode($this->t('Return Time')) . '\s*(.+)/is')));

        if (empty($dropoffDate)) {
            $dropoffDate = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Return Time')) . "]/ancestor::td[1])[1]", null, true, '/' . $this->preg_implode($this->t('Return Time')) . '\s*(.+)/is')));
        }

        $r->dropoff()
            ->date($dropoffDate);

        $r->car()
            ->image($this->http->FindSingleNode("//img[contains(@src, 'vehicles') or contains(@src, 'vehicleimage')]/@src"), true, true);

        $total = $this->http->FindSingleNode("(//td[(" . $this->contains($this->t('Amount to be paid at time of rent')) . ") and not(.//td)]/div[1])[1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//td[(" . $this->contains($this->t('Total')) . ") and not(.//td)]/following-sibling::td[normalize-space()]", null, true, "#(\d+.\d+|\d+)#");
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total'))}]/ancestor::td[1]", null, true, "#{$this->preg_implode($this->t('Total'))}\s*(.+)#");
        }

        if (preg_match("/^(?<amount>\d[,.'\d]*)[ ]*(?<currency>[A-Z]{3})\b/", $total, $m)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $r->price()->total(PriceHelper::parse($m['amount'], $currencyCode))->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//td[{$this->eq($this->t('Taxes'))}]/following-sibling::td[normalize-space()]");

            if (preg_match("/^(?<amount>\d[,.'\d]*)\s*(?:" . preg_quote($m['currency'], '/') . ')?/', $tax, $matches)) {
                $r->price()->tax(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            $fees = $this->http->XPath->query("//*[(self::div or self::p) and {$this->contains($this->t('Fees'))}]/following-sibling::table[normalize-space()][1]/descendant::tr[not(.//tr) and count(*[normalize-space()])=2]");

            if ($fees->length === 0) {
                $fees = $this->http->XPath->query("//text()[{$this->eq($this->t('feeNames'))}]/ancestor::tr[1]");
            }

            foreach ($fees as $feeNode) {
                if (preg_match("/^(?<name>\w[\w ()\-]*?):?\s*(?<charge>\d[,.'\d]*)[ ]*" . preg_quote($m['currency'], '/') . "/u", trim($feeNode->nodeValue), $matches)) {
                    $r->price()->fee($matches['name'], PriceHelper::parse($matches['charge'], $currencyCode));
                }
            }
        }

        $renterName = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->contains($this->t('Thanks for Traveling at the Speed of Hertz®')) . ']', null, true, '/' . $this->preg_implode($this->t('Thanks for Traveling at the Speed of Hertz®')) . ',?\s+([A-z][-.\'A-z\s]*[.A-z])(?:,|$)/m');

        if (empty($renterName)) {
            $renterName = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->contains($this->t('You have successfully checked-in')) . ']', null, true, '/^([A-z][-.\'A-z\s]*[.A-z])\s+' . $this->preg_implode($this->t('You have successfully checked-in')) . '/');
        }

        if (empty($renterName)) {
            $renterName = beautifulName($this->re("#^(\D+)$#", $this->http->FindSingleNode("(//a[" . $this->eq($this->t('Modify/ Cancel Reservation')) . "])[1]/ancestor::tr[2]/preceding-sibling::tr[last()]", null, true, "#(?:Hertz,)?\s*([\w\s]{5,30})\s*$#u")));
        }

        if ($renterName) {
            $r->general()
                ->traveller($renterName);

            $accountNumber = $this->http->FindSingleNode("//text()[contains(., '{$renterName}')]/following-sibling::span/following-sibling::b", null, false, '/\d+$/');

            if (empty($accountNumber)) {
                $accountNumber = $this->http->FindSingleNode("//text()[contains(., '{$renterName}')]/preceding::text()[contains(normalize-space(), '#')]/ancestor::tr[1]", null, false, '/[#]\s*(\d+)$/us');
            }

            if (!empty($accountNumber)) {
                $r->program()
                    ->account($accountNumber, false);
            }
        }

        $carType = implode(', ', $this->http->FindNodes("//text()[" . $this->contains($this->t('Your Vehicle')) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]"));

        if (!$carType) {
            $carTypeArray = array_unique($this->http->FindNodes("//text()[" . $this->contains($this->t('Your Vehicle')) . "]/ancestor::table[1]/following-sibling::table[1]//span[1]", null, "#\((\w{1})\) [\w\s]+#"));

            if (isset($carTypeArray[0])) {
                $r->car()
                    ->type($carTypeArray[0]);
            }
        } else {
            $r->car()
                ->type($carType);
        }

        $carModel = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Your Vehicle')) . "]/ancestor::tr[1]/following-sibling::tr[2]/td[1])[1]");

        if (!$carModel) {
            $carModel = preg_replace("#[\s]{2,}#", " ", str_ireplace('(C) ', '', implode(' ', array_unique($this->http->FindNodes("//td[" . $this->contains($this->t('Your Vehicle') . 'text()') . "]/ancestor::table/following-sibling::table[1]//span")))));
        }

        $r->car()
            ->model($carModel);
    }

    protected function normalizeDate($str): string
    {
//        $this->logger->debug('date in = '.print_r( $str,true));

        $str = str_replace(['下午', '上午'], ['PM', 'AM'], $str);

        $in = [
            "#^\s*[^\d\s]+[\s,.]+([^\d\s\,\.]+)\s+(\d{1,2})[\s,]+(\d{4})\s+[^\d\s]+\s+((\d{1,2}:\d{2}(?: [APM]{2})?))\s*$#u", // Sun, Feb 25, 2018 at 08:00 AM
            "#^\s*[^\d\s]+[\s,.]+(\d{1,2})\s+([^\d\s\,\.]+)[\s,]+(\d{4})\s+[^\d\s]+(?: [^\d\s]+)?\s+((\d{1,2}:\d{2}(?: [APM]{2})?))\s*$#ui", // dom, 13 mag, 2018 a 23:00; lun, 13 may, 2019 a la(s) 11:00 AM;
            "#^\s*[^\d\s]+[\s,.]+([^\d\s\,\.]+)\s+(\d{1,2})[\s,]+(\d{4})\s+[^\d\s]+(?: [^\d\s]+)?\s+((\d{1,2}:\d{2}(?: [apm\.]{4})?))\s*$#ui", // lun, oct 07, 2019 a la(s) 11:00 a.m.
            "#^\s*[^\d\s]+[\s,.]+(\d{1,2})\s+(\d{1,4})[\s,]+(\d{4})\s+[^\d\s]+\s+((\d{1,2}:\d{2}(?: [APM]{2})?))\s*$#u", // 土, 27 4, 2019 at 11:00
            // 星期六, 23 11月, 2019 时间 10:30
            '/^.+?, (\d+) (\d+)月, (\d{4}) 时间 (\d+:\d+)$/',
            //jeu., 21 avr., 2022 à 14:00
            "#^\w+\.\,\s*(\d+)\s*(\w+)\.\,\s*(\d{4})\s*à\s*([\d\:]+)$#u",
            //mar., sept. 20, 2022 à 11:00 AM
            "#^\w+\.\,\s*(\w+)\.\s*(\d+)\,\s*(\d{4})\s*à\s*([\d\:]+\s*A?P?M?)$#u",
            // 금, 4월 01, 2022 @ 11:30
            '/^.+?, (\d+)월\s*(\d+),\s*(\d{4})\s*@\s*(\d+:\d+)$/',
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
            "$1.$2.$3, $4",
            "$1-$2-$3, $4",
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
            "$3-$1-$2, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('date out = '.print_r( $str,true));

        if (preg_match("#\d+\s+[^\d\s]+\s+\d{4}#", $str)) {
            $str = str_replace('.', '', $str);
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function eq($field)
    {
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

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
