<?php

namespace AwardWallet\Engine\ukrail\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TicketPdf extends \TAccountChecker
{
    public $mailFiles = "ukrail/it-338428408.eml, ukrail/it-339398897.eml, ukrail/it-341197477.eml, ukrail/it-422608394.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $detectFrom = "@uz.gov.ua";
    public $detectSubject = [
        'Tickets Purchase',
        'Купівля квитків',
        'Ваш квиток на потяг',
        'Your ticket on Train',
    ];

    public $detectPdf = [
        'en' => ['BOARDING DOCUMENT'],
        'uk' => ['ПОСАДОЧНИЙ ДОКУМЕНТ'],
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'ПОСАДОЧНИЙ ДОКУМЕНТ' => 'ПОСАДОЧНИЙ ДОКУМЕНТ',
            'Прізвище, Ім’я'      => 'Family name, first name',
            'Відправлення'        => ['Від / From', 'От / From'],
            'Призначення'         => 'До / To',
            'Дата/час відпр.'     => ['Відправлення / Departure', 'Отправление / Departure'],
            'Дата/час приб.'      => ['Прибуття / Arrival', 'Прибытие / Arrival'],
            'Поїзд'               => ['Поїзд / Train', 'Поезд / Train'],
            'Вагон'               => 'Вагон / Car',
            'Місце'               => ['Місце / Place', 'Место / Place'],
            'ВАРТ'                => ['PAID BACK / ВАРТІСТЬ', 'PAID BACK / СТОИМОСТЬ'],
        ],
        'uk' => [
            //            'ПОСАДОЧНИЙ ДОКУМЕНТ' => '',
            //            'Прізвище, Ім’я' => [''],
            //            'Відправлення' => [''],
            //            'Призначення' => [''],
            //            'Дата/час відпр.' => [''],
            //            'Дата/час приб.' => [''],
            //            'Поїзд' => [''],
            //            'Вагон' => [''],
            //            'Місце' => [''],
            //            'ВАРТ' => [''],
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'АТ "УКРЗАЛІЗНИЦЯ"') === false
                && stripos($text, "АТ 'УКРЗАЛІЗНИЦЯ'") === false
                && stripos($text, 'JSC "UKRZALIZNYTSYA"') === false
            ) {
                continue;
            }

            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($text)) {
                $this->parseEmailPdf($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset($this->detectPdf, $this->lang)) {
            return false;
        }

        foreach ($this->detectPdf as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases)) {
                continue;
            }

            if ($this->strposArray($text, $phrases) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null): void
    {
        $tickets = $this->split("/\n *({$this->opt(['ПОСАДОЧНИЙ ДОКУМЕНТ', 'ПОСАДОЧНЫЙ ДОКУМЕНТ'])}(?: {2,}|\n))/u", "\n\n" . $textPdf);
        // no examples for two or more passengers, or two or more segments
        foreach ($tickets as $tText) {
            $this->assignLang($tText);

            $t = $email->add()->train();

            // General
            $t->general()
                ->confirmation($this->re("/^(?:.*\n){1,6}.* {2,}# ?([\d\p{Lu}]{3}-[\d\p{Lu}\-]{5,})(?: {3,}|\n)/u", $tText))
                ->traveller($this->re("/{$this->opt($this->t('Прізвище, Ім’я'))} {2,}(.+?)(?: {2,}|\n)/u", $tText))
            ;

            // Ticket
            $t->addTicketNumber(str_replace(' ', '',
                $this->re("/^(?:.*\n){0,6}.* {2,}([\d\p{Lu}]{8}- *[\d\p{Lu}]{4}(?:-?[\d\p{Lu}]{4}){2})(?: {3,}|\n)/u", $tText)), false);

            // Segments
            $s = $t->addSegment();

            // Departure
            $s->departure()
                ->name($this->re("/{$this->opt($this->t('Відправлення'))} +\d+ +(\w.+?) {3,}/u", $tText))
                ->geoTip('ua')
                ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Дата/час відпр.'))} +(\w.+?)(?:\(| {3,}|\n)/u", $tText)))
            ;

            // Arrival
            $s->arrival()
                ->name($this->re("/{$this->opt($this->t('Призначення'))} +\d+ +(\w.+?) {3,}/u", $tText))
                ->geoTip('ua')
                ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Дата/час приб.'))}(?:[ ]+\d{3} ?[*]+)? +(\w.+?)(?:\(| {3,}| *\n)/u", $tText)))
            ;

            // Extra
            $s->extra()
                ->number($this->re("/{$this->opt($this->t('Поїзд'))} +(\d+[* ]?[[:upper:]]+)(?: |\n)/u", $tText))
                ->seat($this->re("/{$this->opt($this->t('Місце'))} +(\w+)/u", $tText))
                ->car($this->re("/{$this->opt($this->t('Вагон'))} +(\w+)/u", $tText))
            ;

            // Price
            $total = $this->re("/{$this->opt($this->t('ВАРТ'))}=(.+?)\(/", $tText);

            if (preg_match("/^\s*(\d[\d,. ]*)ГРН\s*$/", $total, $m)) {
                // 1 374,99ГРН
                $t->price()
                    ->total(PriceHelper::parse($m[1], 'UAH'))
                    ->currency('UAH');
            } else {
                $t->price()
                    ->total(null);
            }
        }
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Wed, Jul 26, 2023 | 1:00 PM
            //            "/^\s*\w+\s*[,\s.]+\s*([[:alpha:]]+)[.]?\s*(\d+)\s*[,\.\s]\s*(\d{4})\s*\|\s*(\d+)[:.](\d+(?:\s*[AP]M)?)\s*$/iu",
            // Mi, 22. Feb 2023 | 18:00
            //            "/^\s*\w+\,\s*(\d+)[.]?\s+([[:alpha:]]+)\s*(\d{4})\s*\|\s*(\d+:\d+(?:\s*[AP]M)?)\s*$/iu",
        ];
        $out = [
            //            "$2 $1 $3, $4:$5",
            //            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            } else {
//                foreach (['de', 'fr'] as $lang) {
//                    if ($en = MonthTranslate::translate($m[1], $lang)) {
//                        $str = str_replace($m[1], $en, $str);
//
//                        break;
//                    }
//                }
//            }
//        }

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
