<?php

namespace App\Controller;

use App\Form\NewGameBtnType;
use App\Service\CodeSecretService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    public function __construct(private readonly CodeSecretService $codeSecretService)
    {
    }

    #[Route('/game', name: 'app_game', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->query->has('start') && $request->query->get('start') === 'true' ) {
            $this->codeSecretService->startGame();
            return $this->redirectToRoute('app_game');
        }

        if (!$request->getSession()->has('newGame')) {
            return $this->redirectToRoute('app_main');
        }

        $form = $this->createForm(NewGameBtnType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newGameInfo = $request->getSession()->get('newGame');
            $newGameInfo['newGame'] = true;
            $request->getSession()->set('newGame', $newGameInfo);

            return $this->redirectToRoute('app_game');
        }

        return $this->render('game/index.html.twig', [
            'controller_name' => 'GameController',
            'codeToFind' => $this->codeSecretService->getCodeToFind(),
            'codeToDisplay' => $this->codeSecretService->getCodeToDisplay(),
            'journal' => $this->codeSecretService->getJournal(),

            'form' => $form->createView(),
        ]);
    }
}