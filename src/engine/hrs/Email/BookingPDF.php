<?php

namespace AwardWallet\Engine\hrs\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingPDF extends \TAccountChecker
{
    public $mailFiles = "hrs/it-0.eml, hrs/it-1.eml, hrs/it-1590255.eml, hrs/it-1670486.eml, hrs/it-167679516.eml, hrs/it-1702991.eml, hrs/it-197757977.eml, hrs/it-2.eml, hrs/it-2650205.eml, hrs/it-2993766.eml, hrs/it-2993771.eml, hrs/it-2993775.eml, hrs/it-2993780.eml, hrs/it-7440614.eml, hrs/it-770201381.eml, hrs/it-7717982.eml, hrs/it-7718143.eml"; // +2 bcdtravel(pdf)[sv,ja]

    public $reSubject = [
        'hu' => 'Szállodai foglalásának visszaigazolása ',
        'de' => 'Vorgangs-Nr.',
        'it' => 'Cod. di procedura',
        //		'nl' => '',
        'ru' => 'Номер заказа в',
        'es' => 'Nº de reserva',
        'en' => 'Confirmation of your hotel reservation',
        'tr' => 'Otel rezervasyonunuzun teyidi',
        'fr' => "Annulation de votre réservation d'hôtel",
        'zh' => "您的酒店预订确认函",
        'sv' => 'ärendenr.',
        'ja' => '処理番号',
        'da' => '処理番号',
    ];

    public $pdfNamePattern = ".*\.pdf.*";

    private static $detectBody = [
        'hu' => ['Kiválasztott szálloda'],
        'de' => ['Ihr ausgewähltes Hotel'],
        'it' => ["L'hotel da voi scelto"],
        'nl' => ['Uw gekozen hotel'],
        'ru' => ['Выбранный вами отель'],
        'es' => ['El hotel seleccionado por usted'],
        'en' => ['Your chosen hotel'],
        'tr' => ['Seçtiğiniz otel'],
        'fr' => ["L'hôtel sélectionné"],
        'pt' => ["O seu hotel escolhido"],
        'zh' => ["您选择的酒店"],
        'sv' => ['Ditt valda hotell'],
        'ja' => ['ご指定のホテル'],
        'da' => ['Dine reservationsdata'],
    ];

    private $lang = '';

    private static $dict = [
        'da' => [
            'Vorgangsnummer'         => 'HRS Procesnummer',
            'Buchungsnummer'         => 'Reservationsnummer',
            'Ihr ausgewähltes Hotel' => 'Dit valgte hotel',
            'Anreise'                => 'Ankomst',
            'Abreise'                => 'Afrejse',
            'Telefon'                => 'Telefon',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Tilrejsende gæster',
            'Stornierung'            => 'Afbestillingsfrist',
            'Ratenbeschreibung'      => 'Prisbeskrivelse', // next after Stornierung
            'Gesamtpreis'            => 'Pris i alt',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum'          => 'Reservationsdato',
            'RoomTypeDescription'    => 'Beskrivelse af hotelværelset',
            'RoomTypeDescriptionEnd' => 'Specielle afbestillingsbetingelser',
            //			'cancel' => '',
        ],
        'hu' => [
            'Vorgangsnummer'         => 'HRS Eljárási szám',
            'Buchungsnummer'         => 'Foglalási szám',
            'Ihr ausgewähltes Hotel' => ['Select Hotel', 'Kiválasztott szálloda'],
            'Anreise'                => 'Érkezés',
            'Abreise'                => 'Elutazás',
            'Telefon'                => 'Telefon',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Érkező vendégek',
            'Stornierung'            => 'Lemondási határidő',
            'Ratenbeschreibung'      => ['Az ár leírása', 'Számlacím'], // next after Stornierung
            'Gesamtpreis'            => 'Összköltség/ ár',
            'Zimmer-Gesamtpreis'     => 'Szoba teljes ára (adókkal együtt)',
            'Buchungsdatum'          => 'Foglalás kelte',
            'RoomTypeDescription'    => 'Description of hotel room',
            'RoomTypeDescriptionEnd' => 'Special cancellation conditions',
            //			'cancel' => '',
        ],
        'de' => [
            //			'Vorgangsnummer' => '',
            //			'Buchungsnummer' => '',
            //			'Ihr ausgewähltes Hotel' => '',
            //			'Anreise' => '',
            //			'Abreise' => '',
            //			'Telefon' => '',
            //			'Fax' => '',
            //			'Anreisende Gäste' => '',
            'Stornierung'       => ['Stornierung', 'Stornierungsfrist'],
            'Ratenbeschreibung' => ['Ratenbeschreibung', 'Zahlungsart'], // next after Stornierung
            //			'Gesamtpreis' => '',
            //			'Zimmer-Gesamtpreis' => '',
            //			'Buchungsdatum' => '',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            'cancel' => 'Reservierung storniert',
        ],
        'it' => [
            'Vorgangsnummer'         => 'HRS Codice di procedura',
            'Buchungsnummer'         => 'Numero prenotazione',
            'Ihr ausgewähltes Hotel' => "L'hotel da voi scelto",
            'Anreise'                => 'Arrivo',
            'Abreise'                => 'Partenza',
            'Telefon'                => 'Telefono',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Ospiti',
            'Stornierung'            => ['Modalità di pagamento', 'Termine ultimo di cancellazione'],
            'Ratenbeschreibung'      => ['Termine ultimo di cancellazione', 'Modalità di pagamento'], // next after Stornierung
            'Gesamtpreis'            => 'Prezzo totale',
            'Buchungsdatum'          => 'Data della prenotazione',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            //			'cancel' => '',
        ],
        'nl' => [
            //			'Vorgangsnummer' => '',
            'Buchungsnummer'         => 'Reserveringsnummer',
            'Ihr ausgewähltes Hotel' => 'Uw gekozen hotel',
            'Anreise'                => 'Aankomst',
            'Abreise'                => 'Vertrek',
            'Telefon'                => 'Telefoon',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Aankomende gasten',
            'Stornierung'            => 'Annuleringstermijn',
            'Ratenbeschreibung'      => 'Wijze van betalen', // next after Stornierung
            'Gesamtpreis'            => 'Totaalprijs',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => 'Reserveringsdatum',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            //			'cancel' => '',
        ],
        'ru' => [
            'Vorgangsnummer'         => 'HRS номер заказа',
            'Buchungsnummer'         => 'Номер бронирования',
            'Ihr ausgewähltes Hotel' => 'Выбранный вами отель',
            'Anreise'                => 'Приезд',
            'Abreise'                => 'Отъезд',
            'Telefon'                => 'Телефон',
            'Fax'                    => 'Факс',
            'Anreisende Gäste'       => 'Список прибывающих',
            'Stornierung'            => 'Срок аннулирования заказа',
            'Ratenbeschreibung'      => 'Вид оплаты', // next after Stornierung
            'Gesamtpreis'            => 'Общая стоимость',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => 'Дата бронирования',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            //			'cancel' => '',
        ],
        'es' => [
            'Vorgangsnummer'         => 'HRS Número de reserva',
            'Buchungsnummer'         => 'Número de reserva',
            'Ihr ausgewähltes Hotel' => 'El hotel seleccionado por usted',
            'Anreise'                => 'Llegada',
            'Abreise'                => 'Salida',
            'Telefon'                => 'Teléfono',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Llegada de clientes',
            'Stornierung'            => 'Plazo de anulación',
            'Ratenbeschreibung'      => 'Modo de pago', // next after Stornierung
            'Gesamtpreis'            => 'Precio total',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => 'Fecha de reserva',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            //			'cancel' => '',
        ],
        'en' => [
            'Vorgangsnummer'         => 'HRS Process number',
            'Buchungsnummer'         => 'Reservation number',
            'Ihr ausgewähltes Hotel' => 'Your chosen hotel',
            'Anreise'                => 'Arrival',
            'Abreise'                => 'Departure',
            'Telefon'                => 'Telephone',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Arriving guests',
            'Stornierung'            => ['Special cancellation conditions', 'Cancellation deadline', 'Cancelation deadline'],
            'Ratenbeschreibung'      => ['Travel information', 'Payment method'], // next after Stornierung
            'Gesamtpreis'            => 'Total price',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum'          => 'Reservation date',
            'RoomTypeDescription'    => 'Description of hotel room',
            'RoomTypeDescriptionEnd' => 'Special cancellation conditions',
            //			'cancel' => '',
        ],
        'pt' => [
            'Vorgangsnummer'         => 'HRS Número de processo',
            'Buchungsnummer'         => 'Número de reserva',
            'Ihr ausgewähltes Hotel' => 'O seu hotel escolhido',
            'Anreise'                => 'Chegada',
            'Abreise'                => 'Partida',
            'Telefon'                => 'Telefone',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Hóspedes a chegar',
            'Stornierung'            => ['Prazo para cancelamento'],
            'Ratenbeschreibung'      => ['Loyalty ID', 'Taxa de anulação'], // next after Stornierung
            'Gesamtpreis'            => 'Preço total',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum'          => 'Data de reserva',
            //'RoomTypeDescription'    => 'Description of hotel room',
            //'RoomTypeDescriptionEnd' => 'Special cancellation conditions',
            //			'cancel' => '',
        ],
        'tr' => [
            'Vorgangsnummer'         => 'HRS İşlem numarası',
            'Buchungsnummer'         => 'Rezervasyon numarası',
            'Ihr ausgewähltes Hotel' => 'Seçtiğiniz otel',
            'Anreise'                => 'Otele varış',
            'Abreise'                => 'Otelden ayrılış',
            'Telefon'                => 'Telefon',
            'Fax'                    => 'Faks',
            'Anreisende Gäste'       => 'Gelen müşteri',
            'Stornierung'            => 'Özel iptal koşulları',
            'Ratenbeschreibung'      => 'Vergilerle ilgili notlar', // next after Stornierung
            'Gesamtpreis'            => 'Toplam tutar',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => 'Rezervasyon tarihi',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            //			'cancel' => '',
        ],
        'fr' => [
            'Vorgangsnummer'         => 'HRS Numéro de',
            'Buchungsnummer'         => 'Numéro de réservation',
            'Ihr ausgewähltes Hotel' => "L'hôtel sélectionné",
            'Anreise'                => 'Arrivée',
            'Abreise'                => 'Départ',
            'Telefon'                => 'Téléphone',
            'Fax'                    => 'Télécopie',
            'Anreisende Gäste'       => "Client(s) logeant à l'hôtel",
            'Stornierung'            => ["Conditions spéciales d'annulation", "Délai d'annulation"],
            'Ratenbeschreibung'      => ['TVA : ', 'Mode de paiement:'], // next after Stornierung
            'Gesamtpreis'            => ['Prix total du séjour'],
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => 'Date de la réservation',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            'cancel' => 'Annulation de réservation',
        ],
        'zh' => [
            'Vorgangsnummer'         => 'HRS 处理号',
            'Buchungsnummer'         => '预订号',
            'Ihr ausgewähltes Hotel' => "您选择的酒店",
            'Anreise'                => '抵店',
            'Abreise'                => '离店',
            'Telefon'                => '电话',
            'Fax'                    => '传真',
            'Anreisende Gäste'       => "入住客人",
            'Stornierung'            => "取消预订的截止日期",
            'Ratenbeschreibung'      => '支付方法', // next after Stornierung
            'Gesamtpreis'            => '总价',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => '预订日期',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            //			'cancel' => '',
        ],
        'sv' => [
            'Vorgangsnummer'         => 'HRS Ärendenummer',
            'Buchungsnummer'         => 'Bokningsnummer',
            'Ihr ausgewähltes Hotel' => 'Ditt valda hotell',
            'Anreise'                => 'Ditresa',
            'Abreise'                => 'Avresa',
            'Telefon'                => 'Telefon',
            'Fax'                    => 'Fax',
            'Anreisende Gäste'       => 'Ankommand gäster',
            'Stornierung'            => 'Avbokningsfrist',
            'Ratenbeschreibung'      => 'Prisbeskrivning', // next after Stornierung
            'Gesamtpreis'            => 'Totalpris',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => 'Bokningsdatum',
            //			'RoomTypeDescription' => '',
            //			'RoomTypeDescriptionEnd' => '',
            //			'cancel' => '',
        ],
        'ja' => [
            'Vorgangsnummer'         => 'HRS 処理番号',
            'Buchungsnummer'         => '予約番号',
            'Ihr ausgewähltes Hotel' => 'ご指定のホテル',
            'Anreise'                => 'チェックイン',
            'Abreise'                => 'チェックアウト',
            'Telefon'                => 'お電話番号',
            'Fax'                    => 'ファックス',
            'Anreisende Gäste'       => 'ご宿泊者お名前 (姓/名)',
            'Stornierung'            => '特別なキャンセル条件',
            'Ratenbeschreibung'      => '税金に関する情報', // next after Stornierung
            'Gesamtpreis'            => '合計料金',
            //            'Zimmer-Gesamtpreis' => '',
            'Buchungsdatum' => '予約日',
            //            'RoomTypeDescription' => '',
            //            'RoomTypeDescriptionEnd' => '',
            //            'cancel' => '',
        ],
    ];
    private $pdf;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $this->lang = '';
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($body === null) {
                continue;
            }
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($body);

            foreach (self::$detectBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }

            if (empty($this->lang)) {
                continue;
            }
            $this->parseEmail($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (preg_match('#(HRS\s+Preisgarantie\s+mit\s+Geld-zurück|HRS\s+[\-–]\s+HOTEL\s+RESERVATION\s+SERVICE|HRS\s+services|HRS服务)#ui', $text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && !preg_match('/\bHRS\b/', $headers['subject'])) {
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
        return stripos($from, 'HOTEL RESERVATION SERVICE') !== false
            || preg_match('/\bHRS\b/', $from) > 0
            || preg_match('/[.@]hrs\./i', $from) > 0;
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
        $pdfText = $this->pdf->Response['body'];
        $it = ['Kind' => 'R'];

        $h = $email->add()->hotel();

        if (preg_match_all("#\n\s*" . $this->t('Buchungsnummer') . "\.?:\s+(\d+)\s+#u", $pdfText, $m)) {
            foreach ($m[1] as $confNumber) {
                $h->general()
                    ->confirmation($confNumber);
            }
        }

        if (preg_match("#" . $this->t('Vorgangsnummer') . ":?\s+(\d{7,})\s+#u", $pdfText, $m)) {
            $email->ota()
                ->confirmation($m[1]);
        }

        //HotelName
        //Address
        if (preg_match("#" . $this->opt($this->t('Ihr ausgewähltes Hotel')) . "\s+(.*\n(?:[^|]+\n)?)\s*(.+\n(?:.+\n)?)\s*" . $this->opt($this->t('Telefon')) . "#u", $pdfText, $m)) {
            $h->hotel()
                ->name(trim(str_replace("\n", ' ', $m[1])))
                ->address(trim(str_replace(["\n", ' | ', '->'], [' ', ', ', '-'], $m[2])));
        }

        //CheckInDate
        //CheckOutDate
        // Th. 11.09.2014 - Fr. 12.09.2014
        $regex = "#" . $this->t('Anreise') . "\s*\/\s*" . $this->t('Abreise') . ":\s+(.{6,})\s+-\s+(.{6,})#iu";
        $this->logger->error($regex);
        $this->logger->debug($pdfText);

        if (preg_match($regex, $pdfText, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        //Phone
        //Fax
        if (preg_match("#" . $this->t('Telefon') . "\s+[/|]\s+" . $this->t('Fax') . ":?\s+(.*)\s*[/|]\s*(.*)#", $pdfText, $m)) {
            $h->hotel()
                ->phone(trim($m[1]));
            $m[2] = trim($m[2]);

            if (strlen($m[2]) > 5) {
                $h->hotel()
                    ->fax(trim($m[2]));
            }
        }

        //GuestNames
        if (preg_match_all("#" . preg_quote($this->t('Anreisende Gäste')) . ":\s+(.*)#", $pdfText, $m)) {
            $paxArray = [];

            foreach ($m[1] as $value) {
                $paxArray = array_merge($paxArray, explode('|', $value));
            }

            foreach ($paxArray as $key => $value) {
                $paxArray[$key] = trim($value);
            }
        }

        if (count($paxArray) > 0) {
            $h->general()
                ->travellers(array_unique($paxArray));
        }

        //CancellationPolicy
        if (preg_match('/' . $this->opt($this->t('Stornierung')) . ':\s+(.+?)\s+' . $this->opt($this->t('Ratenbeschreibung')) . '/s', $pdfText, $m)) {
            $h->general()
                ->cancellation(preg_replace('/\s+/', ' ', trim($m[1])));
        }

        //Rooms
        //RoomType
        if (preg_match_all('/\n\s*\d{1,3}\.\s+(.+)\s+' . $this->t('Buchungsnummer') . '/u', $pdfText, $m)) {
            $h->booked()
                ->rooms(count($m[1]));
            $roomType = implode(', ', array_unique($m[1]));
        }

        //RoomTypeDescription
        if (preg_match("#(?:" . $this->t('RoomTypeDescription') . ")\s+((?:.+\n)+)\s*(?:" . $this->t('RoomTypeDescriptionEnd') . ")#", $pdfText, $m)) {
            $roomDescription = trim($m[1]);
        }

        if (!empty($roomType) || !empty($roomDescription)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }
        }

        //Total
        //Currency
        if (preg_match('/\s+' . $this->opt($this->t('Gesamtpreis')) . '(?:[ ]*\([^\)\n]*\))?[ ]*:[ ]*(\d[,.\d ]*)[ ]*([A-Z]{3})\b/', $pdfText, $m)) {
            $h->price()
                ->total($this->normalizePrice($m[1]))
                ->currency($m[2]);
        } elseif (preg_match_all('/\s+' . $this->opt($this->t('Zimmer-Gesamtpreis')) . '(?:[ ]*\([^\)\n]*\))?[ ]*:[ ]*(\d[,.\d ]*)[ ]*([A-Z]{3})\b/', $pdfText, $m)) {
            $h->price()
                ->total(0.0);

            foreach ($m[0] as $key => $value) {
                $total += $this->normalizePrice($m[1][$key]);
            }
            $h->price()
                ->total($total)
                ->currency($m[2][0]);
        }

        //Status
        //Cancelled
        if ($this->t('cancel') !== 'cancel' && stripos($pdfText, $this->t('cancel')) !== false) {
            $h->general()
                ->status($this->t('cancel'))
                ->cancelled();
        }

        //ReservationDate
        if (
            preg_match("#" . $this->t('Buchungsdatum') . "\s*:\s*(?<date>.{6,})\s+\|\s+(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)#", $pdfText, $m)
            && $reservationDateNormal = $this->normalizeDate($m['date'])
        ) {
            $h->general()
                ->date(strtotime($m['time'], $reservationDateNormal));
        }

        if (preg_match_all("/(\d+\.\d+\.\s*\-\s*\d+\.\d+\..+[ ]{2}[\d\.\,\']+\s*[A-Z]{3})\n/", $pdfText, $m)) {
            $rates = [];

            foreach ($m[1] as $value) {
                if (preg_match("/(?<dateRange>\d+\.\d+\.\s*\-\s*\d+\.\d+\.).+[ ]{2}(?<price>[\d\.\,\']+\s*[A-Z]{3})/", $value, $m)) {
                    $rates[] = $m['dateRange'] . ' - ' . $m['price'];
                }
            }

            if (count($rates) > 0) {
                $room->setRate(implode('; ', $rates));
            }
        }

        $this->detectDeadLine($h);
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('#[^\d\W]{1,}\.?\s+(\d{1,2})\s+(\w+)\s+(\d{4})\b#u', $string, $matches)) { // Sa. 14 September 2024
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('#[^\d\W]{1,}\.?\s+(\d{1,2})[./](\d{1,2})[./](\d{4})\b#u', $string, $matches)) { // Сб. 21.03.2016
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('#[^\d\W]{2,}\.?\s+(\d{1,2})[./](\d{1,2})[./](\d{2})\b#u', $string, $matches)) { // Сб. 21.03.16
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('#[^\d\W]+\.?\s+(\d{4})/(\d{1,2})/(\d{2})\b#u', $string, $matches)) { // 日. 2018/09/16
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})\b/', $string, $matches)) { // 06.06.14
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                $date = $m[1] . '/' . $day . ($year ? '/' . $year : '');
            } else {
                if ($this->lang !== 'th') {
                    $month = str_replace('.', '', $month);
                }

                if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                    $month = $monthNew;
                }
                $date = $day . ' ' . $month . ($year ? ' ' . $year : '');
            }
        }

        if (isset($date)) {
            $date = strtotime($date, false);
            // it-43989618.eml - 08/28/2019
            if (!$date && preg_match('#\d+/\d+/\d{4}#', $string, $matches)) {
                $date = strtotime($matches[0], false);
            }

            return $date;
        }

        return false;
    }

    private function normalizePrice($price)
    {
        if (preg_match("#([.,])\d{2}($|[^\d])#", $price, $m)) {
            $delimiter = $m[1];
        } else {
            $delimiter = '.';
        }
        $price = preg_replace('/[^\d\\' . $delimiter . ']+/', '', $price);
        $price = (float) str_replace(',', '.', $price);

        return $price;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        $cancellationText = str_replace('12:00:00 AM', '12:00', $cancellationText);

        if (preg_match("/Sie können diese Buchung bis zum (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/This booking can be cancelled free of charge until (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/This booking can be canceled free of charge until (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/Ezt a foglalást (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/Die garantierte Buchung ist kostenfrei stornierbar bis (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/Du kan afbestille denne reservation gratis indtil (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/È possibile annullare gratuitamente questa prenotazione entro il (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/Podrá anular la presente reserva de modo gratuito hasta el (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        || preg_match("/Это бронирование можно бесплатно аннулировать до (\d+\.\d+\.)(\d+\,\s*[\d\:]+)/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m[1] . '20' . $m[2]));
        }

        if (
            preg_match("/This booking can be cancelled free of charge until (\d+\s+\w+\s+\d+)\s*([\d\:]+\s*a?\.?p?\.?m\.)/u", $cancellationText, $m)
            || preg_match("/Pode anular esta reserva até (\d+\.\d+\.\d{4})\s*(\d+\:\d+) horas/u", $cancellationText, $m)
            || preg_match("/Podrá anular la presente reserva de modo gratuito hasta el (\d+\.\d+\.\d{4})\s*(\d+\:\d+) horas/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m[1] . ', ' . $m[2]));
        }

        if (preg_match("/The booking in the selected period can no longer be cancelled free of charge/", $cancellationText, $m)) {
            $h->booked()
                ->nonRefundable();
        }
    }
}
