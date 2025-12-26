<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RateYourStay extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang = '';
    public $detectSubject = [
        // en
        'Final Reminder: Would you recommend',
        ', would you recommend',
        // zh
        '最終提示：你會不會推介',
        '，你推薦入住',
        // ja
        'ユーザーにおすすめしたいと思いますか？',
        // it
        'Ultimo promemoria: Consiglieresti',
        // pl
        'Ostatnie przypomnienie: Czy polecisz obiekt',
        // ko
        '숙박을 추천하시겠습니까?',
        // es
        ', ¿recomendarías',
        // pt
        ', recomendaria o ',
    ];

    public static $dictionary = [
        'en' => [
            ", let's rate your stay!" => ", let's rate your stay!",
            'Booking ID:'             => 'Booking ID:',
            'HotelNameStart'          => 'Rate your stay at',
            'HotelNameEnd'            => '',
            '→'                       => '→',
        ],
        'zh' => [
            ", let's rate your stay!" => ["，為你的住宿體驗評分吧！", '，為您的住宿體驗評分吧！'],
            'Booking ID:'             => ['預訂編號：', '訂單編號：'],
            'HotelNameStart'          => ['為你的', '為您的'],
            'HotelNameEnd'            => '住宿體驗評分',
            '→'                       => '→',
        ],
        'ja' => [
            ", let's rate your stay!" => 'さん、クチコミを投稿しましょう！',
            'Booking ID:'             => ['予約ID：'],
            'HotelNameStart'          => [''],
            'HotelNameEnd'            => 'での宿泊の感想をぜひお聞かせください。',
            '→'                       => '～',
        ],
        'it' => [
            ", let's rate your stay!" => ', valuta il tuo soggiorno!',
            'Booking ID:'             => ['Numero di prenotazione:'],
            'HotelNameStart'          => ['Valuta il tuo soggiorno presso'],
            'HotelNameEnd'            => '.',
            '→'                       => '→',
        ],
        'pl' => [
            ", let's rate your stay!" => ', oceńmy Twój pobyt!',
            'Booking ID:'             => ['Identyfikator rezerwacji:'],
            'HotelNameStart'          => ['Oceń swój pobyt w obiekcie'],
            'HotelNameEnd'            => '.',
            '→'                       => '→',
        ],
        'ko' => [
            ", let's rate your stay!" => '님, 숙박을 평가해 주세요!',
            'Booking ID:'             => ['예약 번호:'],
            'HotelNameStart'          => [''],
            'HotelNameEnd'            => '에서의 숙박을 평가하고',
            '→'                       => '→',
        ],
        'es' => [
            ", let's rate your stay!" => ', vamos a valorar tu estancia!',
            'Booking ID:'             => ['Número de reserva:'],
            'HotelNameStart'          => ['Califica tu estancia en'],
            'HotelNameEnd'            => '.',
            '→'                       => '→',
        ],
        'pt' => [
            ", let's rate your stay!" => ', classifique a sua estadia!',
            'Booking ID:'             => ['ID de reserva:'],
            'HotelNameStart'          => ['Classifique a sua estadia no'],
            'HotelNameEnd'            => '.',
            '→'                       => '→',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]agoda\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
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
        if ($this->http->XPath->query('//a[contains(@href,".agoda.com/") or contains(@href,"www.agoda.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Agoda Company Pte Ltd")]')->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases[", let's rate your stay!"]) && $this->http->XPath->query("//*[{$this->contains($phrases[", let's rate your stay!"])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict[", let's rate your stay!"]) && $this->http->XPath->query("//node()[{$this->contains($dict[", let's rate your stay!"])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $email->setType('RateYourStay' . ucfirst($this->lang));

        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID:'))}]",
            null, true, "/^\s*{$this->opt($this->t('Booking ID:'))}\s*(\d{5,})\s*$/u"));

        // Hotels
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t(", let's rate your stay!"))}]",
                null, true, "/^\s*(?:¡)?([[:alpha:] \-]+)\s*{$this->opt($this->t(", let's rate your stay!"))}\s*$/u"), false);

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('HotelNameStart'))}][{$this->contains($this->t('HotelNameEnd'))}]",
                null, true, "/^\s*{$this->opt($this->t('HotelNameStart'))}\s*(.+?)\s*{$this->opt($this->t('HotelNameEnd'))}\s*$/u"))
            ->noAddress();

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('→'))}]",
                null, true, "/^\s*\W*\s*(.+?)\s*{$this->opt($this->t('→'))}\s*.+?\s*$/u")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('→'))}]",
                null, true, "/^\s*\W*\s*.+?\s*{$this->opt($this->t('→'))}\s*(.+?)\s*$/u")))
        ;

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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function normalizeDate(?string $text)
    {
        // $this->logger->debug('$text starts =  ' . print_r($text, true));

        if (preg_match('/^\s*([[:alpha:]\p{Thai}]{3,})[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // September 2, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\s*(\d{1,2})(?:[,.\s]+| de )([[:alpha:]\p{Thai}]{3,})(?:[,.\s]+| de )(\d{4})$/u', $text, $m)) {
            // 21 novembre 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\s*(?:[[:alpha:]]+ )?(\d{4}) ?[年년] ?(\d{1,2}) ?[月월] ?(\d{1,2}) ?[日일]\s*$/u', $text, $m)) {
            // 2024年12月25日
            // 2024년 11월 07일
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        }

        if (!empty($day) && !empty($month) && !empty($year)) {
            // $this->logger->debug('$day = ' . print_r($day, true));
            // $this->logger->debug('$month = ' . print_r($month, true));
            // $this->logger->debug('$year = ' . print_r($year, true));

            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return strtotime($m[1] . '/' . $day . '/' . $year);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return strtotime($day . ' ' . $month . ' ' . $year);
        }

        return null;
    }
}
