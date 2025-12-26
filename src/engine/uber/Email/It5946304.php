<?php

namespace AwardWallet\Engine\uber\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It5946304 extends \TAccountChecker
{
    public $mailFiles = "uber/it-10016654.eml, uber/it-10033110.eml, uber/it-10033297.eml, uber/it-10096692.eml, uber/it-10460136.eml, uber/it-12846904.eml, uber/it-12850571.eml, uber/it-12941352.eml, uber/it-12947088.eml, uber/it-12958130.eml, uber/it-139705269.eml, uber/it-27004892.eml, uber/it-27027628.eml, uber/it-27034553.eml, uber/it-27076446.eml, uber/it-27219121.eml, uber/it-28434989.eml, uber/it-28522835.eml, uber/it-28535736.eml, uber/it-28591676.eml, uber/it-28742737.eml, uber/it-28760701.eml, uber/it-28927710.eml, uber/it-29763265.eml, uber/it-328968576.eml, uber/it-328985555.eml, uber/it-33005758.eml, uber/it-33596952.eml, uber/it-33717603.eml, uber/it-33964401.eml, uber/it-34147644.eml, uber/it-348375553.eml, uber/it-348390328.eml, uber/it-5200781.eml, uber/it-5201085.eml, uber/it-58834846.eml, uber/it-5946178.eml, uber/it-6084323.eml, uber/it-6127567.eml, uber/it-6167326.eml, uber/it-6396583.eml, uber/it-6645243.eml, uber/it-8626693.eml, uber/it-8688196.eml, uber/it-8718238.eml, uber/it-8734460.eml, uber/it-8808462.eml";

    public static $dictionary = [
        'it' => [
            "You rode with"             => "Hai viaggiato con",
            "Car"                       => "Auto",
            "CHARGED"                   => ["ADDEBITO"],
            "Thanks for choosing Uber," => ["Grazie per aver scelto Uber,", "Grazie per la corsa"],
            //            "anotherNameRegexp" => ["##"],
            //            'for your ride on' => '',
            'miles'     => ['miglia'],
            'Trip time' => ['Durata della corsa'],
            'Subtotal'  => 'Subtotale',
            'New Total' => 'Prezzo della corsa',
            //			'not Fee' => [''],
            "Download PDF" => ["Scarica PDF"], //t2
            "Total"        => "Totale", //t2
            // 'You earned' => '',
            // 'points' => '',
            //            "Refund" => "", //t2
            //            "h" => "", // duration->hour
            //            "min" => "",// duration->minute
        ],
        'es' => [
            "You rode with"             => "Viajaste con",
            "Car"                       => ["Auto", "Vehículo"],
            "CHARGED"                   => ["COBRADO"],
            "Thanks for choosing Uber," => ["Gracias por elegir Uber,", "Solo una rápida y última novedad,", "Gracias por viajar con nosotros,", "Estaremos en contacto,", "Gracias por usar Uber,", "Gracias por dar un extra,"],
            //            "anotherNameRegexp" => ["##"],
            'for your ride on' => 'tu viaje del',
            'miles'            => ['kilómetros', 'millas'],
            'Trip time'        => ['Tiempo del viaje'],
            'Subtotal'         => 'Subtotal',
            'New Total'        => 'Total actualizado',
            //			'not Fee' => [''],
            "Download PDF" => ["Descargar PDF", "Descarga el PDF"], //t2
            "Total"        => "Total", //t2
            'You earned'   => 'Has conseguido',
            'points'       => 'puntos',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "min", // duration->minute
        ],
        'pt' => [
            "You rode with"             => ["Você viajou com", "VocÃª viajou com", "Viajou com", "You rode with"],
            "Car"                       => "Carro",
            "CHARGED"                   => ["COBRADO", "Valor cobrado"],
            "Thanks for choosing Uber," => ["Obrigado por escolher a Uber,", "Apenas uma atualização rápida,", "Obrigado por viajar,", "Obrigado por dar um valor extra,",
                'Thanks for riding,', ],
            //            "anotherNameRegexp" => ["##"],
            'for your ride on' => 'de sua viagem de',
            'miles'            => ['QuilÃ´metros', 'Quilômetros', 'milhas'],
            'Trip time'        => ['Duração', 'DuraÃ§Ã£o'],
            'Subtotal'         => 'Subtotal',
            'New Total'        => 'Novo total',
            'not Fee'          => ['Antes de incluir os impostos'],
            "Download PDF"     => ["Baixar o PDF", "Fazer o download do PDF", 'Download PDF'],  //t2
            "Total"            => "Total", //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "min", // duration->minute
        ],
        'nl' => [
            "You rode with"             => "Je reed met",
            "Car"                       => "Auto",
            "CHARGED"                   => "IN REKENING GEBRACHT",
            "Thanks for choosing Uber," => ["Bedankt dat je voor Uber hebt gekozen,", "Je hebt voor Uber gekozen,"],
            //            "anotherNameRegexp" => ["##"],
            'miles'     => 'kilometers',
            'Trip time' => 'Tijdsduur rit',
            'Subtotal'  => 'Subtotaal',
            //			'New Total' => '',
            //			'not Fee' => [''],
            "Download PDF" => "PDF downloaden", //t2
            "Total"        => "Totaal", //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "min.", // duration->minute
        ],
        'fr' => [
            "You rode with"             => ["Votre chauffeur était", "Votre chauffeur Ã©tait"],
            "Car"                       => "Voiture",
            "CHARGED"                   => ["TOTAL RÉGLÉ", "TOTAL RÃ‰GLÃ‰"],
            "Thanks for choosing Uber," => ["Merci d'avoir choisi Uber,", ", merci d'avoir utilisé Uber", "Merci d'utiliser Uber,"],
            //            "anotherNameRegexp" => ["##"],
            'miles'     => ['kilomÃ¨tres', 'kilomètres', 'miles'],
            'Trip time' => ['DurÃ©e de la course', 'Durée de la course'],
            'Subtotal'  => 'Sous-total',
            'New Total' => 'Nouveau total',
            //			'not Fee' => [''],
            "Download PDF" => ["Téléchargez le PDF", "Télécharger le PDF"], //t2
            "Total"        => "Total", //t2
            // 'You earned' => '',
            // 'points' => '',
            "Refund"       => "Remboursement", //t2
            //            "h" => "", // duration->hour
            //            "min" => "",// duration->minute
        ],
        'de' => [
            "You rode with"             => ["Du bist mit", "Du wurdest von"],
            "Car"                       => "Auto",
            "CHARGED"                   => ["BERECHNET"],
            "Thanks for choosing Uber," => ["Danke für Dein Vertrauen in Uber,", "Danke, dass du die Uber App nutzt,"],
            //            "anotherNameRegexp" => ["##"],
            'miles'     => ['Meilen', 'Kilometer'],
            'Trip time' => 'Fahrzeit',
            'Subtotal'  => 'Zwischensumme',
            //			'New Total' => '',
            'not Fee'      => ['Insgesamt'],
            "Download PDF" => "PDF herunterladen", //t2
            "Total"        => ["Gesamtfahrpreis", 'Gesamtsumme'], //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            //            "min" => "",// duration->minute
        ],
        'en' => [
            "You rode with"             => ["You rode with", "You were driven by", 'You rented with '],
            "Car"                       => ["Car", "Vehicle", "Switching Option"],
            "CHARGED"                   => ["CHARGED", "collected", "Total", "Amount Charged"],
            "Thanks for choosing Uber," => ["Thanks for choosing Uber,", "Just a quick update,", "Thanks for tipping,", "Thanks for riding,", "Just a quick update,", "Thanks for giving an extra,",
                'We\'ll connect another time,', 'Here\'s your receipt', 'Here\'s your receipt,', ],
            //            "anotherNameRegexp" => ["##"],
            'miles' => ['kilometres', 'kilometers', 'miles'],
            //			'Trip time' => '',
            //			'Subtotal' => '',
            //			'New Total' => '',
            'not Fee' => ['Before Taxes', 'Total'],
            //			"Download PDF" => "", //t2
            "Total" => ["Total", "Adjusted Total"], //t2
            // 'You earned' => '',
            // 'points' => '',
            "Refund" => ["Refund", "Cancellation fee", "Cancellation Fee"], //t2
            //            "h" => "", // duration->hour
            //            "min" => "",// duration->minute
            'cancelledText'               => ['the receipt for your canceled trip', 'Trip cancelled', 'Ride cancelled'], // t2 cancelled to junk
            'notYourTrip'                 => ['delivered your package.', 'Delivered by'], // t2 to junk
            'emptyLocationTextItToJunk'   => ['We adjusted the total for your recent ride'], // no dates and locations to junk
        ],
        'sv' => [
            "You rode with" => ["Du reste med"],
            //			"Car" => [""],
            //			"CHARGED" => [""],
            "Thanks for choosing Uber," => ["Tack för att du reser,", "Vi ses en annan gång,"],
            //            "anotherNameRegexp" => ["##"],
            //			'for your ride on' => '',
            //			'miles' => [''],
            //			'Trip time' => [''],
            'Subtotal' => 'Subtotaal',
            //			'New Total' => '',
            //			'not Fee' => [''],
            "Download PDF" => ["Ladda ned PDF"], //t2
            "Total"        => ["Totalt", 'Avbokningsavgift'], //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            //            "min" => "",// duration->minute
            'cancelledText' => ['Här har du kvittot för din avbokade resa', 'Resan har avbokats'], // t2 cancelled to junk
            //            'notYourTrip'   => [''], // t2 to junk
            //            'emptyLocationTextItToJunk'   => [''], // no dates and locations to junk
        ],
        'ru' => [
            "You rode with" => "Ваш водитель:",
            //			"Car" => [""],
            //			"CHARGED" => [""],
            "Thanks for choosing Uber," => ["Благодарим за поездку,"],
            //            "anotherNameRegexp" => ["##"],
            //			'for your ride on' => '',
            //			'miles' => [''],
            //			'Trip time' => [''],
            'Subtotal' => 'Промежуточный итог',
            //			'New Total' => '',
            //			'not Fee' => [''],
            "Download PDF" => ["Скачать PDF"], //t2
            "Total"        => "Итого", //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "мин.", // duration->minute
        ],
        'vi' => [
            "You rode with" => "Bạn đi xe với",
            //			"Car" => [""],
            //			"CHARGED" => [""],
            //			"Thanks for choosing Uber," => [""],
            "anotherNameRegexp" => ["#Cảm ơn (.+) đã đi xe#"],
            //			'for your ride on' => '',
            //			'miles' => [''],
            //			'Trip time' => [''],
            'Subtotal' => 'Tổng cước',
            //			'New Total' => '',
            //			'not Fee' => [''],
            "Download PDF" => ["Tải xuống bản PDF"], //t2
            "Total"        => "Tổng", //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "phút", // duration->minute
        ],
        'pl' => [
            "You rode with" => "Twoim kierowcą był(a)",
            //			"Car" => [""],
            //			"CHARGED" => [""],
            "Thanks for choosing Uber," => ["Dziękujemy za przejazd,"],
            //			"anotherNameRegexp" => ["##"],
            //			'for your ride on' => '',
            //			'miles' => [''],
            //			'Trip time' => [''],
            'Subtotal' => 'Podsumowanie',
            //			'New Total' => '',
            //			'not Fee' => [''],
            "Download PDF" => ["Pobierz plik PDF"], //t2
            "Total"        => ["Razem", 'Suma'], //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            "h"   => "godz.", // duration->hour
            "min" => "min", // duration->minute
        ],
        'tr' => [
            "You rode with" => "ile yolculuk yaptın",
            //			"Car" => [""],
            //			"CHARGED" => [""],
            "Thanks for choosing Uber," => ["Yolculuk yaptığınız için teşekkürler,"],
            //			"anotherNameRegexp" => ["##"],
            //			'for your ride on' => '',
            //			'miles' => [''],
            //			'Trip time' => [''],
            'Subtotal' => 'Alt Toplam',
            //			'New Total' => '',
            //			'not Fee' => [''],
            "Download PDF" => ["PDF’yi indirin"], //t2
            "Total"        => ["Toplam", 'İptal Ücreti'], //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min"           => "dk.", // duration->minute
            'cancelledText' => ['İptal ettiğiniz yolculuğun makbuzu burada', 'Yolculuk iptal edildi'], // t2 cancelled to junk
            //            'notYourTrip'   => [''], // t2 to junk
            //            'emptyLocationTextItToJunk'   => [''], // no dates and locations to junk
        ],
        'ko' => [ // check this lang
            //			"You rode with" => "",
            //			"Car" => [""],
            //			"CHARGED" => [""],
            //			"Thanks for choosing Uber," => [""],
            "anotherNameRegexp" => ["#([a-z\s]+)\s*\S+, 차량 서비스를 #"],
            //			'for your ride on' => '',
            //			'miles' => [''],
            //			'Trip time' => [''],
            //			'Subtotal' => '',
            //			'New Total' => '',
            //			'not Fee' => [''],
            //			"Download PDF" => [""], //t2
            "Total" => "합계", //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "분", // duration->minute
        ],
        'no' => [
            "You rode with" => ["Du satt på med"],
            //            "Car" => [""],
            //            "CHARGED" => [""],
            "Thanks for choosing Uber," => ["Takk for reisen,"],
            //            "anotherNameRegexp" => ["##"],
            'miles' => ['km'],
            //			'Trip time' => '',
            'Subtotal' => 'Delsum',
            //			'New Total' => '',
            //            'not Fee' => [''],
            //			"Download PDF" => "", //t2
            "Total" => "Sum", //t2
            // 'You earned' => '',
            // 'points' => '',
            //            "Refund" => "", //t2
            //            "h" => "", // duration->hour
            //            "min" => "",// duration->minute
        ],
        'zh' => [
            "You rode with" => ["的车辆"],
            //            "Car" => [""],
            //            "CHARGED" => [""],
            "Thanks for choosing Uber," => ["感谢您搭乘优步！"],
            //            "anotherNameRegexp" => ["##"],
            'miles' => ['英里'],
            //			'Trip time' => '',
            'Subtotal' => '小计',
            //			'New Total' => '',
            //            'not Fee' => [''],
            //			"Download PDF" => "", //t2
            "Total" => "总计", //t2
            // 'You earned' => '',
            // 'points' => '',
            //            "Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "分钟", // duration->minute
        ],
        'ja' => [
            "You rode with" => ["ドライバー名"],
            //            "Car" => [""],
            //            "CHARGED" => [""],
            "Thanks for choosing Uber," => ["、ご乗車いただきありがとうございました", "、謝礼を追加していただきありがとうございます"],
            //            "anotherNameRegexp" => ["##"],
            'miles' => ['マイル'],
            //			'Trip time' => '',
            'Subtotal' => '小計',
            //			'New Total' => '',
            //            'not Fee' => [''],
            //			"Download PDF" => "", //t2
            "Total" => "合計", //t2
            // 'You earned' => '',
            // 'points' => '',
            //            "Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "分", // duration->minute
        ],
        'da' => [
            "You rode with"             => ["Du kørte med"],
            //            "Car"                       => ["Car", "Vehicle", "Switching Option"],
            //            "CHARGED"                   => ["CHARGED", "collected", "Total", "Amount Charged"],
            "Thanks for choosing Uber," => ["Tak, fordi du kører med os,"],
            //            "anotherNameRegexp" => ["##"],
            'miles' => ['kilometer'],
            //			'Trip time' => '',
            'Subtotal' => 'Subtotal',
            //			'New Total' => '',
            //            'not Fee' => ['Before Taxes', 'Total'],
            //			"Download PDF" => "", //t2
            "Total" => ["I alt"], //t2
            // 'You earned' => '',
            // 'points' => '',
            //			"Refund" => "", //t2
            //            "h" => "", // duration->hour
            "min" => "min", // duration->minute
        ],
    ];

    private $reFrom = '@uber.com';

    private $reSubject = [
        'es' => 'con Uber',
        'pt' => 'com a Uber',
        'nl' => 'rit met Uber',
        'de' => 'mit Uber',
        'fr' => 'en Uber',
        'en' => 'trip with Uber',
        'sv' => 'med Uber',
        'it' => 'La tua corsa Uber il',
        'ru' => 'Ваша поездка с Uber',
        'vi' => 'với Uber',
        'pl' => 'przejazd z Uber',
        'tr' => 'Uber ile',
        'no' => 'med Uber',
        //        'zh' => '',
        'ja' => 'Uberご乗車の領収書',
        //        'ko' => '',
        'da' => 'med Uber',
    ];

    private $lang = '';

    private $langDetectors = [
        'es' => ['Viajaste con', 'Viaje'],
        // pt before en
        'pt' => ['Você viajou com', 'VocÃª viajou com', 'Viajou com', 'Uber do Brasil', 'Preço da viagem'],
        'nl' => ['Je reed met', 'Je hebt voor Uber gekozen'],
        'fr' => ['Votre chauffeur était', 'Votre chauffeur Ã©tait', "merci d'avoir utilisé Uber"],
        'de' => ['Du bist mit', 'Du wurdest von'],
        'en' => ['You rode with', 'You were driven by', 'Thanks for choosing Uber', 'Just a quick update,', 'We hope you enjoyed your ride this',
            'Here\'s your receipt', 'We\'ll connect another time', 'Thanks for tipping,', ],
        'sv' => ['Du reste med', 'Ladda ned PDF'],
        'it' => ['Grazie per aver scelto Uber', 'Grazie per la corsa'],
        'ru' => ['Благодарим за поездку', 'спасибо, что выбираете'],
        'vi' => ['đã đi xe'],
        'pl' => ['Pobierz plik PDF', 'Jak udał się Twój przejazd?'],
        'tr' => ['Yolculuk yaptığınız için teşekkürler', 'Bahşiş verdiğiniz için teşekkürler', 'Duygu seninle tekrar iletişim kuracağız'],
        'ko' => ['이용해 주셔서 감사합니다'],
        'no' => ['Takk for reisen', 'Her er den oppdaterte reiseoversikten for'],
        'zh' => ['感谢您搭乘优步！'],
        'ja' => ['ドライバー名'],
        'da' => ['Tak, fordi du kører med os,'],
    ];

    private $subj;
    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subj = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));

        if ($this->assignLang() === false) {
            if (!$this->detectEmailByBody($parser)) {
                $this->logger->debug("Can't determine body. Wrong format!");

                return $email;
            }
            $this->assignLang();
        }

        if ($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Download PDF")) . "])[1]")
        || $this->http->FindSingleNode("(//td/img[contains(@src,'receipt_18_rider_')]/@src)[1]")) {
            // big painted car at the beginning of mail. Section order: total, driver, dates and locations
            $this->transfer_t2($email);
            $type = '2';
        } else {
            // big map at the beginning of mail. Section order: dates and locations, driver, total
            $this->transfer_t1($email);
            $type = '1';

            foreach ($email->getItineraries() as $it) {
                /** @var \AwardWallet\Schema\Parser\Common\Transfer $it */
                foreach ($it->getSegments() as $s) {
                    if (empty($s->getDepAddress()) && empty($s->getDepDate()) && empty($s->getArrDate()) && empty($s->getArrAddress())) {
                        $this->logger->debug("try type 2, but it didn't detect");
                        $this->transfer_t2($email);
                        $type = '2';
                        $email->removeItinerary($it);

                        break 2;
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || (isset($headers['from']) && false === stripos($headers['from'], $this->reFrom))) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (isset($headers['subject']) && stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body) || mb_strlen($body) < 6000) {
            $this->logger->notice('attached body');
            $htmls = $this->getHtmlAttachments($parser);

            foreach ($htmls as $body) {
                $this->http->SetEmailBody($body);
            }
        } elseif ($this->assignLang() === false) {
            // it-8734460.eml
            $this->logger->notice('change body');
            $this->changeBody($parser);
        }

        if (strpos($body, 'Uber') !== false && $this->assignLang() === true) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    // big map at the beginning of mail. Section order: dates and locations, driver, total
    private function transfer_t1(Email $email): void
    {
        $this->logger->debug(__METHOD__);
        $t = $email->add()->transfer();
        $t->general()
            ->noConfirmation();

        $traveller = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Thanks for choosing Uber,")) . "])[1]", null, true, "#" . $this->opt($this->t("Thanks for choosing Uber,")) . "\s*([\w \-]+)\b#us");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Thanks for choosing Uber,")) . "])[1]", null, true, "#([\w \-]+)\b" . $this->opt($this->t("Thanks for choosing Uber,")) . "#su");
        }

        if (strlen(trim($traveller)) > 1) {
            $t->general()
                ->traveller($traveller, false);
        }
        $s = $t->addSegment();

        if (!$date = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("You rode with")) . "])[1]/preceding::table[contains(., '|')][1]/preceding::td[normalize-space(.) and contains(., '|')][1]", null, true, "#(.*?)\s+\|#")) {
            $date = $this->re("#{$this->opt($this->t('for your ride on'))} (.+)#", $this->subj);
        }
        $date = strtotime($this->normalizeDate($date));

        if (empty($date)) {
            $this->logger->debug("date not detect");

            return;
        }

        $node = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("You rode with")) . "])[1]/preceding::table[contains(., '|')][1]//tr[2]/../tr[1]");

        if (preg_match("#^([^\|]{4,10})\|\s*(.+)#", $node, $m)) {
            $s->departure()
                ->address(trim($m[2]))
                ->date(strtotime($m[1], $date));
        }

        $node = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("You rode with")) . "])[1]/preceding::table[contains(., '|')][1]//tr[2]");

        if (preg_match("#^([^\|]{4,10})\|\s*(.+)#", $node, $m)) {
            $s->arrival()
                ->address(trim($m[2]))
                ->date(strtotime($m[1], $date));
        } elseif (preg_match("#^\s*\|\s*(.+)#", $node, $m)) {
            $s->arrival()
                ->address(trim($m[1]))
                ->noDate();
        }

        $s->extra()
            ->type($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Car")) . "])[1]/preceding::text()[normalize-space(.)][1]"))
            ->duration($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Trip time")) . "])[1]/preceding::text()[normalize-space(.)][1]", null, true, "#^[\s\d:\.]+$#"));

        $miles = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("miles")) . "])[1]/preceding::text()[normalize-space(.)][1]", null, true, "#^[\s\d\.\,]+$#");

        if (!empty($miles)) {
            $s->extra()->miles($miles . ' ' . $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("miles")) . "])[1]"));
        }

        // TotalCharge
        $totals = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("CHARGED")) . "]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($totals)) {
            $amount = 0;

            foreach ($totals as $value) {
                $amount += $this->amount($value);
            }
            $t->price()->total($amount);
        } else {
            $total = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("New Total")) . "])[1]/ancestor::td[1]/following-sibling::td[1]");

            if (empty($total)) {
                $total = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Thanks for choosing Uber,")) . "])[1]/preceding::table[1]", null, true, "#^\s*([^\d]{1,5}\s*\d[\d\.\,]+|\d[\d\.\,]+\s*[^\d]{1,5})\s*$#");
            }
            $t->price()->total($this->amount($total));
        }

        // Currency
        $location = $s->getDepAddress();
        $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("CHARGED")) . " or " . $this->eq($this->t("New Total")) . "])[1]/ancestor::td[1]/following-sibling::td[1]"), $location);

        if (empty($currency)) {
            $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Thanks for choosing Uber,")) . "])[1]/preceding::table[1]", null, true, "#^\s*([^\d]{1,5}\s*\d[\d\.\,]+|\d[\d\.\,]+\s*[^\d]{1,5})\s*$#"), $location);
        }

        if (!empty($currency)) {
            $t->price()->currency($currency);
        }

        // Fees
        $xpath = "(//text()[" . $this->eq($this->t("Subtotal")) . "])[1]/ancestor::table[1]/following-sibling::table//tr[not(.//tr) and count(td["
                        . "normalize-space()])=2 and not(" . $this->eq($this->t("CHARGED"), 'normalize-space(.//text())') . ") and not(" . $this->eq($this->t("not Fee"), 'normalize-space(.//text())') . ")]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $name = trim($this->http->FindSingleNode("./td[normalize-space()][1]", $root, true, "#(.+?)(\(\?\))?\s*$#"));
            $amount = $this->http->FindSingleNode("./td[normalize-space()][2]", $root);

            if (!empty($name) && !empty($amount)) {
                if (preg_match("#^[\d\s\.\,]+$#", $amount)) {
                    $t->price()->fee($name, $this->amount($amount));

                    continue;
                }

                if (preg_match("#^\s*\-(.*\d.*)$#", $amount)) {
                    $amount = trim($amount, '-');
                    $currency = $this->currency($amount);

                    if (empty($currency) || ($currency == $t->obtainPrice()->getCurrencyCode())) {
                        if (empty($t->obtainPrice()->getDiscount())) {
                            $t->price()->discount($this->amount($amount));
                        } else {
                            $t->price()->discount($this->amount($amount) + $t->obtainPrice()->getDiscount());
                        }
                    }

                    continue;
                }

                $currency = $this->currency($amount);

                if (!empty($currency) && ($currency == $t->obtainPrice()->getCurrencyCode())) {
                    $t->price()->fee($name, $this->amount($amount));
                }
            }
        }
        $tip = $this->amount($this->http->FindSingleNode("(//text()[(" . $this->eq($this->t("Tip")) . ") and (preceding::td[" . $this->eq($this->t("CHARGED")) . "])])[1]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tip)) {
            $t->price()->fee("Tip", $tip);
        }

        // BaseFare
        $cost = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Subtotal")) . "])[1]/ancestor::td[1]/following-sibling::td[1]");

        if (!empty($cost)) {
            $currency = $this->currency($cost);

            if (empty($currency) || ($currency == $t->obtainPrice()->getCurrencyCode())) {
                $t->price()->cost($this->amount($cost));
            }
        }

        if (empty($s->getDepAddress()) || empty($s->getArrAddress()) || empty($s->getDepDate())) {
            return;
        }
    }

    // big painted car at the beginning of mail. Section order: total, driver, dates and locations
    private function transfer_t2(Email $email): void
    {
        $this->logger->debug(__METHOD__);
        $t = $email->add()->transfer();
        $t->general()
            ->noConfirmation();

        $display = "not(ancestor::*/@style[{$this->contains(['display:none', 'display: none'])}])";
        $traveller = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Thanks for choosing Uber,")) . "][{$display}])[1]", null, true, "#" . $this->opt($this->t("Thanks for choosing Uber,")) . "\s*([\w \-\.]{2,})\b#us");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Thanks for choosing Uber,")) . "][{$display}])[1]", null, true, "#([\w \-]{2,})\b[, ，]*" . $this->opt($this->t("Thanks for choosing Uber,")) . "#su");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("(//img[" . $this->contains($this->t("Thanks for choosing Uber,"), '@alt') . "])[1]/@alt", null, true, "#" . $this->opt($this->t("Thanks for choosing Uber,")) . " ([a-z\-]{2,15})[^\w]+#isu");
        }

        if (empty($traveller) && is_array($this->t("anotherNameRegexp"))) {
            foreach ($this->t("anotherNameRegexp") as $regexp) {
                if (preg_match("#^([^\w\s])(.+)\\1$#", $regexp, $m)) { // string is regexp
                    $traveller = $this->http->FindPreg($regexp . "u"); // add modifiers

                    if (!empty($traveller)) {
                        break;
                    }
                }
            }
        }

        if (strlen(trim($traveller)) > 1) {
            $t->general()
                ->traveller($traveller, false);
        }

        $s = $t->addSegment();

        $date = $this->normalizeDate($this->http->FindSingleNode("//tr[count(*)=2 and *[1]/descendant::img]/*[2]/descendant::div[{$this->starts($this->t("Total"))} or {$this->starts($this->t("Refund"))}]/following-sibling::node()[normalize-space()]", null, true, "/(?:{$this->opt($this->t('Date of the trip'))}[:\s]+)?(.{6,})$/i"));

        if (empty($date)) {
            $dateArray = array_unique(array_filter($this->http->FindNodes("//tr[count(*)=2 and *[1]/descendant::img]/*[2]/descendant::div[{$this->starts($this->t("Total"))} or {$this->starts($this->t("Refund"))}]/following-sibling::node()[normalize-space()]")));

            if (count($dateArray) == 1) {
                $date = $this->normalizeDate($dateArray[0]);
            }
        }
        $date = strtotime($date);

        if (empty($date)) {
            $this->logger->debug('date not detect!');

            return;
        }

        $rule = in_array($this->lang, ['tr', 'zh']) ? $this->contains($this->t("You rode with")) : $this->starts($this->t("You rode with"));
        $containsTime = "contains(translate(.,'0123456789','++++++++++'), '+:++')";

        if ($this->lang == 'fr') {
            $containsTime .= " or contains(translate(.,'0123456789','++++++++++'), '++ h ++')";
        }

        if ($this->lang == 'nl') {
            $containsTime .= " or contains(translate(.,'0123456789','++++++++++'), '++.++')";
        }
        $xpath = "/descendant::text()[$rule][1]/following::table[{$containsTime}][1]/descendant::td[ string-length(normalize-space())>1 and not(.//td[normalize-space()]) and (preceding-sibling::* or following-sibling::*) ]";
        $s->extra()
            ->type($this->http->FindSingleNode($xpath . "[1][not(contains(.,'|'))]", null, true, "#^.+$#"), false, true)
            ->miles($this->http->FindSingleNode($xpath . "[position()<3][contains(.,'|')]", null, true, "#^\s*(.+?)\s*\|.+$#"), false, true)
            ->duration($this->http->FindSingleNode($xpath . "[position()<3][contains(.,'|')]", null, true, "#^.+\|\s*(.+?)\s*$#"), false, true)
        ;

        $xpath = "//img[contains(@src,'square_top_fill_middle') or contains(@src,'square_top_fill_right')]/ancestor::td[1]/following::td[not(.//td) and normalize-space()][position()<10][{$containsTime}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "/descendant::text()[{$this->starts($this->t("You rode with"))}][1]/following::td[not(.//td) and ({$containsTime})]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/ancestor::*[1]";
            $nodes = $this->http->XPath->query($xpath);
        }
        // 08: 0 5am Ote. 173 12, Esmeralda, 07549 Ciudad de México, CDMX, México
        // 15:21 Aeroporto Santos Dumont{comma} Embarque{comma} Pista da direita - Centro, Rio de Janeiro - RJ, 20021-340, Brazil
        $patterns['timeLocation'] = '/(?<time>\d[\d ]*[:h.][ ]*\d[ ]*\d(?:\s*[AaPp][Mm])?)\s+(?<location>.{2,})/u';

        if ($nodes->length === 2 || $nodes->length === 4) {
            if (preg_match($patterns['timeLocation'], implode(" ", $this->http->FindNodes('(' . $xpath . ')[1]/descendant::text()[normalize-space()]')), $mDep)) {
                $s->departure()
                    ->date(strtotime($this->normalizeTime($mDep['time']), $date))
                    ->address(str_replace('{comma}', '', $mDep['location']));

                if ($mDep['location'] == 'Pedido aceptado') {
                    $maybeJunk = true;
                }
            }

            if (preg_match($patterns['timeLocation'], implode(" ", $this->http->FindNodes('(' . $xpath . ')[2]/descendant::text()[normalize-space()]')), $mArr)) {
                if ($s->getDuration() !== null && preg_match("/^\s*(?<duration>\d{1,3})\s*{$this->opt($this->t("min"))}/u", $s->getDuration(), $m)
                    && (int) $m['duration'] === 0
                ) {
                    // 0 min
                    $s->arrival()->noDate();
                } else {
                    $s->arrival()->date(strtotime($this->normalizeTime($mArr['time']), $date));
                }

                $s->arrival()->address($mArr['location']);

                if (!empty($s->getDepDate()) && !empty($s->getArrDate()) && $s->getDepDate() > $s->getArrDate()) {
                    $s->arrival()->date(strtotime("+1 day", $s->getArrDate()));
                }

                if ($mArr['location'] == 'Pedido cancelado') {
                    $maybeJunk = true;
                }
            }
        } elseif ($nodes->length === 1) {
            if (preg_match($patterns['timeLocation'], implode(" ", $this->http->FindNodes('(' . $xpath . ')[1]/descendant::text()[normalize-space()]')), $m)) {
                $s->departure()
                    ->address($m[2])
                    ->date(strtotime($this->normalizeTime($m[1]), $date));

                if ($m[2] == 'Pedido aceptado') {
                    $maybeJunk = true;
                }
            }

            $arr = $this->http->FindSingleNode("(//td[normalize-space()='" . $s->getDepAddress() . "'][last()]/following::img[//img[contains(@src, 'square_top_fill_right')]])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]");

            if (!preg_match("#\d{1,2}:\d{2}#", $arr)) {
                $s->arrival()->address($arr);
            }

            if (preg_match("#^\s*(?<m>\d+)\s*" . $this->opt($this->t("min")) . "(?:\s*\(\s*[Ss]\s*\))?\s*$#u", $s->getDuration(), $m)) {
                // 0 min(s)
                if ((int) $m['m'] === 0) {
                    $s->arrival()->noDate();
                } else {
                    $s->arrival()->date(strtotime('+' . $m['m'] . ' minutes', $s->getDepDate()));
                }
            } elseif (preg_match("#^\s*(?<h>\d+)\s*(?:" . $this->opt($this->t("h")) . "|\w+)\s*(?<m>\d+)\s*" . $this->opt($this->t("min")) . "(?:\s*\(\s*[Ss]\s*\))?\s*$#u", $s->getDuration(), $m)) {
                // 1 h 16 min(s)
                $s->arrival()->date(strtotime('+' . $m['h'] . ' hours, +' . $m['m'] . ' minutes', $s->getDepDate()));
            } elseif (preg_match("#^\s*(?<h>\d+)\s*" . $this->opt($this->t("h")) . "\s*$#u", $s->getDuration(), $m)) {
                // 2 h
                $s->arrival()->date(strtotime('+' . $m['h'] . ' hours', $s->getDepDate()));
            }
        }

        if ($s) {
            $country = null;
            $addresses = implode("\n", [$s->getDepAddress(), $s->getDepName(), $s->getArrName(), $s->getArrAddress()]);

            if (preg_match("/\b\d{4}, South Africa\s*$/m", $addresses)) {
                $country = 'South Africa';
            } elseif (preg_match("/\b\d{6}, India\s*$/m", $addresses)) {
                $country = 'India';
            } elseif (preg_match("/, [A-Z]{3} \d{4}, Australia\s*$/m", $addresses)) {
                $country = 'Australia';
            } elseif (preg_match("/, [A-Z]{2} \d{5}, US\s*$/m", $addresses)) {
                $country = 'USA';
            } elseif (preg_match("/, [A-Z]{2} \d{4}, ZA\s*$/m", $addresses)) {
                $country = 'South Africa';
            } elseif (preg_match("/, [A-Z]{3} \d{4}, AU\s*$/m", $addresses)) {
                $country = 'Australia';
            }

            switch ($country) {
                // code for geotip
                case 'South Africa': $code = 'za';

                    break;

                case 'USA': $code = 'us';

                    break;

                case 'Australia': $code = 'au';

                    break;

                case 'India': $code = 'in';

                    break;

                default: $code = $country;
            }

            if (!empty($country)) {
                if (!empty($s->getDepAddress())) {
                    $s->departure()
                        ->address($country . ', ' . $s->getDepAddress());
                }

                if (!empty($s->getDepName())) {
                    $s->departure()
                        ->name($country . ', ' . $s->getDepName());
                }

                if (!empty($s->getArrAddress())) {
                    $s->arrival()
                        ->address($country . ', ' . $s->getArrAddress());
                }

                if (!empty($s->getArrName())) {
                    $s->arrival()
                        ->name($country . ', ' . $s->getArrName());
                }
                // $s->departure()
                //     ->geoTip($code);
                // $s->arrival()
                //     ->geoTip($code);
            }
        }

        // TotalCharge
        $amount = null;
        $total = $this->http->FindSingleNode("//td[{$this->eq($this->t("New Total"))}]/following-sibling::td[normalize-space()]");

        if ($total !== null) {
            $amount = $this->amount($total);
        } elseif (count($totals = $this->http->FindNodes("//text()[{$this->eq($this->t("Total"))}]/ancestor::td[1]/following-sibling::td[1]"))) {
            foreach ($totals as $value) {
                $valueNormal = $this->amount($value);

                if ($valueNormal === null) {
                    $amount = null;

                    break;
                }
                $amount += $valueNormal;
            }
        } else {
            $total = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Total")) . " and contains(.,':')])[1]", null, true, "#:\s*(\S.+)#");

            if (empty($total)) {
                $total = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Total")) . " and contains(.,':')])[1]/following::text()[normalize-space()][1]");
            }
            $amount = $this->amount($total);
        }
        $t->price()->total($amount);

        $earnedPoints = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('You earned'))}]", null, true, "/^{$this->opt($this->t("You earned"))}\s+(\d[,.\'\d ]*{$this->opt($this->t('points'))})/i");
        $t->program()->earnedAwards($earnedPoints, false, true);

        // Currency
        $location = $s->getDepAddress();
        $currency = $this->currency($this->http->FindSingleNode("//td[{$this->eq($this->t("New Total"))}]/following-sibling::td[normalize-space()]"), $location);

        if (empty($currency)) {
            $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Total")) . " and contains(.,':')])[1]/following::text()[normalize-space()][1]"), $location);
        }

        if (empty($currency)) {
            $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Total")) . " and contains(.,':')])[1]", null, true, "#:\s*(\S.+)#"), $location);
        }

        if (empty($currency)) {
            $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total")) . "])/ancestor::td[1]/following-sibling::td[1]"), $location);
        }

        if (!empty($currency)) {
            $t->price()->currency($currency);
        }

        // Fees
        $xpath = "(//text()[" . $this->eq($this->t("Subtotal")) . "])[1]/ancestor::table[1]/following-sibling::table//tr[not(.//tr) and count(td["
            . "normalize-space()])=2 and not(" . $this->eq($this->t("CHARGED"), 'normalize-space(.//text())') . ") and not(" . $this->eq($this->t("not Fee"), 'normalize-space(.//text())') . ")]";

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $name = trim($this->http->FindSingleNode("./td[normalize-space()][1]", $root, true, "#(.+?)(\(\?\))?\s*$#"));
            $tempAmount = $this->http->FindSingleNode("./td[normalize-space()][2]", $root);

            $amount = str_replace('?', '', $tempAmount);

            if (!empty($name) && !empty($amount)) {
                if (preg_match("#^[\d\s\.\,]+$#", $amount)) {
                    $t->price()->fee($name, $this->amount($amount));

                    continue;
                }

                if (preg_match("#^\s*\-(.*\d.*)$#", $amount)) {
                    $amount = trim($amount, '-');
                    $currency = $this->currency($amount);

                    if (empty($currency) || ($currency == $t->obtainPrice()->getCurrencyCode())) {
                        if (empty($t->obtainPrice()->getDiscount())) {
                            $t->price()->discount($this->amount($amount));
                        } else {
                            $t->price()->discount($this->amount($amount) + $t->obtainPrice()->getDiscount());
                        }
                    }

                    continue;
                }

                $currency = $this->currency($amount);

                if (!empty($currency) && ($currency == $t->obtainPrice()->getCurrencyCode())) {
                    $t->price()->fee($name, $this->amount($amount));
                }
            }
        }

        $tip = $this->amount($this->http->FindSingleNode("(//text()[(" . $this->eq($this->t("Tip")) . ") and (preceding::td[" . $this->eq($this->t("CHARGED")) . "])])[1]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tip)) {
            $t->price()->fee("Tip", $tip);
        }

        // BaseFare
        $cost = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Subtotal")) . "])[1]/ancestor::td[1]/following-sibling::td[1]");

        if (!empty($cost)) {
            $currency = $this->currency($cost);

            if (empty($currency) || ($currency == $t->obtainPrice()->getCurrencyCode())) {
                $t->price()->cost($this->amount($cost));
            }
        }

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('emptyLocationTextItToJunk'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Download PDF'))}]/following::text()[string-length(normalize-space()) > 3]")->length < 12
            && !empty($t->getTravellers())
        ) {
            $email->removeItinerary($t);

            $email->setIsJunk(true);
        }

        if (empty($s->getDepAddress()) || empty($s->getArrAddress()) || empty($s->getDepDate())) {
            return;
        }

        if (isset($maybeJunk) && $t->validate(false)) {
            $email->removeItinerary($t);
            $email->setIsJunk(true);
        } else {
            $resStatus = $this->http->FindSingleNode("(//text()[" . $this->eq("Viaje cancelado") . "])");

            if (!empty($resStatus)) {
                $t->general()
                    ->status('cancelled')->cancelled();
            }
        }

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('cancelledText'))} or {$this->contains($this->t('notYourTrip'))}]")->length > 0
            && !empty($t->getTravellers())
        ) {
            $email->removeItinerary($t);

            $email->setIsJunk(true);
        }
    }

    private function getHtmlAttachments(PlancakeEmailParser $parser, $length = 6000): array
    {
        $result = [];

        for ($i = 0; $i <= $parser->countAttachments(); $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#\s+text/html;#", $info) && is_string($html) && strlen($html) > $length) {
                $result[] = $html;

                break;
            }
        }

        return $result;
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(?string $str): string
    {
        $year = date("Y", $this->date);
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#", // December 6, 2016
            "/^(\d+)(?:[,.\s]+de)?[,.\s]+([[:alpha:]]+)(?:[,.\s]+de)?[,.\s]+(\d{4})\D*$/u", // 2 de febrero de 2017    |    20. Dezember 2017    |    4 May 2020 г.
            "#^[^\d\s]+,\s*(\d+)月\s+(\d+),\s+(\d{4})$#u", // 木, 2月 14, 2019 //ja  |  周二, 2月 12, 2019 //zh
            "#^\s*(\d{4})年(\d+)月(\d+)日\s*$#u", // 2020年3月12日
            "#^(\d+)\s+de\s+([^\d\s]+)\s+de\s*$#u", // 2 de febrero de

            "#^([^\d\s]+)\s+(\d+)$#", // May 16
            "#^(\d+)\/(\d+)$#", // 16/05

            "#^(\d+)\s*([^\s\d\.]+)[\. ]*$#u", // 25 abr.
            "#^[^\d\s]+[., ]+([^\d\s\.\,]+)[.,]?\s+(\d+),\s+(\d{4})$#", // dom, set 30, 2018
            "#^\s*.+?,\s*[^\d\s\.\,]+\s+(\d+)\s+(\d+),\s+(\d{4})\s*$#", // Th 5, thg 11 01, 2018; CN, thg 11 04, 2018
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
            "$3-$1-$2",
            "$3-$2-$1",
            "$1 $2 $year",

            "$2 $1 $year",
            "$year-$2-$1",

            "$1 $2 $year",
            "$2 $1 $3",
            "$2.$1.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        $str = trim(preg_replace('/\s+/', '', $str));
        $in = [
            // 01 h 24,
            // 08: 0 5am
            '#^(\d{1,2})[h:.](\d{2}(?:[ap]m)?)$#i',
        ];
        $out = [
            '$1:$2',
            '$1:$2',
        ];
        $str = preg_replace($in, $out, $str);

        return trim($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s): ?float
    {
        $c = str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re('/(\d[,.\'\d ]*)/', $s)));

        if (empty($c)) {
            return null;
        }

        return (float) $c;
    }

    private function currency($s, $address = false)
    {
        $sym = [
            'NT$' => 'TWD',
            'CA$' => 'CAD',
            'MX$' => 'MXN',
            'R$'  => 'BRL',
            'A$'  => 'AUD',
            '€'   => 'EUR',
            'â‚¬' => 'EUR',
            '$'   => 'USD',
            'US$' => 'USD',
            '₹'   => 'INR',
            '£'   => 'GBP',
            'R'   => 'ZAR',
            'kr'  => 'SEK',
            'Kč'  => 'CZK',
            'JP¥' => 'JPY',
            '₩'   => 'KRW',
            'zł'  => 'PLN',
            '₪'   => 'ILS',
            '₴'   => 'UAH',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                if ($r == 'USD' && !empty($address)) {
                    $r = $this->fixCurrencyByAddress($r, $address);
                }

                return $r;
            }
        }

        return null;
    }

    private function fixCurrencyByAddress($cur, $address)
    {
        $a = [
            'USD' => [
                "Australia"   => 'AUD',
                "Canada"      => 'CAD',
                "Singapore"   => 'SGD',
                "Hong Kong"   => 'AUD',
                "New Zealand" => 'NZD',
                "Taiwan"      => 'TWD', "台灣" =>'TWD',
                "Mexico"      => 'MXN',
            ],
        ];

        if (isset($a[$cur])) {
            foreach ($a[$cur] as $find=>$newcur) {
                if (stripos($address, $find) !== false) {
                    return $newcur;
                }
            }
        }

        return $cur;
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
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

    private function changeBody($parser)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, "Content-Type: text/html") > 1) {
            $texts = preg_replace("#^(--\w{25,32}(--)?)$#m", "\n", $texts);
            $texts = preg_replace("#^(--Apple-Mail=.*)$#m", "\n", $texts);

            $posBegin1 = stripos($texts, "Content-Type: text/html");
            $i = 0;
            $text = '';

            while ($posBegin1 !== false && $i < 30) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $posEnd = stripos($texts, "\n\n", $posBegin);

                if (isset($posEnd)) {
                    $t = substr($texts, $posBegin, $posEnd - $posBegin);

                    if (preg_match("#quoted-printable#s", substr($texts, $posBegin1 - 50, $posBegin - $posBegin1 + 50))) {
                        $t = substr($texts, $posBegin, $posEnd - $posBegin);
                        $text .= quoted_printable_decode($t);
                    } else {
                        $t = substr($texts, $posBegin, $posEnd - $posBegin);
                        $text .= htmlspecialchars_decode($t);
                    }
                }
                $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                $i++;
            }
            $text = str_replace("&nbsp;", " ", $text);
            $this->http->SetEmailBody($text, true);
        }
    }
}
