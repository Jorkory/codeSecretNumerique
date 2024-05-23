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
    private int $time = 0;
    private array $penalty = [];
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

    private function createGame(?bool $fast = false): void
    {
        $this->gameID = $this->gameSession->generateGameId();
        $this->state = ($this->session->get('newGame')['mode'] === 'multiplayer' || $fast) ? 'open' : 'inProgress';
        $this->players[] = $this->userID;
        $this->hardDifficulty = $this->session->get('newGame')['difficulty'] === 'hard';
        $this->codeToFind = $this->generateCode();
        if ($this->state === 'open') {
            $this->journal[] = '<p>Identifiant de la partie : <span class="font-bold">' . $this->gameID . '</span></p>';
            $this->journal[] = '<p>En attente des autres joueurs... (1 sur 4 joueurs connectés)</p>';
        } else if ($this->state === 'inProgress' && count($this->players) === 1) {
            $this->journal[] = '<p>C\'est parti ! Trouvez le code secret !</p>';
        }


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
            $arrayRoom = $this->gameSession->findRoomPublic() ?? [];
            $gameID = reset($arrayRoom) ?? null;
            if (!$gameID) {

                $this->createGame(true);
                return;
            }
        }
            $gameData = $this->gameSession->getGameData($gameID) ?? null;

        if ($gameData && $gameData['state'] === 'open') {
            $newGame = $this->session->get('newGame');
            $newGame['joinGame'] = '';
            $newGame['newGame'] = false;
            $this->session->set('newGame', $newGame);

            $this->gameID = $gameID;
            $this->session->set('game', $this->gameID);
            $this->getGame();
            if (!in_array($this->userID, $this->players, true)) {
                $this->players[] = $this->userID;
            };

            $this->journal[] = '<p>Un(e) joueur(se) vient d\'arriver ! (' . count($this->players) . ' sur 4 joueurs connectés)</p>';

            if (count($this->players) === 4 ) {
                $this->state = 'clos';
                $this->startGame();
            }

        } else {
            if ($gameID) {
                return throw new \Exception("La partie (Identifiant : " . htmlspecialchars($gameID) . ") n'est pas disponible.");
            } else {
                return throw new \Exception("La partie n'est pas disponible, veuillez réessayer.");
            }
        }

        $this->save();
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
            'time' => $this->time,
            'penalty' => $this->penalty,
        ];

        $this->gameSession->updateGameData($this->gameID, $game);
    }

    public function startGame(): void
    {
        if ($this->players[0] === $this->userID || $this->state === 'clos'){
            $this->state = 'inProgress';
            $this->gameSession->deleteRoomPublic($this->gameID);
            if (count($this->players) > 1) {
                shuffle($this->players);
                $this->currentPlayer = $this->players[0];
            }
            $this->journal = [];
            if (count($this->players) === 1) {
                $this->journal[] = '<p>Vous êtes le seul joueur pour cette partie. Bonne chance et trouvez le code secret !</p>';
            } else {
                $this->journal[] = '<p>La partie commence. Bonne chance à tous !</p>';
                $this->time = time() + 20;
            };
            $this->save();
        }
    }

    public function keypadAddNumber(string $key): void
    {
        if ((count($this->players) > 1 && $this->userID !== $this->currentPlayer) || (count($this->players) === 1 && !in_array($this->userID, $this->players))) {return;}

        if ($this->state === 'inProgress') {
            if(strlen($this->codeEntered) >= strlen($this->codeToFind) || !preg_match('/^[0-9]$/', $key)) {
                return;
            }
            $this->codeEntered .= $key;
            $this->save();
        }
    }

    public function clearCodeEntered(): void
    {
        if ((count($this->players) > 1 && $this->userID !== $this->currentPlayer)  || (count($this->players) === 1 && !in_array($this->userID, $this->players))) {return;}

        if ($this->state === 'inProgress') {
            $this->codeEntered = '';
            $this->save();
        }
    }

    public function getCodeToFind(): string
    {
        return $this->codeToFind;
    }

    public function getCodeToDisplay(): array
    {
        if ($this->state === 'inProgress' && (($this->userID !== $this->currentPlayer && count($this->players) > 1)  || (count($this->players) === 1 && !in_array($this->userID, $this->players)))) {
            return str_split(str_pad('', strlen($this->codeToFind), '-', STR_PAD_RIGHT));
        }
        return str_split(str_pad($this->codeEntered, strlen($this->codeToFind), '-', STR_PAD_RIGHT));
    }

    public function checkCodeEntered(): void
    {
        if ((count($this->players) > 1 && $this->userID !== $this->currentPlayer) || (count($this->players) === 1 && !in_array($this->userID, $this->players))) {return;}

        if ($this->state === 'inProgress') {

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
    }

    public function getJournal(): array
    {
        if (($this->state === 'open' || $this->state === 'private') && $this->players[0] === $this->userID) {
            if (!in_array('<p>Vous êtes l\'hôte ! Démarrez la partie à tout moment, sans attendre d\'autres joueurs. <a href="/game?start=true" class="btn">Commencez maintenant</a></p>'
                , $this->journal)) {
                $this->journal[] = '<p>Vous êtes l\'hôte ! Démarrez la partie à tout moment, sans attendre d\'autres joueurs. <a href="/game?start=true" class="btn">Commencez maintenant</a></p>'
                ;
            }
        } else if (($this->state === 'open' || $this->state === 'private') && $this->players[0] !== $this->userID) {
            if (!in_array('<p>Veuillez patienter pour l\'arrivée des autres joueurs, ou jusqu\'à ce que l\'hôte lance la partie.</p>'
                , $this->journal)) {
                $this->journal[] = '<p>Veuillez patienter pour l\'arrivée des autres joueurs, ou jusqu\'à ce que l\'hôte lance la partie.</p>'
                ;
            }
        }

        if ($this->state === 'inProgress' && count($this->players) > 1) {
            if ($this->time < time()) {
                $this->journal[] = '<p>Le joueur a passé son tour (le temps est écoulé !)</p>';
                $this->penaltyPlayer();
                $this->save();
            }

            if ($this->getCurrentPlayer() === 'you' && !in_array('<p class="font-bold">À vous de jouer ! (Vous avez '. $this->time - time() .' seconde' . ($this->time - time() > 1 ? 's' : '') . '.)</p>', $this->journal)) {
                $this->journal[] ='<p class="font-bold">À vous de jouer ! (Vous avez '. $this->time - time() .' seconde' . ($this->time - time() > 1 ? 's' : '') . '.)</p>';
            } else if ($this->getCurrentPlayer() !== 'you' && !in_array('<p class="font-bold">Patientez, l\'autre joueur est en train de faire son tour.</p>', $this->journal)) {
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
            throw new \Exception("Current player not found in players array");
        }

        $nextIndex = ($currentIndex + 1) % count($this->players);
        $this->currentPlayer = $this->players[$nextIndex];
        $this->time = time() + 20;
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

    public function getCurrentPlayer(): string
    {
        if ($this->currentPlayer === $this->userID) {
            return "you";
        };

        return '';
    }
}