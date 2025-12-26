<?php

namespace AwardWallet\Engine\virginamerica\CardImageParser;

use AwardWallet\CardImageParser\CardImageParserInterface;
use AwardWallet\CardImageParser\CardRecognitionResult;

class CardImageParserVirginamerica implements CardImageParserInterface
{
    public function parseImages(CardRecognitionResult $cardRecognitionResult): array
    {
        $properties = [];

        $front = $cardRecognitionResult->getFront();

        if (!$front) {
            return [];
        }

        $textFront = $front->getText();
        $textFront = str_replace('.', '', $textFront);

        if ($result = $this->parseFormat_1($textFront)) {
            return $result;
        }

        return $properties;
    }

    public static function getSupportedProperties(): array
    {
        return [
            'Login',
        ];
    }

    protected function parseFormat_1($textFront)
    {
        $properties = [];

        // 3946918 https://awardwallet.com/cardImage/proxy/5a73ae32-b427-4a9f-b331-ddd069a53587
        $pattern = '/'
            . '^[ ]*[A-z][-\'A-z ]+[ ]*$'	// Name & Last Name
            . '\s+^[ ]*(\d{5,})[ ]*$'		// Number
            . '/m';

        if (preg_match($pattern, $textFront, $matches)) {
            return [
                'Login' => $matches[1],
            ];
        }

        return $properties;
    }
}
