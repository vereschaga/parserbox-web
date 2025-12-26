<?php

namespace AwardWallet\Engine\cheapflightsfares\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpdatedFlight extends \TAccountChecker
{
	public $mailFiles = "cheapflightsfares/it-833316610.eml, cheapflightsfares/it-833316612.eml, cheapflightsfares/it-833316628.eml, cheapflightsfares/it-859529339.eml";
    public $subjects = [
        '/^Flight is On time\: Flight .+ is on\-time Ref\. BookingID.+/',
        '/^Flight .+ has a change in .+ Ref\. BookingID/',
        '/^Flight is Delayed\: Flight .+ is delayed by .+ Ref\. BookingID/',
        //es
        '/^Vuelo .+ tiene un cambio en departure gate Ref\. BookingID/'
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Please find your flight details as under"],
        "es" => ["Encuentre los detalles de su vuelo como se muestra a continuación"],
    ];

    public static $dictionary = [
        'en' => [

        ],
        'es' => [
            'Please find your flight details as under:' => 'Encuentre los detalles de su vuelo como se muestra a continuación:',
            'Flight' => 'El vuelo',
            'Hi' => ['¡Hola']
        ],

    ];
    private $providerCode;
    private static $detectsProvider = [
        'cheapflightsfares' => [
            'from'           => 'cheapflightsfares.com',
            'bodyHtml'       => [
                'Cheapflightsfares LLC',
                "//img[contains(@src, 'cff.png')]",
            ],
        ],
        'travelopick' => [
            'from'           => 'travelopick.com',
            'bodyHtml'       => [
                'www.travelopick.com',
                "//img[contains(@src, 'travelopick.png')]"
            ],
        ],
        'edestinos' => [
            'from'           => 'edestinos.com',
            'bodyHtml'       => [
                'eSky',
                "//img[contains(@src, 'edestinos_icon.png')]"
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectsProvider as $code => $detect) {
            if (isset($headers['from']) && stripos($headers['from'], $detect['from']) !== false) {
                $this->providerCode = $code;

                foreach ($this->subjects as $subject) {
                    if (preg_match($subject, $headers['subject'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        foreach (self::$detectsProvider as $code => $detect) {
            $detectedProvider = false;
            if (!empty($detect['bodyHtml'])) {
                foreach ($detect['bodyHtml'] as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && $this->http->XPath->query("//node()[{$this->contains($search)}]")->length > 0)
                    ) {
                        $this->providerCode = $code;
                        $detectedProvider = true;
                        break;
                    }
                }
            }

            if ($detectedProvider === false) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Estimated Date & Time'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectsProvider as $code => $detect) {
            if (strpos($from, $detect['from']) !== false){
                return true;
            };
        }
        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->assignLang();

        if ($this->providerCode !== null){
            $email->setProviderCode($this->providerCode);
        }

        $this->UpdatedFlight($email, $subject = $parser->getSubject());
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function UpdatedFlight(Email $email, $subject)
    {
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        if (preg_match("/{$this->opt($this->t('Ref. BookingID'))}[ ]+([A-Z\d]{5,})$/u", $subject, $m)){
            $f->ota()->confirmation($m[1], "BookingID");
        }

        $f->addTraveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Please find your flight details as under:'))}]/preceding::text()[normalize-space()][1]", null, false, "/^{$this->opt($this->t('Hi'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\!$/"), true);

        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Please find your flight details as under:'))}]/following::div[normalize-space()][1]", null, false, "/^(.+)[ ]*{$this->t('Flight')}\s*\d{1,4}\s+/"))
            ->number($this->http->FindSingleNode("//text()[{$this->eq($this->t('Please find your flight details as under:'))}]/following::div[normalize-space()][1]", null, false, "/^.+[ ]*{$this->t('Flight')}\s*(\d{1,4})\s+/"));

        $depTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Airport:'))}]/ancestor::div[2]/following-sibling::div[1]/descendant::text()[normalize-space()][{$this->eq($this->t('Estimated Date & Time:'))}]/following::text()[normalize-space()][1]", null, false, "/^(\d{4}\-\d{1,2}\-\d{1,2}\s+\d{1,2}\:\d{2}\:\d{2})$/");

        if ($depTime !== null){
            $s->departure()
                ->date($this->normalizeDate($depTime));
        }

        $arrTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Airport:'))}]/ancestor::div[2]/following-sibling::div[1]/descendant::text()[normalize-space()][{$this->eq($this->t('Estimated Date & Time:'))}]/following::text()[normalize-space()][1]", null, false, "/^(\d{4}\-\d{1,2}\-\d{1,2}\s+\d{1,2}\:\d{2})\:\d{2}$/");

        if ($arrTime !== null){
            $s->arrival()
                ->date($this->normalizeDate($arrTime));
        }

        $s->departure()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Airport:'))}]/following::text()[normalize-space()][1]", null, false,"/^(.+)\s+\([A-Z]{3}\)$/"))
            ->code($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Airport:'))}]/following::text()[normalize-space()][1]", null, false,"/^.+\s+\(([A-Z]{3})\)$/"));

        $s->arrival()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Airport:'))}]/following::text()[normalize-space()][1]", null, false,"/^(.+)\s+\([A-Z]{3}\)$/"))
            ->code($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Airport:'))}]/following::text()[normalize-space()][1]", null, false,"/^.+\s+\(([A-Z]{3})\)$/"));

        $depTerminal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Airport:'))}]/ancestor::div[2]/following-sibling::div[2]/descendant::div[normalize-space()][{$this->eq($this->t('Terminal:'))}][last()]/following-sibling::div[normalize-space()][1]", null, false, "/^(?!NA).+$/");

        if ($depTerminal !== null){
            $s->departure()
                ->terminal($depTerminal);
        }

        $arrTerminal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Airport:'))}]/ancestor::div[2]/following-sibling::div[2]/descendant::div[normalize-space()][{$this->eq($this->t('Terminal:'))}][last()]/following-sibling::div[normalize-space()][1]", null, false, "/^(?!NA).+$/");

        if ($arrTerminal !== null){
            $s->arrival()
                ->terminal($arrTerminal);
        }
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsProvider);
    }

    private function normalizeDate($str)
    {
        return strtotime($str);
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $value) {
                if ($this->http->XPath->query("//*[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'RUB' => ['Руб.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'CAD' => ['C$']
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "normalize-space(.)=\"{$s}\"";
            }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
