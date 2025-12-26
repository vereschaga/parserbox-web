<?php

namespace AwardWallet\Engine\solmelia\Transfer;

class Transfer extends \TAccountCheckerSolmelia
{
    public $idle = false;

    public static $providersMap = [
        'aa'          => '8003_AA',
        'triprewards' => '8086_WG',
        'iberia'      => '8038_IB',
        'airberlin'   => '8052_AB',
        'klm'         => '8066_FB',
        'airfrance'   => '8066_FB',
        'lufthansa'   => '8051_MM',
        'swissair'    => '8051_MM',
        'austrian'    => '8051_MM',
    ];

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        try {
            $this->checkTransferParameters($targetProviderCode, $targetAccountNumber, $numberOfMiles);
            $this->LoadLoginForm();
            $this->Login();
            $this->doTransfer($targetProviderCode, $targetAccountNumber, $numberOfMiles);
        } catch (\CheckException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            $this->http->Log($e->getMessage(), LOG_LEVEL_ERROR);

            return false;
        }
    }

    protected function checkTransferParameters($targetProviderCode, $targetAccountNumber, $numberOfMiles)
    {
        if (!isset(self::$providersMap[$targetProviderCode])) {
            throw new \UserInputError('Invalid target provider');
        }

        return true;
    }

    protected function doTransfer($targetProviderCode, $targetAccountNumber, $numberOfMiles)
    {
        $this->http->GetURL('https://www.melia.com/nMas/jsp/C_TransferirPuntos_Step1.jsp');

        if (!$this->http->ParseForm("transForm")) {
            throw new \Exception('Parse form error');
        }

        $target = explode('_', self::$providersMap[$targetProviderCode]);
        $values = [
            'idPartner'                          => 'DEFAULT',
            'type'                               => 'on',
            'puntosATransferir'                  => $numberOfMiles,
            'paso0_puntos'                       => $numberOfMiles,
            'tipoTarjetaBeneficiaria'            => $target[1],
            'numTarjetaBeneficiaria'             => $targetAccountNumber,
            'paso0_numTarjeta2_programaAsociado' => $targetAccountNumber,
            'partner'                            => $target[0],
            'paso0_programa'                     => self::$providersMap[$targetProviderCode],
        ];
        //vacacional:N
        //masAmas:N

        foreach ($values as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        if ($this->idle) {
            $this->http->Log('Idle run, no submit');

            return true;
        }

        if (!$this->http->PostForm()) {
            throw new \Exception('Post form error');
        }

        if ($error = $this->http->FindSingleNode("//div[@class='alertError']")) {
            throw new \ProviderError($error);
        } // Is it always provider error?

        return false;
    }
}
