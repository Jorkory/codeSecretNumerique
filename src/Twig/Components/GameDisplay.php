<?php

namespace App\Twig\Components;


use App\Service\CodeSecretService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class GameDisplay
{
    use DefaultActionTrait;

    public function __construct(private readonly CodeSecretService $codeSecretService){}

    #[LiveProp]
    public array $journal = [];

    #[LiveListener('handleKeypadClick')]
    public function handleKeypadClick(#[LiveArg('journal')] array $journal): void
    {
        $this->journal = $journal;
    }

    public function getJournal() : array
    {
        return $this->journal = $this->codeSecretService->getJournal();
    }
}