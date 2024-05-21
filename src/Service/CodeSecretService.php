<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CodeSecretService
{
    private sessionInterface $session;
    private bool $hardDifficulty;
    private string $codeToFind;
    private string $codeEntered = '';
    private array $journal = [];
    private bool $finished = false;


    public function __construct(RequestStack $requestStack)
    {
        $this->session = $requestStack->getSession();

        $difficulty = $this->session->get('newGame')['difficulty'];
        $codeLength = $this->session->get('newGame')['codeLength'];
        $newGame = $this->session->get('newGame')['newGame'];
        $hardDifficulty = $this->session->get('newGame')['difficulty'] === 'hard';

        if ($this->session->has('game') && !$newGame) {
            $game = $this->session->get('game');
            $this->codeToFind = $game['codeToFind'];
            $this->codeEntered = $game['codeEntered'];
            $this->journal = $game['journal'];
            $this->finished = $game['finished'];
            $this->hardDifficulty = $game['hardDifficulty'];
        } else {
            $this->hardDifficulty = $hardDifficulty;
            $length = $codeLength ?? random_int(4,9);
            $this->codeToFind = (string) random_int((int) str_repeat(0, $length), (int) str_repeat(9, $length));
            $this->codeToFind = str_pad($this->codeToFind, $length, '0', STR_PAD_LEFT);
            $this->save();
            $this->session->set('newGame', ['newGame' => false, 'difficulty' => $difficulty ,'codeLength' => $codeLength]);
        }
    }

    private function save(): void
    {
        $this->session->set('game', ['hardDifficulty' => $this->hardDifficulty, 'codeToFind' => $this->codeToFind, 'codeEntered' => $this->codeEntered, 'journal' => $this->journal, 'finished' => $this->finished]);
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

        $newEntry = [];
        $codeToFindFreq = array_count_values(str_split($this->codeToFind));
        $codeEnteredFreq = array_count_values(str_split($this->codeEntered));

        $countGreen = 0;
        $countYellow = 0;


        for ($i = 0; $i < strlen($this->codeToFind); $i++) {
            if ($this->codeEntered[$i] === $this->codeToFind[$i]) {
                $newEntry[$i] = '<span class="green">' . htmlspecialchars($this->codeEntered[$i]) . '</span>';
                $countGreen++;
                $codeEnteredFreq[$this->codeEntered[$i]]--;
                $codeToFindFreq[$this->codeEntered[$i]]--;
            } else {
                $newEntry[$i] = null;
            }
        }

        for ($i = 0; $i < strlen($this->codeToFind); $i++) {
            if ($newEntry[$i] === null) {
                if (isset($codeToFindFreq[$this->codeEntered[$i]])
                    && $codeEnteredFreq[$this->codeEntered[$i]] > 0
                    && $codeToFindFreq[$this->codeEntered[$i]] > 0
                    ) {
                    $newEntry[$i] = '<span class="yellow">' . htmlspecialchars($this->codeEntered[$i]) . '</span>';
                    $countYellow++;
                    $codeEnteredFreq[$this->codeEntered[$i]]--;
                    $codeToFindFreq[$this->codeEntered[$i]]--;
                } else {
                    $newEntry[$i] = '<span class="null">' . htmlspecialchars($this->codeEntered[$i]) . '</span>';
                }
            }
        }

        if ($this->hardDifficulty) {
            $newEntryHard = '<p class="font-bold">Code saisi : ' . htmlspecialchars($this->codeEntered) . '</p></p><p>Nombre de chiffres corrects et bien placés : <span class="font-bold">' . $countGreen . '</span></p><p>Nombre de chiffres corrects mais mal placés : <span class="font-bold">' . $countYellow . '</span></p>';
            $this->journal[] = '[' . date('H:i:s') . ']  ' . '<div class="code">' . $newEntryHard . '</div>';
        } else {
            $newEntryString = implode('', $newEntry);
            $this->journal[] = '[' . date('H:i:s') . ']  ' . '<div class="code">' . $newEntryString . '</div>';
        }

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