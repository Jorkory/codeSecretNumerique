<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class UserGameService
{
    private $session;
    public string $userID;
    public ?string $gameID;
    public bool $isPrivate = true;
    private ?int $codeLength;
    public string $difficulty;
    public bool $newGame;
    private string $mode;
    private ?string $joinGame;

    public function __construct(RequestStack $requestStack)
    {
        $this->session = $requestStack->getSession();

        if ($this->session->has('userID')) {
            $this->userID = $this->session->get('userID');
        } else {
            $this->createUserID();
        }

        if ($this->session->has('gameID')) {
            $this->gameID = $this->session->get('gameID');
        } else {
            $this->gameID = null;
        }

        foreach ($this->session->get('newGame') as $key => $value) {
            $this->$key = $value;
        }
    }

    public function save(): void
    {
        $arrayToSave = [
            'codeLength' => $this->codeLength,
            'difficulty' => $this->difficulty,
            'newGame' => $this->newGame,
            'mode' => $this->mode,
            'joinGame' => $this->joinGame,
        ];

        $this->session->set('newGame', $arrayToSave);
    }

    private function createUserID(): void
    {
        $this->userID = uniqid('user_', true);
        $this->session->set('userID', $this->userID);
    }

    public function hasGame(): bool
    {
        if ($this->gameID !== null && $this->newGame === false) {
            return true;
        }
        return false;
    }

    public function isSolo(): bool
    {
        if ($this->mode === 'player' || !$this->joinGame === 'fast') {
            return true;
        }
        return false;
    }

    public function askToJoinGame(): ?string
    {
        if (!empty($this->joinGame)) {
            return $this->joinGame;
        } else {
            return null;
        }
    }

    public function joinedGame(string $id): void
    {
        $this->joinGame = '';
        $this->newGame = false;
        $this->save();

        $this->gameID = $id;
        $this->session->set('gameID', $this->gameID);
    }
}