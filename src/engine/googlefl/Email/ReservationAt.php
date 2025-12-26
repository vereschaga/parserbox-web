<?php

namespace AwardWallet\Engine\googlefl\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationAt extends \TAccountChecker
{
    public $mailFiles = "googlefl/it-114559006.eml, googlefl/it-114559008.eml, googlefl/it-116316459.eml, googlefl/it-39202417.eml, googlefl/it-39260473.eml, googlefl/it-39407912.eml, googlefl/it-39421175.eml, googlefl/it-39426497.eml, googlefl/it-39501659.eml, googlefl/it-39531205.eml, googlefl/it-614548468.eml, googlefl/it-72945398.eml, googlefl/it-73071898.eml, googlefl/it-73430292.eml, googlefl/it-81796607.eml, googlefl/it-82413466.eml, googlefl/it-82438884.eml, googlefl/it-82447053.eml, googlefl/it-82568126.eml";

    public $reFrom = ["reserve-noreply@google.com"];
    public $reBody = [
        'en'    => ['In partnership with', 'Google LLC'],
        'en2'   => ['In partnership with', 'Google Inc'],
        'en3'   => ['The Google Assistant handled this reservation', 'Google LLC'],
        'en4'   => ['The Google Assistant handled this reservation', 'Google Inc'],
        'en5'   => ['is canceled', 'Google LLC'],
        'en6'   => ['Upcoming reservation', 'Google LLC'],
        'en7'   => ['Reservation canceled', 'Google LLC'],
        'es'    => ['En colaboración con', 'Google LLC'],
        'nl'    => ['Reservering bevestigd', 'Google LLC'],
        'pt'    => ['Em parceria com', 'Google LLC'],
        'pt2'   => ['Em parceria com', 'Google Inc'],
        'fr'    => ['L\'Assistant Google a effectué cette réservation', 'Google LLC'],
        'fr2'   => ['Afficher la réservation', 'Google LLC'],
        'fr3'   => ['Réservation confirmée', 'Google'],
        'fr4'   => ['Réservation modifiée', 'Google'],
        'fr5'   => ['Votre réservation a été annulée', 'Google LLC'],
        'fr6'   => ['Réservation annulée', 'Google LLC'],
        'fr7'   => ['Rendez-vous annulé', 'Google LLC'],
        'fr8'   => ['Demande refusée', 'Google LLC'],
        'zh'    => ['查看訂座資料', 'Google LLC'],
        'zh2'   => ['予約が確定しました', 'Google LLC'],
        'de'    => ['Reservierung anzeigen', 'Google LLC'],
        'de2'   => ['Anstehende Reservierung', 'Google LLC'],
        'ru'    => ['Посмотреть бронирование', 'Google LLC'],
        'it'    => ['Visualizza la prenotazione', 'Google LLC'],
        'it2'   => ['Prenotazione annullata', 'Google LLC'],
        'it4'   => ['Appuntamento annullato', 'Google LLC'],
        'it5'   => ['Richiesta rifiutata', 'Google LLC'],
        'it3'   => ['Verifica i requisiti di sicurezza', 'Google LLC'],
        'ja'    => ['予約が確定しました', 'Google LLC'],
    ];
    public $reSubject = [
        '#Your reservation at .+? is confirmed$#', // + es, pt
        '#Your reservation at .+? has been cancell?ed$#',
        '#Your reservation at .+? was declined$#',
        // fr
        '#Votre réservation chez .+?  n\'a pas été acceptée#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Upcoming reservation' => ['Upcoming reservation', 'Upcoming booking', 'Walk in accepted', 'Pending', 'Upcoming appointment'],
            'Reservation canceled' => ['Reservation canceled', 'Reservation cancelled', 'Request declined', 'Appointment canceled'],
            'Address'              => 'Address',
            'Get directions'       => 'Get directions',
            'textHeader'           => ['Reservation confirmed', 'Reservation modified', 'It\'s almost time'],
        ],
        'nl' => [
            'Upcoming reservation' => 'Aanstaande reservering',
            //'Reservation canceled' => [''],
            //'Address'              => 'Address',
            'Get directions'        => 'Routebeschrijving',
            'textHeader'            => ['Reservering bevestigd'],
            'Reservation for'       => 'Reservering voor',
            'Reservation confirmed' => 'Reservering bevestigd',
        ],
        'it' => [
            'Upcoming reservation' => 'Prenotazione imminente',
            'Reservation canceled' => ['Prenotazione annullata', 'Appuntamento annullato', 'Richiesta rifiutata'],
            //'Address'              => 'Address',
            'Get directions'        => 'Indicazioni stradali',
            'textHeader'            => ['Prenotazione confermata', 'La tua prenotazione è stata annullata', 'Prenotazione annullata'],
            'Reservation for'       => 'Prenotazione per',
            'Reservation confirmed' => 'Prenotazione confermata',
        ],
        'es' => [
            'Upcoming reservation' => 'Próxima reserva',
            //            'Reservation canceled' => [''],
            //            'Address' => '',
            'Get directions'  => 'Cómo llegar',
            'Reservation for' => 'Reserva para',
            'textHeader'      => ['Reserva confirmada'],
        ],
        'pt' => [
            'Upcoming reservation' => ['Próxima reserva', 'Reserva futura', 'A sua próxima reserva'],
            //            'Reservation canceled' => [''],
            //            'Address' => '',
            'Get directions'  => ['Como chegar', 'Obter direções'],
            'Reservation for' => 'Reserva para',
            'textHeader'      => ['Reserva confirmada'],
        ],
        'fr' => [
            'Upcoming reservation' => 'Réservation à venir',
            'Reservation canceled' => ['Demande refusée', 'Réservation annulée', 'Rendez-vous annulé'],
            //            'Address' => '',
            'Get directions'        => ['Itinéraire', 'Obtenir l\'itinéraire', 'Obtenir un itinéraire', 'Obtenez un itinéraire'],
            'Reservation for'       => ['Réservation pour'],
            'textHeader'            => ['Votre demande de réservation n\'a pas été acceptée', 'Afficher la réservation'],
            'Reservation confirmed' => 'Réservation confirmée',
        ],
        'zh' => [
            'Upcoming reservation' => '已確定的預訂',
            //'Reservation canceled' => ['Reservation canceled', 'Reservation cancelled', 'Request declined'],
            //'Address' => 'Address',
            'Reservation for' => '預約人數：',
            'Get directions'  => '取得路線',
            'textHeader'      => '已確認預訂',
        ],
        'de' => [
            'Upcoming reservation' => ['Anstehende Reservierung', 'Anstehende Reservierung'],
            //'Reservation canceled' => ['Reservation canceled', 'Reservation cancelled', 'Request declined'],
            //'Address' => 'Address',
            'Reservation for' => 'Reservierung für',
            'Get directions'  => ['Routenplaner', 'Wegbeschreibung'],
            'textHeader'      => 'Reservierung anzeigen',
        ],
        'ru' => [
            'Upcoming reservation' => 'Бронирование',
            //'Reservation canceled' => ['Reservation canceled', 'Reservation cancelled', 'Request declined'],
            //'Address' => 'Address',
            'Reservation for' => 'Бронирование на',
            'Get directions'  => 'Маршруты',
            'textHeader'      => 'Посмотреть бронирование',
        ],
        'ja' => [
            'Upcoming reservation' => '近日中の予約',
            //'Reservation canceled' => [''],
            //'Address' => 'Address',
            'Reservation for' => '予約:',
            'Get directions'  => 'ルートを検索',
            'textHeader'      => '予約が確定しました',
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Address'))}]")->length === 0) {
            $this->parseEmail_1($email);
        } else {
            $this->parseEmail_2($email);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'googleusercontent.com/')] | //a[contains(@href,'google.com/appserve/mkt/')] | //a[contains(@href,'https://notifications.google.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if (!is_array($reBody) || count($reBody) !== 2) {
                    continue;
                }

                if ($this->http->XPath->query("//text()[{$this->eq($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    return $this->assignLang();
                }
            }
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
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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
        $formats = 2;
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmail_1(Email $email): void
    {
        // it-114559006.eml, it-114559008.eml, it-116316459.eml, it-39202417.eml, it-39260473.eml, it-39407912.eml, it-39531205.eml, it-72945398.eml, it-73071898.eml, it-73430292.eml, it-81796607.eml, it-82413466.eml, it-82438884.eml, it-82447053.eml, it-82568126.eml

        $this->logger->debug(__FUNCTION__);

        $r = $email->add()->event();

        $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ID '))}]", null, false,
            "#{$this->opt($this->t('ID '))}\s*([\w\-]+)$#");

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Get directions'))}]/following::text()[{$this->starts($this->t('ID '))}]", null, false,
                "#{$this->opt($this->t('ID '))}\s*([\w\-]+)$#");
        }

        $r->ota()
            ->confirmation($otaConf);

        $r->general()
            ->noConfirmation();

        // canceled reservation
        if ($status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation canceled'))}]", null, true, "/^\w+\s*(\w+)/u")) {
            $r->general()
                ->status($status)
                ->cancelled();

            $place = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation canceled'))}]/preceding::text()[normalize-space()!=''][1]");
            $r->place()
                ->address($place);

            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation for'))}]", null, false,
                "#{$this->opt($this->t('Reservation for'))}\s*(\d+)#u");

            if (!empty($guests)) {
                $r->booked()
                    ->guests($guests);
            }

            $time = trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation canceled'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][2]", null, true, "/^\s*(\w+\D+[\d\:\.h\s]+)/su"));

            if (!empty($time)) {
                $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation canceled'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][1]");

                if (!preg_match("/{$this->opt($this->t('Reservation for'))}/u", $name)) {
                    $r->place()
                        ->name($name);
                } else {
                    $r->place()
                        ->name($place);
                }
            } else {
                $r->place()
                    ->name($place);
            }

            // no time start --> junk (noStart,noEnd)
            $datePart1 = implode(' ',
                $this->http->FindNodes("//text()[{$this->eq($this->t('Reservation canceled'))}]/ancestor::table[count(./descendant::text()[normalize-space()!=''])=2][1]/preceding::table[count(./descendant::text()[normalize-space()!=''])=2][1]/descendant::text()[normalize-space()!='']"));

            if (!empty($datePart1) && !empty($time)) {
                $r->booked()
                    ->start($this->normalizeDate($datePart1 . ' ' . $time))
                    ->noEnd();
            } elseif (!empty($datePart1)) {
                $r->booked()
                    ->start($this->normalizeDate($datePart1))
                    ->noEnd();
            }

            $r->setEventType($this->assignEventType());

            return;
        }

        // confirmed reservation
        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation confirmed'))}]")) {
            $r->general()->status('confirmed');
        }

        $r->place()
            ->address(implode(' ',
                $this->http->FindNodes("//text()[{$this->eq($this->t('Get directions'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][position()!=last()]")))
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Upcoming reservation'))}]/preceding::text()[normalize-space()!=''][1]"));

        $datePart1 = implode(' ',
            $this->http->FindNodes("//text()[{$this->eq($this->t('Upcoming reservation'))}]/ancestor::table[count(./descendant::text()[normalize-space()!=''])=2][1]/preceding::table[count(./descendant::text()[normalize-space()!=''])=2][1]/descendant::text()[normalize-space()!='']"));
        $datePart2 = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation for'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($datePart2)) {
            $datePart2 = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Upcoming reservation'))}]/following::text()[normalize-space()!=''][2]");
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation for'))}]", null, false,
            "#{$this->opt($this->t('Reservation for'))}\s*(\d+)#u");

        if (!empty($guests)) {
            $r->booked()
                ->guests($guests);
        }

        $r->booked()
            ->noEnd()
            ->start($this->normalizeDate($datePart1 . ' - ' . $datePart2));

        $r->setEventType($this->assignEventType());
    }

    private function parseEmail_2(Email $email): void
    {
        // it-39421175.eml, it-39426497.eml, it-39501659.eml

        $this->logger->debug(__FUNCTION__);

        $r = $email->add()->event();
        $r->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation ID'))}]/following::text()[normalize-space()!=''][1]"));
        $r->general()
            ->noConfirmation();

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation confirmed'))}]")) {
            $r->general()->status('confirmed');
        }

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation modified'))}]")) {
            $r->general()->status('modified');
        }

        $r->place()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('textHeader'))}]/following::text()[normalize-space()!=''][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space()!=''][1]"))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space()!=''][2]"));

        $party = $this->http->FindSingleNode("//text()[{$this->eq($this->t('textHeader'))}]/following::text()[normalize-space()!=''][2]",
            null, false, "#[•]\s*{$this->opt($this->t('Party of'))}\s+(\d+)$#");

        if (!empty($party)) {
            $r->booked()->guests($party);
        }

        $date = $this->http->FindSingleNode("//text()[{$this->eq($this->t('textHeader'))}]/following::text()[normalize-space()!=''][2]",
            null, false, "#(.+?)\s*(?:[•]|$)#");
        $r->booked()
            ->start($this->normalizeDate($date))
            ->noEnd();

        $r->setEventType($this->assignEventType());
    }

    private function normalizeDate($date)
    {
        if (stripos($date, '下午') !== false || stripos($date, '上午') !== false || stripos($date, '月') !== false) {
            $this->lang = 'zh';

            if (stripos($date, '下午') !== false) {
                $date = preg_replace("/下午\s*([\:\d]+)/", "$1PM", $date);
            }
        }

        if (stripos($date, 'م') !== false || stripos($date, 'ص') !== false) {
            $this->lang = 'ar';
            $date = preg_replace("/\s(\d+\:\d+)\s*م/u", "$1PM", $date);

            if (stripos($date, '下午') !== false) {
                $date = preg_replace("/下午\s*([\:\d]+)/", "$1PM", $date);
            }
        } elseif (stripos($date, 'יום') !== false) {
            $this->lang = 'he';
        } elseif ($this->http->XPath->query("//text()[{$this->contains('kişilik parti')}]")->length > 0) {
            $this->lang = 'tr';
        }

        $year = date('Y', $this->date);
        $in = [
            //12 déc. sam. · 12:30
            '#^(\d+)\s*(\w+)\.?\s*\-?\s*(\w+)\D+(\d+[\d\:\.]+)$#u',
            //19 דצמ׳ יום ב׳ · 19:30
            '#^(\d+)\s(\w+)\s*(\D+)\s[·\s]+\s+(\d+\:\d+)$#u',

            //26 12月 - 周一 · 7:30PM (EST) · counter
            '#^(\d+)\s*(\d+)月[\-\s·]+(\w+)\D+(\d+\:\d+A?P?M?)\s*(?:\([A-Z]+\))?(?:[\s\·]+\w+)?$#ui',
            //3 mag - lun · 13.00 (CEST)
            '#^(\d+)\s*(\w+)\s*\-\s*(\w+)\s*[·]\s*([\d\.]+)\s*\([A-Z]{3,}\)$#u',
            //12 3月 - 五 · 下午5:30 (CST)
            "#^(\d+)\s*(\d+)月\s*\-\s*(\w+).+(\d+\:\d+A?P?M?)\s\([A-Z]+\)$#u",
            //15 Jun - Sat · 7:00 PM; 10 Dec - Thu · 5:30 PM (EST) · Indoor Dining;
            '#^(\d+)\s+(\w+)\.?\s+\-\s+(\w+)\.?\D+(\d+:\d+(?:\s*[ap]m)?)\s*(?:\([A-Z]{3,}\).*|·.*)?$#ui',
            //4 Oct Mon · 7:30
            '#^(\d+)\s*(\w+)\s*(\w+)\D+(\d+[\d\:\.]+)$#ui',
            // Sunday, March 3, 2019 at 7:00 PM
            '#^\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            //8 三月 - 星期一 · 下午5:30 (AEDT)
            "#^(\d+)\s*(\D+)\s+\-\s+(\D+)\s+.+\s(\d+\:\d+)\s\([A-Z]+\)$#u",
            //30 十月 - 星期六 · 上午11:00 (SGT)
            "#^(\d+)\s*(\D+)\s*\-\s*(\D+)\s*[·]\s*\D+([\d\:]+)\s*\([A-Z]{3}\)$#",
            //15 Jun
            '#^(\d+)\s+(\w+)\.?\s*$#ui',
            //26 déc. - lun. · 18 h 00 (EST)
            '#^(\d+)\s+(\w+)\.?\s+\-\s+(\w+)\.?\D+(\d+)[\sh]+(\d+)\s*\([A-Z]+\).*$#ui',
            //20 déc. mar. · 18 h 00
            '#^(\d+)\s*(\w+)\.\s*(\w+)\.[\s\·]+(\d+)[\sh]+(\d+)$#u',
            //24 12月 - 日 · 17:00 （AEDT）
            '#^(\d+)\s*(\d+)月\s*\-\s*日\s*[·]\s*([\d\:]+)\s*\（AEDT\）#u',
            //30 نوفمبر - الخميس · 10:30 ص (GMT)
            '#^(\d+)\s*(\w+)\s*\-\s+\w+\s+[·]\s+(\d+\:\d+)\s+\D+$#u',
            //25 Nov - Sat · 6:00 PM (or 6:00 PM - 7:30 PM)
            '#^(\d+)\s*(\w+)\s*\-\s*\w+\s*[·]\s*([\d\.\:]+\s*A?P?M?)\s+\(.*$#u',
            // 12 May - Thu · Between 2:00 PM - 4:00 PM (CDT)
            '#^(\d+)\s*(\w+)\s*\-\s*\w+\s*[·]\s*Between\s*([\d\:]+\s*A?P?M)\s*\-\s*[\d\:]+\D+$#u',
        ];
        $out = [
            '$1 $2 ' . $year . ', $4',
            '$1 $2 ' . $year . ', $4',
            '$1.$2.' . $year . ', $4',
            '$1 $2 ' . $year . ', $4',
            '$1.$2.' . $year . ', $4',
            '$1 $2 ' . $year . ', $4',
            '$1 $2 ' . $year . ', $4',
            '$2 $1 $3, $4',
            '$1 $2 ' . $year . ', $4',
            '$1 $2 ' . $year . ', $4',
            '$1 $2 ' . $year,
            '$1 $2 ' . $year . ', $4:$5',
            '$1 $2 ' . $year . ', $4:$5',
            '$1.$2.' . $year . ', $3',
            '$1 $2 ' . $year . ', $3',
            '$1 $2 ' . $year . ', $3',
            '$1 $2 ' . $year . ', $3',
        ];
        $outWeek = [
            '$3',
            '$3',
            '$3',
            '$3',
            '$3',
            '$3',
            '$3',
            '',
            '$3',
            '$3',
            '',
            '$3',
            '$3',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            //$this->logger->debug($week);
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $date = str_replace('׳', '', preg_replace($in, $out, $date));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (
                (isset($words['Get directions']) && $this->http->XPath->query("//*[{$this->contains($words['Get directions'])}]")->length > 0)
                //|| (isset($words['Upcoming reservation']) && $this->http->XPath->query("//*[{$this->contains($words['Upcoming reservation'])}]")->length > 0)
                || (isset($words['Reservation canceled']) && $this->http->XPath->query("//*[{$this->contains($words['Reservation canceled'])}]")->length > 0)
                || (isset($words['Address']) && $this->http->XPath->query("//*[{$this->eq($words['Address'])}]")->length > 0)
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function assignEventType(): int
    {
        return Event::TYPE_RESTAURANT;
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
}
