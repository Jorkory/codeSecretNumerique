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
    #[Route('/game', name: 'app_game', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if (!$request->getSession()->has('newGame')) {
            return $this->redirectToRoute('app_main');
        }

        $codeSecretService = new CodeSecretService($request);

        $form = $this->createForm(NewGameBtnType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newGame = $request->getSession()->get('newGame');
            $newGame['newGame'] = true;
            $request->getSession()->set('newGame', $newGame);

            return $this->redirectToRoute('app_game');
        }

        return $this->render('game/index.html.twig', [
            'controller_name' => 'GameController',
            'codeToFind' => $codeSecretService->getCodeToFind(),
            'codeToDisplay' => $codeSecretService->getCodeToDisplay(),
            'journal' => $codeSecretService->getJournal(),

            'form' => $form->createView(),
        ]);
    }
}