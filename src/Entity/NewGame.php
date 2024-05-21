<?php

namespace App\Entity;

use App\Repository\NewGameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewGameRepository::class)]
class NewGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $codeLength = null;

    #[ORM\Column(length: 255)]
    private ?string $difficulty = null;

    #[ORM\Column]
    private bool $newGame = true;

    #[ORM\Column(length: 255)]
    private ?string $mode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $joinGame = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeLength(): ?int
    {
        return $this->codeLength;
    }

    public function setCodeLength(?int $codeLength): static
    {
        $this->codeLength = $codeLength;

        return $this;
    }

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function isNewGame(): ?bool
    {
        return $this->newGame;
    }

    public function setNewGame(bool $newGame): static
    {
        $this->newGame = $newGame;

        return $this;
    }

    public function getNewGameInfo(): array
    {
        $newGameInfo = [];
        $newGameInfo['codeLength'] = $this->codeLength;
        $newGameInfo['difficulty'] = $this->difficulty;
        $newGameInfo['newGame'] = $this->newGame;
        $newGameInfo['mode'] = $this->mode;
        $newGameInfo['joinGame'] = $this->joinGame;
        return $newGameInfo;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getJoinGame(): ?string
    {
        return $this->joinGame;
    }

    public function setJoinGame(?string $joinGame): static
    {
        $this->joinGame = $joinGame;

        return $this;
    }
}
