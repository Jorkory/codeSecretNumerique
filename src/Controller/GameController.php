<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    #[Route('/game', name: 'app_game')]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        if ($session->has('codeToFind')) {
            $codeToFind = $session->get('codeToFind');
        } else {
            $codeToFind = random_int(00000,99999);
            $session->set('codeToFind', $codeToFind);
        }

        return $this->render('game/index.html.twig', [
            'controller_name' => 'GameController',
            'codeToFind' => $codeToFind,
        ]);
    }
}