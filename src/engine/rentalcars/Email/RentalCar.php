<?php

namespace AwardWallet\Engine\rentalcars\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCar extends \TAccountChecker
{
    public $mailFiles = "rentalcars/it-109241202.eml, rentalcars/it-109638185.eml, rentalcars/it-1704593.eml, rentalcars/it-1898057.eml, rentalcars/it-195658120.eml, rentalcars/it-39780029.eml, rentalcars/it-39842903.eml, rentalcars/it-4.eml, rentalcars/it-44978678.eml, rentalcars/it-5960567.eml, rentalcars/it-6052157.eml, rentalcars/it-78137104.eml, rentalcars/it-7982152.eml, rentalcars/it-88634678.eml, rentalcars/it-9128138.eml, rentalcars/it-344251258-cs.eml, rentalcars/it-337287000-sk.eml"; // +2 bcdtravel(html)[sv,no]

    public $providerCode;

    private static $detectHeaders = [
        'wizz' => [
            'from'    => 'wizzair',
            'company' => 'Wizz Air',
        ],
        'rentalcars' => [
            'from'    => 'rentalcars.com',
            'company' => 'Rentalcars.com',
        ],
        'booking' => [
            'from'    => 'cars.booking.com',
            'company' => 'Booking.com',
        ],
        'ryanair' => [
            'from'    => 'carhire.ryanair.com',
            'company' => 'Ryanair',
        ],
    ];

    private $detectSubject = [
        "es" => "Información importante de su reserva con %Company% – Ref:",
        "Su petición de reserva con %Company% – Ref:",
        "sv" => "Viktig information om din reservation hos %Company%", "Din reservation hos %Company% - Ref:",
        'Avbokning av din reservation hos %Company% – Ref:',
        "en" => "Your %Company% Request - Ref",
        "Important information regarding your %Company% booking - Ref",
        "Your %Company% Voucher - Ref:",
        "Your %Company% Reservation - Ref:",
        "fr"   => "Informations importantes concernant votre réservation avec %Company% - réf :",
        "fr2"  => "Votre demande de réservation avec %Company% - réf :",
        "zh"   => "轉寄: 在%Company% 租車的重要訊息 - 訂單號碼",
        "zh2"  => "您的%Company%租車詢價單 - 訂單號碼:",
        'sk'   => 'Vaša rezervácia s %Company% - Ref.č.:',
        'pt'   => 'Informação Importante sobre a sua reserva com %Company%',
        'Seu voucher da %Company% – Ref:', 'Confirmação da sua reserva com a %Company% - Ref:',
        'Sua reserva com %Company% - Ref:', 'Cancelamento da Reserva com a %Company% - Ref:',
        'Cancelamento da Reserva da %Company% - Ref:',
        'it' => 'Il vostro voucher con %Company% - Ref:', 'La vostra richiesta con %Company% - Ref:',
        'Cancellazione della prenotazione con %Company% - Ref:',
        'nl'   => 'Belangrijke informatie met betrekking tot uw %Company% boeking - Ref:',
        'nl2'  => 'Uw %Company% boeking - Ref:',
        'no'   => 'Viktig informasjon angående din %Company% Reservasjon',
        'no2'  => 'Din %Company% Reservasjon',
        'da'   => 'Vigtig information vedrørende din %Company% Reservation – Ref:',
        'Din %Company% Reservation - Ref Nr:',
        'de'   => 'Wichtige Information zu Ihrer %Company% Buchung - Ref:',
        'de2'  => 'Ihre %Company% Buchung - Ref:',
        'ru'   => 'Важная Информация о Вашем Запросе о Бронировании с %Company% - Номер брони:',
        'uk'   => 'Дякуємо за замовлення через %Company%',
        'fi'   => 'Kiitos, että teit varauksen %Company%',
        'ko'   => '%Company% 렌터카 예약에 따른 중요사항 - 예약번호:',
        'cs'   => 'Vaše rezervace s %Company% - Ref:',
        'pl'   => 'Twoja Rezerwacja %Company%:',
        'ar'   => 'طلب سعر مع %Company% – ',
        'ar2'  => 'معلومات هامة متعلقة بالحجز عبر موقعنا %Company% – الرقم المرجعي:',
    ];

    private $detectBody = [
        "es" => ["Información de recogida:", "Datos de la recogida:"],
        "sv" => ["Upphämtningsinformation:", "Information om upphämtning", "bokning har nu avbokats"],
        "en" => ["Pick-up details:", "has now been cancelled", "has now been canceled"],
        "fr" => ["Au sujet de la prise en charge :"],
        "zh" => ["取車細節", '還車資訊：'],
        'sk' => ['Informácie o vyzdvihnutí:'],
        'pt' => ['Detalhes da retirada:', 'Detalhes para o retirada:', 'Detalhes para retirada:', 'Detalhes para levantamento:', 'Dados do levantamento:', ' foi cancelada'],
        'it' => ['Dettagli del ritiro:', 'è stata cancellata', 'è stata cancelata', 'Dati per il ritiro:'],
        'nl' => ['Wij wensen u een prettige reis toe', 'Hartelijk dank voor het reserveren via'],
        'no' => ['Avhentningsdetaljer:', 'Avhentingsdetaljer:'],
        'da' => ['Tak for at booke med Rentalcars.com', 'Du kan tjekke din booking status'],
        'de' => ['Abholdetails:', 'Informationen zur Abholung:'],
        'ru' => ['Детали получения:'],
        'uk' => ['Деталі отримання:'],
        'fi' => ['Noutotiedot:'],
        'ko' => ['차량인수 상세정보:'],
        'cs' => ['Podrobnosti vyzvednutí:', 'Informace o vyzvednutí:'],
        'pl' => ['Szczegóły Odbioru:'],
        'ar' => ['تفاصيل الاستلام'],
    ];
    private $rentalProviders = [
        'alamo'              => ['Alamo'],
        'avis'               => ['Avis'],
        'dollar'             => ['Dollar'],
        'europcar'           => ['Europcar'],
        'hertz'              => ['Hertz'],
        'localiza'           => ['Localiza'],
        'perfectdrive'       => ['Budget'],
        'sixt'               => ['Sixt'],
        'rentalcars'         => ['Global Rent A Car'],
    ];

    private $lang = "en";
    private static $dictionary = [
        "en" => [ // it-1704593.eml, it-4.eml, it-5960567.eml, it-88634678.eml
            "%Company% booking - Ref:" => ["%Company% Request - Ref:", "%Company% booking - Ref:", "%Company% Voucher - Ref:", "%Company% Reservation - Ref:"],
            "dear"                     => ["Dear", "Hi"],
            "Car Group:"               => "Car Group:",
            "Supplier:"                => "Supplier:",
            "Pick-up details:"         => ["Pick-up details:", "Pick-up Details:"],
            "Country:"                 => ["Country", "country:"],
            "City:"                    => ["City:", "city:"],
            "Location:"                => ["location:", "Location:"],
            "Date:"                    => ["date:", "Date:"],
            "Drop-off details:"        => "Drop-off details:",
            "Total Cost:"              => "Total Cost:",
            "cancelledPhrases"         => ["Your reservation has now been cancelled", "Your reservation has now been canceled"],
        ],
        "es" => [ // it-6052157.eml, it-7982152.eml
            "%Company% booking - Ref:" => "%Company% – Ref:",
            "dear"                     => ["Estimado(a)", "Hola"],
            "Car Group:"               => ["Tipo de coche:", "Categoría del coche:"],
            "Supplier:"                => "Proveedor:",
            "Pick-up details:"         => ["Información de recogida:", "Datos de la recogida:"],
            "Country:"                 => "País:",
            "City:"                    => "Ciudad:",
            "Location:"                => ["Lugar:", "Ubicación:"],
            "Date:"                    => "Fecha:",
            "Drop-off details:"        => ["Información de devolución:", "Datos de la devolución:"],
            "Total Cost:"              => "Precio total:",
            // "cancelledPhrases" => [""],
        ],
        "da" => [
            "%Company% booking - Ref:" => ["%Company% Reservation – Ref:", "%Company% Reservation - Ref Nr:"],
            "dear"                     => "Hej",
            "Car Group:"               => ["Bilgruppe:", "Bil gruppe:"],
            "Supplier:"                => "Udlejer:",
            "Pick-up details:"         => ["Afhentningsdetaljer:", "Afhentnings oplysninger:"],
            "Country:"                 => "Land:",
            "City:"                    => "By:",
            "Location:"                => ["Lokation:", "Sted:"],
            "Date:"                    => "Dato:",
            "Drop-off details:"        => ["Afleveringsdetaljer:", "Afleverings oplysninger:"],
            "Total Cost:"              => "Total pris:",
            // "cancelledPhrases" => [""],
        ],
        "sv" => [
            "%Company% booking - Ref:" => "%Company% – Ref:",
            "dear"                     => "Hej",
            "Car Group:"               => ["Bilgrupp:", "Bilkategori:"],
            "Supplier:"                => ["Uthyrningsbolag:", "Leverantör:"],
            "Pick-up details:"         => ["Upphämtningsinformation:", "Information om upphämtning"],
            "Country:"                 => "Land:",
            "City:"                    => "Stad:",
            "Location:"                => "Plats:",
            "Date:"                    => "Datum:",
            "Drop-off details:"        => ["Avlämningsinformation:", "Information om återlämning"],
            "Total Cost:"              => "Totalkostnad:",
            "cancelledPhrases"         => ["Din bokning har nu avbokats"],
        ],
        "fr" => [
            "%Company% booking - Ref:" => "%Company% - réf :",
            "dear"                     => "Cher/Chère",
            "Car Group:"               => ["Véhicule du groupe :", "Type de véhicule :"],
            "Supplier:"                => "Fournisseur :",
            "Pick-up details:"         => "Au sujet de la prise en charge :",
            "Country:"                 => "Pays :",
            "City:"                    => "Ville :",
            "Location:"                => "Lieu :",
            "Date:"                    => ["Date de prise en charge :", "Date de restitution :", "Date de", "Date :"],
            "Drop-off details:"        => "Au sujet de la restitution :",
            "Total Cost:"              => "Prix total :",
            // "cancelledPhrases" => [""],
        ],
        "zh" => [
            "%Company% booking - Ref:" => ["租車的重要訊息 - 訂單號碼：", '租車詢價單 - 訂單號碼:'],
            "dear"                     => ["親愛的先生", '您好：'],
            "Car Group:"               => ["車輛級別", '車款：'],
            "Supplier:"                => ["車輛供應商", "供應商："],
            "Pick-up details:"         => ["取車細節", "取車資訊："],
            "Country:"                 => "國家：",
            "City:"                    => "城市：",
            "Location:"                => ['地點：', "地點"],
            "Date:"                    => ['日期：', "日期"],
            "Drop-off details:"        => ["還車細節", "還車資訊："],
            "Total Cost:"              => ["總價", "總費用："],
            // "cancelledPhrases" => [""],
        ],
        "sk" => [ // it-337287000-sk.eml
            "%Company% booking - Ref:" => ["Vaša rezervácia s %Company% - Ref.č.:"],
            "dear"                     => ["Dobrý deň"],
            "Car Group:"               => ["Kategória auta:"],
            "Supplier:"                => ["Autopožičovňa:"],
            "Pick-up details:"         => ["Informácie o vyzdvihnutí:"],
            "Country:"                 => ["Krajina:"],
            "City:"                    => ["Mesto:"],
            "Location:"                => ["Lokalita:"],
            "Date:"                    => ["Dátum:"],
            "Drop-off details:"        => ["Informácie o vrátení:"],
            "Total Cost:"              => ["Celková cena:"],
            // "cancelledPhrases" => [""],
        ],
        "pt" => [ // it-1898057.eml, it-9128138.eml
            "%Company% booking - Ref:" => ["da %Company% – Ref:", 'com %Company% – Ref:', 'Sua reserva com %Company% - Ref:'],
            "dear"                     => ["Exmo(a).", 'Caro (a)', 'Caro(a)', 'Prezado(a)', 'Olá'],
            "Car Group:"               => ["Grupo do veículo:", 'Grupo do carro:', 'Tipo de carro:', 'Categoria de carro:'],
            "Supplier:"                => ['Empresa de aluguer de carros:', "Fornecedor:", "Locadora:"],
            "Pick-up details:"         => ['Detalhes para o retirada:', 'Detalhes para retirada:', 'Detalhes da retirada:', 'Detalhes para levantamento:', 'Dados do levantamento:'],
            "Country:"                 => ["País:", "país:"],
            "City:"                    => ["Cidade:", "cidade:"],
            "Location:"                => ["Localização:", "localização:", "Local:"],
            "Date:"                    => ["Data:", "data:"],
            "Drop-off details:"        => ["Detalhes para a devolução:", 'Detalhes para devolução:', 'Detalhes da devolução:', 'Dados da devolução:'],
            "Total Cost:"              => ["Custo Total:", 'Custo total:'],
            "cancelledPhrases"         => ["Sua reserva já foi cancelada", "A sua reserva foi cancelada"],
        ],
        "it" => [
            "%Company% booking - Ref:" => "%Company% - Ref:",
            "dear"                     => ["Gentile", "Ciao"],
            "Car Group:"               => ["Categoria del veicolo:", 'Gruppo del veicolo:', 'Tipologia di auto:'],
            "Supplier:"                => "Fornitore",
            "Pick-up details:"         => ["Dettagli del ritiro:", "Dati per il ritiro:"],
            "Country:"                 => "Paese",
            "City:"                    => "Città:",
            "Location:"                => ["Località:", "Punto di noleggio:"],
            "Date:"                    => "Data:",
            "Drop-off details:"        => ["Dettagli del rilascio:", 'Dettagli consegna:', 'Dati per la riconsegna:'],
            "Total Cost:"              => ["Costo totale:"],
            "cancelledPhrases"         => ["La tua prenotazione è stata cancellata", "La tua prenotazione è stata cancelata"],
        ],
        "nl" => [
            "%Company% booking - Ref:" => "%Company% boeking - Ref:",
            "dear"                     => ["Beste"],
            "Car Group:"               => ["Autotype:"],
            "Supplier:"                => "Autoverhuurbedrijf:",
            "Pick-up details:"         => ["Ophaalgegevens:"],
            "Country:"                 => "Land:",
            "City:"                    => "Plaats:",
            "Location:"                => "Locatie:",
            "Date:"                    => "Datum:",
            "Drop-off details:"        => ["Inlevergegevens:"],
            "Total Cost:"              => ["Totaalbedrag:", "Totale kosten:"],
            // "cancelledPhrases" => [""],
        ],
        "no" => [
            "%Company% booking - Ref:" => "Reservasjon - Ref:",
            "dear"                     => ["Hei"],
            "Car Group:"               => ["Bilgruppe:"],
            "Supplier:"                => "Utleieselskap:",
            "Pick-up details:"         => ["Avhentningsdetaljer:", "Avhentingsdetaljer:"],
            "Country:"                 => "Land:",
            "City:"                    => "By:",
            "Location:"                => ["Sted:", "Sted :"],
            "Date:"                    => ["Dato:", "Dato :"],
            "Drop-off details:"        => ["Avleveringsdetaljer:", "Returneringsdetaljer:"],
            "Total Cost:"              => ["Total kostnad:"],
            // "cancelledPhrases" => [""],
        ],
        "de" => [ // it-195658120.eml, it-39842903.eml
            "%Company% booking - Ref:" => "Buchung - Ref:",
            "dear"                     => ["Sehr geehrte(r)", "Hallo"],
            "Car Group:"               => ["Kategorie:", "Fahrzeuggruppe:"],
            "Supplier:"                => ["Anbieter:", "Mietwagenfirma:"],
            "Pick-up details:"         => ["Abholdetails:", "Informationen zur Abholung:"],
            "Country:"                 => "Land:",
            "City:"                    => "Stadt:",
            "Location:"                => ["Station:", "Standort:"],
            "Date:"                    => ["Datum:"],
            "Drop-off details:"        => ["Rückgabedetails:", "Informationen zur Rückgabe:"],
            "Total Cost:"              => ["Gesamtpreis:"],
            // "cancelledPhrases" => [""],
        ],
        "ru" => [ // it-39780029.eml
            "%Company% booking - Ref:" => "Бронировании с %Company% - Номер брони:",
            "dear"                     => ["Уважаемый/ая"],
            "Car Group:"               => ["Класс автомобиля:"],
            "Supplier:"                => "Поставщик:",
            "Pick-up details:"         => ["Детали получения:"],
            "Country:"                 => "Страна:",
            "City:"                    => "Город:",
            "Location:"                => ["Место:"],
            "Date:"                    => ["Дата:"],
            "Drop-off details:"        => ["Детали возврата:"],
            "Total Cost:"              => ["Общая Сумма:"],
            // "cancelledPhrases" => [""],
        ],
        "ja" => [
            "%Company% booking - Ref:" => "%Company%ご予約確認書（車両確定済）（ご予約番号 :",
            "dear"                     => ["様"],
            "Car Group:"               => ["お車の代表車種"],
            // "Supplier:" => "",
            "Pick-up details:"  => ["お貸出し詳細"],
            "Country:"          => "国",
            "City:"             => "都市",
            "Location:"         => ["営業所"],
            "Date:"             => ["貸出し日時", '返却日時'],
            "Drop-off details:" => ["ご返却詳細"],
            "Total Cost:"       => ["レンタル合計料金"],
            // "cancelledPhrases" => [""],
        ],
        "uk" => [ // it-44978678.eml
            "%Company% booking - Ref:" => "бронювання з %Company% - Номер:",
            "dear"                     => ["Здравствуйте Пан", "Шановний (-а) Пан"],
            "Car Group:"               => ["Группа автомобіля:", "Клас автомобіля:"],
            "Supplier:"                => "Постачальник:",
            "Pick-up details:"         => ["Деталі отримання:"],
            "Country:"                 => "Країна:",
            "City:"                    => "Місто:",
            "Location:"                => ["Розташування:", "Місце:"],
            "Date:"                    => ["Дата:"],
            "Drop-off details:"        => ["Деталі повернення:"],
            "Total Cost:"              => ["Повна вартість:", "Загальна Сума:"],
            // "cancelledPhrases" => [""],
        ],
        "fi" => [ // it-78137104.eml
            "%Company% booking - Ref:" => "%Company% Varauksen referenssinumero :",
            "dear"                     => ["Hyvä"],
            "Car Group:"               => ["Kokoluokka:"],
            "Supplier:"                => "Toimittaja:",
            "Pick-up details:"         => ["Noutotiedot:"],
            "Country:"                 => "maa:",
            "City:"                    => "kaupunki:",
            "Location:"                => ["sijainti:"],
            "Date:"                    => ["päivämäärä:"],
            "Drop-off details:"        => ["Palautustiedot:"],
            "Total Cost:"              => ["Kokonaishinta:"],
            // "cancelledPhrases" => [""],
        ],
        "ko" => [
            "%Company% booking - Ref:" => "%Company% 렌터카 예약에 따른 중요사항 - 예약번호:",
            "dear"                     => ["고객님께"],
            "Car Group:"               => ["차량등급:"],
            "Supplier:"                => "차량업체 :",
            "Pick-up details:"         => ["차량인수 상세정보:"],
            "Country:"                 => "국가:",
            "City:"                    => "도시:",
            "Location:"                => ["장소:"],
            "Date:"                    => ["날짜:"],
            "Drop-off details:"        => ["차량반납 상세정보:"],
            "Total Cost:"              => ["총 비용:"],
            // "cancelledPhrases" => [""],
        ],
        "cs" => [ // it-344251258-cs.eml
            "%Company% booking - Ref:" => "Vaše rezervace s %Company% - Ref: ",
            "dear"                     => ["Vážený/á", "Dobrý den"],
            "Car Group:"               => ["Kategorie vozidla:", "Typ auta:"],
            "Supplier:"                => ["Název dodavatele:", "Dodavatel:"],
            "Pick-up details:"         => ["Podrobnosti vyzvednutí:", "Informace o vyzvednutí:"],
            "Country:"                 => ["Země:", "země:"],
            "City:"                    => ["Město:", "město:"],
            "Location:"                => ["Lokalita:", "místo:"],
            "Date:"                    => ["Datum:", "datum:"],
            "Drop-off details:"        => ["Podrobnosti vrácení:", "Informace o vrácení:"],
            "Total Cost:"              => ["Celková cena:"],
            // "cancelledPhrases" => [""],
        ],
        "pl" => [ // it-109241202.eml
            "%Company% booking - Ref:" => "Twoja Rezerwacja %Company%:",
            "dear"                     => ["Drogi/-a"],
            "Car Group:"               => ["Model Samochodu:"],
            "Supplier:"                => "Nazwa dostawcy:",
            "Pick-up details:"         => ["Szczegóły Odbioru:"],
            "Country:"                 => "Kraj:",
            "City:"                    => "Miasto:",
            "Location:"                => ["Lokalizacja:"],
            "Date:"                    => ["Data:"],
            "Drop-off details:"        => ["Szczegóły Zwrotu:"],
            "Total Cost:"              => ["Całkowity Koszt:"],
            // "cancelledPhrases" => [""],
        ],
        "ar" => [ // it-109638185.eml
            "%Company% booking - Ref:" => "%Company% – الرقم المرجعي:",
            "dear"                     => ["السيد"],
            "Car Group:"               => ["نوع السيارة"],
            "Supplier:"                => "المزود",
            "Pick-up details:"         => ["تفاصيل الاستلام"],
            "Country:"                 => [":البلد", ": البلد"],
            "City:"                    => [":المدينة", ": المدينة"],
            "Location:"                => [":المكان", ": المكان"],
            "Date:"                    => [":التاريخ", ": التاريخ"],
            "Drop-off details:"        => ["تفاصيل التسليم"],
            "Total Cost:"              => [":السعر الإجمالي", ": السعر الإجمالي"],
            // "cancelledPhrases" => [""],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $prov => $dHeaders) {
            if (stripos($from, $dHeaders['from']) !== false) {
//                $this->providerCode = $prov; // can be from "rentalcars.com", but provider wizz
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"])) {
            return false;
        }

        foreach (self::$detectHeaders as $prov => $dHeaders) {
            foreach ($this->detectSubject as $dSubject) {
                if (stripos(preg_replace('/\s+/', ' ', $headers['subject']), $this->rp($dSubject, $dHeaders['company'])) !== false) {
                    $this->providerCode = $prov;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        foreach (self::$detectHeaders as $prov => $dHeaders) {
            if ($this->http->XPath->query("(//text()[" . $this->contains([$dHeaders['company'], strtolower($dHeaders['company'])]) . "])[1]")->length === 0) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false || $this->http->XPath->query("(//text()[" . $this->contains($dBody) . "])[1]")->length > 0) {
                        $this->providerCode = $prov;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false || $this->http->XPath->query("(//text()[" . $this->contains($dBody) . "])[1]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        if (empty($this->providerCode)) {
            $this->detectEmailByBody($parser);
        }

        if (empty($this->providerCode)) {
            $this->detectEmailByHeaders($parser->getHeaders());
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectHeaders as $prov => $dHeaders) {
                if (stripos($parser->getCleanFrom(), $dHeaders['from']) !== false) {
                    $this->providerCode = $prov;

                    break;
                }
            }
            $this->detectEmailByHeaders($parser->getHeaders());
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        // Travel Agency
        $email->obtainTravelAgency();
        $tripNum = $this->re("#" . $this->preg_implode($this->t("%Company% booking - Ref:")) . "\s*(\d+)#iu", $parser->getSubject());

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("%Company% booking - Ref:")) . "][1]", null, true, "#" . $this->preg_implode($this->t("%Company% booking - Ref:")) . "\s+(\d+)#u");
        }

        if (empty($tripNum)) {
            $tripNum = $this->re("/\:\s*(\d+)$/", $parser->getSubject());
        }

        if (!empty($tripNum)) {
            $email->ota()->confirmation($tripNum);
        }

        $this->parseHtml($email);

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
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $mainHtml = $this->http->FindHTMLByXpath("//text()[{$this->contains($this->t("Pick-up details:"))}]/ancestor::*[self::p or self::div or self::tr][1][{$this->contains($this->t("Drop-off details:"))}]");
        $mainText = $this->htmlToText($mainHtml);

        $r = $email->add()->rental();
        $r->general()->noConfirmation();

        $pax = null;

        if (in_array($this->lang, ['ja', 'ko', 'zh', 'ar'])) {
            $pax = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("dear"))}][1]/preceding::text()[normalize-space()][1]", null, true, "#^{$patterns['travellerName']}$#u");

            if (empty($pax)) {
                $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t("dear"))}]", null, "#^\s*({$patterns['travellerName']})\s*{$this->preg_implode($this->t("dear"))}\W*$#u"));

                if (count(array_unique($travellerNames)) === 1) {
                    $pax = array_shift($travellerNames);
                }
            }
        } else {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("dear"))}]", null, "#^{$this->preg_implode($this->t("dear"))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)#u"));

            if (count(array_unique($travellerNames)) === 1) {
                $pax = array_shift($travellerNames);
            }
        }

        // General
        if ($pax
            || count(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("dear"))}]"), function ($item) { return !preg_match("#^{$this->preg_implode($this->t("dear"))}[, ]*$#u", $item); })) > 0 // experimental
        ) {
            $r->general()->traveller(preg_replace("#^(?:Pan|Sr|Herr|Herra)[.\s]+(.{2,})$#iu", '$1', $pax), false);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t("cancelledPhrases"))}]")->length > 0) {
            // it-88634678.eml
            $r->general()
                ->status('cancelled')
                ->cancelled();
        }

        $account = $this->http->FindSingleNode("//text()[contains(.,'Loyalty number')]", null, false, "#Loyalty number\s*:\s*([\w\-]+)#");

        if (!empty($account)) {
            $r->program()->account($account, false);
        }

        $pickUpText = $dropOffText = null;

        if (preg_match("#(?:^|\n)[ ]*{$this->preg_implode($this->t("Pick-up details:"))}[ ]*\n{0,1}(?<pickup>(?:\n{1,2}.{2,}){1,6})\n+[ ]*{$this->preg_implode($this->t("Drop-off details:"))}[ ]*\n{0,1}(?<dropoff>(?:\n{1,2}.{2,}){1,6})#u", $mainText, $m)) {
            $pickUpText = trim($m['pickup']);
            $dropOffText = trim($m['dropoff']);
        }

        // Pick Up
        $country = $this->re("#^[ ]*{$this->preg_implode($this->t("Country:"))}[: ]*(.{2,}?)[ ]*$#mu", $pickUpText)
            ?? $this->re("#^[ ]*(.{2,}?)[: ]*{$this->preg_implode($this->t("Country:"))}[ ]*$#mu", $pickUpText)
            ?? $this->getNode($this->t("Pick-up details:"), $this->t("Country:"), "/following::text()[normalize-space(.)][not(contains(., ':'))][1]")
            ?? $this->getNode($this->t("Pick-up details:"), $this->t("Country:"));

        $city = $this->re("#^[ ]*{$this->preg_implode($this->t("City:"))}[: ]*(.{2,}?)[ ]*$#mu", $pickUpText)
            ?? $this->re("#^[ ]*(.{2,}?)[: ]*{$this->preg_implode($this->t("City:"))}[ ]*$#mu", $pickUpText)
            ?? $this->getNode($this->t("Pick-up details:"), $this->t("City:"))
            ?? $this->getNode($this->t("Pick-up details:"), $this->t("City:"), "/following::text()[normalize-space(.)][not(contains(., ':'))][1]");

        $date = $this->normalizeDate($this->re("#^[ ]*{$this->preg_implode($this->t("Date:"))}[: ]*(.{2,}?)[ ]*$#mu", $pickUpText)
            ?? $this->re("#^[ ]*(.{2,}?)[: ]*{$this->preg_implode($this->t("Date:"))}[ ]*$#mu", $pickUpText)
        );

        if (empty($date)) {
            $date = $this->normalizeDate($this->getNode($this->t("Pick-up details:"), $this->t("Date:")));
        }

        if (empty($date)) {
            $date = $this->normalizeDate($this->getNode($this->t("Pick-up details:"), $this->t("Date:"), "/following::text()[normalize-space(.)][1]", '/\:[ ]*(.+)/'));
        }

        $location = $this->re("#^[ ]*{$this->preg_implode($this->t("Location:"))}[: ]*(.{2,}?)[ ]*$#mu", $pickUpText)
            ?? $this->re("#^[ ]*(.{2,}?)[: ]*{$this->preg_implode($this->t("Location:"))}[ ]*$#mu", $pickUpText)
            ?? $this->getNode($this->t("Pick-up details:"), $this->t("Location:"))
            ?? $this->getNode($this->t("Pick-up details:"), $this->t("Location:"), "/following::text()[normalize-space(.)][not(contains(., ':'))][1]");

        if (!empty($date) && !empty(implode(', ', array_filter([$location, $city, $country])))) {
            $r->pickup()->date($date)->location(implode(', ', array_filter([$location, $city, $country])));
        }

        // Drop Off
        $dropoffDate = $this->normalizeDate($this->re("#^[ ]*{$this->preg_implode($this->t("Date:"))}[: ]*(.{2,}?)[ ]*$#m", $dropOffText)
            ?? $this->re("#^[ ]*(.{2,}?)[: ]*{$this->preg_implode($this->t("Date:"))}[ ]*$#mu", $dropOffText)
        );

        if (empty($dropoffDate)) {
            $dropoffDate = $this->normalizeDate($this->getNode($this->t("Drop-off details:"), $this->t("Date:")));
        }

        if (empty($dropoffDate)) {
            $dropoffDate = $this->normalizeDate($this->getNode($this->t("Drop-off details:"), $this->t("Date:"), "/following::text()[normalize-space(.)][1]", '/\:[ ]*(.+)/'));
        }

        $dropoffLoc = $this->re("#^[ ]*{$this->preg_implode($this->t("Location:"))}[: ]*(.{2,}?)[ ]*$#mu", $dropOffText)
            ?? $this->re("#^[ ]*(.{2,}?)[: ]*{$this->preg_implode($this->t("Location:"))}[ ]*$#mu", $dropOffText)
            ?? $this->getNode($this->t("Drop-off details:"), $this->t("Location:"))
            ?? $this->getNode($this->t("Drop-off details:"), $this->t("Location:"), "/following::text()[normalize-space(.)][not(contains(., ':'))][1]");

        if (!empty($dropoffDate) && !empty($dropoffLoc)) {
            $r->dropoff()->date($dropoffDate)->location($dropoffLoc);
        }

        if ($location === $dropoffLoc) {
            $r->dropoff()->same();
        }

        // Extra
        $company = $this->getDetails($this->t("Supplier:"));

        if (!empty($company)) {
            $foundCode = false;

            foreach ($this->rentalProviders as $code => $names) {
                foreach ($names as $name) {
                    if (stripos($name, $company) === 0) {
                        $r->program()->code($code);
                        $foundCode = true;

                        break 2;
                    }
                }
            }

            if ($foundCode === false) {
                $r->extra()->company($company);
            }
        }

        // Car
        $model = $this->getDetails($this->t("Car Group:"));

        if (empty($model)) {
            $model = implode(', ', $this->http->FindNodes("//text()[contains(., 'お車の代表車種')]/following::text()[normalize-space(.)][position() < 3]"));
        }

        if (!empty($model)) {
            $r->car()
                ->model($model);
        }

        // Price
        $total = $this->re("#^[ ]*{$this->preg_implode($this->t('Total Cost:'))}[: ]*(.+?)[ ]*$#mu", $mainText)
            ?? $this->re("#^[ ]*(.+?)[: ]*{$this->preg_implode($this->t('Total Cost:'))}[ ]*$#mu", $mainText);

        if (preg_match("#^(?<amount>\d[,.\'\d ]*)[( ]*(?<curr>[^\d)(]{1,5}?)[ )]*$#m", $total, $matches) // 35.65€    |    6166.00 (SEK)
            || preg_match("#^(?<curr>[^\d)(]{1,5})[ ]*(?<amount>\d[,.\'\d ]*)$#m", $total, $matches) // ₪3427.15    |    UAH 2104.21
        ) {
            $currency = $this->currency($matches['curr']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function rp($str, $companyName = null)
    {
        return str_replace('%Company%', ($companyName ?? self::$detectHeaders[$this->providerCode ?? 0]['company']), $str);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $this->rp($word);
        }

        return $this->rp(self::$dictionary[$this->lang][$word]);
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})\s+(\d+:\d+)$#u", //25 Aug 2017 17:00
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if ($this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s): ?string
    {
        $s = trim($s);

        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '₪'  => 'ILS',
            '€'  => 'EUR',
            'US$'=> 'USD',
            '$'  => 'USD',
            '£'  => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function getNode($str, $text, string $xpath = "", ?string $re = null): ?string
    {
        if ('' === $xpath) {
//            return $this->http->FindSingleNode(".//text()[".$this->contains($str)."]/following::text()[".$this->contains($text)."][normalize-space()][1]", null, true, "#".$this->preg_implode($text)."[:： ]+(.+)#u");
            return $this->http->FindSingleNode(".//text()[" . $this->contains($str) . "]/following::text()[" . $this->contains($text) . "][normalize-space()][1]", null, true, "#" . $this->preg_implode(preg_replace(["#[:： ]+$#u", "#^[:： ]+#u"], '', $text)) . "[:： ]+(.+)#u");
        } else {
            return $this->http->FindSingleNode(".//text()[" . $this->contains($str) . "]/following::text()[" . $this->contains($text) . "][normalize-space()][1]{$xpath}", null, true, $re);
        }
    }

    private function getDetails($str): ?string
    {
        return $this->http->FindSingleNode(".//text()[" . $this->contains($str) . "]", null, true, "#\s*\D+[:：] *(.+)$#u")
            ?? $this->http->FindSingleNode(".//text()[" . $this->contains($str) . "]", null, true, "#{$this->preg_implode($str)}[:：\s]*(.+)$#u")
            ?? $this->http->FindSingleNode(".//text()[" . $this->contains($str) . "]", null, true, "#^(.+?)[:：\s]*{$this->preg_implode($str)}#u")
        ;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s, "#"); }, $field)) . ')';
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
}
