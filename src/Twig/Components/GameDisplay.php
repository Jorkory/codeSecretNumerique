<?php

namespace App\Twig\Components;


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
    public string $code = '';

    private string $codeToFind;

    #[LiveProp]
    public array $journal = [];

    #[LiveListener('handleKeypadClick')]
    public function handleKeypadClick(#[LiveArg] string $code, Request $request): void
    {
        $session = $request->getSession();
        $this->code = $code;
        $this->codeToFind = (string) $session->get('codeToFind');

        $newEntry = "";

        for ($i = 0; $i < strlen($this->codeToFind); ++$i) {
            if ($this->code[$i] === $this->codeToFind[$i]) {
                $newEntry .= '<span class="green">' . htmlspecialchars($this->code[$i]) . '</span>';
                continue;
            } else if (strpos($this->codeToFind, $this->code[$i]) !== false) {
                $newEntry .= '<span class="yellow">' . htmlspecialchars($this->code[$i]) . '</span>';
                continue;
            }
            $newEntry .= '<span class="null">' . htmlspecialchars($this->code[$i]) . '</span>';
        }

        $journal = $session->get('journal', []);
        $journal[] = '[' . date('H:i:s') . ']  ' . '<div class="code">' . $newEntry . '</div>';

        if ($this->code === $this->codeToFind) {
            $journal[] = '<div class="win"> Vous avez gagné ! </div>';
        }

        $session->set('journal', $journal);
        $this->journal = $journal;

    }
}