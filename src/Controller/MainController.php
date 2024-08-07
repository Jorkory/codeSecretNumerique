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
        if ($request->query->has('fast')) {
            if ($request->get('fast') == 'true') {
                $newGame = new NewGame();
                $newGame->setDifficulty('normal');
                $newGame->setMode('multiplayer');
                $newGame->setJoinGame('fast');
                $request->getSession()->set('newGame', $newGame->getNewGameInfo());

                return $this->redirectToRoute('app_game');

            }
        }

        $newGame = new NewGame();
        $form = $this->createForm(NewGameType::class, $newGame);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->set('newGame', $newGame->getNewGameInfo());
            $joinGame = $newGame->getJoinGame();
            if ($joinGame !== null && !preg_match('/^[a-zA-Z0-9]{8}$/', $joinGame)) {
                throw new \Exception('L\'identifiant doit contenir exactement 8 caractères composés uniquement de lettres sans caractères spéciaux et des chiffres.');
            }
            return $this->redirectToRoute('app_game');
        }

        return $this->render('main/index.html.twig', [
            'controller_name' => 'MainController',
            'form' => $form->createView(),
        ]);
    }
}