<?php

namespace App\Twig\Components;

use App\Service\CodeSecretService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class GameEnterCode
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public array $codeToDisplay;

    #[LiveAction]
    public function keypadClick(#[LiveArg('number')] string $key, GameDisplay $gameDisplay, Request $request): void
    {
        $codeSecretService = new CodeSecretService($request);

        if ($key === 'C' ) {
            $codeSecretService->clearCodeEntered();
        } else if ($key === 'OK') {
            $codeSecretService->checkCodeEntered();
            $this->emit('handleKeypadClick', [
                'journal' => $codeSecretService->getJournal()
            ]);
        } else {
            $codeSecretService->keypadAddNumber($key);
        }

        $this->codeToDisplay = $codeSecretService->getCodeToDisplay();
    }
}