<?php

namespace App\Controller;

use App\Service\CodeSecretService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    #[Route('/game', name: 'app_game', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $codeLength = $request->getSession()->get('codeLength');

        if ($codeLength !== null && (!is_integer((int) $codeLength) || $codeLength < 4 || $codeLength > 9)) {
            return $this->redirectToRoute('app_main');
        }

        $codeSecretService = new CodeSecretService($request, $codeLength);

        return $this->render('game/index.html.twig', [
            'controller_name' => 'GameController',
            'codeToFind' => $codeSecretService->getCodeToFind(),
            'codeToDisplay' => $codeSecretService->getCodeToDisplay(),
            'journal' => $codeSecretService->getJournal(),
        ]);
    }
}