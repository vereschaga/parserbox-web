<?php

namespace AwardWallet\Engine\resy\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationBooked extends \TAccountChecker
{
    public $mailFiles = "resy/it-117103825.eml, resy/it-121870233.eml, resy/it-53707785.eml, resy/it-53734786.eml, resy/it-681254332.eml, resy/it-83099465.eml, resy/it-83854078.eml, resy/it-883383144.eml";

    public $reFrom = '@resy.com';

    public $reSubject = [
        'fr' => ['Votre réservation au', 'Votre réservation chez'],
        'en' => ['Your Reservation at'],
        'de' => ['Deine Reservierung im'],
        'pt' => ['Sua reserva em'],
        'es' => ['Tu reserva en'],
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Get Directions"],
        "pt" => ["Como chegar"],
        "es" => ["Obtener direcciones"],
        "fr" => ['Obtenir des directions', 'Obtenir les directions'],
        "de" => ['Wegbeschreibung aufrufen'],
    ];

    public static $dictionary = [
        'pt' => [
            'statusPhrases'  => ['Reserva'],
            'statusVariants' => ['feita'],
            // 'Ticket' => '',
            'Party of'       => ['Grupo de', 'Party of'],
            'Get Directions' => 'Como chegar',
            // 'Total Charged' => '',
            // 'Base Price' => '',
            // 'thankYou' => '',
        ],
        'es' => [
            'statusPhrases'  => ['Reserva'],
            'statusVariants' => ['realizada'],
            // 'Ticket' => '',
            //'Party of'       => '',
            'Get Directions' => 'Obtener direcciones',
            'Total Charged' => 'Total cobrado',
            'Base Price' => 'Subtotal',
            // 'thankYou' => '',
        ],
        'fr' => [ // it-83854078.eml
            'statusPhrases'  => ['Réservation', 'Votre réservation a été'],
            'statusVariants' => ['faite', 'modifiée'],
            // 'Ticket' => '',
            'Party of'       => 'Table pour',
            'Get Directions' => ['Obtenir des directions', 'Obtenir les directions'],
            // 'Total Charged' => '',
            // 'Base Price' => '',
            // 'thankYou' => '',
        ],
        'de' => [ // it-83854078.eml
            'statusPhrases'  => ['Reservierung'],
            'statusVariants' => ['gebucht'],
            // 'Ticket' => '',
            //'Party of'       => '',
            'Get Directions' => 'Wegbeschreibung aufrufen',
            // 'Total Charged' => '',
            // 'Base Price' => '',
            // 'thankYou' => '',
        ],
        'en' => [
            'statusPhrases' => [
                'Your reservation has been successfully',
                'Your reservation has been',
                'Reservation',
            ],
            'statusVariants' => ['changed', 'booked'],
            'Get Directions' => 'Get Directions',
            'thankYou'       => 'Thank you for making a reservation',
        ],
    ];

    public $date = 0;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = str_replace('w:lsdexception','', $parser->getHTMLBody());

        $this->http->SetEmailBody($body);

        $text = str_replace(['> ', '>=20', '=E2=80=99s'], '', strip_tags($parser->getBodyStr()));

        $this->assignLang($parser->getBodyStr());

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $this->date = strtotime($parser->getDate());

        $event = $email->add()->event();

        $thankYouText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('thankYou'))}]/ancestor::tr[count(descendant::text()[normalize-space()])>1][1]"));

        if (preg_match("/{$this->opt($this->t('thankYou'))}[.!? ]*\n+[ ]*(.{2,250}?)[ ]*(?:\n|$)/", $thankYouText, $m)
            || preg_match("/{$this->opt($this->t('thankYou'))}.*?[.!?]+[ ]*([[:upper:]][ [:lower:]].{2,250}?)(?:[ ]*\n{2}|\s*$)/u", $thankYouText, $m)
        ) {
            $event->general()->notes($m[1]);
        }

        $status = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t('statusPhrases'))} and {$this->contains($this->t('statusVariants'))}][1]", null, true, "/{$this->opt($this->t('statusPhrases'))}[ ]+({$this->opt($this->t('statusVariants'))})[ ]*[.!]$/");

        if (!empty($status)) {
            $event->general()
                ->status($status);
        }

        $event->general()
            ->noConfirmation();

        // it-117103825.eml
        $xpathIcoTicket = "//tr[ count(*)=2 and *[1][descendant::img[contains(@src,'/ico_ticket.')] and normalize-space()=''] and *[2][normalize-space()] ]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr";

        $eventName = $this->http->FindSingleNode($xpathIcoTicket . "[{$this->eq(['EVENT', 'Event', 'event'])}]/following-sibling::tr[normalize-space()][1]");

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//img[contains(@src, 'calendar')]/preceding::text()[normalize-space()][1]");
        }

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Get Directions'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]/preceding::text()[normalize-space()][1]/ancestor::td[1]");
        }

        $location = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][descendant::img[contains(@src,'/ico_pin.')] and normalize-space()=''] ]/*[2]")
            ?? $this->http->FindSingleNode("//tr[{$this->starts($this->t('Party of'))}]/preceding-sibling::tr[normalize-space()][2]")
            ?? $this->http->FindSingleNode($xpathIcoTicket . "[2]")
        ;

        $event->setName($eventName ?? $location);

        if (!empty($location)) {
            $eventAddress = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Get Directions'))}]/preceding::tr[{$this->starts($location)}][1]/following::tr[normalize-space()][1]");

            if (!empty($eventAddress)) {
                $event->setAddress($eventAddress);
            }

            $event->setEventType(Event::TYPE_RESTAURANT);
        }

        if (empty($location)) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Get Directions'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

            if (!empty($address)) {
                $event->setAddress($address);
            }
        }

        if (empty($event->getAddress())) {
            if (preg_match("/{$this->opt($this->t('Download our app'))}.+(?:Restaurant)(.+){$this->t('Get Directions')}/us", $text, $m)) {
                $event->setAddress(preg_replace("/\s+/u", " ", $m[1]));
            }
        }

        $date = $time = null;

        $dateVal = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][descendant::img[contains(@src,'/ico_calendar.') or contains(@src,'/ic_calendar@2x.') or contains(@src,'ic_calendar%402x')] and normalize-space()=''] ]/*[2]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//tr[{$this->starts($this->t('Party of'))}]/preceding-sibling::tr[normalize-space()][1]", null, true, "/^.*\d.*$/")
        ;

        if ($this->date && preg_match("/^(?<date>.*\d.*?)\s+(?:at\s+)?(?<time>{$patterns['time']})$/i", $dateVal, $m)) {
            $date = $this->normalizeDate($m['date']);
            $time = $m['time'];
        }

        if ($date && $time) {
            $event->booked()->start(strtotime($time, $date));
        }

        if (!empty($event->getStartDate())) {
            $event->booked()->noEnd();
        }

        $event->booked()->guests($this->http->FindSingleNode("//tr[ count(*)=2 and *[1][descendant::img[contains(@src,'/ico_ticket.')] and normalize-space()=''] ]/*[2]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Ticket'))}/i")
            ?? $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Party of'))}]", null, true, "/{$this->opt($this->t('Party of'))}\s+(\d+)/i")
            ?? $this->http->FindSingleNode("//img[contains(@src, 'guests')]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)/"))
        ;

        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Get Directions'))}]/following::text()[normalize-space()][1]", null, true, "/^[+(\d][-. \d)(]{5,}[\d)]$/");
        $event->place()->phone($phone, false, true);

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total Charged'))}]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+?)$/', $totalPrice, $m)) {
            // $128.88
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $event->price()->currency($m['currency'])->total(PriceHelper::parse($m['amount'], $currencyCode));

            $m['currency'] = trim($m['currency']);
            $baseFare = $this->http->FindSingleNode("//td[{$this->starts($this->t('Base Price'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $baseFare, $matches)) {
                $event->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[1][{$this->starts($this->t('Base Price'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('Total Charged'))}]] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $feeCharge, $matches)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                    $event->price()->fee($feeName, PriceHelper::parse($matches['amount'], $currencyCode));
                }
            }
        }

        $event->setEventType(Event::TYPE_RESTAURANT);

        $cancellation = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}] ]/tr[normalize-space()][2]");
        $event->general()->cancellation($cancellation, false, true);

        $email->setType('ReservationBooked' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/{$this->reFrom}/", $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".resy.com/") or contains(@href,"newsletter.resy.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"Resy.com") or contains(.,"@resy.com")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang($parser->getBodyStr());
    }

    private function assignLang($text)
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            $this->logger->debug(var_export($phrases, true));
            if (!is_string($lang) || empty($phrases['Get Directions'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Get Directions'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }


            if (is_array($phrases['Get Directions'])){
                foreach ($phrases['Get Directions'] as $phrase){
                    if (stripos($text, $phrase)) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } else if (is_string($phrases['Get Directions'])){
                if (stripos($text, $phrases['Get Directions'])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }


    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            // 20 de jun
            '/^(\d{1,2})\s*de[ ]+([[:alpha:]]{3,})$/u',
            // Friday, Feb 7
            '/^([-[:alpha:]]+)[,\s]+([[:alpha:]]+)\.?[ ]*(\d{1,2})$/u',
            // dim., 17 oct.
            '/^([-[:alpha:]]+)[ .]*,[ ]*(\d{1,2})[ ]*([[:alpha:]]+)[. ]*$/u',
            // 27. Sa Nov
            '/^(\d{1,2})\.?\s*([-[:alpha:]]{2,})[ ]+([[:alpha:]]{3,})$/u',
        ];
        $out = [
            '$1 $2 ' . $year,
            '$1, $3 $2 ' . $year,
            '$1, $2 $3 ' . $year,
            '$2, $1 $3 ' . $year,
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\b\d{1,2}\s+([[:alpha:]]+)\s+\d{4}\b/u', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match('/^([-[:alpha:]]+),\s+(\d{1,2}\s+[[:alpha:]]+\s+\d{4}\b)/u', $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m[2], $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
}
