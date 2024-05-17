<?php

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class GameEnterCode
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    private int $codeGenerated = 45780;

    private const LENGTH = 5;

    public string $code = '';

    public function getCode(): array
    {
        return str_split(str_pad($this->code, self::LENGTH, '-', STR_PAD_RIGHT));
    }

    #[LiveAction]
    public function keypadClick(#[LiveArg('code')] string $code, #[LiveArg('number')] string $key, GameDisplay $gameDisplay): void
    {
        if ($key === 'C') {
            return;
        }

        if ($key === 'OK') {
            $this->emit('handleKeypadClick', [
                'code' => $code,
            ]);
            return;
        }

        if(strlen($code) >= self::LENGTH) {
            $this->code = $code;
            return;
        }

        $this->code = $code . $key;
    }
}