<?php

namespace AwardWallet\Engine\tablecheck\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "tablecheck/it-644718665.eml, tablecheck/it-646398845-cancelled.eml, tablecheck/it-651287494-ja.eml, tablecheck/it-651701112.eml, tablecheck/it-861193547-pt.eml, tablecheck/it-864308199-es.eml, tablecheck/it-866474531-zh.eml, tablecheck/it-879212155-de.eml";

    public $subjects = [
        'Reservierung gebucht bei ', // de
        'Reserva feita em ', // pt
        'Reserva aceptada en ', // es
        '預約提醒 |', // zh
        '您即將進行的預約 |', // zh
        '予約確定 |', // ja
        'Reservation at ', // en
        'Your reservation at ', // en
        'Reservation booked at ', // en
        'Reservation cancelled for ', // en
        'Reservation canceled for ', // en
        'Reservation Confirmation at ', // en
        'Confirm your reservation at ', // en
    ];

    public $lang = '';

    public static $dictionary = [
        "de" => [
            // 'detectFormat' => '',
            'People' => ['Person'],
            // 'Child' => '',
            'confNumber' => ['Bestätigung'],
            'Get directions' => ['Route erstellen'],
            // 'cancelledPhrases' => [''],
            // 'Total' => '',
        ],
        "pt" => [
            // 'detectFormat' => '',
            'People' => ['Pessoas', 'Adulto'],
            'Child' => 'Criança',
            'confNumber' => ['Confirmação'],
            'Get directions' => ['Obter direções'],
            // 'cancelledPhrases' => [''],
            // 'Total' => '',
        ],
        "es" => [
            // 'detectFormat' => '',
            'People' => ['persona'],
            // 'Child' => '',
            'confNumber' => ['Confirmación'],
            'Get directions' => ['Obtener las direcciones'],
            // 'cancelledPhrases' => [''],
            // 'Total' => '',
        ],
        "zh" => [
            // 'detectFormat' => '',
            'People' => ['名'],
            // 'Child' => '',
            'confNumber' => ['確認號碼'],
            'Get directions' => ['查看地圖'],
            // 'cancelledPhrases' => [''],
            'Total' => '總',
        ],
        "ja" => [
            'detectFormat' => [
                'お客様のご予約が確定致しました。',
                'ご予約内容確認のお願い',
                '我們已經成功預訂了您的預約。',
                'ご予約内容の再送',
                'ご予約内容変更',
                'ご予約内容確認のお願い',
            ],
            'People'         => '名',
            // 'Child' => '',
            'confNumber' => ['予約番号'],
            'Get directions' => ['行き方を調べる'],
            // 'cancelledPhrases' => [''],
            // 'Total' => '',
        ],
        "en" => [
            'detectFormat' => [
                'Please confirm that you will attend your reservation',
                'We look forward to welcoming you!',
                'Get ready for your upcoming reservation.',
                "You've successfully cancelled your reservation.",
                "You've successfully canceled your reservation.",
                'Reservation Amended',
                'Reservation Accepted',
                'Reservation Cancelled',
                'Reservation Canceled',
                'Your Upcoming Reservation',
                'Still going to make it?',
                'Reservation Reminder',
                'If you need any more assistance with the reservation',
                'We will charge regardless of the reason for cancellation',
            ],
            'People'         => ['People', 'Person', 'Adult', 'person'],
            // 'Child' => '',
            'confNumber' => ['Confirmation #', 'Confirmation'],
            'Get directions' => ['Get directions', 'Obtenir des directions'],
            'cancelledPhrases' => [
                "You've successfully cancelled your reservation.", "You've successfully canceled your reservation.",
                'Your Reservation is Cancelled.', 'Your Reservation is Canceled.',
                'Reservation Cancelled', 'Reservation Canceled',
            ],
            // 'Total' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false)
                return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $href = ['.tablecheck.com/', 'www.tablecheck.com'];

        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true
            && $this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0
            && $this->http->XPath->query('//text()[starts-with(translate(.," ",""),"Copyright©TableCheck")]')->length === 0
        ) {
            return false;
        }
        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tablecheck\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEvent(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'travellerName' => '[[:alpha:]][-@.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang  |  Werthmannjake@Gmail.Com Jake
        ];

        $e = $email->add()->event();

        $traveller = $this->normalizeTraveller(trim(preg_replace('/\s*\S+@\S+\s*/', ' ', // remove email
            $this->http->FindSingleNode("//div[contains(@style,'icon-sm-person-black') or contains(@style,'icon-sm-person-white')]/ancestor::tr[1]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Confirmation #')]/following::text()[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? ''
        )));

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/"))
            ->traveller($traveller, true);

        $nameTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/preceding::text()[normalize-space()][position()<3][ ancestor::*[{$this->contains(['shop-name', 'shop-location'], "@class")}] ]");
        $eventName = implode(' / ', $nameTexts);

        if (!$eventName) {
            $eventName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/preceding::text()[normalize-space()][1]");
        }

        $e->setName($eventName)->setEventType(EVENT_RESTAURANT);

        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Get directions'))}]/preceding::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[normalize-space()='Orders']/preceding::text()[string-length()>2][1]", null, true, "/^\s*((?:Level)?\s*\d.+)/")
            ?? $this->http->FindSingleNode("//text()[normalize-space()='Restaurant Address:']/following::text()[normalize-space()][1]")
            ?? ($eventName ? $this->http->FindSingleNode("//text()[{$this->starts($eventName)}][contains(normalize-space(),',')]", null, true, "/{$this->opt($eventName)},\s*(\d+.+)/") : null)
            ?? $this->http->FindSingleNode("//div[contains(@class,'address')]")
        ;

        if (!empty($address)) {
            $e->setAddress($address);
        }

        $startDateVal = $this->http->FindSingleNode("//div[contains(@style,'icon-sm-calendar-black') or contains(@style,'icon-sm-calendar-white')]/ancestor::tr[1]", null, true, "/^.*(?:\D|\b)\d{4}(?:\D|\b).*$/")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Confirmation #')]/following::text()[normalize-space()][3]", null, true, "/^.*(?:\D|\b)\d{4}(?:\D|\b).*$/")
        ;
        $startDate = strtotime($this->normalizeDate($startDateVal));

        $startTime = $this->http->FindSingleNode("//div[contains(@style,'icon-sm-clock-black') or contains(@style,'icon-sm-clock-white')]/ancestor::tr[1]", null, true, "/^{$patterns['time']}/")
            ?? $this->http->FindSingleNode("//div[contains(@style,'icon-sm-clock-black') or contains(@style,'icon-sm-clock-white')]/ancestor::tr[1]", null, true, "/[→]\s*({$patterns['time']})/")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Confirmation #')]/following::text()[normalize-space()][4]", null, true, "/^{$patterns['time']}/")
        ;

        if (!empty($startDate) && !empty($startTime)) {
            $e->setStartDate(strtotime($startTime, $startDate))->setNoEndDate(true);
        }

        $guests = $this->http->FindSingleNode("//div[contains(@style,'icon-sm-people-black') or contains(@style,'icon-sm-people-white')]/ancestor::tr[1]", null, true, "/^(\d+)\s*{$this->opt($this->t('People'))}/i")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Confirmation #')]/following::text()[normalize-space()][5]", null, true, "/^(\d+)\s*{$this->opt($this->t('People'))}/i")
        ;

        if ($guests !== null) {
            $e->setGuestCount($guests);
        }

        $kids = $this->http->FindSingleNode("//div[contains(@style,'icon-sm-people-black') or contains(@style,'icon-sm-people-white')]/ancestor::tr[1]", null, true, "/\b(\d+)\s*{$this->opt($this->t('Child'))}/i")
            ?? $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Confirmation #')]/following::text()[normalize-space()][5]", null, true, "/\b(\d+)\s*{$this->opt($this->t('Child'))}/i")
        ;

        if ($kids !== null) {
            $e->setKidsCount($kids);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $e->general()->cancelled();

            return;
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.’‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // S$ 150.00    |    ¥ 45,540
            $currency = $this->normalizeCurrency($matches['currency']);
            $e->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currency));
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) ) {
                continue;
            }
            if (!empty($phrases['confNumber']) && $this->http->XPath->query("//text()[{$this->eq($phrases['confNumber'], "translate(.,':','')")}]")->length > 0
                || !empty($phrases['detectFormat']) && $this->http->XPath->query("//*[{$this->contains($phrases['detectFormat'])}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate(?string $str): string
    {
        $this->logger->debug($str);
        $in = [
            "/^(\d{4})\D(\d{1,2})\D(\d{1,2})\D*$/u", // 2024年3月20日 (水)
            "/^([[:alpha:]]+)[,.\s]*(\d{1,2})[,.\s]*(\d{4})\D*$/u", // Mar 6, 2024 (Wed)
            "/^(\d{1,2})[,.\s]*([[:alpha:]]+)[,.\s]*(\d{4})\D*$/u", // 15 mar. 2024 (ven)
        ];
        $out = [
            "$3.$2.$1",
            "$2 $1 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    /**
     * @param string $string Unformatted string with currency
     * @return string
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'SGD' => ['S$'],
        ];
        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency)
                    return $currencyCode;
            }
        }
        return $string;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR|様)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
            '$1',
        ], $s);
    }
}
