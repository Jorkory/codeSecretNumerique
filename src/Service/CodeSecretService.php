<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CodeSecretService
{
    private sessionInterface $session;

    private string $state;
    private array $players = [];
    private string $currentPlayer = '';
    private bool $hardDifficulty;
    private string $codeToFind;
    private string $codeEntered = '';
    private array $journal = [];
    private int $time = 0;
    private array $penalty = [];
    private bool $finished = false;


    public function __construct(RequestStack $requestStack, private GameSessionService $gameSession, private UserGameService $userGame)
    {
        $this->session = $requestStack->getSession();

        if ($this->userGame->hasGame()) {
            $this->getGame();
        } else {
            if ($this->userGame->askToJoinGame()) {
                $this->joinGame();
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

    private function getGame(): void
    {
        $game = $this->gameSession->getGameData($this->userGame->gameID);

        if ($game !== null) {
            foreach ($game as $key => $value) {
                $this->$key = $value;
            }
        }
        // else ???
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
            'time' => $this->time,
            'penalty' => $this->penalty,
        ];

        $this->gameSession->updateGameData($this->userGame->gameID, $game);
    }

    private function createGame(): void
    {
        $this->userGame->joinedGame($this->gameSession->generateGameId());

        $this->state = 'open';
        $this->players[] = $this->userGame->userID;
        $this->hardDifficulty = $this->userGame->difficulty === 'hard';
        $this->codeToFind = $this->generateCode();

        if ($this->userGame->isSolo()) {
            $this->startGame();
            return;
        }

        $this->gameSession->addRoom($this->userGame->gameID, $this->userGame->isPrivate);

        $this->journal[] = '<p>Identifiant de la partie : <span class="font-bold">' . $this->userGame->gameID . '</span></p>';
        $this->journal[] = '<p>En attente des autres joueurs... (1 sur 4 joueurs connectés)</p>';

        $this->save();
    }

    public function joinGame(): void
    {
        $gameID = $this->userGame->askToJoinGame();

        if ($gameID === "fast") {
            $arrayRoom = $this->gameSession->findRoom(false) ?? [];
            $gameID = reset($arrayRoom) ?? null;

            if (!$gameID) {
                $this->createGame();
                return;
            }
        }

        $gameData = $this->gameSession->getGameData($gameID) ?? null;

        if ($gameData && $gameData['state'] === 'open') {
            $this->userGame->joinedGame($gameID);

            $this->getGame();

            if (!in_array($this->userGame->userID, $this->players, true)) {
                $this->players[] = $this->userGame->userID;
                $this->journal[] = '<p>Un(e) joueur(se) vient d\'arriver ! (' . count($this->players) . ' sur 4 joueurs connectés)</p>';
            };

            if (count($this->players) === 4 ) {
                $this->startGame();
                return;
            }

            $this->save();

        } else {
            if ($gameID) {
                throw new \Exception("La partie (Identifiant : " . htmlspecialchars($gameID) . ") n'est pas disponible.");
            } else {
                throw new \Exception("La partie n'est pas disponible, veuillez réessayer.");
            }
        }
    }

    public function startGame(): void
    {
        if ($this->players[0] !== $this->userGame->userID ){
            return;
        }

        $this->state = 'inProgress';
        $this->gameSession->deleteRoom($this->userGame->gameID, $this->userGame->isPrivate);
        if (count($this->players) > 1) {
            shuffle($this->players);
            $this->currentPlayer = $this->players[0];
        }
        $this->journal = [];
        if (count($this->players) === 1) {
            if ($this->userGame->isSolo()) {
                $this->journal[] = '<p>C\'est parti ! Trouvez le code secret !</p>';
            } else {
                $this->journal[] = '<p>Vous êtes le seul joueur pour cette partie. Bonne chance et trouvez le code secret !</p>';
            }
        } else {
            $this->journal[] = '<p>La partie commence. Bonne chance à tous !</p>';
            $this->initializeTime();
        };
        $this->save();
    }

    private function initializeTime(): void
    {
        $this->time = time() + 20;
    }

    private function canPlay(): bool
    {
        if ($this->state === 'inProgress' && in_array($this->userGame->userID, $this->players, true)) {
            if (count($this->players) > 1 && $this->userGame->userID === $this->currentPlayer) {
                return true;
            } else if (count($this->players) === 1) {
                return true;
            }
        }
        return false;
    }

    public function keypadAddNumber(string $key): void
    {
        if (!$this->canPlay()) {return;}

        if(strlen($this->codeEntered) >= strlen($this->codeToFind) || !preg_match('/^[0-9]$/', $key)) {
            return;
        }
        $this->codeEntered .= $key;
        $this->save();
    }

    public function clearCodeEntered(): void
    {
        if (!$this->canPlay()) {return;}

        $this->codeEntered = '';
        $this->save();
    }

    public function getCodeToFind(): string
    {
        return $this->codeToFind;
    }

    public function getCodeToDisplay(): array
    {
        if ($this->canPlay()) {
            return str_split(str_pad($this->codeEntered, strlen($this->codeToFind), '-', STR_PAD_RIGHT));
        }
        return str_split(str_pad('', strlen($this->codeToFind), '-', STR_PAD_RIGHT));
    }

    public function checkCodeEntered(): void
    {
        if (!$this->canPlay()) {return;}

        if (strlen($this->codeEntered) < strlen($this->codeToFind)) {
            return;
        }

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
            $this->state = 'finished';
            $this->finished = true;
            $this->journal[] = '<div class="win"> Bravo, vous avez trouvé le code secret ! </div>';
        }


        $this->clearCodeEntered();
        $this->nextPlayer();
        $this->save();
    }

    public function getJournal(): array
    {
        if ($this->state === 'open') {
            if ($this->userGame->userID === $this->players[0]) {
                $this->journal[] = '<p>Vous êtes l\'hôte ! Démarrez la partie à tout moment, sans attendre d\'autres joueurs. <a href="/game?start=true" class="btn">Commencez maintenant</a></p>';
            } else {
                $this->journal[] = '<p>Veuillez patienter pour l\'arrivée des autres joueurs, ou jusqu\'à ce que l\'hôte lance la partie.</p>';
            }
        }
        if ($this->state === 'inProgress' && count($this->players) > 1) {
            if ($this->time < time()) {
                $this->journal[] = '<p>Le joueur a passé son tour (le temps est écoulé !)</p>';
                $this->penaltyPlayer();
                $this->save();
            }
            if ($this->isCurrentPlayer()) {
                $this->journal[] ='<p class="font-bold">À vous de jouer ! (Vous avez '. $this->time - time() .' seconde' . ($this->time - time() > 1 ? 's' : '') . '.)</p>';
            } else {
                $this->journal[] = '<p class="font-bold">Patientez, l\'autre joueur est en train de faire son tour.</p>';
            }
        }

        return $this->journal;
    }

    private function nextPlayer(): void
    {
        if (count($this->players) === 1) {return;}

        $currentIndex = array_search($this->currentPlayer, $this->players);

        if ($currentIndex === false) {
            throw new \Exception("Aïe, nous avons rencontré un problème.");
        }

        $nextIndex = ($currentIndex + 1) % count($this->players);
        $this->currentPlayer = $this->players[$nextIndex];
        $this->initializeTime();
    }

    private function penaltyPlayer(): void
    {
        if (!in_array($this->currentPlayer, $this->penalty)) {
            $this->penalty[] = $this->currentPlayer;
            $this->nextPlayer();
        } else {
            $index = array_search($this->currentPlayer, $this->players);
            $this->nextPlayer();
            if ($index !== false) {
                unset($this->players[$index]);
                $this->players = array_values($this->players);
            }
            $this->journal[] = '<p>Un joueur a été exclu pour avoir dépassé le temps limite deux fois. (Vous êtes maintenant ' . count($this->players) . ' joueur' . (count($this->players) > 1 ? 's' : '') . '.)</p>';
        }
    }

    public function isCurrentPlayer(): bool
    {
        if ($this->currentPlayer === $this->userGame->userID) {
            return true;
        };
        return false;
    }
}