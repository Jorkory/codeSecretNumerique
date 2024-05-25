<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CodeSecretService
{
    private sessionInterface $session;

    private string $state;
    private array $lastActive = [];
    private array $players = [];
    private string $currentPlayer = '';
    private bool $hardDifficulty;
    private string $codeToFind;
    private string $codeEntered = '';
    private array $journal = [];
    private int $time = 0;
    private array $penalty = [];
    private array $colorPlayers = [];


    public function __construct(RequestStack $requestStack, private GameSessionService $gameSession, private UserGameService $userGame)
    {
        $this->session = $requestStack->getSession();

        if ($this->userGame->hasGame() && $this->userGame->newGame === false) {
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
        } else {
            throw new \Exception('Il n\'y a aucun partie en cours.');
        }
    }

    private function save(): void
    {
        $game = [
            'state' => $this->state,
            'lastActive' => $this->lastActive,
            'players' => $this->players,
            'currentPlayer' => $this->currentPlayer,
            'hardDifficulty' => $this->hardDifficulty,
            'codeToFind' => $this->codeToFind,
            'codeEntered' => $this->codeEntered,
            'journal' => $this->journal,
            'time' => $this->time,
            'penalty' => $this->penalty,
            'colorPlayers' => $this->colorPlayers,
        ];

        $this->gameSession->updateGameData($this->userGame->gameID, $game);
    }

    private function createGame(): void
    {
        $this->userGame->joinedGame($this->gameSession->generateGameId());

        $this->state = 'open';
        $this->lastActive[$this->userGame->userID] = time();
        $this->players[] = $this->userGame->userID;
        $this->hardDifficulty = $this->userGame->difficulty === 'hard';
        $this->codeToFind = $this->generateCode();

        if ($this->userGame->isSolo()) {
            $this->startGame();
            return;
        }

        $this->gameSession->addRoom($this->userGame->gameID, $this->userGame->isPrivate());

        $this->journal[] = '<p>[Partie '. ($this->userGame->isPrivate() ? "Privée" : "Public") .' – Difficulté ' . ($this->userGame->difficulty === 'hard' ? 'Difficile' : 'Normal') . ']<br/>Identifiant de la partie : <span class="font-bold">' . $this->userGame->gameID . '</span> (Partagez-le avec vos amis pour qu\'ils puissent rejoindre cette partie)</p>';
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
                throw new \Exception("Aucune partie en ligne n'a été trouvée. Vous pouvez créer une partie multijoueur !");
            }
        }

        $gameData = $this->gameSession->getGameData($gameID) ?? null;

        if ($this->userGame->askToJoinGame() === "fast") {
            if (!$gameData) {
                $this->gameSession->deleteRoom($gameID, false);
                $this->joinGame();
                return;
            } else  {
                $active = false;
                $time = time();
                foreach ($gameData['lastActive'] as $key => $value) {
                    if ($time - $value <= 5) {
                        $active = true;
                    }
                }
                if (!$active) {
                    $this->gameSession->deleteRoom($gameID, false);
                    $this->gameSession->deleteGameData($gameID);
                    $this->joinGame();
                    return;
                }
            }
        }

        if ($gameData && $gameData['state'] === 'open') {
            $this->userGame->joinedGame($gameID);

            $this->getGame();
            $this->lastActive[$this->userGame->userID] = time();
            if (!in_array($this->userGame->userID, $this->players, true)) {
                $this->players[] = $this->userGame->userID;
                $this->journal[] = '<p>Un joueur vient d\'arriver ! (' . count($this->players) . ' sur 4 joueurs connectés)</p>';
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
        if ($this->players[0] !== $this->userGame->userID && (count($this->players) === 4 && $this->players[3] !== $this->userGame->userID)) {
            return;
        }

        $this->state = 'inProgress';
        $this->gameSession->deleteRoom($this->userGame->gameID, $this->userGame->private);
        if (count($this->players) > 1) {
            shuffle($this->players);
        }
        $this->attributeColorPlayer();
        $this->currentPlayer = $this->players[0];
        $this->journal = [];
        if (count($this->players) === 1) {
            if ($this->userGame->isSolo()) {
                $this->journal[] = '<p>C\'est parti ! Trouvez le code secret !</p>';
            } else {
                $this->journal[] = '<p>Vous êtes le seul joueur pour cette partie. Bonne chance !</p>';
            }
        } else {
            $this->journal[] = '<p>Voici l\'ordre des joueurs : <span class="flex gap-2">' . $this->getPlayerOrder() . '</span></p>';
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
            $this->journal[] = '<span>' . $this->getSvgPlayer($this->colorPlayers[$this->currentPlayer]) . ' :</span> ' . '<div class="code">' . $newEntryHard . '</div>';
        } else {
            $newEntryString = implode('', $newEntry);
            $this->journal[] = '<span>' . $this->getSvgPlayer($this->colorPlayers[$this->currentPlayer]) . ' :</span> ' . '<div class="code">' . $newEntryString . '</div>';
        }

        if ($this->codeEntered === $this->codeToFind) {
            $this->state = 'finished';
        }


        $this->clearCodeEntered();
        $this->penaltyPlayer(false);
        if ($this->state !== 'finished') {
            $this->nextPlayer();
        }
        $this->save();
    }

    public function getJournal(): array
    {
        if ($this->state === 'open') {
            $this->checkRoom();
            if ($this->userGame->userID === $this->players[0]) {
                $this->journal[] = '<p>Vous êtes l\'hôte ! Démarrez la partie à tout moment, sans attendre d\'autres joueurs. <a href="/game?start=true" class="btn">Commencez maintenant</a></p>';
            } else {
                $this->journal[] = '<p>Veuillez patienter pour l\'arrivée des autres joueurs, ou jusqu\'à ce que l\'hôte lance la partie.</p>';
            }
        }
        if ($this->state === 'inProgress') {
            if (count($this->players) > 1) {
                if ($this->time < time()) {
                    $this->journal[] = '<p><span>' . $this->getSvgPlayer($this->colorPlayers[$this->currentPlayer]) . '</span> a passé son tour (le temps est écoulé !)</p>';
                    $this->penaltyPlayer();
                    $this->save();
                }
                if ($this->isCurrentPlayer()) {
                    $this->journal[] = '<p class="font-bold"><span>[Vous : ' . $this->getSvgPlayer($this->colorPlayers[$this->userGame->userID]) . ' ] </span>À vous de jouer ! (Vous avez ' . $this->time - time() . ' seconde' . ($this->time - time() > 1 ? 's' : '') . '.)</p>';
                } else {
                    $this->journal[] = '<p class="font-bold"><span>[Vous : ' . $this->getSvgPlayer($this->colorPlayers[$this->userGame->userID]) . ' ] </span>Patientez, ' . $this->getSvgPlayer($this->colorPlayers[$this->currentPlayer]) . ' est en train de faire son tour.</p>';
                }
            } else {
                $this->journal[] = '<p class="font-bold"><span>[Vous : ' . $this->getSvgPlayer($this->colorPlayers[$this->userGame->userID]) . ' ] </span>Saisissez le code.</p>';
            }

            if (!in_array($this->userGame->userID, $this->players, true)) {
                throw new \Exception('Vous avez été exclu pour avoir dépassé le temps limite deux fois de suite.');
            }
        }

        if ($this->state === 'finished') {
            if ($this->userGame->userID === $this->currentPlayer) {
                $this->journal[] = '<div class="win"> Bravo, vous avez trouvé le code secret ! </div>';
            } else {
                $this->journal[] = '<div class="win"> Dommage, c\'est <span>' . $this->getSvgPlayer($this->colorPlayers[$this->currentPlayer]) . '</span> qui a trouvé le code Secret ! </div>';
            }
            $this->journal[] = '<a href="/" class="btn">Quitter cette partie</a>';
        }

        return $this->journal;
    }

    private function checkRoom(): void
    {
        $currentTime = time();
        $this->lastActive[$this->userGame->userID] = $currentTime;

        foreach ($this->lastActive as $key => $value) {
            if ($currentTime - $value >= 3) {
                $currentIndex = array_search($key, $this->players, true);
                if ($currentIndex !== false) {
                    unset($this->players[$currentIndex]);
                    unset($this->lastActive[$key]);
                    $this->journal[] = '<p>Un joueur quitte. (' . count($this->players) . ' sur 4 joueurs connectés)</p>';
                }
            }
        }

        $this->players = array_values($this->players);
        $this->save();
    }

    private function nextPlayer(): void
    {
        if (count($this->players) === 1) {return;}

        $currentIndex = array_search($this->currentPlayer, $this->players);

        if ($currentIndex === false) {
            throw new \Exception("Aïe, nous avons rencontré un problème.");
        }
        $this->codeEntered = '';
        $nextIndex = ($currentIndex + 1) % count($this->players);
        $this->currentPlayer = $this->players[$nextIndex];
        $this->initializeTime();
    }

    private function penaltyPlayer(bool $hit = true): void
    {
        if (!$hit) {
            if (in_array($this->currentPlayer, $this->penalty)) {
                $currentIndex = array_search($this->currentPlayer, $this->penalty, true);
                if ($currentIndex !== false) {
                    unset($this->penalty[$currentIndex]);
                }
            }
        } else {
            if (!in_array($this->currentPlayer, $this->penalty)) {
                $this->penalty[] = $this->currentPlayer;
                $this->nextPlayer();
            } else {
                $index = array_search($this->currentPlayer, $this->players);
                $this->journal[] = '<p><span>' . $this->getSvgPlayer($this->colorPlayers[$this->currentPlayer]) . '</span>  a été exclu pour avoir dépassé le temps limite deux fois de suite. (Vous êtes maintenant ' . count($this->players)-1 . ' joueur' . (count($this->players)-1 > 1 ? 's' : '') . '.)</p>';
                $this->nextPlayer();
                if ($index !== false) {
                    unset($this->players[$index]);
                    $this->players = array_values($this->players);
                }
            }
        }
    }

    public function isCurrentPlayer(): bool
    {
        if ($this->currentPlayer === $this->userGame->userID) {
            return true;
        };
        return false;
    }

    private function attributeColorPlayer(): void
    {
        $colors = ["#0067FF", "#FF0000", "#00D700", "#FF7E00", "#B200FF"];
        foreach ($this->players as $player) {
            $color = array_rand($colors);
            $this->colorPlayers[$player] = $colors[$color];
            unset($colors[$color]);
        }
    }

    private function getPlayerOrder() : string
    {
        $string = '';
        foreach ($this->colorPlayers as $color) {
            $string .= $this->getSvgPlayer($color);
        }

        return $string;
    }

    private function getSvgPlayer($color) : string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 16 16" class="inline">
                    <path fill="' . $color . '" d="M8 16A8 8 0 1 1 8 0a8 8 0 0 1 0 16m.847-8.145a2.502 2.502 0 1 0-1.694 0C5.471 8.261 4 9.775 4 11c0 .395.145.995 1 .995h6c.855 0 1-.6 1-.995c0-1.224-1.47-2.74-3.153-3.145"/>
                </svg>';
    }
}