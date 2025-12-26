<?php

namespace AwardWallet\Engine\uber\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourRidePdf extends \TAccountChecker
{
    public $mailFiles = "uber/it-34019868.eml, uber/it-34313359.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Thanks for '   => ['Thanks for ', "Here's your receipt for your "],
            'You rode with' => ['You rode with'],
            'taxes'         => ['Tolls, Surcharges, and Fees', 'Tip'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@uber.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = self::detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && strpos($textPdf, 'Uber') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('YourRidePdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text)
    {
        $transfer = $email->add()->transfer();

        $youRodeStart = $this->strposArray($text, $this->t('You rode with'));

        if ($youRodeStart === false) {
            $this->logger->debug('You rode not found!');

            return;
        }

        $headerText = substr($text, 0, $youRodeStart);

        $date = 0;

        /*
            Mon, Feb 18, 2019
            Thanks for tipping, Francisco
            -------------------------------
            25 June 2020
            Here's your receipt for your ride, inigo
         */
        $patterns['dateTraveller'] = "/"
                . "^[ ]*(?<date>.{6,})$\n+"
                . "^[ ]*{$this->opt($this->t('Thanks for '))}\w+[ ]*,[ ]*(?<traveller>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$"
                . "/mu";

        if (preg_match($patterns['dateTraveller'], $headerText, $m)) {
            $date = strtotime($m['date']);
            $transfer->general()->traveller($m['traveller']);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Total'))}[ ]{2,}(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/m", $headerText, $m)) {
            // $45.56
            $transfer->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total($this->normalizeAmount($m['amount']))
            ;
            $m['currency'] = trim($m['currency']);

            if (preg_match("/^[ ]*{$this->opt($this->t('Subtotal'))}[ ]{2,}" . preg_quote($m['currency'], '/') . "[ ]*(?<amount>\d[,.\'\d]*)$/m", $headerText, $matches)) {
                $transfer->price()->cost($this->normalizeAmount($matches['amount']));
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('taxes'))}[ ]{2,}" . preg_quote($m['currency'], '/') . "[ ]*(?<amount>\d[,.\'\d]*)$/m", $headerText, $matches)) {
                $transfer->price()->tax($this->normalizeAmount($matches['amount']));
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Promotions'))}[ ]{2,}-[ ]*" . preg_quote($m['currency'], '/') . "[ ]*(?<amount>\d[,.\'\d]*)$/m", $headerText, $matches)) {
                $transfer->price()->discount($this->normalizeAmount($matches['amount']));
            }
        }

        $youRodeText = substr($text, $youRodeStart);
        $youRodeText = str_replace("\nmin", " min", $youRodeText);

        $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?'; // 4:19PM    |    2:00 p.m.

        /*
            UberX    30.22 miles | 34 min
            08:08pm | 4281 Ironwood Ct, Weston, FL
            08:43pm | Miami International Airport, Miami, FL
            -------------------------------
            UberX   5.73 miles | 14 min(s)
            20:39 | 12 Channel St, Boston, MA 02210, USA
            20:53 | 15A Tremont St, Cambridge, MA 02139, USA
         */
        $patterns['segment'] = "/"
            . "^[ ]*(?<carType>[^\n\|]+)[ ]{2,}(?<miles>\d[^\n\|]+?)[ ]*\|[ ]*(?<duration>\d[^\n\|]+)$"
            . "\s+^[ ]*(?<depTime>{$patterns['time']})[ ]*\|[ ]*(?<depLocation>.{3,})$"
            . "\s+^[ ]*(?<arrTime>{$patterns['time']})[ ]*\|[ ]*(?<arrLocation>.{3,})$"
            . "/m";
        preg_match_all($patterns['segment'], $youRodeText, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $m) {
            $s = $transfer->addSegment();

            $s->extra()
                ->type($m['carType'])
                ->miles($m['miles'])
                ->duration($m['duration'])
            ;

            if ($date) {
                $s->departure()->date(strtotime($m['depTime'], $date));
                $s->arrival()->date(strtotime($m['arrTime'], $date));
            }

            $s->departure()->address($m['depLocation']);
            $s->arrival()->address($m['arrLocation']);
        }

        $transfer->general()->noConfirmation();
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Thanks for ']) || empty($phrases['You rode with'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Thanks for ']) !== false
                && $this->strposArray($text, $phrases['You rode with']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
