<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookNowJunkStatement extends \TAccountChecker
{
    public $mailFiles = "hotels/it-59353250.eml, hotels/it-59455857.eml";
    public static $dictionary = [
        'en' => [
            "Membership Number:" => "Membership Number:",
        ],
        'zh' => [
            "Membership Number:" => "會員編號:",
        ],
    ];

    private $detectFrom = ['info@mail.hotels.com', 'info@mail.hoteis.com'];
    private $detectSubject = [
        // en
        'Book for ', // Book for New Orleans now. You could collect 4 nights with Hotels.com® Rewards.
        ' going to be your next trip?', // Gerard, is Rapid City going to be your next trip?
        'Grab a great deal before it\'s too late!', // Your Washington trip - Grab a great deal before it's too late!
        // sv
        'Boka inför din resa till ', // Boka inför din resa till New York nu. Du kan samla 3 nätter med Hotels.com® Rewards.
        'Haffa ett supererbjudande för ', // Haffa ett supererbjudande för New York medan du kan!.
        // pt
        'Reserve a sua estadia em ', // Reserve a sua estadia em Los Angeles. Você pode acumular 4 noites com o Hoteis.com™ Rewards.
        // it
        'Il tuo viaggio a ', // Il tuo viaggio a Varadero: non farti scappare queste fantastiche offerte!
        'Prenota una sistemazione a ', // Il tuo viaggio a Varadero: non farti scappare queste fantastiche offerte!
        // de
        'Jetzt in ', // Jetzt in Grossarl buchen. Mit Hotels.com® Rewards könnten Sie 2 Übernachtungen sammeln.
        ' – jetzt buchen. ', // Kühlungsborn – jetzt buchen. Sie könnten mit Hotels.com® Rewards 1 Stempel sammeln.
        // ko
        '여행을 예약하세요', // 지금 사천 여행을 예약하세요. Hotels.com™ 호텔스닷컴 리워드로 3박을 적립하실 수 있어요.
        // no
        'Bestill overnatting i ', // Bestill overnatting i Kristiansand nå. Du kan samle 2 netter med Hotels.com™ Rewards.
        ' – sikre deg et godt tilbud før det er for sent!', // Din reise til Kristiansand – sikre deg et godt tilbud før det er for sent!
        // fr
        'Réservez maintenant un séjour à', // Réservez maintenant un séjour à Saint-Adolphe-d'Howard. Vous pourriez accumuler 6 étampes avec Hotels.com™ Rewards.
        'Réservez un séjour à ', // Réservez un séjour à Rouen dès maintenant. Vous pourriez cumuler 1 vignette avec Hotels.com® Rewards.
        'Saisissez une super offre avant ', // Votre voyage à Pérols - Saisissez une super offre avant qu'elle ne s'envole !
        'Profitez d’une excellente offre avant qu’il ne soit trop tard!', // Votre voyage à Saint-Adolphe-d'Howard - Profitez d’une excellente offre avant qu’il ne soit trop tard!
        'vous planifiez un voyage à ', // Méganne, vous planifiez un voyage à Macau SAR ?
        'sera votre prochaine destination?', // Marie-Andree, est-ce que Saint-Adolphe-d'Howard sera votre prochaine destination?
        // zh
        '立即預訂', // 立即預訂瑞里住宿，就可以集 2 個 Hotels.com™ Rewards 印花。
        '把握超值價格，錯過就沒囉！', // 瑞里之旅：把握超值價格，錯過就沒囉！
        '預訂', // 預訂宜蘭住宿，Hotels.com™ Rewards 即送你 1 個印花！
        // pl
        'zgarnij świetną ofertę na swój wyjazd, zanim będzie za późno', // Boleslawiec — zgarnij świetną ofertę na swój wyjazd, zanim będzie za późno!
        'Zarezerwuj już teraz za', // Zarezerwuj już teraz za Boleslawiec. Możesz zebrać 1 pieczątkę w programie Hotels.com® Rewards.
        ' — czy to tam udasz się w następną podróż', // Boleslawiec — czy to tam udasz się w następną podróż?
        // he
        'הזמינו עכשיו', //    הזמינו עכשיו ב-אלניה. תוכלו לאסוף חותמת 1 עם תכנית Hotels.com™ Rewards.//
        'האם אלניה הולך להיות היעד הבא שלכם?', // Itamar, האם אלניה הולך להיות היעד הבא שלכם?//
        ' - מצאו מבצע נהדר לפני שיהיה מאוחר מדי!', //הנסיעה שלכם אל אלניה - מצאו מבצע נהדר לפני שיהיה מאוחר מדי!
    ];

    private $detectText = [
        'en' => ['Continue your search for '],
        'sv' => ['Återuppta din sökning för '],
        'pt' => ['Continue a sua busca em '],
        'it' => ['Continua a cercare la tua sistemazione a'],
        'de' => ['Setzen Sie Ihre Suche in '],
        'ko' => ['여행을 계속 검색해 보세요'],
        'no' => ['Fortsett søket ditt for '],
        'fr' => ['Continuez votre recherche pour '],
        'zh' => ['繼續搜尋'],
        'pl' => ['— kontynuuj wyszukiwanie'],
        'he' => ['המשיכו את החיפוש שלכם'],
    ];

    private $lang;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $t) {
            if (!empty($t['Membership Number:']) && !empty($this->http->FindSingleNode("//text()[" . $this->starts($t["Membership Number:"]) . "]"))) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $class = explode('\\', __CLASS__);
            $email->setType(end($class));

            if (self::detectEmailByBody($parser) === true) {
                $email->setIsJunk(true);

                return $email;
            }
        }

        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->eq($t["Membership Number:"]) . "]/following::text()[normalize-space()][1]",null, true,
            "/^\s*(\d{5,12})\s*$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[" . $this->starts($t["Membership Number:"]) . "]",null, true,
                "/^" . $this->preg_implode($t["Membership Number:"]) . "\s*(\d{5,12})\s*$/");
        }

        if (empty($number)) {
            return $email;
        }

        $st->addProperty('Number', $number);

        // Balance
        $st->setNoBalance(true);

        // Status
        if ($this->http->FindSingleNode("//text()[" . $this->starts($t["Membership Number:"]) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'purple_Inv.png')] or ancestor::table[contains(@style, '#7B1FA2')]]")) {
            $st->addProperty('Status', "Member");
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($t["Membership Number:"]) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'silver_Inv.png')] or ancestor::table[contains(@style, '#4F6772')]]")) {
            $st->addProperty('Status', "Silver");
        }

        if ($this->http->FindSingleNode("//text()[" . $this->starts($t["Membership Number:"]) . "]/ancestor::tr[1][.//img]"
            . "[.//img[contains(@src, 'gold_Inv.png')] or ancestor::table[contains(@style, '#8F6F32')]]")) {
            $st->addProperty('Status', "Gold");
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailByHeaders($parser->getHeaders()) === false) {
            return false;
        }

        foreach ($this->detectText as $lang => $detectText) {
            if ($this->http->XPath->query("//*[" . $this->starts($detectText) . "]")->length > 0
            || (in_array($lang, ['ko', 'pl']) && !empty($this->http->FindSingleNode("//text()[" . $this->contains($detectText) . "]", null, true, "#^\s*[\w ]+\s+" . $this->preg_implode($detectText) . "$#u")))) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if (empty($headers["subject"])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function eq($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'normalize-space(' . $text . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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
