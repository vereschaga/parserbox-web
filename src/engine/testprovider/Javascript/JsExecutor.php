<?php
namespace AwardWallet\Engine\testprovider\Javascript;

use AwardWallet\Engine\testprovider\Success;

class JsExecutor extends Success {

    public function Parse()
    {
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $result = $jsExecutor->executeString(" 
                var enFirstParam = CryptoJS.enc.Latin1.parse('AEE0715D0778A4E4');
                var enSecondParam = CryptoJS.enc.Latin1.parse('secredemptionKey');
                function changedLoginData(encryptValue, enFirstParam, enSecondParam) {
                    const givenString =
                        typeof encryptValue === 'string'
                            ? encryptValue.slice()
                            : JSON.stringify(encryptValue);
                    const encrypted = CryptoJS.AES.encrypt(givenString, enSecondParam, {
                        iv: enFirstParam,
                        mode: CryptoJS.mode.CBC,
                        padding: CryptoJS.pad.Pkcs7,
                    });
                    return encrypted.toString();
                }
                sendResponseToPhp(changedLoginData('somepass', enFirstParam, enSecondParam));
                ", 5, ['https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.js']);

        if ($result === "zls2S3sCBj40MfES/P7dEg==") {
            $this->SetBalance(1);
        }
    }

}