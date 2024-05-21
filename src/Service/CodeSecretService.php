<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CodeSecretService
{
    private sessionInterface $session;

    private string $userID;
    private string $gameID;
    private string $state;
    private array $players = [];
    private string $currentPlayer = '';
    private bool $hardDifficulty;
    private string $codeToFind;
    private string $codeEntered = '';
    private array $journal = [];
    private bool $finished = false;


    public function __construct(RequestStack $requestStack, private GameSessionService $gameSession)
    {
        $this->session = $requestStack->getSession();
        (bool) $newGame = $this->session->get('newGame')['newGame'];


        if ($this->session->has('userID')) {
            $this->userID = $this->session->get('userID');
        } else {
            $this->createUserID();
        }

        if ($this->session->has('game') && !$newGame) {
            $this->gameID = $this->session->get('game');
            $this->getGame();
        } else {
            $joinGameID = $this->session->get('newGame')['joinGame'];
            if (!empty($joinGameID)) {
                $this->joinGame($joinGameID);
            } else {
                $this->createGame();
            }

        }
    }

    private function generateCode(): string
    {
        $codeLength = $this->session->get('newGame')['codeLength'];
        $length = $codeLength ?? random_int(4,9);
        $codeToFind = (string) random_int((int) str_repeat(0, $length), (int) str_repeat(9, $length));
        return str_pad($codeToFind, $length, '0', STR_PAD_LEFT);
    }

    private function createUserID(): void
    {
        $this->userID = uniqid('user_', true);
        $this->session->set('userID', $this->userID);
    }

    private function createGame(): void
    {
        $this->gameID = $this->gameSession->generateGameId();
        $this->state = $this->session->get('newGame')['mode'] === 'multiplayer' ? 'open' : 'inProgress';
        $this->players[] = $this->userID;
        $this->hardDifficulty = $this->session->get('newGame')['difficulty'] === 'hard';
        $this->codeToFind = $this->generateCode();
        $this->journal[] = '<p>Identifiant de la partie : <span class="font-bold">' . $this->gameID . '</span></p>';
        $this->journal[] = '<p>En attente des autres joueurs... (1 sur 4 joueurs connectés)</p>';


        $this->save();

        $this->session->set('game', $this->gameID);

        $newGame = $this->session->get('newGame');
        $newGame['newGame'] = false;
        $this->session->set('newGame', $newGame);

        if ($this->state === 'open') {
            $this->gameSession->addRoomPublic($this->gameID);
        }
    }

    public function joinGame(string $gameID)
    {
        if ($gameID === "fast") {
            $gameID = $this->gameSession->findRoomPublic()[0];
        }
            $gameData = $this->gameSession->getGameData($gameID);

        if ($gameData['state'] === 'open') {
            $newGame = $this->session->get('newGame');
            $newGame['joinGame'] = '';
            $newGame['newGame'] = false;
            $this->session->set('newGame', $newGame);

            $this->gameID = $gameID;
            $this->getGame();
            if (!in_array($this->userID, $this->players, true)) {
                $this->players[] = $this->userID;
            };

            if (count($this->players) === 4 ) {
                $this->state = 'clos';
            }

            $this->journal[] = '<p>Un(e) joueur(se) vient d\'arriver ! (' . count($this->players) . ' sur 4 joueurs connectés)</p>';

        } else {
            throw new \Exception("La partie n'est pas disponible, veuillez réessayer.");
        }

        $this->save();

        $this->session->set('game', $this->gameID);
    }

    private function getGame(): void
    {
        $game = $this->gameSession->getGameData($this->gameID);

        foreach ($game as $key => $value) {
            $this->$key = $value;
        }
    }

    private function save(): void
    {
        $game = [
            'state' => $this->state,
            'players' => $this->players,
            'currentPlayer' => $this->currentPlayer,
            'hardDifficulty' => $this->hardDifficulty,
            'codeToFind' => $this->codeToFind,
            'codeEntered' => $this->codeEntered,
            'journal' => $this->journal,
        ];

        $this->gameSession->updateGameData($this->gameID, $game);
    }

    public function startGame(): void
    {
        if ($this->players[0] === $this->userID){
            $this->state = 'inProgress';
            $this->gameSession->deleteGameData($this->gameID);
            if (count($this->players) > 1) {
                shuffle($this->players);
                $this->currentPlayer = $this->players[0];
            }

            $this->save();
        }
    }

    public function keypadAddNumber(string $key): void
    {
        if ($this->finished || $this->state !== 'inProgress' || $this->userID !== $this->currentPlayer) {return;}

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

        $this->nextPlayer();

        $this->save();
        $this->clearCodeEntered();
    }

    public function getJournal(): array
    {
        if (($this->state === 'open' || $this->state === 'private') && $this->players[0] === $this->userID) {
            $this->journal[] = '<a href="/game?start=true" class="btn">Démarrer</a>';
        }

        if ($this->state === 'inProgress') {
            if ($this->getCurrentPlayer() === 'you') {
                $this->journal[] = '<p class="font-bold">À vous de jouer !</p>';
            } else {
                $this->journal[] = '<p class="font-bold">Patientez, l\'autre joueur est en train de faire son tour.</p>';
            }
        }
        return $this->journal;
    }

    private function nextPlayer(): void
    {
        $currentIndex = array_search($this->currentPlayer, $this->players);

        if ($currentIndex === false) {
            throw new \Exception("Current player not found in players array");
        }

        $nextIndex = ($currentIndex + 1) % count($this->players);
        $this->currentPlayer = $this->players[$nextIndex];
    }

    public function getCurrentPlayer(): string
    {
        if ($this->currentPlayer === $this->userID) {
            return "you";
        };

        return '';
    }
}