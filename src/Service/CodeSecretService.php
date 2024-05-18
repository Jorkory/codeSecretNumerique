<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CodeSecretService
{
    private sessionInterface $session;
    private string $codeToFind;
    private string $codeEntered = '';
    private array $journal = [];


    public function __construct(Request $request, int $length = null)
    {
        $this->session = $request->getSession();

        if ($this->session->has('game')) {
            $game = $this->session->get('game');
            $this->codeToFind = $game['codeToFind'];
            $this->codeEntered = $game['codeEntered'];
            $this->journal = $game['journal'];
        } else {
            $length ??= random_int(4,9);
            $this->codeToFind = (string) random_int((int) str_repeat(0, $length), (int) str_repeat(9, $length));
            $this->save();
        }
    }

    private function save(): void
    {
        $this->session->set('game', ['codeToFind' => $this->codeToFind, 'codeEntered' => $this->codeEntered, 'journal' => $this->journal]);
    }

    public function keypadAddNumber(string $key): void
    {
        if(strlen($this->codeEntered) >= strlen($this->codeToFind) || !preg_match('/^[0-9]$/', $key)) {
            return;
        }
        $this->codeEntered .= $key;
        $this->save();
    }

    public function clearCodeEntered(): void
    {
        $this->codeEntered = '';
        $this->save();
    }

    public function getCodeToFind(): string
    {
        return $this->codeToFind;
    }

    public function getCodeToDisplay(): array
    {
        return str_split(str_pad($this->codeEntered, strlen($this->codeToFind), '-', STR_PAD_RIGHT));
    }

    public function checkCodeEntered(): void
    {
        if (strlen($this->codeEntered) < strlen($this->codeToFind)) { return;}

        $newEntry = '';

        for ($i = 0; $i < strlen($this->codeToFind); ++$i) {
            if ($this->codeEntered[$i] === $this->codeToFind[$i]) {
                $newEntry .= '<span class="green">' . htmlspecialchars($this->codeEntered[$i]) . '</span>';
                continue;
            } else if (str_contains($this->codeToFind, $this->codeEntered[$i])) {
                $newEntry .= '<span class="yellow">' . htmlspecialchars($this->codeEntered[$i]) . '</span>';
                continue;
            }
            $newEntry .= '<span class="null">' . htmlspecialchars($this->codeEntered[$i]) . '</span>';
        }

        $this->journal[] = '[' . date('H:i:s') . ']  ' . '<div class="code">' . $newEntry . '</div>';

        if ($this->codeEntered === $this->codeToFind) {
            $this->journal[] = '<div class="win"> Vous avez gagné ! </div>';
        }

        $this->save();
        $this->clearCodeEntered();
    }

    public function getJournal(): array
    {
        return $this->journal;
    }
}