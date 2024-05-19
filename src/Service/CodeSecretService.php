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
    private bool $finished = false;


    public function __construct(Request $request)
    {
        $this->session = $request->getSession();

        $difficulty = $this->session->get('newGame')['difficulty'];
        $codeLength = $this->session->get('newGame')['codeLength'];
        $newGame = $this->session->get('newGame')['newGame'];

        if ($this->session->has('game') && !$newGame) {
            $game = $this->session->get('game');
            $this->codeToFind = $game['codeToFind'];
            $this->codeEntered = $game['codeEntered'];
            $this->journal = $game['journal'];
            $this->finished = $game['finished'];
        } else {
            $length = $codeLength ?? random_int(4,9);
            $this->codeToFind = (string) random_int((int) str_repeat(0, $length), (int) str_repeat(9, $length));
            $this->codeToFind = str_pad($this->codeToFind, $length, '0', STR_PAD_LEFT);
            $this->save();
            $this->session->set('newGame', ['newGame' => false, 'difficulty' => $difficulty ,'codeLength' => $codeLength]);
        }
    }

    private function save(): void
    {
        $this->session->set('game', ['codeToFind' => $this->codeToFind, 'codeEntered' => $this->codeEntered, 'journal' => $this->journal, 'finished' => $this->finished]);
    }

    public function keypadAddNumber(string $key): void
    {
        if ($this->finished) {return;}

        if(strlen($this->codeEntered) >= strlen($this->codeToFind) || !preg_match('/^[0-9]$/', $key)) {
            return;
        }
        $this->codeEntered .= $key;
        $this->save();
    }

    public function clearCodeEntered(): void
    {
        if ($this->finished) {return;}

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
        if ($this->finished) {return;}

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
            $this->finished = true;
            $this->journal[] = '<div class="win"> Bravo, vous avez trouvé le code secret ! </div>';
        }

        $this->save();
        $this->clearCodeEntered();
    }

    public function getJournal(): array
    {
        return $this->journal;
    }
}