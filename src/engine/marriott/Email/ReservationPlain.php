<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationPlain extends \TAccountChecker
{
    public $mailFiles = "marriott/it-160720338.eml, marriott/it-1630912.eml, marriott/it-1897984.eml, marriott/it-203505274.eml, marriott/it-2687121.eml, marriott/it-34057796.eml, marriott/it-40441940.eml, marriott/it-418380483.eml, marriott/it-425930605.eml, marriott/it-426289122.eml, marriott/it-429881582.eml, marriott/it-430470060.eml, marriott/it-438761505.eml, marriott/it-67973629.eml";

    public $detectSubject = [
        // en
        'sent you an email from www.marriott.com',
        // ja
        'www.marriott.co.jpからEメールが送信されました。',
        // fr
        'vous a envoyé un e-mail de www.marriott.com',
        // es
        'le envió un correo electrónico desde www.espanol.marriott.com',
        // zh
        '通过 www.marriott.com.cn 向您发送电子邮件',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Confirmation number:'  => ['Confirmation number:', 'Confirmation Number:'],
            "Guest name:"           => ["Guest name:", "Thank you for booking with us,", "Thank you for your booking,"],
            'Check-in:'             => ['CheckIn:', 'Check-in:', 'Check-In:', 'Check-in'],
            'Check-out:'            => ['CheckOut:', 'Check-out:', 'Check-Out:', 'Check out:'],
            'dateFormatRe'          => ['[[:alpha:]\-]+, *[[:alpha:]]+ \d{1,2}, \d{4}', '[[:alpha:]]+[,\s] *\d{1,2}\s+[[:alpha:]]+ \d{4}'],
            //            'Cancellation Number:' => '',
            'CancelledTextRe' => [
                'Your reservation has been (?<status>cancelled)\.',
            ],
            'CancellationTextRe' => 'Cancell?ing\s+Your\s+Reservation\s+(.*?)\s+Modifying\s+Your\s+Reservation',
            //            'Guest name:' => '',
            'Number of rooms:'                => ['Number of rooms:', 'Number of rooms'],
            'Number of guests:'               => ['Guests per room', 'Number of guests:'],
            'Room Preferences & Description:' => ['Room Preferences & Description:', 'Room Type', 'Room Preferences & Description'],
            //            'Room' => '',
            "Total for stay in hotel's currency" => ["Total for stay in hotel's currency", "Total for Stay \(all rooms\)"],
            //            'Total points for stay:' => '',
            //            'Summary of Charges:' => '',
            //            'Total cash rate' => '',
            'Estimated government taxes and fees' => ['Estimated government taxes and fees', 'Estimated Government Taxes & Fees'],
        ],
        "ja" => [
            'Confirmation number:'  => ['予約確認番号:', '予約確認番号 :'],
            'Check-in:'             => ['チェックイン：', 'チェックイン:', 'チェックイン :'],
            'Check-out:'            => ['チェックアウト:', 'チェックアウト :'],
            'dateFormatRe'          => '\d{4} *年 *\d{1,2} *月 *\d{1,2} *日',
            //            'Cancellation Number:' => '',
            //            'CancelledTextRe' => [
            //                'Your reservation has been (?<status>cancelled)\.'
            //            ],
            //            'CancellationTextRe' => 'Cancell?ing\s+Your\s+Reservation\s+(.*?)\s+Modifying\s+Your\s+Reservation',
            'Guest name:'                         => ['お客様の氏名:', 'お客様の氏名 :'],
            'Number of rooms:'                    => ['部屋数:', '部屋数 :'],
            'Number of guests:'                   => ['人数:', '人数 :'],
            'Room Preferences & Description:'     => '客室のご希望と詳細',
            'Room'                                => '客室',
            'Total for stay in hotel\'s currency' => 'ホテルの現地通貨でのご滞在の合計額',
            //            'Total points for stay:' => '',

            'Summary of Charges:'                 => ['料金の概要:', '料金の概要 :'],
            'Total cash rate'                     => '現金料金合計',
            'Estimated government taxes and fees' => '税金および手数料見積もり',
        ],
        "fr" => [
            'Confirmation number:'  => ['Numéro de confirmation :'],
            'Check-in:'             => ['Arrivée :'],
            'Check-out:'            => ['Départ :'],
            'dateFormatRe'          => '[[:alpha:]]+ \d{1,2} [[:alpha:]]+ \d{4}',
            //            'Cancellation Number:' => '',
            //            'CancelledTextRe' => [
            //                'Your reservation has been (?<status>cancelled)\.'
            //            ],
            'CancellationTextRe'                  => 'Annulation de votre réservation\s+(.*?)\s+Modification de votre réservation',
            'Guest name:'                         => ['Nom du client :'],
            'Number of rooms:'                    => ['Nombre de chambres:'],
            'Number of guests:'                   => ['Nombre d\'occupants:'],
            'Room Preferences & Description:'     => 'Préférences de chambre et description :',
            // 'Room'                                => '客室',
            'Total for stay in hotel\'s currency' => 'Total du séjour dans la devise locale',
            //            'Total points for stay:' => '',

            'Summary of Charges:'                 => ['Récapitulatif des frais:'],
            'Total cash rate'                     => 'Tarif total en argent',
            'Estimated government taxes and fees' => 'Estimation des taxes gouvernementales et des frais',
        ],
        "es" => [
            'Confirmation number:'  => ['Número de confirmación:'],
            'Check-in:'             => ['Registro de llegada:'],
            'Check-out:'            => ['Registro de salida:'],
            'dateFormatRe'          => '[[:alpha:]]+, \s*\d{1,2} de [[:alpha:]]+ de \d{4}', // viernes, 21 de julio de 2023
            //            'Cancellation Number:' => '',
            //            'CancelledTextRe' => [
            //                'Your reservation has been (?<status>cancelled)\.'
            //            ],
            'CancellationTextRe'                  => 'Cancelando su reserva\s+(.*?)\s+Modificar su reserva',
            'Guest name:'                         => ['Nombre del huésped:'],
            'Number of rooms:'                    => ['Número de habitaciones:'],
            'Number of guests:'                   => ['Número de huéspedes:'],
            'Room Preferences & Description:'     => 'Descripción y preferencias de la habitación:',
            'Room'                                => 'Habitación',
            'Total for stay in hotel\'s currency' => 'Total por estancia en la moneda local del hotel',
            //            'Total points for stay:' => '',

            'Summary of Charges:'                 => ['Detalle de los cargos:'],
            'Total cash rate'                     => 'Tarifa total en efectivo',
            'Estimated government taxes and fees' => 'Tarifas e impuestos del gobierno calculados',
        ],
        "zh" => [
            'Fax:'                  => ['传真:'],
            'Confirmation number:'  => ['确认号码：', '确认号码:'],
            'Check-in:'             => ['入住：', '入住:'],
            'Check-out:'            => ['退房：', '退房:'],
            'dateFormatRe'          => '\d{4} *年 *\d{1,2} *月 *\d{1,2} *日 \w+', // 2022年1月7日 星期五
            //            'Cancellation Number:' => '',
            //            'CancelledTextRe' => [
            //                'Your reservation has been (?<status>cancelled)\.'
            //            ],
            'CancellationTextRe'                  => '取消您的预订\s+(.*?)\s+修改您的预订',
            'Guest name:'                         => ['客人姓名：', '客人姓名:'],
            'Number of rooms:'                    => ['客房数量:'],
            'Number of guests:'                   => ['宾客人数:'],
            'Room Preferences & Description:'     => '客房偏好及说明：',
            // 'Room'                                => '',
            'Total for stay in hotel\'s currency' => '按酒店当地货币，住宿金额总计为',
            //            'Total points for stay:' => '',

            'Summary of Charges:'                 => ['费用摘要:'],
            'Total cash rate'                     => '总现金房价',
            'Estimated government taxes and fees' => '按酒店当地货币，住宿金额总计为',
        ],
    ];

    public function parseText(Email $email, $text)
    {
        $text = str_replace("[res-marriott.com]", "", $text);

        $h = $email->add()->hotel();

        $confirmation = $this->re('#' . $this->opt($this->t("Confirmation number:")) . '\s+([\w\-]+)#iu', $text);

        if ($confirmation) {
            $h->general()->confirmation($confirmation);
        } else {
            // Cancelled
            $confirmation = array_filter(explode(',', $this->re('#' . $this->opt($this->t("Cancellation Number:")) . '\s+([\w\-,]+)#i', $text)));

            if (isset($confirmation[0])) {
                $h->general()->cancellationNumber($confirmation[0]);
                $reCanceled = (array) $this->t('CancelledTextRe');
                $cancelled = false;

                foreach ($reCanceled as $re) {
                    if (preg_match('#' . $re . '#i', $text, $m)) {
                        $cancelled = true;

                        if (!empty($m['status'])) {
                            $status = $m['status'];
                        }

                        break;
                    }
                }

                if ($cancelled == true) {
                    $h->general()->status($status);
                    $h->general()->cancelled();
                }
            }
        }

        $cancellation = str_replace("\n", " ", $this->re("/" . $this->t("CancellationTextRe") . "/us", $text));

        if (empty($cancellation)) {
            $cancellation = $this->re("/Rate Details & Cancellation Policy\s+(.*?)\s+Rate Guarantee Limitation/su", $text);
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation(str_replace("\n", " ", $cancellation));
        }

        $traveller = $this->re('#' . $this->opt($this->t("Guest name:")) . '\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\b#iu', $text);

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller, true);
        }

        /*
                           The Ritz-Carlton, Kyoto
                           Kamogawa Nijo-Ohashi Hotori, Nakagyo-ku
                           Kyoto, 604-0902
                           Japan
                           +81757465555
                           Fax: +81757465501
                        */
        $pattern = '/'
            . '(?:^[\s>]*|[\r\n]+[ >]*[\r\n]+[ >]*)'
            . '(?<name>.{3,}?)[\r\n]+'
            . '(?<address>(?:[ >]*\S.+[\r\n]+){1,3})'
            . '[ >]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:; ext= \d+)?[\r\n]+'
            . '[ >]*\s*' . $this->opt($this->t('Fax:')) . '\s*(?<fax>[+(\d][-. \d)(]{5,}[\d)])?(?:; ext= \d+)?[\r\n]+'
            . '[ >]*[\r\n]'
            . '/iu';

        $pattern2 = "/(?:^[\s>]*|[\r\n]+[ >]*[\r\n]+[ >]*)(?<name>.{3,}?)[\r\n]+(?<address>(?:[ >]*\S.+[\r\n]+){1,3})[ >]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])[\r\n]+[ >]*Thank\s*you/iu";

        $pattern3 = "/\n+(?<name>.+)\n+\SAddress\S\s*(?<address>.+)\n+\STelefone\S\s*(?<phone>[\+\-\d]+)/u";
        $pattern4 = "/\( ?\S+@\S+ *\)\s*\n+(?<name>.+)\n+(?:(?:Marriott|The Ritz-Carlton|Sheraton|The Westin)[[:alpha:] ]+\n+)?(?:{$this->opt($this->t('Marriott Rewards Redemption Reservation'))}\n+)?{$this->opt($this->t('Guest name:'))}/u";
        /* This Marriott.com reservation email has been forwarded to you by PAUL DAVID ()

           The Westin Hapuna Beach Resort

           Guest name: PAUL DAVID*/
        $pattern5 = "/email has been forwarded to you by [A-Z]+[A-Z \-]+ \(\)\s*\n+(?<name>.+)\n+(?:(?:Marriott|The Ritz-Carlton)[[:alpha:] ]+\n+)?{$this->opt($this->t('Guest name:'))}/u";

        $pattern6 = '/'
            . '(?:^[\s>]*|[\r\n]+[ >]*[\r\n]+[ >]*)'
            . '(?<name>.{3,}?)[\r\n]+'
            . '(?<address>(?:[ >]*\S.+[\r\n]+){1,4})'
            . '[ >]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])(?:; ext= \d+)?[\r\n]+'
            . '[ >]*\s*' . $this->opt($this->t('Fax:')) . '\s*(?<fax>[+(\d][-. \d)(]{5,}[\d)])?(?:; ext= \d+)?[\r\n]+'
            . '[ >]*[\r\n]?'
            . '/miu';

        if (preg_match($pattern, $text, $m)
        || preg_match($pattern2, $text, $m)
        || preg_match($pattern3, $text, $m)
        || preg_match($pattern6, $text, $m)
        ) {
            $h->hotel()
                ->name($m['name'])
                ->address(nice($m['address'], ','))
                ->phone($m['phone']);

            if (isset($m['fax']) && !empty($m['fax'])) {
                $h->hotel()
                    ->fax($m['fax']);
            }
        } elseif (preg_match($pattern4, $text, $m)
            || preg_match($pattern5, $text, $m)
        ) {
            $h->hotel()
                ->name($m['name'])
                ->noAddress();
        }

        $h->booked()
            ->rooms($this->re('#' . $this->opt($this->t("Number of rooms:")) . '\s+(\d{1,3})\b#ui', $text))
            ->guests($this->re('#' . $this->opt($this->t("Number of guests:")) . '\s+(\d{1,3})\b#ui', $text));

        $kids = $this->re("#{$this->opt($this->t('Number of guests:'))}.*\s(\d{1,3})\s*{$this->opt($this->t('Child'))}\b#ui", $text);

        if (!empty($kids)) {
            $h->booked()
                ->kids($kids);
        }

        if (preg_match("#{$this->opt($this->t('Check-in:'))}\s+(" . implode('|', (array) $this->t("dateFormatRe")) . ")\s*\((.*)\)#iu", $text, $m)) {
            $h->booked()->checkIn($this->normalizeDate($m[1] . ', ' . $m[2]));
        } elseif (preg_match("#{$this->opt($this->t('Check-in:'))}\s+\s*([\w\-]+\,\s*\w+\s*\d+\,\s*\d{4}\s*[\d\:]+\s*[AP]M)#iu", $text, $m)) {
            $h->booked()->checkIn($this->normalizeDate($m[1]));
        } elseif (preg_match("#{$this->opt($this->t('Check-in:'))}\s+(" . implode('|', (array) $this->t("dateFormatRe")) . ")\b#iu", $text, $m)) {
            $h->booked()->checkIn($this->normalizeDate($m[1]));
        }

        if (preg_match("#{$this->opt($this->t('Check-out:'))}\s+(" . implode('|', (array) $this->t("dateFormatRe")) . ")\s*\((.*)\)#iu", $text, $m)) {
            $h->booked()->checkOut($this->normalizeDate($m[1] . ', ' . $m[2]));
        } elseif (preg_match("#{$this->opt($this->t('Check-out:'))}\s+\s*([\w\-]+\,\s*\w+\s*\d+\,\s*\d{4}\s*[\d\:]+\s*[AP]M)#iu", $text, $m)) {
            $h->booked()->checkOut($this->normalizeDate($m[1]));
        } elseif (preg_match("#{$this->opt($this->t('Check-out:'))}\s+(" . implode('|', (array) $this->t("dateFormatRe")) . ")\b#iu", $text, $m)) {
            $h->booked()->checkOut($this->normalizeDate($m[1]));
        }

        $roomType = $this->re("#{$this->opt($this->t('Room Preferences & Description:'))}\s+(.*?\S)\s+{$this->opt($this->t('Room'))} \d+:#ui", $text);

        if (empty($roomType)) {
            $roomType = $this->re("#{$this->opt($this->t('Room Preferences & Description:'))}\s+(.*\S)#iu", $text);
        }

        if (!empty($roomType) && stripos($roomType, "Summary of Charges") === false) {
            $room = $h->addRoom();
            $room->setType($roomType);

            $roomDescription = $this->re("#{$this->opt($this->t('Room'))}\s+\d+:\s+(.*\S)#ui", $text);

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }
        }

        $points = $this->re('/' . $this->opt($this->t("Total points for stay:")) . '\s+(\d[,.\'\d]*)[ ]*$/ium', $text);

        if (!empty($points)) {
            $spentAwards = $points . ' points';
        }

        if (preg_match("/{$this->opt($this->t("Total for stay in hotel's currency"))} - .*?\b(\d[,.\'\d]*)[ ]*(points?).*$/ium", $text, $m)) {
            $spentAwards = $m[1] . ' ' . $m[2];
        }

        $nightsCharges = $this->re("/{$this->opt($this->t("Summary of Charges:"))}[\s\S]*?((?:\s*^ *(?:" . implode('|', (array) $this->t("dateFormatRe")) . ") +-.*$)+)/mu", $text);

        $ratesValues = [];
        $freeNight = 0;

        foreach (array_filter(explode("\n", trim($nightsCharges))) as $row) {
            if (preg_match("/^ *(?:" . implode('|', (array) $this->t("dateFormatRe")) . ") +- *(?<rate>\d[\d., ]* *[^\d\s]*(?: [^\d\s]*)?|[^\d\s]*(?: [^\d\s]*)? *\d[\d., ]|)\s*$/iu", $row, $m)
            ) {
                if (empty($m['rate'])) {
                    $m['rate'] = '0.00';
                    $freeNight++;
                }
                $ratesValues[] = $m['rate'];
            }
        }

        if (!empty($freeNight)) {
            $h->booked()->freeNights($freeNight);
        }

        if (!empty($ratesValues) && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $day = date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');

            if (count($ratesValues) == $day) {
                if (isset($room)) {
                    $room->setRates($ratesValues);
                } else {
                    $h->addRoom()->setRates($ratesValues);
                }
            }
        }

        if (count($ratesValues) === 0 && preg_match_all("/([\d.,]+\s*[A-Z]{3}\s*per\s*night\s*per\s*room)/u", $text, $m)) {
            if (isset($room)) {
                $room->setRate(implode(', ', $m[1]));
            } else {
                $h->addRoom()->setRate(implode(', ', $m[1]));
            }
        }

        if (preg_match_all("/\b(\d[,.'\d]*)[ ]*(points?)/i", $nightsCharges, $m)
            && count(array_unique($m[2])) === 1
        ) {
            $points = null;

            foreach ($m[1] as $amount) {
                $points += $this->amount($amount);
            }
            $spentAwards = $points ? (int) $points . ' ' . $m[2][0] : null;
        }

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }

        $currency = null;

        if (preg_match("#{$this->opt($this->t("Total for stay in hotel's currency"))}\s*\-*\s*(\d[\d.,]*?) ([A-Z]{3})#u", $text, $m)) {
            $currency = $m[2];
            $h->price()
                ->currency($m[2])
                ->total(PriceHelper::parse($m[1], $currency));
        }

        $cost = $this->re("#{$this->opt($this->t("Total cash rate"))}\s*-\s*(\d[\d.,]*?)\D*\n#u", $text);

        if (!empty($cost)) {
            $h->price()
                ->cost(PriceHelper::parse($cost, $currency));
        }

        $taxes = $this->re("#{$this->opt($this->t("Estimated government taxes and fees"))}\s*\-*\s*(\d[\d.,]+?)\D*\n#u", $text);

        if ($this->lang === 'en' && $currency === 'USD' && preg_match("/^\s*(\d[\d, ]*\.)(\d{1,2}(\d))(\\3){4,}\d\s*$/", $taxes, $m)) {
            $taxes = $m[1] . substr($m[2], 0, 2);
            // 59.376666666666665 -> 59.37
            // 8.966666666666667 -> 8.96
        }

        if ($this->lang === 'en' && $currency === 'USD' && preg_match("/^\s*(\d[\d, ]*)\.(\d{3})$/", $taxes, $m)) {
            $taxes = $m[1] . '.' . substr($m[2], 0, 2);
            // 59.376666666666665 -> 59.37
            // 8.966666666666667 -> 8.96
        }

        if (!empty($taxes)) {
            $h->price()
                ->tax(PriceHelper::parse($taxes, $currency));
        }

        if (preg_match("/View Account\s*\n+[X]+(?<accountNumber>[\d]+)\nAccount\n*(?<points>[\d\,]+)\n+Points\n*(?:(?<status>\D+)\n*Status)?/", $text, $match)) {
            $h->program()
                ->account($match['accountNumber'], true);

            $st = $email->add()->statement();

            if (!empty($traveller)) {
                $st->addProperty('Name', trim($traveller, ','));
            }

            if (isset($match['status']) && !empty($match['status'])) {
                $st->addProperty('Level', $match['status']);
            }

            $st->setNumber($match['accountNumber']);
            $st->setBalance(str_replace(',', '', $match['points']));
        }

        $this->detectDeadLine($h, $h->getCancellation());

        return true;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Confirmation number:']) && $this->http->XPath->query('//node()[' . $this->contains($dict['Confirmation number:']) . ']')->length > 0
                && !empty($dict['Check-out:']) && $this->http->XPath->query('//node()[' . $this->contains($dict['Check-out:']) . ']')->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $text = $parser->getHTMLBody();
        $text = str_replace('：', ':', $text);
        $text = text($text);

        if (preg_match("/^(.+)(?:\n+.*){0,8}$/", $text)) {
            $text = preg_replace("/^>+ /m", "", strip_tags($parser->getBody()));
        }
        $this->parseText($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@marriott.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query('//node()[contains(normalize-space(),"This Marriott.com reservation") or contains(.,"marriott.com")]')->length === 0
            && false === stripos($parser->getSubject(), 'www.marriott.')
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Bankside Hotel, Autograph Collection")]')->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Confirmation number:']) && $this->http->XPath->query('//node()[' . $this->contains($dict['Confirmation number:']) . ']')->length > 0
                && !empty($dict['Check-out:']) && $this->http->XPath->query('//node()[' . $this->contains($dict['Check-out:']) . ']')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function amount($s, $currency = null)
    {
        if (($s = re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellationText)
    {
        if (preg_match("/You may cancel your reservation for no charge before (?<time>[\d\:]+\s+[AP]M) local hotel time on (?<date>\w+\s+\d+\,\s+\d{4}) \(/u", $cancellationText, $m)
            || preg_match("/You may cancel your reservation for no charge until (?<time>[\d\:]+\s+[AP]M) hotel time on (?<date>\w+\s+\d+\,\s+\d{4})/u", $cancellationText, $m)
            || preg_match("/You may cancel your reservation for no charge before (?<time>[\d\:]+\s+[AP]M) local hotel time on .* (?<date>\w+\s+\d+\,\s+\d{4}) /u", $cancellationText, $m)
            // fr
            || preg_match("/Vous pouvez annuler votre réservation gratuitement avant (?<time>\d{1,2}h\d{2}) \(heure locale de l hôtel\) le (?<date>\d+\s+\w+\s+\d{4}) \(/u", $cancellationText, $m)
            // es
            || preg_match("/Podrá cancelar su reserva sin cargo hasta las (?<time>\d{1,2}:\d{2}) h, hora local del hotel, del (?<date>\d+\s+de\s+\w+\s+de\s+\d{4}) \(/u", $cancellationText, $m)
            // zh
            || preg_match("/您可以在 (?<date>\d{4}年\d{1,2}月\d{1,2}日) 酒店当地时间 (?<time>(?:下午|上午)?\d{1,2}:\d{2}) 前免费取消预订/u", $cancellationText, $m)
        ) {
            $m['time'] = preg_replace("/^\s*(\d{1,2})h(\d{2})\s*$/", '$1:$2', $m['time']);
            $m['time'] = preg_replace("/^\s*下午(\d{1,2}:\d{2})\s*$/", '$1 pm', $m['time']);
            $m['time'] = preg_replace("/^\s*上午(\d{1,2}:\d{2})\s*$/", '$1 am', $m['time']);
            $date = $this->normalizeDate($m['date']);
            $h->booked()
                ->deadline(!empty($date) ? strtotime($m['time'], $date) : null);
        }

        if (preg_match("/You may cancel your reservation for no charge until (?<date>\w+\s+\d+\,\s+\d{4}) \(/u", $cancellationText, $m)
            || preg_match("/Please note that you may cancel your reservation for no charge until (?<date>\w+\s+\d+\,\s+\d{4})/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m['date']));
        }
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // October 10, 2020, 11:59 PM
            '#^\s*(?:[\w\-]+, *)?(\w+)\s*(\d+)\,\s+(\d+)\,\s+([\d\:]+\s+[AP]M)\s*$#iu',
            // October 10, 2020
            // Tuesday, August 12, 2014
            '#^\s*(?:[\w\-]+, *)?(\w+)\s*(\d+)\,\s+(\d{4})\s*$#u',
            // 2019年8月8日
            '/^\s*(\d{4})\s*[年]\s*(\d{1,2})\s*[月]\s*(\d{1,2})\s*[日](\s+[[:alpha:]]+)?\s*$/u',
            // 2019年8月8日, 12:00 PM
            '/^\s*(\d{4})\s*[年]\s*(\d{1,2})\s*[月]\s*(\d{1,2})\s*[日]\s*\,\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/u',
            // vendredi 8 septembre 2023
            '#^\s*[[:alpha:]\-]+\s+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$#u',
            // sábado, 15 de julio de 2023
            '#^\s*(?:[[:alpha:]\-]+,)?\s*(\d+)\s+de\s+([[:alpha:]]+)\s+de\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2 $1 $3',
            '$3-$2-$1',
            '$3-$2-$1, $4',
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'pt')) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->debug('$str = '.print_r( $str,true));

        return strtotime($str);
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
