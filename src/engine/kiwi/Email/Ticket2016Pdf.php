<?php

namespace AwardWallet\Engine\kiwi\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Ticket2016Pdf extends \TAccountChecker
{
    public $mailFiles = "kiwi/it-10003748.eml, kiwi/it-10004635.eml, kiwi/it-10017133.eml, kiwi/it-10273765.eml, kiwi/it-12053012.eml, kiwi/it-135616068.eml, kiwi/it-18374512.eml, kiwi/it-18462644.eml, kiwi/it-35685906.eml, kiwi/it-45715750.eml, kiwi/it-6430881.eml, kiwi/it-6434784.eml, kiwi/it-6483785.eml, kiwi/it-6508219.eml, kiwi/it-6512567.eml, kiwi/it-6513098.eml, kiwi/it-6526684.eml, kiwi/it-6530036.eml, kiwi/it-6548767.eml, kiwi/it-6605869.eml, kiwi/it-6611671.eml, kiwi/it-7044466.eml, kiwi/it-7058519.eml, kiwi/it-71138580.eml, kiwi/it-71138656.eml, kiwi/it-71346053.eml, kiwi/it-7174646.eml, kiwi/it-7399775.eml, kiwi/it-75663871.eml, kiwi/it-7693700.eml, kiwi/it-7787271.eml, kiwi/it-7790700.eml, kiwi/it-7887228.eml, kiwi/it-7934175.eml, kiwi/it-7936126.eml, kiwi/it-8040543.eml, kiwi/it-8876486.eml, kiwi/it-9016754.eml, kiwi/it-667889934-junk.eml"; // +3 bcdtravel(html)[en,da,sv]

    private $lang = '';

    private $subjects = [
        'en' => '#Booking [\w-]+:#',
        'es' => '#Reserva [\w-]+:#',
        '#Prenotazione [\w-]+:#',
        '#Réservation [\w-]+:#',
        '#Buchung [\w-]+:#',
        '#Rezervare [\w-]+:#',
        '#Rezerwacja [\w-]+:#',
        '#Foglalás sz. [\w-]+:#',
        '#Rezervasyon [\w-]+:#',
        'ru' => '#Бронирование [\w-]+:#',
        '#Boeking [\w-]+:#',
        'da' => '#Reservation [\w-]+:#',
        'sv' => '#Bokning [\w-]+:#',
        'cs' => '#Rezervace\s*#',
    ];

    private $body = [
        'pt' => ['Número da reserva:', 'Companhia Aérea', 'O seu cartão', 'os seus cartões', 'Cancelada e reembolsada'],
        'es' => ['Número de reserva', 'Imprima el billete electrónico adjunto', 'procesando tu pedido y te enviaremos tu billete', "Información adicional"],
        'it' => ['Numero di prenotazione:', 'Numero prenotazione:', 'Numero di prenotazione di Kiwi.com', 'Ci stiamo occupando della tua prenotazione', 'Numero di prenotazione'],
        'fr' => ['Numéro de réservation:', 'Numéro de réservation :', 'Numéro de réservation', 'Vous trouverez vos documents de voyage en pièce jointe'],
        'de' => ['Buchungsnummer', 'BUCHUNGSNUMMER'],
        'ro' => ['Număr rezervare:', 'şi am ataşat tichetele dvs. de îmbarcare'],
        'pl' => ['Numer rezerwacji'],
        'hu' => ['Foglalás száma:'],
        'tr' => ['Rezervasyon numarası:'],
        'ru' => ['Номер брони:', 'Код бронирования:', 'Номер бронирования'],
        'nl' => ['Boekingsnummer:', 'Boeking'],
        'fi' => ['Varausnumero:', 'Varausnumero'],
        'da' => ['Reservationsnummer', 'Reservationsnummer (PNR):'],
        'no' => ['Bookingnummer:'], // shoul be after 'da'-detect
        'sv' => ['Bokningsnummer:', 'Kiwi.com bokningsnummer', 'BOKNINGSNUMMER'],
        'sk' => ['Číslo rezervácie'],
        'cs' => ['Číslo rezervace'],
        'en' => ['Booking number', 'Kiwi.com booking number', 'processing your order and you can expect to receive'],
    ];

    private $date;

    private static $dict = [
        'pt' => [
            // Html
            'Booking number:' => ['Número da reserva:', 'Número de reserva:', 'Número da reserva', 'Número de reserva', 'NÚMERO DE RESERVA'], // +pdf1,pdf2
            'Booking status:' => ['Estado da reserva:', 'Estado da reserva'],

            'Passengers -'            => 'Passageiros -',
            "Passengers"              => ["PASSAGEIROS", "Passageiros"], // +pdf1,pdf2
            "Baggage"                 => ["BAGAGEM", "Bagagem"],
            'Successful cancellation' => 'Cancelada e reembolsada',
            //            "Total paid" => "",
            'Airline:'           => ['Companhia aérea:', 'Companhia Aérea:', 'Transportadora:', 'Transportadora:'], // +pdf2
            "Operating airline:" => "Transportadora operadora:",

            // Pdf1
            'Fare conditions'    => 'Condições tarifárias',
            'Flight information' => 'Informações do voo',
            //            "Bus information" => "",
            'Flight no:'  => ['Número do voo:', 'N.º do voo:'], // +pdf2
            'Operated by' => 'Operado por', // +pdf2
            'Duration:'   => 'Duração:', // +pdf2
            'Local time'  => 'Hora local',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Número de billete electrónico [eTicket number - PNR]:',
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            'Carrier reservation number (PNR)' => 'Número de reserva da transportadora (PNR)',
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'es' => [
            // Html
            'Booking number:' => ['Número de reserva:', 'Número de reserva', 'Número de reserva de Kiwi.com:', 'NÚMERO DE RESERVA'], // +pdf1,pdf2
            'Booking status:' => ['Estado de la reserva:', 'Estado de la reserva'],

            'Passengers -'            => 'Pasajeros -',
            'Passengers'              => ['Pasajeros', 'Passageiros'], // +pdf1,pdf2
            'Baggage'                 => ['Equipaje', 'Bagagem'],
            'Successful cancellation' => 'Este documento confirma a anulação do pagamento',
            'Total paid'              => 'Total pagado',
            'Airline:'                => ['Aerolínea:', 'Compañía aérea:', 'Transportadora:', 'Compañía:'], // +pdf2
            'Operating airline:'      => 'Compañía aérea operadora:',

            // Pdf1
            'Fare conditions'    => 'Condiciones de la',
            'Flight information' => ['Información sobre el', 'Información del vuelo'],
            //            "Bus information" => "",
            'Flight no:' => ['Número del vuelo:', 'N.° de vuelo:', 'N.º de vuelo:'], // +pdf2
            // 'Operated by' => '', // +pdf2
            "Duration:"  => "Duración:", // +pdf2
            'Local time' => 'Hora local',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => ['Número de reserva (PNR):', 'Número de billete electrónico [eTicket number - PNR]:'],
            // 'Just being issued' => '',
            "In process"            => "En proceso",

            // Pdf2
            "E-ticket number"                  => "N.º de billete electrónico",
            "Carrier reservation number (PNR)" => "N.º de reserva de la compañía (PNR)",
            "Additional information"           => "Información adicional",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'it' => [
            // Html
            'Booking number:' => ['Numero di prenotazione:', 'Numero prenotazione:', 'Numero di prenotazione (PNR):', 'Numero di prenotazione'], // +pdf1,pdf2
            'Booking status:' => 'Stato prenotazione:',

            'Passengers -' => 'Passeggeri -',
            'Passengers'   => 'Passeggeri', // +pdf1,pdf2
            "Baggage"      => "Bagaglio",
            //            "Successful cancellation" => "",
            //            "Total paid" => "",
            'Airline:' => ['Compagnia Aerea:', 'Compagnia aerea:', 'Vettore:'], // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'Condizioni tariffa',
            'Flight information' => ['Informazioni sul volo', 'Informazioni volo'],
            //            "Bus information" => "",
            'Flight no:' => 'Volo n.:', // +pdf2
            // 'Operated by' => '', // +pdf2
            'Duration:'  => 'Durata:', // +pdf2
            'Local time' => ['Tempo locale', 'Ora locale'],
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => ["numero dell'eTicket [eTicket number - PNR]:", 'Numero di prenotazione (PNR):'],
            //            "Just being issued" => "",

            // Pdf2
            'E-ticket number'                  => 'Numero di biglietto elettronico',
            'Carrier reservation number (PNR)' => 'Numero di prenotazione vettore (PNR)',
            "Additional information"           => "Informazioni aggiuntive",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'fr' => [
            // Html
            'Booking number:' => ['Numéro de réservation:', 'Numéro de réservation :', 'Numéro de réservation'], // +pdf1,pdf2
            'Booking status:' => ['État de la réservation:', 'Statut de la réservation :', 'Statut de la réservation'],

            'Passengers -' => 'Passagers -',
            'Passengers'   => 'Passagers', // +pdf1,pdf2
            'Baggage'      => 'Bagages',
            //            "Successful cancellation" => "",
            "Total paid" => "Montant total du paiement",
            'Airline:'   => ['Compagnie aérienne:', 'Compagnie aérienne :', 'Transporteur :', 'Transporteur:'], // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'Conditions tarifaires',
            'Flight information' => ['Information de vol', 'Informations sur le'],
            //            "Bus information" => "",
            'Flight no:' => ['Vol n°:', 'N de vol :', 'N° de vol :', 'N° de vol:'], // +pdf2
            // 'Operated by' => '', // +pdf2
            "Duration:"  => "Durée:", // +pdf2
            'Local time' => 'Heure locale',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => ['numéro de réservation [Reservation number - PNR]:', 'Numéro de réservation (PNR) :'],
            //            "Just being issued" => "",

            // Pdf2
            "E-ticket number"                  => "Numéro de billet électronique",
            "Carrier reservation number (PNR)" => "Numéro de réservation du transporteur (PNR)",
            "Additional information"           => "Informations supplémentaires",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'de' => [
            // Html
            'Booking number:' => ['Buchungsnummer:', 'Buchungsnummer', 'BUCHUNGSNUMMER'], // +pdf1,pdf2
            'Booking status:' => ['Buchungsstatus:', 'Buchungsstatus'],

            'Passengers -' => 'Fluggäste -',
            'Passengers'   => ['Fluggäste', 'Reisende'], // +pdf1,pdf2
            'Baggage'      => 'Gepäck',
            //            "Successful cancellation" => "",
            "Total paid"         => "Bezahlter Betrag",
            'Airline:'           => ['Fluggesellschaft:', 'Transportunternehmen:'], // +pdf2
            'Operating airline:' => ['Ausgeführt durch:', 'Ausführendes Transportunternehmen:'],

            // Pdf1
            'Fare conditions'    => ['Tarifbedingungen', 'Tarifbestimmungen'],
            'Flight information' => 'Fluginformationen',
            //            "Bus information" => "",
            'Flight no:' => ['Flugnummer:', 'Flug-Nr.:'], // +pdf2
            // 'Operated by' => '', // +pdf2
            'Duration:'  => 'Dauer:', // +pdf2
            'Local time' => 'Ortszeit',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Reservierungsnummer [Reservation number - PNR]:',
            //            "Just being issued" => "",

            // Pdf2
            'E-ticket number'                  => 'E-Ticket-Nummer',
            'Carrier reservation number (PNR)' => 'Buchungsnummer des Transportunternehmens (PNR)',
            'Additional information'           => 'Zusatzinformationen',
            'E-ticket / segment details'       => 'E-Ticket / Streckeninformationen',
            //'seat' => '',
        ],
        'ro' => [
            // Html
            'Booking number:' => ['Număr rezervare:', 'Număr rezervare'], // +pdf1,pdf2
            'Booking status:' => ['Starea rezervării:', 'Stare rezervare'],

            'Passengers -' => 'Pasageri -',
            'Passengers'   => 'Pasageri', // +pdf1,pdf2
            'Baggage'      => 'Bagaj',
            //            "Successful cancellation" => "",
            //            "Total paid" => "",
            'Airline:' => 'Companie aeriană:', // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            //            "Fare conditions" => "",
            //            "Flight information" => "",
            //            "Bus information" => "",
            //            "Flight no:" => "", // +pdf2
            // 'Operated by' => '', // +pdf2
            //            "Duration:" => "", // +pdf2
            //            "Local time" => "",
            //            "Terminal" => "",// +pdf2
            //            "eTicket number (PNR):" => "",
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'pl' => [
            // Html
            'Booking number:' => ['Numer rezerwacji:', 'Numer rezerwacji'], // +pdf1,pdf2
            'Booking status:' => ['Status rezerwacji:', 'Status rezerwacji'],

            'Passengers -' => 'Pasażerowie -',
            'Passengers'   => 'Pasażerowie', // +pdf1,pdf2
            'Baggage'      => 'Bagaż',
            //            "Successful cancellation" => "",
            //            "Total paid" => "",
            'Airline:' => ['Linia lotnicza:', 'Przewoźnik:'], // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => ['Zasady taryfy', 'Warunki dotyczące'],
            'Flight information' => ['Szczegóły lotu', 'Informacja o locie'],
            //            "Bus information" => "",
            'Flight no:' => ['Nr lotu:', 'Nr lotu::'], // +pdf2
            // 'Operated by' => '', // +pdf2
            'Duration:'  => 'Czas trwania::', // +pdf2
            'Local time' => ['czasu lokalnego', 'Czas lokalny'],
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => ['Numer rezerwacji (PNR):', 'reservation number (PNR):'],
            //            "Just being issued" => "",

            // Pdf2
            "E-ticket number"                  => "Numer biletu elektronicznego:",
            'Carrier reservation number (PNR)' => 'Numer rezerwacji u przewoźnika (PNR):',
            "Additional information"           => "Informacje dodatkowe",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'hu' => [
            // Html
            'Booking number:' => ['Foglalás száma:', 'A foglalás száma::'], // +pdf1,pdf2
            'Booking status:' => 'Foglalás állapota:',

            'Passengers -' => 'Utasok -',
            'Passengers'   => 'Utasok', // +pdf1,pdf2
            //            "Baggage" => "",
            //            "Successful cancellation" => "",
            //            "Total paid" => "",
            'Airline:' => 'Légitársaság:', // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'Utasbiztosítás',
            'Flight information' => 'Járatinformációk',
            //            "Bus information" => "",
            'Flight no:' => 'Járat száma:', // +pdf2
            // 'Operated by' => '', // +pdf2
            //            "Duration:" => "", // +pdf2
            'Local time' => 'Helyi idő',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Foglalási szám (PNR):',
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",

            //            'passengerNameSubText' => ['Utasfelvétel elvégezve'],
            //'seat' => '',
        ],
        'tr' => [
            // Html
            'Booking number:' => 'Rezervasyon numarası:', // +pdf1,pdf2
            'Booking status:' => ['Rezervasyon durumu:', 'Rezzervasyon durumu:'],

            'Passengers -' => 'Yolcular -',
            //            "Passengers" => "", // +pdf1,pdf2
            //            "Baggage" => "",
            //            "Successful cancellation" => "",
            //            "Total paid" => "",
            'Airline:' => 'Havayolu:', // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'Tarife koşulları',
            'Flight information' => 'Uçuş bilgileri',
            //            "Bus information" => "",
            'Flight no:' => 'Uçuş no:', // +pdf2
            // 'Operated by' => '', // +pdf2
            //            "Duration:" => "", // +pdf2
            'Local time' => 'Yerel saat',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Rezervasyon numarası (PNR):',
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'ru' => [
            // Html
            'Booking number:' => ['Номер брони:', 'Код бронирования:', 'Номер бронирования', 'Номер бронирования Kiwi.com:'], // +pdf1,pdf2
            'Booking status:' => ['Статус бронирования:', 'Состояние бронирования:', 'Статус бронирования'],

            'Passengers -' => ['Количество пассажиров: -', 'Пассажиры -'],
            "Passengers"   => "Пассажиры", // +pdf1,pdf2
            'Baggage'      => 'Багаж',
            //            "Successful cancellation" => "",
            "Total paid" => "Итого оплачено",
            'Airline:'   => ['Авиалинии:', 'Авиакомпания:', 'Перевозчик:'], // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'Паспорт/Виза',
            'Flight information' => ['Информация о рейсе', 'Информация о'],
            //            "Bus information" => "",
            'Flight no:'            => ['№ рейса:', 'Рейс №:', 'Номер рейса:', 'Рейс номер:'], // +pdf2
            'Operated by'           => 'Фактический перевозчик', // +pdf2
            'Duration:'             => 'Продолжительность:', // +pdf2
            'Local time'            => 'Местное время',
            'Terminal'              => 'Терминал', // +pdf2
            'eTicket number (PNR):' => ['Запись регистрации пассажира (PNR):', 'номер бронирования [Reservation number - PNR]:'],
            //            "Just being issued" => "",

            // Pdf2
            'E-ticket number'                  => 'Номер электронного авиабилета',
            'Carrier reservation number (PNR)' => 'Номер бронирования перевозчика (PNR)',
            'Additional information'           => 'Дополнительная информация',
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'nl' => [
            // Html
            'Booking number:' => ['Boekingsnummer:', 'boekingsnummer:', 'booking number:', 'Boekingsnummer'], // +pdf1,pdf2
            'Booking status:' => ['Boekingsstatus:', 'BOEKINGSSTATUS', 'Boekingsstatus'],

            'Passengers -' => ['Passagiers -'],
            'Passengers'   => ['Passagiers'], // +pdf1,pdf2
            'Baggage'      => 'Bagage',
            //            "Successful cancellation" => "",
            'Total paid' => 'Totaal betaald',
            'Airline:'   => ['Luchtvaartmaatschappij:', 'Vervoersmaatschappij:'], // +pdf2
            //            "Operating airline:" => "Nummer e-ticket",

            // Pdf1
            'Fare conditions'    => 'Tariefvoorwaarden',
            'Flight information' => 'Vluchtinformatie',
            //            "Bus information" => "",
            'Flight no:' => 'Vluchtnr.:', // +pdf2
            // 'Operated by' => '', // +pdf2
            "Duration:"  => "Duur:", // +pdf2
            'Local time' => 'Lokale tijd',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Reserveringsnummer (PNR):',
            'Just being issued'     => ['Af te geven', 'Aftegeven'],

            // Pdf2
            "E-ticket number"                  => "Nummer e-ticket",
            "Carrier reservation number (PNR)" => "Reserveringsnummer vervoersmaatschappij (PNR)",
            "Additional information"           => "Extra informatie",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'no' => [
            // Html
            'Booking number:' => ['Bookingnummer:'], // +pdf1,pdf2
            'Booking status:' => 'Bestillingsstatus:',

            'Passengers -' => 'Passasjerer -',
            //            "Passengers" => "", // +pdf1,pdf2
            //            "Baggage" => "",
            //            "Successful cancellation" => "",
            "Total paid" => "Betalt beløp",
            'Airline:'   => 'Flyselskap:', // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            //            "Fare conditions" => "",
            //            "Flight information" => "",
            //            "Bus information" => "",
            //            "Flight no:" => "", // +pdf2
            // 'Operated by' => '', // +pdf2
            //            "Duration:" => "", // +pdf2
            //            "Local time" => "",
            //            "Terminal" => "",// +pdf2
            //            "eTicket number (PNR):" => "",
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'fi' => [
            // Html
            'Booking number:' => ['Varausnumero:', 'Varausnumero'], // +pdf1,pdf2
            'Booking status:' => 'Varauksen tila:',

            'Passengers -' => 'Matkustajat -',
            'Passengers'   => 'Matkustajat', // +pdf1,pdf2
            'Baggage'      => 'Matkatavarat',
            //            "Successful cancellation" => "",
            "Total paid" => "Maksettu yhteensä",
            'Airline:'   => 'Lentoyhtiö:', // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            //            "Fare conditions" => "",
            //            "Flight information" => "",
            //            "Bus information" => "",
            //            "Flight no:" => "", // +pdf2
            // 'Operated by' => '', // +pdf2
            //            "Duration:" => "", // +pdf2
            //            "Local time" => "",
            //            "Terminal" => "",// +pdf2
            //            "eTicket number (PNR):" => "",
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'da' => [
            // Html
            'Booking number:' => ['bookingnummer:', 'Reservationsnummer:', 'Reservationsnummer (PNR):', 'Reservationsnummer', 'BOOKINGNUMMER', 'RESERVATIONSNUMMER'], // +pdf1,pdf2
            'Booking status:' => ['Reservationsstatus:', 'RESERVATIONSSTATUS'],

            'Passengers -' => ['Passagerer -', 'Passagerer ('],
            'Passengers'   => ['Passagerer', 'PASSAGERER'], // +pdf1,pdf2
            'Baggage'      => ['BAGAGE', 'Bagage'],
            //            "Successful cancellation" => "",
            'Total paid' => 'Betalt i alt',
            'Airline:'   => ['Flyselskab:', 'Transportselskab:'], // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'Pas/visa',
            'Flight information' => 'Flyinformation',
            //            "Bus information" => "",
            'Flight no:' => ['Flynummer:', 'Flynr.:'], // +pdf2
            // 'Operated by' => '', // +pdf2
            'Duration:'  => 'Rejsetid:', // +pdf2
            'Local time' => 'Lokal tid',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Reservationsnummer (PNR):',
            //            "Just being issued" => "",

            // Pdf2
            "E-ticket number"                  => "E-billetnummer",
            'Carrier reservation number (PNR)' => 'Reservationsnummer hos transportselskabet (PNR)',
            'Additional information'           => 'Yderligere oplysninger',
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'sv' => [
            // Html
            'Booking number:' => ['Bokningsnummer:', 'bokningsnummer:', 'BOKNINGSNUMMER'], // +pdf1,pdf2
            'Booking status:' => 'Bokningsstatus:',

            'Passengers -' => ['Passagerare -', 'Passagerare ('],
            "Passengers"   => "Resenärer", // +pdf1,pdf2
            //            "Baggage" => "",
            //            "Successful cancellation" => "",
            'Total paid' => 'Betald summa',
            'Airline:'   => 'Flygbolag:', // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'Pass/Visum',
            'Flight information' => 'Flyginformation',
            //            "Bus information" => "",
            'Flight no:'  => ['Flygnr:', 'Flightnr:'], // +pdf2
            'Operated by' => 'Transportör:', // +pdf2
            "Duration:"   => "Restid:", // +pdf2
            'Local time'  => 'Lokal tid',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Bokningsnummer (PNR):',
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            "Carrier reservation number (PNR)" => "Transportörens bokningsnummer (PNR)",
            "Additional information"           => "Ytterligare information",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'sk' => [
            // Html
            'Booking number:' => ['Číslo rezervácie'], // +pdf1,pdf2
            'Booking status:' => 'Stav rezervácie',

            'Passengers -' => [' Cestujúci'],
            'Passengers'   => 'Cestujúci', // +pdf1,pdf2
            //            "Baggage" => "",
            //            "Successful cancellation" => "",
            //            "Total paid" => "",
            'Airline:' => 'Letecká spoločnosť:', // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            'Fare conditions'    => 'pas/víza',
            'Flight information' => 'Informácie o lete',
            //            "Bus information" => "",
            'Flight no:' => 'Let č.:', // +pdf2
            // 'Operated by' => '', // +pdf2
            //            "Duration:" => "", // +pdf2
            'Local time' => 'Miestny čas',
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => 'Číslo rezervácie (PNR):',
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'cs' => [
            // Html
            'Booking number:' => ['Číslo rezervace'], // +pdf1,pdf2
            'Booking status:' => 'Stav rezervace',

            //            "Passengers -" => "",
            'Passengers' => 'Cestující', // +pdf1,pdf2
            'Baggage'    => 'Zavazadla',
            //            "Successful cancellation" => "",
            //            "Total paid" => "",
            'Airline:'           => 'Letecká společnost:', // +pdf2
            "Operating carrier:" => "Dopravce:",

            // Pdf1
            //            "Fare conditions" => "",
            //            "Flight information" => "",
            //            "Bus information" => "",
            "Flight no:" => "Číslo letu:", // +pdf2
            // 'Operated by' => '', // +pdf2
            "Duration:"  => "Délka:", // +pdf2
            //            "Local time" => "",
            //            "Terminal" => "",// +pdf2
            //            "eTicket number (PNR):" => "",
            //            "Just being issued" => "",

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',
        ],
        'en' => [
            // Html
            "Booking number:" => ['Booking number:', 'booking number:', 'Booking number', 'BOOKING NUMBER'], // +pdf1,pdf2
            'Booking status:' => ['Booking status:', 'Booking status', 'BOOKING STATUS'],

            'Passengers -'            => ['Passengers -', 'Passengers (', 'PASSENGERS'],
            "Passengers"              => ['Passengers', 'PASSENGERS'], // +pdf1,pdf2
            "Baggage"                 => ['Baggage', 'BAGGAGE'],
            "Successful cancellation" => [
                'Successful cancellation', 'Your cancellation request has been approved', 'We cancelled your booking', 'Here’s the document confirming your refund',
            ],
            //            "Total paid" => "",
            "Airline:" => ["Airline:", "Carrier:"], // +pdf2
            //            "Operating airline:" => "",

            // Pdf1
            //            "Fare conditions" => "",
            //            "Flight information" => "",
            //            "Bus information" => "",
            //            "Flight no:" => "", // +pdf2
            // 'Operated by' => '', // +pdf2
            //            "Duration:" => "", // +pdf2
            //            "Local time" => "",
            //            "Terminal" => "",// +pdf2
            'eTicket number (PNR):' => ['eTicket number (PNR):', 'Reservation number (PNR):', 'Reservation number:'],
            'Just being issued'     => ['Just being issued', 'Justbeingissued', 'To be issued', 'Tobeissued'],

            // Pdf2
            //            "E-ticket number" => "",
            //            "Carrier reservation number (PNR)" => "",
            //            "Additional information" => "",
            //            "E-ticket / segment details" => "",
            //'seat' => '',

            'passengerNameSubText' => [
                'Check-in available from', 'Check in at the airport for free', 'Airport check-in required with e-ticket',
                'Your ticket is ready',
            ],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?',
        'travellerName' => '[[:alpha:]][-.\'|[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']) === true
            && $this->arrikeyMatch($headers['subject'], $this->subjects) !== null;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // PDF
        $pdfs = $parser->searchAttachmentByName('(?:.*pdf|\d+)');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (stripos($textPdf, 'kiwi.com') === false) {
                continue;
            }

            if ($this->arrikey($textPdf, $this->body) !== false) {
                return true;
            }
        }

        // HTML
        $condition1 = $this->http->XPath->query('//node()[contains(.,"Kiwi.com") or contains(.,".kiwi.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"www.kiwi.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        $textBody = str_replace(chr(194) . chr(160), ' ', $parser->getHTMLBody());

        return $this->arrikey($textBody, $this->body) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@kiwi.') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        $this->date = strtotime($parser->getDate(), false);

        // PDF

        $pdfs = $parser->searchAttachmentByName('(?:.*pdf|\d+)');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;°\"'’<>«»?~`!@\#№$%^&*\[\]=\(\)\-–‑{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if (($lang = $this->arrikey($textPdf, $this->body)) === false) {
                continue;
            }

            $this->lang = $lang;
            $this->logger->debug('PDF lang: ' . $this->lang);

            if (preg_match("/\n([ ]*{$this->preg_implode($this->t('Fare conditions'))}.{60,})/u", $textPdf, $matches)) {
                $textPdf = $this->strCut($textPdf, null, $matches[1]);
                $this->parseAirPdf1($textPdf, $email); // examples: it-6434784.eml
                $type = 'Pdf1';
            } elseif (
                preg_match("/{$this->preg_implode($this->t('Flight no:'))}(?:.*\n){1,3}.*{$this->preg_implode($this->t('Duration:'))}/u", $textPdf, $matches)
            ) {
                if (preg_match("/\n([ ]*{$this->preg_implode($this->t('Additional information'))})\n/u", $textPdf, $matches)) {
                    $textPdf = $this->strCut($textPdf, null, $matches[1]);
                }

                // Travel Agency
                $email->obtainTravelAgency();

                $conf = null;

                if (preg_match("/(?:^\s*|[ ]{2}){$this->preg_implode($this->t('Booking number:'))}[ ]*([\d ]{5,})\n/ui", $textPdf, $m)) {
                    $conf = str_replace(' ', '', $m[1]);
                }
                $email->ota()
                    ->confirmation($conf);

                if (preg_match("/\n([ ]*{$this->preg_implode($this->t('E-ticket / segment details'))})/u", $textPdf, $matches)) {
                    $textPdf = $this->strCut($textPdf, $matches[1], null);
                } elseif (preg_match("/(?:^|\n)([ ]*{$this->preg_implode($this->t('Passengers'))})\n/u", $textPdf, $matches)) {
                    $textPdf = $this->strCut($textPdf, $matches[1], null, false);
                }

                $this->parseAirPdf2($textPdf, $email); // examples: it-71346053.eml, it-71138580.eml
                $type = 'Pdf2';
            }
        }

        // HTML
        if (count($email->getItineraries()) === 0) {
            $textBody = $this->http->Response['body'];
            $this->lang = $this->arrikey($textBody, $this->body);

            if (stripos($textBody, $this->t('I agree with the change')) !== false) {
                $this->http->SetEmailBody($this->strCut($textBody, null, $this->t('I agree with the change')));
            }
            $email->removeTravelAgency();
            $this->parseAirHtml($email); // examples: it-6513098.eml
            $type = 'Html';
        }

        $this->totalCharge($email);

        $email->setType('Ticket2016' . $type . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 5; //2 pdf + 3 html
    }

    protected function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reBody = ['en' => ['Reservation Modify'],];
     * var $reSubject = ['Reservation Modify']
     * </pre>.
     *
     * @param string $haystack
     *
     * @return int, string, false
     */
    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function arrikeyMatch($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (preg_match($needles, $haystack)) {
                return $key;
            }
        }

        return null;
    }

    private function totalCharge(Email $email): void
    {
        if ($total = $this->http->FindSingleNode("//strong[@id='editTotalPaid']")) {
            if (preg_match('/\b[A-Z]{3}\b/', $total, $matches)) {
                $email->price()
                    ->total($this->cost($total))
                    ->currency($matches[0])
                ;

                if (empty($this->cost($total))) {
                    $this->logger->debug('It is necessary to check the total price.');
                }
            }
        }
    }

    private function parseAirHtml(Email $email): void
    {
        $xpathTime = 'contains(translate(.,"0123456789：Hh","∆∆∆∆∆∆∆∆∆∆:::"),"∆:∆∆")';
        $xpathTime2 = '(starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh ","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        // Travel Agency
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number:'))}]/ancestor::tr[1]/following-sibling::tr[1]", null, true, '/^[-A-Z\d ]{5,}$/');

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number:'))}]/following::text()[normalize-space(.)][1]", null, true, '/^[-A-Z\d ]{5,}$/');
        }
        $email->ota()->confirmation(str_replace(' ', '', $conf));

        if (!empty($conf) && $this->http->XPath->query("//text()[{$xpathTime} or {$xpathTime2}]")->length < 2
            && $this->http->XPath->query("//*[" . $this->contains([
                'remind you to add your details for our online check-in service with the airline',
                'we need additional details for your boarding passes for your flight',
                'We need additional details for your boarding passes for your flight',
            ]) . "]")->length > 0
        ) {
            $email->setIsJunk(true, 'Empty flight segments. Require more information for flight.');

            return;
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking status:'))}]/ancestor::tr[1]/following-sibling::tr[1]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking status:'))}]/following::text()[normalize-space(.)][1]", null, true, '/^[\w\s]{4,}$/u');
        }

        if (!empty($status)) {
            $f->general()->status($status);
        }

        // Travellers
        $xpath = "//text()[{$this->starts($this->t('Passengers -'))}]/ancestor::p[1]/following-sibling::div[ .//*[normalize-space()][name()='strong' or name()='b'] ]";
        $passengers = $this->http->FindNodes($xpath, null, "/^{$this->patterns['travellerName']}$/u");
        $passengerValues = array_values(array_filter($passengers));

        if (empty($passengerValues[0])) {
            $xpath = "//text()[{$this->starts($this->t('Passengers -'))}]/ancestor::p[1]/following-sibling::table/descendant::tr[normalize-space(.) and not(.//tr)]";
            $passengers = $this->http->FindNodes($xpath, null, "/^{$this->patterns['travellerName']}$/u");
            $passengerValues = array_values(array_filter($passengers));
        }

        if (empty($passengerValues[0])) {
            $xpath = "//tr[ *[1][{$this->eq($this->t('Passengers'))}] and *[2][{$this->eq($this->t('Baggage'))}] ]/ancestor::table[1]/following-sibling::table/descendant::tr[normalize-space()][1][count(*)=2]/*[1]";
            $passengers = $this->http->FindNodes($xpath, null, "/^{$this->patterns['travellerName']}$/u");
            $passengerValues = array_values(array_filter($passengers));
        }

        if (empty($passengerValues[0])) {
            // it-71138656.eml
            $xpath = "//tr[ *[1][{$this->eq($this->t('Passengers'))}] and *[3][{$this->eq($this->t('Baggage'))}] ]/../following-sibling::tbody/descendant::tr[normalize-space()][count(*)=3]/*[1]/descendant::text()[normalize-space()]/ancestor::p[1]";
            $passengers = $this->http->FindNodes($xpath, null, "/^{$this->patterns['travellerName']}$/u");
            $passengerValues = array_values(array_filter($passengers));
        }
//        $this->logger->debug('travellers xpath = '.print_r( $xpath,true));

        if (!empty($passengerValues[0])) {
            $f->general()->travellers(preg_replace("/^(?:Mr\.\s*|Ms\.|Mrs\.)/", "", $passengerValues));
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Successful cancellation")) . "])[1]"))) {
            $f->general()
                ->cancelled();

            return;
        }

        // Price
        $payment = $this->http->FindSingleNode('//strong[@id="editTotalPaid"]');

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode('//*[' . $this->starts($this->t('Total paid')) . ' and (self::th or self::td) and not(.//th) and not(.//td)]/following-sibling::td[normalize-space(.)][1]');
        }

        if (preg_match('/\b[A-Z]{3}\b/', $payment, $matches)) {
            $f->price()
                ->total($this->cost($payment))
                ->currency($matches[0]);
        } else {
            $this->logger->debug('It is necessary to check the cost.');
        }

        if ($year = $this->http->FindSingleNode("(//a[starts-with(normalize-space(),'invoice_')])[1]", null, true, "/invoice_(\d{4})_/")) {
            $this->date = strtotime("01.01.$year", false);
        }

        $busCount = 0;
        $trainCount = 0;

        $xpathFragment1 = 'count(./descendant::td[not(.//td)])>2';
        $xpathFragment2 = '[not(ancestor::*[contains(@style,"#FFDDDD") or contains(@style,"#ffdddd")])]'; // it-6513098.eml
        $segments = $this->http->XPath->query("//table[ ./descendant::tr[1][ $xpathFragment1 and ./following-sibling::tr[1][$xpathFragment1] ] ]$xpathFragment2");
        $segments_t2 = $segments_t3 = [];

        if ($segments->length > 0 && $this->http->FindSingleNode("descendant::tr[1]/td[3][{$xpathTime}]", $segments->item(0))) {
            $segments_t2 = $segments;
            $segments = [];
        }

        $xpath = "//text()[{$xpathTime}]/ancestor::tr[position()<3][ count(*)=3 and *[1]/descendant::img and *[3][{$xpathTime2}] ]/ancestor::*[count(*[normalize-space()])>1][1]";
        $segments_3 = $this->http->XPath->query($xpath);

        if ($segments_3->length > 0) {
            $segments = $segments_t2 = [];
            $segments_t3 = $segments_3;
        }

        // Type 1: date/time   duration   airport
        foreach ($segments as $root) {
            $this->logger->debug('Segments type: 1');

            $s = $f->addSegment();

            // Airline
            $airline = $this->http->FindSingleNode('./following-sibling::*[normalize-space(.)][1]/descendant::text()[' . $this->contains($this->t('Airline:')) . ']', $root, true, '/^[^:]+:\s*(.+)$/');

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode('./following-sibling::*[normalize-space(.)][1]/descendant::text()[' . $this->contains($this->t('Airline:')) . ']/following::text()[normalize-space(.)][1]', $root, true, '/^[^:]+$/');
            }

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode('./descendant::text()[' . $this->contains($this->t('Airline:')) . ']', $root, true, '/^[^:]+:\s*(.+)$/');
            }
            $s->airline()
                ->noNumber()
                ->name($airline);

            // Operator
            $operator = $this->http->FindSingleNode('./following-sibling::*[normalize-space(.)][1]/descendant::text()[' . $this->contains($this->t('Operating airline:')) . ']', $root, true, '/^[^:]+:\s*(.+)$/');

            if (empty($operator)) {
                $operator = $this->http->FindSingleNode('./following-sibling::*[normalize-space(.)][1]/descendant::text()[' . $this->contains($this->t('Operating airline:')) . ']/following::text()[normalize-space(.)][1]',
                    $root, true, '/^[^:]+$/');
            }

            if ($operator) {
                $s->airline()
                    ->operator($operator);
            }

            // Departure
            $departureText = $this->http->FindSingleNode('./descendant::tr[not(.//tr)][1]/td[4]', $root);

            if (empty($departureText)) {
                $departureText = $this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]', $root);
            }

            if (preg_match('/^(?<name>.+?)\((?<code>[A-Z]{3})\)/', $departureText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                ;
            }
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]/td[2]', $root)));

            // Arrival
            $arrivalText = $this->http->FindSingleNode('./descendant::tr[not(.//tr)][2]/td[4]', $root);

            if (empty($arrivalText)) {
                $arrivalText = $this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]/following-sibling::tr[normalize-space(.)][1]', $root);
            }

            if (preg_match('/^(?<name>.+?)\((?<code>[A-Z]{3})\)/', $arrivalText, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                ;
            }
            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]/following-sibling::tr[normalize-space(.)][1]/td[2]', $root)));

            if ($s->getArrDate()) {
                $this->date = $s->getArrDate();
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode('./descendant::tr[normalize-space(.)][1]/td[3]', $root), true, true)
            ;
        }

        // Type 2: airport   date/time
        foreach ($segments_t2 as $root) {
            $this->logger->debug('Segments type: 2');

            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->noNumber()
                ->name($this->http->FindSingleNode('(./descendant::tr)[1]/following-sibling::tr[normalize-space()][2]//text()[' . $this->eq($this->t("Airline:")) . ']/following::text()[normalize-space()][1]', $root))
            ;

            // Departure
            $departureText = $this->http->FindSingleNode('(./descendant::tr)[1]/td[2]', $root);

            if (preg_match('/^(?<name>.+?)\s+(?<code>[A-Z]{3})$/', $departureText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                ;
            }
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode('(./descendant::tr)[1]/td[3]', $root)));

            // Arrival
            $arrivalText = $this->http->FindSingleNode('(./descendant::tr)[1]/following-sibling::tr[normalize-space()][1]/td[2]', $root);

            if (preg_match('/^(?<name>.+?)\s+(?<code>[A-Z]{3})$/', $arrivalText, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                ;
            }
            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode('(./descendant::tr)[1]/following-sibling::tr[normalize-space()][1]/td[3]', $root)));

            if ($s->getArrDate()) {
                $this->date = $s->getArrDate();
            }
        }

        // Type 3: airport   time
        foreach ($segments_t3 as $root) {
            $this->logger->debug('Segments type: 3'); // it-35685906.eml, it-71138656.eml

            $segmentType = '';

            if ($this->http->XPath->query('descendant::tr[1]/*[1]/descendant::img[contains(@src,"/bus.")]', $root)->length > 0) {
                // it-45715750.eml
                $busCount++;

                if (!isset($bus)) {
                    $bus = $email->add()->bus();
                    $bus->general()
                        ->noConfirmation()
                        ->travellers(array_column($f->getTravellers(), 0))
                    ;

                    if (!empty($f->getStatus())) {
                        $bus->general()->status($f->getStatus());
                    }
                }
                $s = $bus->addSegment();

                $segmentType = 'bus';
            } elseif ($this->http->XPath->query('descendant::tr[1]/*[1]/descendant::img[contains(@src,"/train.")]', $root)->length > 0) {
                $trainCount++;

                if (!isset($train)) {
                    $train = $email->add()->train();
                    $train->general()
                        ->noConfirmation()
                        ->travellers(array_column($f->getTravellers(), 0))
                    ;

                    if (!empty($f->getStatus())) {
                        $train->general()->status($f->getStatus());
                    }
                }
                $s = $train->addSegment();

                $s->setNoNumber(true);

                $serviceName = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(), 'Carrier:')][1]/ancestor::div[1]", $root, true, "/{$this->opt($this->t('Carrier:'))}\s*(.+)/");
                $this->logger->debug('TRAIN-' . $serviceName);

                if (!empty($serviceName)) {
                    $s->setServiceName($serviceName);
                }

                $segmentType = 'train';
            } else {
                $s = $f->addSegment();
                $segmentType = 'flight';
            }

            // Departure
            $departureText = $this->http->FindSingleNode('descendant::tr[*[3] and normalize-space()][1]/*[2]', $root);

            if (preg_match("/^(?<code>[A-Z]{3})\s+(?<name>.{2,})$/", $departureText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                ;
            } else {
                $s->departure()
                    ->name($departureText)
                ;

                if ($segmentType == 'flight') {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                    $s->departure()
                        ->noCode();
                }
            }
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode('descendant::tr[*[3] and normalize-space()][1]/*[3]', $root)));

            // Arrival
            $arrivalText = $this->http->FindSingleNode('descendant::tr[*[3] and normalize-space()][1]/following::tr[*[3] and normalize-space()][1]/*[2]', $root);

            if (preg_match("/^(?<code>[A-Z]{3})\s+(?<name>.{2,}?)\s*(?:{$this->preg_implode($this->t('Airline:'))}\s*(?<AirlineName>.{2,}))?\s{$this->preg_implode($this->t('Operating carrier:'))}(?<operator>.+)$/", $arrivalText, $m)
                || preg_match("/^(?<code>[A-Z]{3})\s+(?<name>.{2,}?)\s*(?:{$this->preg_implode($this->t('Airline:'))}\s*(?<AirlineName>.{2,}))?$/", $arrivalText, $m)
                || preg_match("/(?<name>[A-Z][a-z].{2,}?)\s*(?:{$this->preg_implode($this->t('Airline:'))}\s*(?<AirlineName>.{2,}))?$/", $arrivalText, $m)) {
                if (isset($m['code'])) {
                    $s->arrival()
                        ->code($m['code']);
                }
                $s->arrival()
                    ->name($m['name']);

                $airlineName = $m['AirlineName'] ?? null;

                if (isset($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }
            } else {
                $s->arrival()
                    ->name($arrivalText);

                if ($segmentType == 'flight') {
                    /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $s */
                    $s->arrival()
                        ->noCode();
                }

                if ($segmentType == 'bus') {
                    /** @var \AwardWallet\Schema\Parser\Common\BusSegment $s */
                    /* WTF?
                    $s->arrival()
                        ->noCode();
                    */
                }
            }

            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode('descendant::tr[*[3] and normalize-space()][1]/following::tr[*[3] and normalize-space()][1]/*[3]', $root)));

            if ($s->getArrDate()) {
                $this->date = $s->getArrDate();
            }

            // Airline
            if (empty($airlineName)) {
                $airlineName = $this->http->FindSingleNode('descendant::tr[*[3] and normalize-space()][1]/following-sibling::tr[normalize-space()][2]//text()[' . $this->eq($this->t("Airline:")) . ']/following::text()[normalize-space()][1]',
                    $root);
            }

            if (empty($airlineName)) {
                $airlineName = $this->http->FindSingleNode("descendant::tr/*[{$this->starts($this->t('Airline:'))}]", $root, false, "/{$this->preg_implode($this->t('Airline:'))}\s*(.+)$/");
            }

            if (empty($airlineName)) {
                $airlineName = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Operating carrier:'))}]/following::text()[normalize-space()][1]", $root);
            }

            if (!empty($airlineName) && $segmentType == 'flight') {
                if (preg_match("/(.+?)\s+" . $this->preg_implode($this->t("Operating airline:")) . "\s*(.+)/", $airlineName, $m)) {
                    $s->airline()
                        ->noNumber()
                        ->name($m[1])
                        ->operator($m[2])
                    ;
                } elseif ($segmentType == 'train') {
                    $this->logger->warning('train');
                } else {
                    $s->airline()
                        ->noNumber()
                        ->name($airlineName);
                }
            }
        }

        if (isset($bus) && empty($f->getSegments())) {
            $email->removeItinerary($f);
        }

        if (isset($train) && empty($f->getSegments())) {
            $email->removeItinerary($f);
        }
    }

    private function parseAirPdf1($textFull, Email $email): void
    {
        $this->logger->debug(__METHOD__);

        // Travel Agency
        $tripNumber = str_replace(' ', '', $this->match('/' . $this->preg_implode($this->t('Booking number:')) . '\s*([-\w ]+)\n/', $textFull));

        if (empty($tripNumber)) {
            $tripNumber = str_replace(' ', '', $this->http->FindSingleNode("//*[{$this->contains($this->t('Booking number:'))}]/following-sibling::span"));
        }

        $email->ota()->confirmation($tripNumber);

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking status:'))} ]/ancestor::tr[1]/following-sibling::tr[1]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking status:'))}]/following::text()[normalize-space(.)][1]",
                null, true, '/^[\w\s]{4,}$/u');
        }

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Airline schedule'))}]", null, true, "/Airline schedule\s*(\w+)\s*\:/");
        }

        $segmentsText = $this->splitter("/({$this->preg_implode($this->t('Booking number:'))}\s(?:.*\n){3})/u", $textFull);

        $startSegments = false;

        foreach ($segmentsText as $i => $stext) {
            if (($startSegments == false || $i == (count($segmentsText) - 1)) && !preg_match("/\n\s*{$this->preg_implode($this->t('Flight information'))} +/u", $stext, $m)
                && ($i == 0 || $i == count($segmentsText) - 1) || (($i < count($segmentsText) / 2) && preg_match("/^(?:.*\n){1,7} *1\. *\w+ - [\w ,.]*\d[\w ,.]*(?: +.*)?\n.* {3,}\d{1,2}:\d{1,2}/u", $stext))) {
                continue;
            } else {
                $startSegments = true;
            }

            if (preg_match_all("/^[ ]*{$this->preg_implode($this->t('Bus information'))}\s+/m", $stext, $m)) {
                // it-45715750.eml
                $busSegments[] = $stext;

                continue;
            }

            $tickets = [];
            // E-ticket number: 1 6 9 1 4 3 9 5 5 1 0 8 6
            if (preg_match_all('/[Ee][-]{0,3}[Tt]icket [Nn]umber:[ ]*([-A-Z\d\/ ]+[\d ]{12}[-A-Z\d\/ ]+)$/m', $stext, $eTicketMatches)) {
                $tickets = str_replace(' ', '', $eTicketMatches[1]);
            }

            $foundIt = false;

            foreach ($email->getItineraries() as $it) {
                /** @var Flight $it */
                if ($it->getType() === 'flight') {
                    $itTicket = array_column($it->getTicketNumbers(), 0);

                    if (strncasecmp($tickets[0] ?? '', $itTicket[0] ?? '', 3) === 0) {
                        $f = $it;
                        $foundIt = true;
                        $ticketAdd = array_diff($tickets, $itTicket);

                        if (!empty($ticketAdd)) {
                            $f->issued()->tickets($ticketAdd, false);
                        }
                    }
                }
            }

            if ($foundIt == false) {
                $f = $email->add()->flight();

                if (!empty($tickets)) {
                    $f->issued()->tickets($tickets, false);
                }

                if (!empty($status)) {
                    $f->general()->status($status);
                }
            }

            $s = $f->addSegment();

            if (preg_match("/{$this->preg_implode($this->t('Flight no:'))}\s*(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})\s*(?<FlightNumber>\d+)\s/", $stext, $m)) {
                $s->airline()
                    ->name($m['AirlineName'])
                    ->number($m['FlightNumber'])
                ;
            }

            if (preg_match("/{$this->preg_implode($this->t('Duration:'))} *(.+)/u", $stext, $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            if (preg_match("/\n(?<space> *){$this->preg_implode($this->t('Flight information'))} +(?<depName1>\S.+)[ ]{3,}(?<depDate>.+){$this->preg_implode($this->t('Local time'))}"
                    . "(?<depName2>[\s\S]*?)\n(?<arrName1>.+)[ ]{3,}(?<arrDate>.+){$this->preg_implode($this->t('Local time'))}(?<arrName2>[\s\S]*?)\n.*{$this->preg_implode($this->t('Flight no:'))}/u", $stext, $m)) {
                // Departure
                $depName = $m['depName1'] . "\n" . $m['depName2'];
                $depName = preg_replace("/\n {" . (strlen($m['space']) - 5) . ',' . (strlen($m['space']) + 5) . "}\S+?(?: {3,}|$)/u", "\n", $depName);
                $depName = preg_replace('/\s*\n\s*/', ' ', trim($depName));

                if (preg_match("/(.+)\s+([A-Z]{3})(?:\s+-\s+(.*{$this->preg_implode($this->t('Terminal'))}.*))?$/u", $depName, $mat)) {
                    $s->departure()
                        ->name($mat[1])
                        ->code($mat[2])
                        ->terminal(trim(preg_replace("/\s*{$this->preg_implode($this->t('Terminal'))}\s*/", ' ', $mat[3] ?? '')), true, true)
                    ;
                }
                $s->departure()->date($this->normalizeDate($m['depDate']));

                // Arrival
                $arrName = preg_replace('/\s*\n\s*/', ' ', trim($m['arrName1'] . " " . $m['arrName2']));

                if (preg_match("/(.+)\s+([A-Z]{3})(?:\s+-\s+(.*{$this->preg_implode($this->t('Terminal'))}.*))?$/u", $arrName, $mat)) {
                    $s->arrival()
                        ->name($mat[1])
                        ->code($mat[2])
                        ->terminal(trim(preg_replace("/\s*{$this->preg_implode($this->t('Terminal'))}\s*/", ' ', $mat[3] ?? '')), true, true)
                    ;
                }
                $s->arrival()->date($this->normalizeDate($m['arrDate']));
            }

            $pnrText = str_replace(' ', '', $this->re('/\s+' . $this->preg_implode($this->t('eTicket number (PNR):')) . ' *([A-Z\d ;]+)(?:\n|;)/u', $stext));

            if (empty($pnrText)) {
                $pnrText = str_replace(' ', '', $this->re('/\bPNR[\)\]]: *([A-Z\d ;]{5,})(?:\n|;)/u', $stext));
            }
            $pnrs = explode(';', $pnrText);
            $pnrsAdd = array_filter(array_diff($pnrs, array_column($f->getConfirmationNumbers(), 0)));

            foreach ($pnrsAdd as $pnr) {
                $f->general()->confirmation($pnr);
            }

            if (empty($f->getConfirmationNumbers()) && (preg_match("/\s+" . $this->preg_implode($this->t('eTicket number (PNR):')) . " *{$this->preg_implode($this->t('Just being issued'))}\n/i", $stext)
                || preg_match("/\bPNR[\)\]]: *{$this->preg_implode($this->t('Just being issued'))}\n/i", $stext))
            ) {
                $f->general()->noConfirmation();
            }

            $travellersText = $this->re("/\n(([ ]+){$this->preg_implode($this->t('Passengers'))}[ ]{3,}(?:.*\n){1,20}?)\\2 {0,5}\S/u", $stext);

            $namePrefixes = ['Mr', 'Ms', 'Mrs', 'Mis', 'Mme', 'Sra', 'Srta', 'Sr', 'Sig', 'Pani', 'Pan', 'Frau', 'Herr', 'Úr', 'Г-Жа', 'Г-Н', 'Г-Ж', 'M.'];
            // Example: PNR: X 4 6 U G B
            if (preg_match_all("/^( *{$this->preg_implode($this->t('Passengers'))})?[ ]{3,}\s*{$this->opt($namePrefixes)}\s*\.*\s*(?<traveller>[-.\'\w ]+?)[ ]{3,}PNR:\s*(?<pnr>[A-Z\d ]{5,})$/um", $travellersText, $travellersMatches)) {
                $travellersAdd = array_diff($travellersMatches['traveller'], array_column($f->getTravellers(), 0));

                if (!empty($travellersAdd)) {
                    $f->general()->travellers($travellersAdd);
                }
                $pnrs = str_replace(' ', '', $travellersMatches['pnr']);
                $pnrsAdd = array_diff($pnrs, array_column($f->getConfirmationNumbers(), 0));

                foreach ($pnrsAdd as $pnr) {
                    $f->general()->confirmation($pnr);
                }
                $this->logger->debug('Passengers type: 1');
            } else {
                $space = mb_strlen($this->re("/^( *{$this->preg_implode($this->t('Passengers'))}[ ]+)/", $travellersText));
                $travellersText = preg_replace("/^( *{$this->preg_implode($this->t('Passengers'))}[ ]+)/", str_pad('', $space), $travellersText);

                if (preg_match_all("/^[ ]{{$space}}{$this->opt($namePrefixes)}\s*\.*\s*({$this->patterns['travellerName']})$/mu", $travellersText, $passengerMatches)
                    || preg_match_all("/^\s*({$this->patterns['travellerName']})\s+\-\s+(?:seat\s*(?<seat>\d+\-\S+)|aisle seating)[ ]{10,}/mu", $travellersText, $passengerMatches)
                    || preg_match_all("/^\s*({$this->patterns['travellerName']})[ ]{10,}/mu", $travellersText, $passengerMatches)) {
                    if (isset($passengerMatches['seat'])) {
                        $s->extra()
                            ->seats($passengerMatches['seat']);
                    }

                    $travellersAdd = array_diff($passengerMatches[1], array_column($f->getTravellers(), 0));

                    if (!empty($travellersAdd)) {
                        $f->general()->travellers(array_unique($travellersAdd));
                    }
                }
                $this->logger->debug('Passengers type: 2');
            }
        }
    }

    private function parseAirPdf2($text, Email $email): void
    {
        $this->logger->debug(__METHOD__);

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking status:'))}]/following::text()[normalize-space()][1]", null, true, '/^[\w\s]{4,}$/u');

        $tickets = [];

        if (preg_match_all("/(?:^[ ]*|[ ]{2}){$this->preg_implode($this->t('E-ticket number'))}[: ]+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2})(?:[ ]{2}|$)/imu", $text, $eTicketMatches)) {
            $tickets = array_unique(str_replace(' ', '', $eTicketMatches[1]));
        }

        $foundIt = false;

        foreach ($email->getItineraries() as $it) {
            /** @var Flight $it */
            if ($it->getType() === 'flight') {
                $itTicket = array_column($it->getTicketNumbers(), 0);

                if (strncasecmp($tickets[0] ?? '', $itTicket[0] ?? '', 3) === 0) {
                    $f = $it;
                    $foundIt = true;
                    $ticketAdd = array_diff($tickets, $itTicket);

                    if (!empty($ticketAdd)) {
                        $f->issued()->tickets($ticketAdd, false);
                    }
                }
            }
        }

        if ($foundIt == false) {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();

            if (!empty($tickets)) {
                $f->issued()->tickets($tickets, false);
            }

            if (!empty($status)) {
                $f->general()
                    ->status($status);
            }
        }

        $textParts = array_values(array_filter(preg_split("/^[ ]*{$this->preg_implode($this->t('Passengers'))}$/um", $text)));

        if (count($textParts) > 0 && !empty($passengersText = $textParts[count($textParts) - 1])
            && preg_match_all("/^\s*[ ]*({$this->patterns['travellerName']})[ ]+{$this->preg_implode($this->t('Carrier reservation number (PNR)'))}.*$/imu", $passengersText, $passengerMatches)
        ) {
            $travellersAdd = array_diff(array_unique($passengerMatches[1]), array_column($f->getTravellers(), 0));

            if (!empty($travellersAdd)) {
                $f->general()->travellers(preg_replace("/^(?:Mr\.\s*|Ms\.|Mrs\.)/", "", $travellersAdd));
            }
        }

        /*
                      16:50          LHR London, United Kingdom
            Mon, 23 Nov 2020         Heathrow
                                                                                           Carrier: Croatia Airlines
                         20:05       ZAG Zagreb, Croatia                                         Flight no: OU491
                                     Zagreb                                                      Duration: 2h 15m
        */
        $patternSegment =
            "([ ]*{$this->patterns['time']}[ ]+\S.{2,}"
            . "(?:\n.*){1,3}"
            . "\n[ ]*{$this->patterns['time']}[ ]+\S.{2,}"
            . "(?:\n.*){1,4})"
        ;

        $segments = $this->splitter('/\n' . $patternSegment . '/u', $text);

        foreach ($segments as $sText) {
            if (preg_match("/Carrier\: Flixbus/", $sText)) {
                $email->removeItinerary($f);
            }

            $sTablePos = [0];
            $td2Positions = [];

            $tableText = $this->re("/" . $patternSegment . "\n\n/u", $sText);

            if (preg_match("/^(.+[ ]{2}){$this->preg_implode($this->t('Airline:'))}/m", $tableText, $m)) {
                $td2Positions[] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+[ ]{2}){$this->preg_implode($this->t('Flight no:'))}/m", $tableText, $m)) {
                $td2Positions[] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+[ ]{2}){$this->preg_implode($this->t('Operated by'))}[ ]*:/m", $tableText, $m)) {
                $td2Positions[] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+[ ]{2}){$this->preg_implode($this->t('Duration:'))}/m", $tableText, $m)) {
                $td2Positions[] = mb_strlen($m[1]);
            }

            if (count($td2Positions)) {
                sort($td2Positions);
                $sTablePos[] = array_shift($td2Positions);
            }
            $sTable = $this->splitCols($tableText, $sTablePos);

            if (count($sTable) !== 2) {
                $this->logger->debug('Segment wrong in PDF!');

                return;
            }

            $s = $f->addSegment();

            if (preg_match("/^\s*(?<timeDep>{$this->patterns['time']})[ ]+(?<nameDep1>.{3,})\n[ ]*(?<dateDep>.{4,}\d{2,4}?)(?:[ ]{2,}(?<nameDep2>[\s\S]{2,}?))?\n+[ ]*(?<timeArr>{$this->patterns['time']})[ ]+(?<nameArr1>.{3,})\n[ ]*(?<dateArr>.{4,}\d{2,4}?)?(?:(?<nameArr2>[\s\S]{2,}))?$/", $sTable[0], $m)) {
                $s->departure()->date($this->normalizeDate($m['dateDep'] . ' ' . $m['timeDep']));

                if (empty($m['dateArr'])) {
                    $s->arrival()->date($this->normalizeDate($m['dateDep'] . ' ' . $m['timeArr']));
                } else {
                    $s->arrival()->date($this->normalizeDate($m['dateArr'] . ' ' . $m['timeArr']));
                }

                // Departure
                /*
                    LIS Lisbon, Portugal
                    Lisbon Portela, Terminal 1
                */
                $nameDep = preg_replace('/\s+/', ' ',
                    $m['nameDep1'] . (empty($m['nameDep2']) ? '' : ' - ' . $m['nameDep2'])
                );

                // LIS Lisbon, Portugal - Lisbon Portela, Terminal 1
                if (preg_match("/^.*(?<code>[A-Z]{3})[ ]+(?<name>.{3,})$/", $nameDep, $mat)) {
                    if (preg_match("/^(.{3,}?)[, ]+{$this->preg_implode($this->t('Terminal'))}[ ]+(.+)$/i", $mat['name'], $matches)) {
                        $mat['name'] = $matches[1];
                        $s->departure()->terminal($matches[2]);
                    }

                    $s->departure()
                        ->name($mat['name'])
                        ->code($mat['code'])
                    ;
                }

                // Arrival
                $nameArr = preg_replace('/\s+/', ' ',
                    $m['nameArr1'] . (empty($m['nameArr2']) ? '' : ' - ' . $m['nameArr2'])
                );

                if (preg_match("/^.*(?<code>[A-Z]{3})[ ]+(?<name>.{3,})$/", $nameArr, $mat)) {
                    if (preg_match("/^(.{3,}?)[, ]+{$this->preg_implode($this->t('Terminal'))}[ ]+(.+)$/i", $mat['name'], $matches)) {
                        $mat['name'] = $matches[1];
                        $s->arrival()->terminal($matches[2]);
                    }

                    $s->arrival()
                        ->name($mat['name'])
                        ->code($mat['code'])
                    ;
                }
            }

            // Airline
            if (preg_match("/^[ ]*{$this->preg_implode($this->t('Flight no:'))}\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]|[A-Z]{3})?\s*(?<number>\d+)$/m", $sTable[1], $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            if ((preg_match("/ +{$this->preg_implode($this->t('Carrier reservation number (PNR)'))}[: ]+(?<pnr>[A-Z\d]{5,})$/imu", $sText, $m)
                || preg_match("/ +{$this->preg_implode($this->t('Carrier reservation number (PNR)'))}(?: *:)?\n.{30,} {5,}(?<pnr>[A-Z\d]{5,7})$/imu", $sText, $m))
                && (strlen($m['pnr']) < 11 || preg_match('/[A-z]/', $m['pnr']) > 0)
            ) {
                $s->airline()->confirmation($m['pnr']);
            }

            // Extra
            if (preg_match("/^[ ]*{$this->preg_implode($this->t('Duration:'))}\s*(\d[\s\S]*)/m", $sTable[1], $m)) {
                $s->extra()->duration(preg_replace('/\s+/', ' ', $m[1]));
            }
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function normalizeDate($str)
    {
        $this->logger->debug($str);

        $in = [
            // 2017 Dec 4 10:25
            '/^\s*(\d{4}) ([^,.\s\d]+)[,.]* (\d+) (\d{1,2}:\d{2})\s*$/u',
            // sáb, abr 14, 2018 02:20    |    søn. jan 20, 2019 10:40PM    |    tis okt. 2, 2018 09:15
            '/^\s*[^\d\W]{2,}[,.\s]+([^\d\W]{3,})[.\s]+(\d{1,2})\s*,\s*(\d{4})\s*(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*$/u',
            // 12:00 Friday, May 3, 2019
            '/^\s*(\d{1,2}:\d{2})\s*[^,.\s\d]+,\s+([^,.\s\d]+)[.]?\s+(\d{1,2})[,\s]+(\d{4})$/u',
            // 17:05 Wed, 2 Dec 2020; 10:00 вс, 27 дек. 2020; 11:05 niedz., 20 gru 2020
            '/^(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s+[-[:alpha:]]+[.,\s]+(\d{1,2})\s+([[:alpha:]]{3,})[.]?\s+(\d{4})$/u',
            // пт, 18 дек. 2020 10:50
            '/^[^\d\W]{2,}[,.\s]+(\d{1,2})\s+([^\d\W.,]{3,})[.\s]+(\d{4})\s*(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*$/u',
            //15:00ter, 30 nov 2021
            '/^([\d\:]+)\w+\,\s*(\d+)\s*(\w+)\s*(\d{4})$/u',

            // no year
            // set 10 10:00
            '/^\s*([^,.\s\d]+)[,.]* (\d+) (\d{1,2}:\d{2})\s*$/u',
        ];
        $out = [
            '$3 $2 $1 $4',
            '$2 $1 $3, $4',
            '$3 $2 $4, $1',
            '$2 $3 $4, $1',
            '$1 $2 $3, $4',
            '$2 $3 $4, $1',

            // no year
            "$2 $1 %Y%, $3",
        ];
        $str = preg_replace($in, $out, $str);

//        $this->logger->debug('Date = '.print_r( $str,true));

        if (preg_match('/\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)/', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else { // it-10273765.eml
                $remainingLangs = array_diff(array_keys(self::$dict), [$this->lang]);

                foreach ($remainingLangs as $lang) {
                    if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }

//        $this->logger->debug('Date = '.print_r( $str,true));

        if (preg_match('/(%Y%)/', $str)) {
            $year = date('Y', $this->date);

            if (empty($year)) {
                return null;
            }

            return strtotime(str_replace('%Y%', $year, $str), $this->date);
        } elseif (preg_match('/\b(\d{4})\b/', $str)) {
            return strtotime($str);
        }

        return null;
    }

    private function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        } elseif ($allMatches) {
            return [];
        }
    }

    private function normalizeText($string): string
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function strCut($input, $searchStart, $searchFinish = null, $deleteSearchStart = true)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        if ($deleteSearchStart == true) {
            $inputResult = mb_substr($inputResult, mb_strlen($searchStart));
        }

        return $inputResult;
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter(string $pattern, ?string $text): array
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    /**
     * Example:
     * <pre>1,234,567.89
     * 1.234.567,89
     * 1 234 567,89</pre>.
     *
     * @version 1.0.0-beta
     *
     * @param mixed $string
     *
     * @return mixed
     */
    private function cost($string)
    {
        // Clean cost
        $costClean = str_replace(',', '.', preg_replace('/[^\d.,]+/', '', $string));
        // The position of the last point
        $costPos = strripos($costClean, '.');
        // Cut the text to the last point
        $leftVal = str_replace('.', '', substr($costClean, 0, $costPos));
        // We paste back the cleared text into the place of the old one
        $cost = substr_replace($costClean, $leftVal, 0, $costPos);

        if (is_numeric($cost)) {
            return (float) $cost;
        }
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
