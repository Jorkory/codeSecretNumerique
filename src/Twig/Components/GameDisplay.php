<?php

namespace App\Twig\Components;


use App\Service\CodeSecretService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class GameDisplay
{
    use DefaultActionTrait;

    #[LiveProp]
    public array $journal = [];

    #[LiveListener('handleKeypadClick')]
    public function handleKeypadClick(#[LiveArg('journal')] array $journal): void
    {
        $this->journal = $journal;
    }
}