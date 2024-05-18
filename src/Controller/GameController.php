<?php

namespace App\Controller;

use App\Service\CodeSecretService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    #[Route('/game', name: 'app_game')]
    public function index(Request $request): Response
    {
        $codeSecretService = new CodeSecretService($request);

        return $this->render('game/index.html.twig', [
            'controller_name' => 'GameController',
            'codeToFind' => $codeSecretService->getCodeToFind(),
            'codeToDisplay' => $codeSecretService->getCodeToDisplay(),
            'journal' => $codeSecretService->getJournal(),
        ]);
    }
}