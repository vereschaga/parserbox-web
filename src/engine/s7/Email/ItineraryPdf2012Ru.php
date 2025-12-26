<?php

namespace AwardWallet\Engine\s7\Email;

class ItineraryPdf2012Ru extends ItineraryPdf2012En
{
    public $mailFiles = "s7/it-4794647.eml, s7/it-6531444.eml, s7/it-6665948.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'aero@s7.ru') !== false
            && isset($headers['subject'])
            && stripos($headers['subject'], 'Подтверждение покупки на сайте www.s7.ru') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->getPdfName());

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (strpos($text, 'МАРШРУТНАЯ КВИТАНЦИЯ ЭЛЕКТРОННОГО БИЛЕТА') !== false
                && strpos($text, 'Спасибо, что выбрали S7') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@s7.ru') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['ru'];
    }

    protected function parseEmail($pdfText)
    {
        $this->result['Kind'] = 'T';
        $this->parseTrip($this->findСutSection($pdfText, 'Дата покупки:', 'ПАССАЖИР:'));
        $this->parsePassengers($this->findСutSection($pdfText, 'ПАССАЖИР:', 'ИНФОРМАЦИЯ О РЕЙСЕ:'));
        $this->parsePayment($this->findСutSection($pdfText, 'ИНФОРМАЦИЯ О ТАРИФЕ:', 'ЧТО ДАЛЬШЕ?'));
        $this->iterationSegments($this->findСutSection($pdfText, 'ИНФОРМАЦИЯ О РЕЙСЕ:', ['ИНФОРМАЦИЯ О ТАРИФЕ:', 'ЧТО ДАЛЬШЕ?']));

        return [$this->result];
    }

    protected function getPdfName()
    {
        return '(eticket|E-Ticket)_[\w\-\s_]+?_ru\.pdf';
    }
}
