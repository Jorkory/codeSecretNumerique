<?php

namespace App\Controller;

use App\Entity\NewGame;
use App\Form\NewGameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_main', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $newGame = new NewGame();
        $form = $this->createForm(NewGameType::class, $newGame);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $codeLength = $newGame->getCodeLength();
            $difficulty = $newGame->getDifficulty();
            $request->getSession()->set('newGame', ['newGame' => true, 'difficulty' => $difficulty ,'codeLength' => $codeLength]);

            return $this->redirectToRoute('app_game');
        }

        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'form' => $form->createView(),
        ]);
    }
}